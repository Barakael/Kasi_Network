<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentQuickVoucherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_agent_can_issue_a_quick_voucher_without_counting_spend_yet(): void
    {
        [$agent, $site, $plan] = $this->agentWithSiteAndPlan();

        $response = $this->actingAs($agent)
            ->postJson('/api/v1/agent/vouchers/quick', [
                'plan_id' => $plan->id,
                'site_id' => $site->id,
            ])
            ->assertCreated();

        $code = $response->json('code');
        $this->assertNotEmpty($code);
        $this->assertSame($code, $response->json('display_code'));
        $this->assertSame($plan->id, $response->json('plan.id'));
        $this->assertSame($plan->name, $response->json('plan.name'));

        $voucher = Voucher::query()->findOrFail($response->json('voucher_id'));
        $this->assertNull($voucher->printed_at);
        $this->assertNull($voucher->first_used_at);
        $this->assertSame($response->json('batch_id'), $voucher->batch_id);
        $this->assertDatabaseHas('voucher_batches', [
            'id' => $voucher->batch_id,
            'assigned_agent_id' => $agent->id,
            'site_id' => $site->id,
            'quantity' => 1,
            'print_count' => 0,
            'printed_at' => null,
        ]);
    }

    #[Test]
    public function two_quick_vouchers_never_share_a_code(): void
    {
        [$agent, $site, $plan] = $this->agentWithSiteAndPlan();

        $first = $this->actingAs($agent)
            ->postJson('/api/v1/agent/vouchers/quick', ['plan_id' => $plan->id, 'site_id' => $site->id])
            ->assertCreated()
            ->json('code');

        $second = $this->actingAs($agent)
            ->postJson('/api/v1/agent/vouchers/quick', ['plan_id' => $plan->id, 'site_id' => $site->id])
            ->assertCreated()
            ->json('code');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, Voucher::query()->count());
        $this->assertSame(2, Voucher::query()->distinct('code_hash')->count('code_hash'));
    }

    #[Test]
    public function inactive_plans_are_rejected(): void
    {
        [$agent, $site, $plan] = $this->agentWithSiteAndPlan();
        $plan->update(['is_active' => false]);

        $this->actingAs($agent)
            ->postJson('/api/v1/agent/vouchers/quick', [
                'plan_id' => $plan->id,
                'site_id' => $site->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    }

    #[Test]
    public function owners_cannot_use_the_agent_quick_endpoint(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $plan = Plan::factory()->for($tenant)->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/agent/vouchers/quick', ['plan_id' => $plan->id])
            ->assertForbidden();
    }

    #[Test]
    public function guests_are_rejected(): void
    {
        $this->postJson('/api/v1/agent/vouchers/quick', ['plan_id' => 1])
            ->assertUnauthorized();
    }

    #[Test]
    public function an_agent_without_a_site_cannot_issue(): void
    {
        $tenant = Tenant::factory()->create();
        $agent = User::factory()->for($tenant)->agent()->create();
        $plan = Plan::factory()->for($tenant)->create();

        $this->actingAs($agent)
            ->postJson('/api/v1/agent/vouchers/quick', ['plan_id' => $plan->id])
            ->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: Site, 2: Plan}
     */
    private function agentWithSiteAndPlan(): array
    {
        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);
        $agent = User::factory()->for($tenant)->agent()->create();
        $site = Site::factory()->for($tenant)->create();
        $agent->sites()->attach($site->id);
        $plan = Plan::factory()->for($tenant)->create(['name' => 'Saa 1', 'price_minor' => 500]);

        return [$agent, $site, $plan];
    }
}
