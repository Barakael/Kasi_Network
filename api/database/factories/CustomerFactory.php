<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\TanzanianPhone;
use App\Models\Customer;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'site_id' => null,
            'phone' => TanzanianPhone::toE164('07'.fake()->numerify('xxxxxxxx')),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    public function atSite(Site $site): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
        ]);
    }
}
