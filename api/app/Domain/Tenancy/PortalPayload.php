<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Customers\CustomerDirectory;
use App\Domain\Voucher\VoucherStatus;
use App\Http\Resources\PlanResource;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\NasDevice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;

/**
 * The captive-portal first payload: branding, sellable packages, and any live
 * offer ribbon. Packages are listed before phone capture so the home screen can
 * show bundles above the USSD number field.
 */
final readonly class PortalPayload
{
    public function __construct(
        private PortalToken $tokens,
        private CustomerDirectory $customers,
    ) {}

    public function json(PortalContext $context, ?NasDevice $nasDevice = null): JsonResponse
    {
        $identified = $context->isIdentified();
        $customer = $identified
            ? Customer::query()->find($context->customerId)
            : null;

        $token = $this->tokens->reissue($context);

        return response()->json([
            'token' => $token,
            'needs_phone' => ! $identified,
            'site' => [
                'name' => $context->site->name,
                'ssid' => $context->site->ssid,
            ],
            'branding' => self::branding($context->tenant),
            'client' => [
                'mac' => $context->clientMac,
                'mac_known' => $context->clientMac !== null,
                'phone' => $customer?->localPhone(),
            ],
            'capabilities' => [
                'online_payments' => $context->tenant->acceptsOnlinePayments(),
                'demo_checkout' => (bool) config('kasi.demo_checkout'),
                'device_discovery' => ($nasDevice ?? $context->nasDevice)?->hasApiCredentials() ?? false,
            ],
            'campaign' => $this->campaign($customer),
            'unused_voucher' => $this->unusedVoucher($customer),
            /*
             * Read-only MAC check for a live wall-clock window (plans.validity_seconds
             * → vouchers.expires_at / radcheck Expiration). Separate from online-time
             * (duration_seconds / Max-All-Session) and data caps. Does not start the clock.
             */
            'active_subscription' => $this->activeSubscription($context),
            'plans' => PlanResource::collection($this->plans()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function branding(Tenant $tenant): array
    {
        return [
            'operator' => $tenant->portal_name ?? $tenant->name,
            'primary_color' => $tenant->primary_color,
            'support_phone' => $tenant->support_phone,
            'currency' => $tenant->currency,
            'logo_url' => $tenant->logoUrl(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function campaign(?Customer $customer): ?array
    {
        $campaign = $customer instanceof Customer
            ? $this->customers->liveCampaign($customer)
            : Campaign::query()->live()->where('audience', 'all')->orderByDesc('id')->first();

        if (! $campaign instanceof Campaign) {
            $offer = Plan::query()
                ->where('is_active', true)
                ->whereNotNull('offer_label')
                ->where('offer_ends_at', '>', now())
                ->orderBy('sort_order')
                ->first();

            if (! $offer instanceof Plan) {
                return null;
            }

            return [
                'title' => $offer->offer_label,
                'body' => null,
            ];
        }

        return [
            'title' => $campaign->title,
            'body' => $campaign->body,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function unusedVoucher(?Customer $customer): ?array
    {
        if (! $customer instanceof Customer) {
            return null;
        }

        $voucher = $this->customers->unusedPaidVoucher($customer);

        if ($voucher === null) {
            return null;
        }

        return [
            'display_code' => $voucher->displayCode(),
            'plan' => $voucher->plan?->name,
        ];
    }

    /**
     * Newest in-window active voucher bound to this MAC (same rules as FreeRADIUS
     * MAC rewrite). Used so the portal can resume without asking to pay again.
     *
     * @return array{code: string, display_code: string, plan: string|null, expires_at: string|null, remaining_seconds: int}|null
     */
    private function activeSubscription(PortalContext $context): ?array
    {
        $mac = $context->clientMac;

        if ($mac === null || $mac === '') {
            return null;
        }

        $voucher = Voucher::query()
            ->with('plan')
            ->where('tenant_id', $context->tenant->id)
            ->where('status', VoucherStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) use ($mac): void {
                $query->where('bound_mac', $mac)
                    ->orWhereHas('devices', function ($devices) use ($mac): void {
                        $devices->where('mac', $mac)->where('status', 'active');
                    });
            })
            ->orderByDesc('id')
            ->first();

        if (! $voucher instanceof Voucher) {
            return null;
        }

        $remaining = $voucher->expires_at === null
            ? (int) $voucher->validity_seconds
            : max(0, (int) now()->diffInSeconds($voucher->expires_at, false));

        if ($remaining <= 0 && $voucher->expires_at !== null) {
            return null;
        }

        return [
            'code' => $voucher->code,
            'display_code' => $voucher->displayCode(),
            'plan' => $voucher->plan?->name,
            'expires_at' => $voucher->expires_at?->toIso8601String(),
            'remaining_seconds' => $remaining,
        ];
    }

    private function plans()
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('is_sold_online', true)
            ->orderBy('sort_order')
            ->orderBy('price_minor')
            ->get();
    }
}
