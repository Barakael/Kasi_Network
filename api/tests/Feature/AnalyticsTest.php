<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Billing\OrderStatus;
use App\Domain\Voucher\BatchStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\RadAcct;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Models\VoucherUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_splits_income_and_credits_each_agent(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $site = Site::factory()->for($tenant)->create(['name' => 'Kariakoo']);
        $agent = User::factory()->for($tenant)->agent()->create(['name' => 'Neema']);
        $agent->sites()->attach($site->id);

        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create(['name' => 'Siku Moja', 'price_minor' => 1000]);
        $batch = VoucherBatch::factory()->for($tenant)->for($plan)->create([
            'site_id' => $site->id,
            'assigned_agent_id' => $agent->id,
            'status' => BatchStatus::Ready,
        ]);

        // Sold at the counter: printed and handed over, cash in the agent's hand.
        Voucher::factory()->forPlan($plan)->create([
            'tenant_id' => $tenant->id,
            'batch_id' => $batch->id,
            'price_minor' => 1000,
            'printed_at' => now(),
        ]);

        // Printed earlier and now actually on the Wi‑Fi: operator income.
        Voucher::factory()->forPlan($plan)->create([
            'tenant_id' => $tenant->id,
            'batch_id' => $batch->id,
            'price_minor' => 1000,
            'printed_at' => now()->subHours(2),
            'first_used_at' => now(),
        ]);

        Order::factory()->for($plan)->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'status' => OrderStatus::Fulfilled,
            'amount_minor' => 2000,
            'net_minor' => 2000,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/reports/insights?period=day')
            ->assertOk()
            ->assertJsonPath('income.lipia_minor', 2000)
            ->assertJsonPath('income.kadi_minor', 1000)
            ->assertJsonPath('income.total_minor', 3000)
            ->assertJsonPath('agents.0.name', 'Neema')
            ->assertJsonPath('agents.0.sold_minor', 2000)
            ->assertJsonPath('agents.0.sold_count', 2)
            ->assertJsonPath('agents.0.delivered_minor', 1000);

        $this->assertSame(['Kariakoo'], $response->json('agents.0.sites'));
        $this->assertSame('Siku Moja', $response->json('plans.0.name'));
        $this->assertSame(3000, $response->json('sites.0.total_minor'));
    }

    public function test_analytics_counts_cards_whose_window_has_closed(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create(['price_minor' => 500]);

        // Sold, still marked active, but its validity ran out.
        Voucher::factory()->forPlan($plan)->expired()->create(['tenant_id' => $tenant->id]);

        // Never sold and past its shelf life: stock that can no longer be sold.
        Voucher::factory()->forPlan($plan)->pastShelfLife()->create([
            'tenant_id' => $tenant->id,
            'price_minor' => 500,
        ]);

        Voucher::factory()->forPlan($plan)->disabled()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/insights?period=day')
            ->assertOk()
            ->assertJsonPath('stock.expired', 1)
            ->assertJsonPath('stock.time_up', 1)
            ->assertJsonPath('stock.dead_stock', 1)
            ->assertJsonPath('stock.dead_stock_value_minor', 500)
            ->assertJsonPath('stock.disabled', 1);
    }

    public function test_analytics_separates_customers_online_from_the_rest(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $site = Site::factory()->for($tenant)->create();
        $this->actingForTenant($tenant);
        Customer::factory()->for($tenant)->atSite($site)->count(3)->create();

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/insights?period=day')
            ->assertOk()
            ->assertJsonPath('customers.total', 3)
            ->assertJsonPath('customers.online_now', 0)
            ->assertJsonPath('customers.kimya', 3)
            ->assertJsonPath('customers.hai', 0);
    }

    public function test_customers_can_be_filtered_down_to_those_on_the_radio_now(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $site = Site::factory()->for($tenant)->create();
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create();

        $online = Customer::factory()->for($tenant)->atSite($site)->create(['phone' => '255712000001']);
        Customer::factory()->for($tenant)->atSite($site)->create(['phone' => '255712000002']);

        $voucher = Voucher::factory()->forPlan($plan)->active()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $online->id,
        ]);
        $usage = VoucherUsage::factory()->for($voucher)->create(['tenant_id' => $tenant->id]);
        RadAcct::factory()->forUsername($usage->username)->create();

        $this->actingAs($owner)
            ->getJson('/api/v1/customers?status=online')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $online->id);

        $this->actingAs($owner)
            ->getJson('/api/v1/reports/insights?period=day')
            ->assertOk()
            ->assertJsonPath('customers.online_now', 1)
            ->assertJsonPath('customers.sessions_open', 1);
    }

    public function test_an_agent_cannot_open_operator_analytics(): void
    {
        $tenant = Tenant::factory()->create();
        $agent = User::factory()->for($tenant)->agent()->create();

        $this->actingAs($agent)
            ->getJson('/api/v1/reports/insights?period=day')
            ->assertForbidden();
    }
}
