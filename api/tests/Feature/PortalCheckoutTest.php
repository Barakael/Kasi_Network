<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_take_a_package_when_demo_checkout_is_on(): void
    {
        config()->set('kasi.demo_checkout', true);

        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);
        $this->actingForTenant($tenant);

        $plan = Plan::factory()->for($tenant)->create([
            'is_active' => true,
            'is_sold_online' => true,
        ]);

        $token = $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])->json('token');

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/checkout', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('plan', $plan->name)
            ->assertJsonStructure(['code', 'display_code']);
    }

    public function test_demo_checkout_is_refused_when_disabled(): void
    {
        config()->set('kasi.demo_checkout', false);

        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);
        $plan = Plan::factory()->for($tenant)->create();

        $token = $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])->json('token');

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/checkout', ['plan_id' => $plan->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'checkout_unavailable');
    }
}
