<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherUsage>
 */
class VoucherUsageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Declared before the closures that read it: factory attributes are
            // resolved in declaration order.
            'voucher_id' => Voucher::factory(),
            'tenant_id' => fn (array $attributes): int => Voucher::withoutTenantScope()
                ->findOrFail($attributes['voucher_id'])->tenant_id,
            'username' => fn (array $attributes): string => Voucher::withoutTenantScope()
                ->findOrFail($attributes['voucher_id'])->code,
            'seconds_used' => 0,
            'bytes_in' => 0,
            'bytes_out' => 0,
            'session_count' => 0,
        ];
    }

    public function consumed(int $seconds, int $bytesIn = 0, int $bytesOut = 0): static
    {
        return $this->state(fn (array $attributes): array => [
            'seconds_used' => $seconds,
            'bytes_in' => $bytesIn,
            'bytes_out' => $bytesOut,
            'session_count' => 1,
            'last_session_at' => now()->subMinutes(10),
            'rolled_up_through' => now()->subMinutes(5),
        ]);
    }
}
