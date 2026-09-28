<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Billing\OrderStatus;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardRevenueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function used_printed_vouchers_count_as_todays_take(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create(['price_minor' => 500]);

        Voucher::factory()->forPlan($plan)->active()->create(['price_minor' => 500]);
        Voucher::factory()->forPlan($plan)->active()->create(['price_minor' => 500]);
        Voucher::factory()->forPlan($plan)->create(['price_minor' => 1000]);

        $this->actingAs($owner)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('revenue_today_minor', 1000)
            ->assertJsonPath('vouchers_activated_today', 2)
            ->assertJsonPath('revenue_month_minor', 1000);
    }

    #[Test]
    public function an_online_order_is_not_counted_again_when_its_voucher_is_used(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create(['price_minor' => 1000]);
        $voucher = Voucher::factory()->forPlan($plan)->active()->create(['price_minor' => 1000]);

        Order::factory()->for($plan)->paid()->create([
            'tenant_id' => $tenant->id,
            'voucher_id' => $voucher->id,
            'amount_minor' => 1000,
            'status' => OrderStatus::Fulfilled,
            'net_minor' => 1000,
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('revenue_today_minor', 1000)
            ->assertJsonPath('vouchers_activated_today', 1);
    }
}
