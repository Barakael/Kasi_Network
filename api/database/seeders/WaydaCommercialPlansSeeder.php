<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Voucher\BillingPeriod;
use App\Domain\Voucher\QuotaAction;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * The live Wayda tariff: printed vouchers, unlimited data, silent 3 Mbps.
 *
 * Speed is stored so MikroTik enforces it, but names and descriptions never
 * mention a throttle. Re-running is safe: matching names are updated in place
 * and leftover demo bundles are retired.
 */
class WaydaCommercialPlansSeeder extends Seeder
{
    /** Demo ladder names that must not stay on the portal. */
    private const array RETIRE = ['Saa Moja', 'Masaa 10'];

    public function run(): void
    {
        Tenant::query()->each(fn (Tenant $tenant) => $this->seedFor($tenant));
    }

    public function seedFor(Tenant $tenant): void
    {
        foreach ($this->plans() as $attributes) {
            $plan = Plan::withTrashed()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $attributes['name']],
                [
                    ...$attributes,
                    'shelf_life_days' => 365,
                    'is_active' => true,
                    'is_sold_online' => true,
                ],
            );

            if ($plan->trashed()) {
                $plan->restore();
            }
        }

        Plan::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('name', self::RETIRE)
            ->get()
            ->each
            ->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        $silentKbps = 3072;

        return [
            [
                'name' => 'Masaa 4',
                'description' => 'Unlimited Wi-Fi for 4 hours',
                'billing_period' => BillingPeriod::Custom,
                'validity_seconds' => 14_400,
                'duration_seconds' => null,
                'data_cap_bytes' => null,
                'price_minor' => 500,
                'rate_limit_down_kbps' => $silentKbps,
                'rate_limit_up_kbps' => $silentKbps,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect,
                'throttle_down_kbps' => null,
                'throttle_up_kbps' => null,
                'sort_order' => 1,
            ],
            [
                'name' => 'Siku Moja',
                'description' => 'Unlimited Wi-Fi for 24 hours',
                'billing_period' => BillingPeriod::Daily,
                'validity_seconds' => 86_400,
                'duration_seconds' => null,
                'data_cap_bytes' => null,
                'price_minor' => 1000,
                'rate_limit_down_kbps' => $silentKbps,
                'rate_limit_up_kbps' => $silentKbps,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect,
                'throttle_down_kbps' => null,
                'throttle_up_kbps' => null,
                'sort_order' => 2,
            ],
            [
                'name' => 'Wiki Moja',
                'description' => 'Unlimited Wi-Fi for 7 days',
                'billing_period' => BillingPeriod::Weekly,
                'validity_seconds' => 604_800,
                'duration_seconds' => null,
                'data_cap_bytes' => null,
                'price_minor' => 5000,
                'rate_limit_down_kbps' => $silentKbps,
                'rate_limit_up_kbps' => $silentKbps,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect,
                'throttle_down_kbps' => null,
                'throttle_up_kbps' => null,
                'sort_order' => 3,
            ],
            [
                'name' => 'Mwezi Mmoja',
                'description' => 'Unlimited Wi-Fi for 30 days',
                'billing_period' => BillingPeriod::Monthly,
                'validity_seconds' => 2_592_000,
                'duration_seconds' => null,
                'data_cap_bytes' => null,
                'price_minor' => 20_000,
                'rate_limit_down_kbps' => $silentKbps,
                'rate_limit_up_kbps' => $silentKbps,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect,
                'throttle_down_kbps' => null,
                'throttle_up_kbps' => null,
                'sort_order' => 4,
            ],
        ];
    }
}
