<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => 'Wiki Moja 4000',
            'body' => 'Bei maalum hadi Jumatatu.',
            'audience' => 'all',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(3),
            'is_active' => true,
        ];
    }
}
