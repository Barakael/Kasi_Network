<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Voucher\VoucherCode;
use App\Domain\Voucher\VoucherCodeHasher;
use App\Domain\Voucher\VoucherStatus;
use App\Models\Plan;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = VoucherCode::generate('KAS', (int) config('kasi.voucher.body_length'));

        return [
            /*
             * plan_id is declared first on purpose. Factory attributes are
             * resolved in declaration order, so a closure reading plan_id before
             * it is declared receives the unresolved PlanFactory instance rather
             * than an id.
             */
            'plan_id' => Plan::factory(),
            'tenant_id' => fn (array $attributes): int => Plan::withoutTenantScope()
                ->findOrFail($attributes['plan_id'])->tenant_id,
            'batch_id' => null,
            'code' => $code,
            'code_hash' => app(VoucherCodeHasher::class)->hash($code),
            'code_suffix' => VoucherCode::suffix($code),
            'status' => VoucherStatus::Unused,

            // Terms are snapshotted onto the voucher at issue, so these mirror
            // the plan rather than being read through the relation at runtime.
            'validity_seconds' => 86400,
            'duration_seconds' => null,
            'data_cap_bytes' => null,
            'rate_limit_down_kbps' => 5120,
            'rate_limit_up_kbps' => 2048,
            'device_limit' => 1,
            'price_minor' => 1000,
        ];
    }

    /**
     * Copies the terms of a specific plan, the way the issuer does.
     */
    public function forPlan(Plan $plan): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $plan->tenant_id,
            'plan_id' => $plan->id,
            ...$plan->termsSnapshot(),
            'shelf_expires_at' => $plan->shelf_life_days === null
                ? null
                : now()->addDays($plan->shelf_life_days),
        ]);
    }

    /**
     * A voucher already redeemed once: clock running, bound to its first device.
     */
    public function active(?string $mac = 'AA:BB:CC:DD:EE:FF'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VoucherStatus::Active,
            'bound_mac' => $mac,
            'first_used_at' => now()->subMinutes(5),
            'expires_at' => now()->addSeconds((int) $attributes['validity_seconds']),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VoucherStatus::Exhausted,
            'first_used_at' => now()->subDay(),
        ]);
    }

    /**
     * Activated, but its validity window has already closed.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VoucherStatus::Active,
            'first_used_at' => now()->subDays(3),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VoucherStatus::Disabled,
            'disabled_at' => now(),
            'disable_reason' => 'Batch reported stolen',
        ]);
    }

    /**
     * Unsold printed stock that has passed its shelf life.
     */
    public function pastShelfLife(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VoucherStatus::Unused,
            'shelf_expires_at' => now()->subDay(),
        ]);
    }
}
