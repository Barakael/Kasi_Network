<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\OrderStatus;
use App\Models\Order;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'status' => OrderStatus::Pending,
            'amount_minor' => 1000,
            'currency' => 'TZS',
            // Normalised Tanzanian mobile number, as Snippe expects.
            'phone' => '2557'.fake()->numerify('########'),
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => fake()->localIpv4(),
            'expires_at' => now()->addHour(),
        ];
    }

    /**
     * The USSD push is out and the customer has a prompt open.
     */
    public function awaitingPayment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::AwaitingPayment,
            'snippe_reference' => 'pi_'.Str::lower(Str::random(12)),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Paid,
            'snippe_reference' => 'pi_'.Str::lower(Str::random(12)),
            'paid_at' => now(),
            'fees_minor' => 20,
            'net_minor' => (int) $attributes['amount_minor'] - 20,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Failed,
            'snippe_reference' => 'pi_'.Str::lower(Str::random(12)),
            'failure_reason' => 'Transaction declined by user',
        ]);
    }

    /**
     * An order left behind by a customer who never confirmed, which the
     * reconciliation sweep is responsible for closing out.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::AwaitingPayment,
            'snippe_reference' => 'pi_'.Str::lower(Str::random(12)),
            'expires_at' => now()->subHour(),
        ]);
    }
}
