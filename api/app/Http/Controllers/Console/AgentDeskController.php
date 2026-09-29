<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\CollectionBook;
use App\Domain\Customers\CustomerDirectory;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\VoucherBatchResource;
use App\Models\Customer;
use App\Models\NasDevice;
use App\Models\RadAcct;
use App\Models\Site;
use App\Models\VoucherBatch;
use App\Models\VoucherUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentDeskController
{
    public function __invoke(Request $request, CollectionBook $collections, CustomerDirectory $directory): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isAgent(), 403);

        $sites = $user->sites()->withCount('nasDevices')->orderBy('name')->get();
        abort_unless($sites->isNotEmpty(), 422, 'You have not been assigned a site yet.');

        $siteId = $request->integer('site_id') ?: $sites->first()->id;
        abort_unless($sites->contains(fn (Site $site) => $site->id === $siteId), 403);

        $site = $sites->firstWhere('id', $siteId);
        $haiIds = $directory->haiIds(siteId: $siteId);

        $batches = VoucherBatch::query()
            ->with(['plan', 'site'])
            ->withCount(VoucherBatchResource::countsFor())
            ->where('assigned_agent_id', $user->id)
            ->where('site_id', $siteId)
            ->latest('id')
            ->get();

        $remaining = (int) $batches->sum(fn (VoucherBatch $batch) => (int) ($batch->getAttributes()['printable_count'] ?? 0));

        $soldToday = $collections->printedTotals(now()->startOfDay(), now(), $siteId, $user->id);
        $usedToday = $collections->totals(now()->startOfDay(), now(), $siteId, $user->id);
        $week = $collections->printedTotals(now()->startOfWeek(), now(), $siteId, $user->id);
        $previousWeek = $collections->printedTotals(
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek(),
            $siteId,
            $user->id,
        );
        $haiCount = $haiIds->count();
        $customerTotal = Customer::query()->where('site_id', $siteId)->count();

        $nasIps = NasDevice::query()->where('site_id', $siteId)->get()
            ->flatMap(fn (NasDevice $d) => array_filter([$d->nasname, $d->api_host]))
            ->unique()
            ->values();

        $usernames = VoucherUsage::query()->pluck('username');
        $online = $usernames->isEmpty() || $nasIps->isEmpty()
            ? collect()
            : RadAcct::query()
                ->open()
                ->whereIn('username', $usernames)
                ->whereIn('nasipaddress', $nasIps)
                ->orderByDesc('acctstarttime')
                ->limit(50)
                ->get()
                ->map(fn (RadAcct $s) => [
                    'acctuniqueid' => $s->acctuniqueid,
                    'username' => $s->username,
                    'callingstationid' => $s->callingstationid,
                    'framedipaddress' => $s->framedipaddress,
                    'seconds' => $s->elapsedSeconds(),
                ]);

        $customers = Customer::query()
            ->where('site_id', $siteId)
            ->orderByDesc('last_seen_at')
            ->limit(40)
            ->get()
            ->map(function (Customer $customer) use ($haiIds, $directory): Customer {
                $customer->setAttribute('presence', $haiIds->contains($customer->id) ? 'hai' : 'kimya');

                return $directory->decorate($customer);
            });

        $devices = NasDevice::query()->where('site_id', $siteId)->where('status', 'active')->get();
        $quiet = $devices->isEmpty() || $devices->contains(fn (NasDevice $device) => $device->isQuiet());
        $lastRadius = $devices
            ->map(fn (NasDevice $device) => $device->lastRadiusAt())
            ->filter()
            ->sort()
            ->last();

        return response()->json([
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'ssid' => $site->ssid,
            ],
            'sites' => $sites->map(fn (Site $row) => [
                'id' => $row->id,
                'name' => $row->name,
                'ssid' => $row->ssid,
            ])->values(),
            'remaining_cards' => $remaining,
            'leo' => [
                'kadi_count' => $soldToday['kadi_count'],
                'kadi_minor' => $soldToday['kadi'],
                'used_count' => $usedToday['kadi_count'],
            ],
            'online' => $online,
            'batches' => VoucherBatchResource::collection($batches),
            'customers' => CustomerResource::collection($customers),
            'router_quiet' => $quiet,
            'last_radius_at' => $lastRadius?->toIso8601String(),
            'hai_count' => $haiCount,
            'kimya_count' => max(0, $customerTotal - $haiCount),
            'week' => [
                'kadi_count' => $week['kadi_count'],
                'kadi_minor' => $week['kadi'],
            ],
            'previous_week' => [
                'kadi_count' => $previousWeek['kadi_count'],
                'kadi_minor' => $previousWeek['kadi'],
            ],
            'series' => $collections->kadiSeries(now()->subDays(13)->startOfDay(), now(), $siteId, $user->id, 'printed_at'),
        ]);
    }
}
