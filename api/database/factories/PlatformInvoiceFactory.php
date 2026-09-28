<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformInvoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformInvoice>
 */
class PlatformInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'amount_minor' => 50_000,
            'currency' => 'TZS',
            'status' => 'issued',
            'period_label' => now()->format('F Y'),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_at' => now()->addDays(14),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
