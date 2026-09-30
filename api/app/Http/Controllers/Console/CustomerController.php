<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Billing\TanzanianPhone;
use App\Domain\Customers\CustomerDirectory;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Voucher\VoucherCard;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController
{
    public function index(Request $request, CustomerDirectory $directory): AnonymousResourceCollection
    {
        abort_unless($this->mayViewCustomers($request->user()), 403);

        $siteIds = $this->siteScope($request->user(), $request->integer('site_id') ?: null);

        $status = $request->string('status')->value();
        $siteId = $request->integer('site_id') ?: null;
        $haiIds = $directory->haiIds(siteId: $siteId, siteIds: $siteIds);
        $onlineIds = $directory->onlineIds(siteId: $siteId, siteIds: $siteIds);
        $paidIds = $directory->paidIds(siteId: $siteId, siteIds: $siteIds);
        $expiredIds = $directory->expiredIds(siteId: $siteId, siteIds: $siteIds);

        $customers = Customer::query()
            ->with('site')
            ->when($siteIds !== null, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($status === 'online', fn ($q) => $q->whereIn('id', $onlineIds->isEmpty() ? [0] : $onlineIds))
            ->when($status === 'hai', fn ($q) => $q->whereIn('id', $haiIds))
            ->when($status === 'kimya', fn ($q) => $q->whereNotIn('id', $haiIds->isEmpty() ? [0] : $haiIds))
            ->when($status === 'paid', fn ($q) => $q->whereIn('id', $paidIds->isEmpty() ? [0] : $paidIds))
            ->when($status === 'expired', fn ($q) => $q->whereIn('id', $expiredIds->isEmpty() ? [0] : $expiredIds))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $raw = trim($request->string('q')->value());
                if ($raw === '') {
                    return;
                }
                $digits = TanzanianPhone::toE164($raw);
                $query->where(function ($inner) use ($raw, $digits): void {
                    $inner->where('phone', 'like', '%'.$raw.'%')
                        ->orWhere('last_mac', 'like', '%'.$raw.'%');
                    if ($digits !== '' && $digits !== $raw) {
                        $inner->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->orderByDesc('first_seen_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10));

        $customers->getCollection()->transform(function (Customer $customer) use ($haiIds, $paidIds, $expiredIds, $directory): Customer {
            $customer->setAttribute('presence', $haiIds->contains($customer->id) ? 'hai' : 'kimya');
            $customer->setAttribute('payment', $paidIds->contains($customer->id) ? 'paid' : ($expiredIds->contains($customer->id) ? 'expired' : 'new'));

            return $directory->decorate($customer);
        });

        $all = Customer::query()
            ->when($siteIds !== null, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->count();

        return CustomerResource::collection($customers)->additional([
            'counts' => [
                'all' => $all,
                'online' => $onlineIds->count(),
                'paid' => $paidIds->count(),
                'expired' => $expiredIds->count(),
            ],
        ]);
    }

    public function show(Request $request, Customer $customer, CustomerDirectory $directory): JsonResponse
    {
        abort_unless($this->mayViewCustomers($request->user()), 403);
        $this->assertSiteAccess($request->user(), $customer);

        $hai = $directory->isHai($customer);
        $customer->setAttribute('presence', $hai ? 'hai' : 'kimya');
        $directory->decorate($customer);
        $unused = $directory->unusedPaidVoucher($customer);

        return response()->json([
            'data' => new CustomerResource($customer->load('site')),
            'unused_voucher' => $unused instanceof Voucher
                ? [
                    'id' => $unused->id,
                    'plan' => $unused->plan?->name,
                    'status' => $unused->status->value,
                ]
                : null,
        ]);
    }

    public function reveal(Request $request, Customer $customer, CustomerDirectory $directory, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $voucher = $directory->unusedPaidVoucher($customer);

        abort_unless($voucher instanceof Voucher, 404);

        $audit->record('voucher.revealed', $voucher, [
            'customer_id' => $customer->id,
            'reason' => 'paid_unused',
        ]);

        return response()->json([
            'code' => $voucher->displayCode(),
            'redemption_url' => VoucherCard::redemptionUrl($voucher->code),
        ]);
    }

    private function mayViewCustomers(?User $user): bool
    {
        return $user !== null && ($user->managesTenant() || $user->isAgent());
    }

    /**
     * @return list<int>|null
     */
    private function siteScope(?User $user, ?int $requestedSite): ?array
    {
        if ($user === null || $user->managesTenant()) {
            return $requestedSite ? [$requestedSite] : null;
        }

        $ids = $user->sites()->pluck('sites.id')->all();

        if ($requestedSite) {
            abort_unless(in_array($requestedSite, $ids, true), 403);

            return [$requestedSite];
        }

        return $ids;
    }

    private function assertSiteAccess(?User $user, Customer $customer): void
    {
        if ($user === null || $user->managesTenant()) {
            return;
        }

        abort_unless($user->sites()->where('sites.id', $customer->site_id)->exists(), 403);
    }
}
