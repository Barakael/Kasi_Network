<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Radius\RadiusAttribute;
use App\Domain\Voucher\VoucherCode;
use App\Domain\Voucher\VoucherIssuer;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
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

        $token = $this->identifiedPortalToken('site-abc', extra: ['mac' => 'aa-bb-cc-dd-ee-ff']);

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => $voucher->code])
            ->assertOk()
            ->assertJsonPath('code', $voucher->code);

        $voucher->refresh();
        $this->assertNotNull($voucher->first_used_at);
        $this->assertNotNull($voucher->expires_at);
        $this->assertSame('active', $voucher->status->value);

        $this->assertDatabaseHas('radcheck', [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::CALLING_STATION_ID,
            'value' => 'AA:BB:CC:DD:EE:FF',
        ]);

        $this->assertDatabaseHas('radcheck', [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::EXPIRATION,
        ]);
    }

    public function test_a_mistyped_code_reports_remaining_attempts_in_swahili(): void
    {
        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);
        RateLimiter::clear('redeem:AA:BB:CC:DD:EE:FF');

        $token = $this->identifiedPortalToken('site-abc', extra: ['mac' => 'AA:BB:CC:DD:EE:FF']);

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => '!!!!'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Umekosea vocha. Umebakiza majaribio 2.');

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => '!!!!'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Umekosea vocha. Umebakiza jaribio 1.');

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => '!!!!'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Umezuiwa kwa dakika 5. Jaribu tena baadaye.');

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => '!!!!'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Umezuiwa kwa dakika 5. Jaribu tena baadaye.');
    }

    public function test_a_successful_redeem_clears_the_attempt_counter(): void
    {
        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);
        $this->actingForTenant($tenant);
        RateLimiter::clear('redeem:AA:BB:CC:DD:EE:01');

        $plan = Plan::factory()->for($tenant)->create(['device_limit' => 1]);
        $voucher = app(VoucherIssuer::class)->issueOne($plan);
        $token = $this->identifiedPortalToken('site-abc', extra: ['mac' => 'AA:BB:CC:DD:EE:01']);

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => '!!!!'])
            ->assertStatus(422);

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => $voucher->code])
            ->assertOk();

        $this->assertSame(0, RateLimiter::attempts('redeem:AA:BB:CC:DD:EE:01'));
    }

    public function test_numeric_charset_issues_six_digit_codes(): void
    {
        $tenant = Tenant::factory()->create([
            'voucher_code_charset' => VoucherCode::CHARSET_NUMERIC,
        ]);
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-num']);
        $this->actingForTenant($tenant);

        $plan = Plan::factory()->for($tenant)->create(['device_limit' => 1]);
        $voucher = app(VoucherIssuer::class)->issueOne($plan);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $voucher->code);

        $token = $this->identifiedPortalToken('site-num', extra: ['mac' => 'AA:BB:CC:DD:EE:02']);

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/redeem', ['code' => $voucher->code])
            ->assertOk()
            ->assertJsonPath('code', $voucher->code);
    }

    public function test_alpha_charset_issues_letter_only_codes(): void
    {
        $tenant = Tenant::factory()->create([
            'voucher_code_charset' => VoucherCode::CHARSET_ALPHA,
        ]);
        $this->actingForTenant($tenant);

        $plan = Plan::factory()->for($tenant)->create();
        $voucher = app(VoucherIssuer::class)->issueOne($plan);

        $this->assertDoesNotMatchRegularExpression('/\d/', $voucher->code);
        $this->assertTrue(VoucherCode::hasValidCheckCharacter($voucher->code, VoucherCode::ALPHABET_ALPHA));
    }

    public function test_owner_can_update_voucher_code_charset(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();

        $this->actingAs($owner)
            ->patchJson('/api/v1/settings', [
                'voucher_code_charset' => VoucherCode::CHARSET_NUMERIC,
            ])
            ->assertOk()
            ->assertJsonPath('data.voucher_code_charset', 'numeric');

        $this->assertSame('numeric', $tenant->fresh()->voucher_code_charset);
    }

    public function test_redeem_requires_a_portal_session(): void
    {
        $this->postJson('/api/portal/redeem', ['code' => 'KASXXXX'])
            ->assertStatus(419);
    }
}
