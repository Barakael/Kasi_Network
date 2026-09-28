<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\CollectionBook;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\UserRole;
use App\Http\Resources\SiteResource;
use App\Models\NasDevice;
use App\Models\RadAcct;
use App\Models\Site;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class SiteController
{
    public function index(): AnonymousResourceCollection
    {
        return SiteResource::collection(
            Site::query()->with('agents')->withCount('nasDevices')->orderBy('name')->get(),
        );
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'ssid' => ['nullable', 'string', 'max:32'],
            'nas_identifier' => ['required', 'string', 'max:64', 'unique:sites,nas_identifier'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $site = Site::create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(4)),
            'status' => 'active',
            'timezone' => $validated['timezone'] ?? 'Africa/Dar_es_Salaam',
        ]);

        $audit->record('site.created', $site, ['name' => $site->name]);

        return (new SiteResource($site->load('agents')->loadCount('nasDevices')))->response()->setStatusCode(201);
    }

    public function show(Site $site, CollectionBook $collections): JsonResponse
    {
        $site->load(['agents', 'nasDevices'])->loadCount('nasDevices');
        $today = $collections->totals(now()->startOfDay(), now(), $site->id);

        $remaining = (int) Voucher::query()
            ->available()
            ->whereNotNull('batch_id')
            ->whereHas('batch', fn ($q) => $q->where('site_id', $site->id))
            ->count();

        $nasIps = $site->nasDevices
            ->flatMap(fn (NasDevice $device) => array_filter([$device->nasname, $device->api_host]))
            ->unique()
            ->values();

        $usernames = VoucherUsage::query()->pluck('username');
        $online = $usernames->isEmpty() || $nasIps->isEmpty()
            ? 0
            : RadAcct::query()->open()->whereIn('username', $usernames)->whereIn('nasipaddress', $nasIps)->count();

        $quiet = $site->nasDevices->isEmpty()
            || $site->nasDevices->contains(fn (NasDevice $device) => $device->isQuiet());

        return response()->json([
            'data' => (new SiteResource($site))->resolve(),
            'remaining_cards' => $remaining,
            'online_count' => $online,
            'today' => [
                'lipia_minor' => $today['lipia'],
                'kadi_minor' => $today['kadi'],
                'total_minor' => $today['lipia'] + $today['kadi'],
            ],
            'router_quiet' => $quiet,
        ]);
    }

    public function update(Request $request, Site $site, AuditLogger $audit): SiteResource
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'ssid' => ['sometimes', 'nullable', 'string', 'max:32'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,disabled'],
            'agent_ids' => ['sometimes', 'array'],
            'agent_ids.*' => ['integer'],
        ]);

        $site->update(collect($validated)->except('agent_ids')->all());

        if (array_key_exists('agent_ids', $validated)) {
            $ids = User::query()
                ->where('role', UserRole::Agent)
                ->whereIn('id', $validated['agent_ids'])
                ->pluck('id');
            $site->agents()->sync($ids);
        }

        $audit->record('site.updated', $site, ['fields' => array_keys($validated)]);

        return new SiteResource($site->fresh(['agents'])->loadCount('nasDevices'));
    }
}
