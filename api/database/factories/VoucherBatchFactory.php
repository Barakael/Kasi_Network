<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Voucher\BatchStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\VoucherBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VoucherBatch>
 */
class VoucherBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Declared before tenant_id, which reads it: factory attributes are
            // resolved in declaration order.
            'plan_id' => Plan::factory(),
            'tenant_id' => fn (array $attributes): int => Plan::withoutTenantScope()
                ->findOrFail($attributes['plan_id'])->tenant_id,
            'site_id' => null,
            'reference' => 'BATCH-'.Str::upper(Str::random(6)),
            'quantity' => 50,
            'status' => BatchStatus::Ready,
        ];
    }

    public function generating(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BatchStatus::Generating,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BatchStatus::Disabled,
        ]);
    }

    /**
     * Handed to a counter agent to print and sell.
     */
    public function assignedTo(User $agent): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $agent->tenant_id,
            'assigned_agent_id' => $agent->id,
            'status' => BatchStatus::Distributed,
        ]);
    }
}
