<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Support\MacAddress;
use App\Models\Voucher;
use App\Models\VoucherDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherDevice>
 */
class VoucherDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Declared before tenant_id, which reads it: factory attributes are
            // resolved in declaration order.
            'voucher_id' => Voucher::factory(),
            'tenant_id' => fn (array $attributes): int => Voucher::withoutTenantScope()
                ->findOrFail($attributes['voucher_id'])->tenant_id,
            'mac' => MacAddress::parse(fake()->unique()->macAddress())->toString(),
            'label' => 'Living room TV',
            'vendor' => 'Samsung Electronics',
            'status' => 'active',
            'created_via' => 'portal',
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'revoked',
        ]);
    }
}
