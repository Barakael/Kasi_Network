<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NasDevice;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NasDevice>
 */
class NasDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Declared before tenant_id, which reads it: factory attributes are
            // resolved in declaration order.
            'site_id' => Site::factory(),
            'tenant_id' => fn (array $attributes): int => Site::withoutTenantScope()
                ->findOrFail($attributes['site_id'])->tenant_id,
            'name' => 'RB'.fake()->numberBetween(1000, 5009),
            'nasname' => fake()->unique()->localIpv4(),
            'shared_secret' => Str::random(32),
            'coa_port' => 3799,
            'status' => 'active',
        ];
    }

    /**
     * A router the platform can query over the RouterOS API, which is what makes
     * device discovery available when binding a voucher to a TV.
     */
    public function withApiAccess(): static
    {
        return $this->state(fn (array $attributes): array => [
            'api_host' => $attributes['nasname'] ?? fake()->localIpv4(),
            'api_port' => 8728,
            'api_username' => 'kasi-api',
            'api_password' => Str::random(20),
        ]);
    }
}
