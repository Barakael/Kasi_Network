<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Radius\RadiusAttribute;
use App\Domain\Voucher\VoucherIssuer;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalRedeemTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_code_is_accepted_and_bound_to_the_client_mac(): void
    {
        $tenant = Tenant::factory()->create(['code_prefix' => 'KAS']);
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);
        $this->actingForTenant($tenant);

        $plan = Plan::factory()->for($tenant)->create(['device_limit' => 1]);
        $voucher = app(VoucherIssuer::class)->issueOne($plan);

        $token = $this->postJson('/api/portal/bootstrap', [
            'site' => 'site-abc',
            'mac' => 'aa-bb-cc-dd-ee-ff',
        ])->json('token');

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => $voucher->code])
            ->assertOk()
            ->assertJsonPath('code', $voucher->code);

        $this->assertDatabaseHas('radcheck', [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::CALLING_STATION_ID,
            'value' => 'AA:BB:CC:DD:EE:FF',
        ]);
    }

    public function test_a_mistyped_code_is_rejected_before_a_lookup(): void
    {
        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);

        $token = $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])->json('token');

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => 'KAS-XXXX-XXXX-X1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_redeem_requires_a_portal_session(): void
    {
        $this->postJson('/api/portal/redeem', ['code' => 'KASXXXX'])
            ->assertStatus(419);
    }
}
