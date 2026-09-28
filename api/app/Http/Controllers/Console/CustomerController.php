<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

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
        $haiIds = $directory->haiIds(
            siteId: $request->integer('site_id') ?: null,
            siteIds: $siteIds,
        );

        $customers = Customer::query()
            ->with('site')
            ->when($siteIds !== null, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->when($request->integer('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($status === 'hai', fn ($q) => $q->whereIn('id', $haiIds))
            ->when($status === 'kimya', fn ($q) => $q->whereNotIn('id', $haiIds->isEmpty() ? [0] : $haiIds))
            ->orderByDesc('last_seen_at')
            ->paginate($request->integer('per_page', 50));

        $customers->getCollection()->transform(function (Customer $customer) use ($haiIds, $directory): Customer {
            $customer->setAttribute('presence', $haiIds->contains($customer->id) ? 'hai' : 'kimya');

            return $directory->decorate($customer);
        });

        return CustomerResource::collection($customers);
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
