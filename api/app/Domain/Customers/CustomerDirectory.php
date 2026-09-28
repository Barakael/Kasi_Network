<?php

declare(strict_types=1);

namespace App\Domain\Customers;

use App\Domain\Billing\OrderStatus;
use App\Domain\Billing\TanzanianPhone;
use App\Domain\Tenancy\PortalContext;
use App\Domain\Voucher\VoucherStatus;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\RadAcct;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Phone ledger for one operator. Hai vs Kimya is derived from RADIUS and
 * vouchers, never from a checkbox.
 */
final class CustomerDirectory
{
    public function identify(PortalContext $context, string $rawPhone): Customer
    {
        if (! TanzanianPhone::isValid($rawPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Weka namba ya simu ya Tanzania, mfano 07XXXXXXXX.',
            ]);
        }

        $phone = TanzanianPhone::toE164($rawPhone);
        $now = now();

        $customer = Customer::query()->firstOrNew([
            'tenant_id' => $context->tenant->id,
            'phone' => $phone,
        ]);

        $customer->fill([
            'site_id' => $context->site->id,
            'last_mac' => $context->clientMac ?? $customer->last_mac,
            'last_seen_at' => $now,
            'first_seen_at' => $customer->first_seen_at ?? $now,
        ]);
        $customer->save();

        return $customer;
    }

    public function isHai(Customer $customer): bool
    {
        if ($this->hasOpenSession($customer)) {
            return true;
        }

        if ($this->hasLiveVoucher($customer)) {
            return true;
        }

        return $this->hasPaidUnused($customer);
    }

    /**
     * @return Collection<int, int>
     */
    public function haiIds(?int $siteId = null, ?iterable $siteIds = null): Collection
    {
        $customers = Customer::query()
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($siteIds !== null, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->get();

        return $customers
            ->filter(fn (Customer $customer) => $this->isHai($customer))
            ->pluck('id');
    }

    public function unusedPaidVoucher(Customer $customer): ?Voucher
    {
        $order = Order::query()
            ->with('voucher')
            ->where('phone', $customer->phone)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->whereNotNull('voucher_id')
            ->latest('id')
            ->first();

        $voucher = $order?->voucher;

        if (! $voucher instanceof Voucher) {
            return null;
        }

        if ($voucher->first_used_at !== null) {
            return null;
        }

        return $voucher->isRedeemable() ? $voucher : null;
    }

    public function liveCampaign(Customer $customer): ?Campaign
    {
        $campaigns = Campaign::query()->live()->orderByDesc('id')->get();

        $hai = $this->isHai($customer);

        return $campaigns->first(function (Campaign $campaign) use ($hai): bool {
            if ($campaign->audience === 'kimya') {
                return ! $hai;
            }

            return true;
        });
    }

    /**
     * Facts the Wateja row needs: last package, kadi vs simu, unused paid card, Kata.
     *
     * @return array{last_package: ?string, paid_via: ?string, has_unused_voucher: bool, session_id: ?string}
     */
    public function snapshot(Customer $customer): array
    {
        $voucher = Voucher::query()
            ->with('plan')
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->first();

        $order = Order::query()
            ->with('plan')
            ->where('phone', $customer->phone)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Fulfilled])
            ->latest('id')
            ->first();

        $lastPackage = $voucher?->plan?->name ?? $order?->plan?->name;
        $paidVia = null;

        if ($voucher instanceof Voucher) {
            $paidVia = $voucher->batch_id !== null ? 'kadi' : 'simu';
        } elseif ($order instanceof Order) {
            $paidVia = 'simu';
        }

        $usernames = $this->voucherUsernames($customer);
        $sessionId = $usernames->isEmpty()
            ? null
            : RadAcct::query()->open()->whereIn('username', $usernames)->value('acctuniqueid');

        return [
            'last_package' => $lastPackage,
            'paid_via' => $paidVia,
            'has_unused_voucher' => $this->hasPaidUnused($customer),
            'session_id' => is_string($sessionId) ? $sessionId : null,
        ];
    }

    public function decorate(Customer $customer): Customer
    {
        foreach ($this->snapshot($customer) as $key => $value) {
            $customer->setAttribute($key, $value);
        }

        return $customer;
    }

    public function attachVoucher(Customer $customer, Voucher $voucher): void
    {
        if ($voucher->customer_id === $customer->id) {
            return;
        }

        $voucher->update(['customer_id' => $customer->id]);
    }

    private function hasOpenSession(Customer $customer): bool
    {
        $usernames = $this->voucherUsernames($customer);

        if ($usernames->isEmpty()) {
            return false;
        }

        return RadAcct::query()->open()->whereIn('username', $usernames)->exists();
    }

    private function hasLiveVoucher(Customer $customer): bool
    {
        return Voucher::query()
            ->where('customer_id', $customer->id)
            ->where('status', VoucherStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function hasPaidUnused(Customer $customer): bool
    {
        return $this->unusedPaidVoucher($customer) instanceof Voucher;
    }

    /**
     * @return Collection<int, string>
     */
    private function voucherUsernames(Customer $customer): Collection
    {
        $ids = Voucher::query()->where('customer_id', $customer->id)->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return VoucherUsage::query()->whereIn('voucher_id', $ids)->pluck('username');
    }
}
