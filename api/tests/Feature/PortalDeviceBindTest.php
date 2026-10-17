<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Voucher\VoucherIssuer;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalDeviceBindTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_bind_activates_the_voucher_and_returns_swahili_message(): void
    {
        $tenant = Tenant::factory()->create(['code_prefix' => 'KAS']);
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-bind']);
        $this->actingForTenant($tenant);

        $plan = Plan::factory()->for($tenant)->create(['device_limit' => 2]);
        $voucher = app(VoucherIssuer::class)->issueOne($plan);

        $token = $this->postJson('/api/portal/bootstrap', [
            'site' => 'site-bind',
            'mac' => 'aa:bb:cc:dd:ee:01',
        ])->json('token');

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/devices', [
                'code' => $voucher->code,
                'mac' => 'aa-bb-cc-dd-ee-ff',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mac', 'AA:BB:CC:DD:EE:FF')
            ->assertJsonPath('message', 'Kifaa kimesajiliwa. Washa Wi‑Fi upya kwenye kifaa hicho.')
            ->assertJsonPath('authorized', false);

        $voucher->refresh();
        $this->assertNotNull($voucher->first_used_at);
        $this->assertSame('active', $voucher->status->value);
    }
}
