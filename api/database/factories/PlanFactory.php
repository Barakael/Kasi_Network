<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Voucher\BillingPeriod;
use App\Domain\Voucher\QuotaAction;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Daily '.fake()->unique()->numerify('####'),
            'description' => '24 hours of internet',
            'billing_period' => BillingPeriod::Daily,
            'validity_seconds' => BillingPeriod::Daily->validitySeconds(),
            'duration_seconds' => null,
            'data_cap_bytes' => null,
            'price_minor' => 1000,
            'rate_limit_down_kbps' => 5120,
            'rate_limit_up_kbps' => 2048,
            'device_limit' => 1,
            'on_quota_exhausted' => QuotaAction::Disconnect,
            'shelf_life_days' => 365,
            'is_active' => true,
            'is_sold_online' => true,
            'sort_order' => 0,
        ];
    }

    public function hourly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Hourly '.fake()->unique()->numerify('####'),
            'billing_period' => BillingPeriod::Hourly,
            'validity_seconds' => BillingPeriod::Hourly->validitySeconds(),
            'price_minor' => 500,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Weekly '.fake()->unique()->numerify('####'),
            'billing_period' => BillingPeriod::Weekly,
            'validity_seconds' => BillingPeriod::Weekly->validitySeconds(),
            'price_minor' => 5000,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Monthly '.fake()->unique()->numerify('####'),
            'billing_period' => BillingPeriod::Monthly,
            'validity_seconds' => BillingPeriod::Monthly->validitySeconds(),
            'price_minor' => 15000,
        ]);
    }

    /**
     * A bundle whose allowance is measured in data rather than only time.
     */
    public function withDataCap(int $bytes = 2_147_483_648): static
    {
        return $this->state(fn (array $attributes): array => [
            'data_cap_bytes' => $bytes,
        ]);
    }

    /**
     * A bundle capped on cumulative online time, usable across many sessions
     * within the validity window.
     */
    public function withTimeBudget(int $seconds = 36000): static
    {
        return $this->state(fn (array $attributes): array => [
            'duration_seconds' => $seconds,
        ]);
    }

    /**
     * Slows the client down instead of cutting them off when the quota is spent.
     */
    public function throttled(int $downKbps = 256, int $upKbps = 128): static
    {
        return $this->state(fn (array $attributes): array => [
            'on_quota_exhausted' => QuotaAction::Throttle,
            'throttle_down_kbps' => $downKbps,
            'throttle_up_kbps' => $upKbps,
        ]);
    }

    public function sharedDevices(int $limit = 3): static
    {
        return $this->state(fn (array $attributes): array => [
            'device_limit' => $limit,
        ]);
    }
}
