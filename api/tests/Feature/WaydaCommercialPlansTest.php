<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Voucher\BillingPeriod;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use Database\Seeders\WaydaCommercialPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WaydaCommercialPlansTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_installs_the_four_wayda_bundles_and_retires_the_demo_ladder(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);

        Plan::factory()->for($tenant)->create(['name' => 'Saa Moja', 'price_minor' => 500]);
        Plan::factory()->for($tenant)->create(['name' => 'Masaa 10', 'price_minor' => 6000]);
        Plan::factory()->for($tenant)->create([
            'name' => 'Siku Moja',
            'price_minor' => 1500,
            'data_cap_bytes' => 5_000_000_000,
        ]);

        (new WaydaCommercialPlansSeeder)->seedFor($tenant);

        $this->assertNull(Plan::query()->where('name', 'Saa Moja')->first());
        $this->assertNull(Plan::query()->where('name', 'Masaa 10')->first());
        $this->assertNotNull(Plan::onlyTrashed()->where('name', 'Saa Moja')->first());

        $plans = Plan::query()->orderBy('sort_order')->get();
        $this->assertCount(4, $plans);
        $this->assertSame(['Masaa 4', 'Siku Moja', 'Wiki Moja', 'Mwezi Mmoja'], $plans->pluck('name')->all());
        $this->assertSame([500, 1000, 5000, 20_000], $plans->pluck('price_minor')->all());
        $this->assertSame(BillingPeriod::Custom, $plans[0]->billing_period);
        $this->assertSame(14_400, $plans[0]->validity_seconds);
        $this->assertTrue($plans->every(fn (Plan $plan) => $plan->rate_limit_down_kbps === 3072));
        $this->assertTrue($plans->every(fn (Plan $plan) => $plan->data_cap_bytes === null));
        $this->assertFalse($plans->contains(fn (Plan $plan) => str_contains(strtolower((string) $plan->description), 'mbps')));
    }

    #[Test]
    public function the_portal_does_not_advertise_the_silent_rate_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);
        $site = Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);
        (new WaydaCommercialPlansSeeder)->seedFor($tenant);

        $response = $this->postJson('/api/portal/bootstrap', ['site' => $site->nas_identifier])
            ->assertOk()
            ->assertJsonCount(4, 'plans');

        $this->assertArrayNotHasKey('rate_limit_down_kbps', $response->json('plans.0'));
        $this->assertArrayNotHasKey('rate_limit_up_kbps', $response->json('plans.0'));
    }
}
