<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Voucher\BillingPeriod;
use App\Domain\Voucher\QuotaAction;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->for($this->tenant)->owner()->create();
    }

    #[Test]
    public function an_owner_can_create_a_bundle_for_each_billing_period(): void
    {
        foreach (BillingPeriod::cases() as $period) {
            if ($period === BillingPeriod::Custom) {
                continue;
            }

            $response = $this->actingAs($this->owner)->postJson('/api/v1/plans', [
                'name' => $period->label().' bundle',
                'billing_period' => $period->value,
                'price_minor' => 1000,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect->value,
            ]);

            $response->assertCreated()
                // The preset supplies its own window, so the caller does not have
                // to know that a week is 604800 seconds.
                ->assertJsonPath('data.validity_seconds', $period->validitySeconds());
        }
    }

    #[Test]
    public function a_custom_bundle_must_state_its_own_validity(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/plans', [
                'name' => 'Event pass',
                'billing_period' => BillingPeriod::Custom->value,
                'price_minor' => 2000,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect->value,
            ])
            ->assertJsonValidationErrors('validity_seconds');
    }

    #[Test]
    public function a_throttled_bundle_needs_a_throttle_speed(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/plans', [
                'name' => 'Capped bundle',
                'billing_period' => BillingPeriod::Daily->value,
                'price_minor' => 2000,
                'device_limit' => 1,
                'data_cap_bytes' => 1_000_000_000,
                'on_quota_exhausted' => QuotaAction::Throttle->value,
            ])
            ->assertJsonValidationErrors('throttle_down_kbps');
    }

    #[Test]
    public function a_throttle_cannot_be_faster_than_the_bundle_it_throttles(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/plans', [
                'name' => 'Backwards bundle',
                'billing_period' => BillingPeriod::Daily->value,
                'price_minor' => 2000,
                'device_limit' => 1,
                'rate_limit_down_kbps' => 2048,
                'data_cap_bytes' => 1_000_000_000,
                'on_quota_exhausted' => QuotaAction::Throttle->value,
                'throttle_down_kbps' => 8192,
            ])
            ->assertJsonValidationErrors('throttle_down_kbps');
    }

    #[Test]
    public function metered_time_cannot_exceed_the_window_it_sits_in(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/plans', [
                'name' => 'Impossible bundle',
                'billing_period' => BillingPeriod::Hourly->value,
                // Two hours of online time inside a one-hour window: the customer
                // could never spend what they paid for.
                'duration_seconds' => 7200,
                'price_minor' => 500,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect->value,
            ])
            ->assertJsonValidationErrors('duration_seconds');
    }

    #[Test]
    public function a_free_bundle_cannot_be_sold_online(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/plans', [
                'name' => 'Free trial',
                'billing_period' => BillingPeriod::Hourly->value,
                'price_minor' => 0,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect->value,
                'is_sold_online' => true,
            ])
            ->assertJsonValidationErrors('is_sold_online');
    }

    #[Test]
    public function editing_a_bundles_name_leaves_a_custom_window_alone(): void
    {
        $plan = Plan::factory()->for($this->tenant)->create([
            'billing_period' => BillingPeriod::Custom,
            'validity_seconds' => 10_800,
        ]);

        $this->actingAs($this->owner)
            ->patchJson("/api/v1/plans/{$plan->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.validity_seconds', 10_800);
    }

    #[Test]
    public function a_partial_edit_cannot_strip_the_throttle_from_a_throttled_bundle(): void
    {
        $plan = Plan::factory()->for($this->tenant)->throttled()->create([
            'rate_limit_down_kbps' => 4096,
        ]);

        /*
         * The payload never mentions on_quota_exhausted, so a rule reading only
         * the request would find nothing to check and let the throttle speed go.
         */
        $this->actingAs($this->owner)
            ->patchJson("/api/v1/plans/{$plan->id}", ['throttle_down_kbps' => null])
            ->assertJsonValidationErrors('throttle_down_kbps');
    }

    #[Test]
    public function an_agent_cannot_change_the_price_list(): void
    {
        // Staff may edit bundles; agents sell what they are given and nothing
        // more, which is the whole of the reseller tier.
        $agent = User::factory()->for($this->tenant)->agent()->create();

        $this->actingAs($agent)
            ->postJson('/api/v1/plans', [
                'name' => 'Unauthorised bundle',
                'billing_period' => BillingPeriod::Daily->value,
                'price_minor' => 1000,
                'device_limit' => 1,
                'on_quota_exhausted' => QuotaAction::Disconnect->value,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function another_operators_bundle_cannot_be_read_or_edited(): void
    {
        $other = Plan::factory()->for(Tenant::factory())->create();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/plans/{$other->id}")
            ->assertNotFound();

        $this->actingAs($this->owner)
            ->patchJson("/api/v1/plans/{$other->id}", ['name' => 'Hijacked'])
            ->assertNotFound();
    }

    #[Test]
    public function a_retired_bundle_disappears_from_the_list_without_breaking_its_vouchers(): void
    {
        $plan = Plan::factory()->for($this->tenant)->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/plans/{$plan->id}")
            ->assertOk();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Soft deleted, so reports and outstanding vouchers can still name what
        // was sold.
        $this->assertNotNull($plan->fresh()->deleted_at);
    }

    #[Test]
    public function the_list_can_be_narrowed_to_bundles_currently_on_sale(): void
    {
        Plan::factory()->for($this->tenant)->count(2)->create(['is_active' => true]);
        Plan::factory()->for($this->tenant)->create(['is_active' => false]);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/plans?active_only=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
