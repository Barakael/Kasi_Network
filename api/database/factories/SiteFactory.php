<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->streetName().' Hotspot';

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'ssid' => 'Kasi-'.Str::upper(Str::random(4)),
            // Mirrors the router's radius-location-id.
            'nas_identifier' => 'site-'.Str::lower(Str::random(10)),
            'timezone' => 'Africa/Dar_es_Salaam',
            'status' => 'active',
            'address' => fake()->address(),
        ];
    }
}
