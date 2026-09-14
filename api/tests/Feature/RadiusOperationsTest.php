<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Radius\AccountingRollup;
use App\Domain\Radius\CoaDispatcher;
use App\Domain\Radius\QuotaEnforcer;
use App\Domain\Radius\RadiusAttribute;
use App\Domain\Voucher\MacBinder;
use App\Domain\Voucher\VoucherIssuer;
use App\Domain\Voucher\VoucherStatus;
use App\Models\NasDevice;
use App\Models\Plan;
use App\Models\RadAcct;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VoucherUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RadiusOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_sessions_are_folded_into_voucher_usage(): void
    {
        $tenant = Tenant::factory()->create(['code_prefix' => 'KAS']);
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create();
        $voucher = app(VoucherIssuer::class)->issueOne($plan);

        RadAcct::factory()->forUsername($voucher->code)->closed(600)->usingBytes(1_000_000, 4_000_000)->create();

        $updated = app(AccountingRollup::class)->rollup();

        $this->assertSame(1, $updated);

        $usage = VoucherUsage::query()->where('username', $voucher->code)->firstOrFail();
        $this->assertSame(600, $usage->seconds_used);
        $this->assertSame(5_000_000, $usage->bytesTotal());
    }

    public function test_disconnect_is_sent_through_radclient(): void
    {
        Process::fake([
            '*' => Process::result(output: 'Sent Disconnect-Request'),
        ]);

        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);
        $site = Site::factory()->for($tenant)->create();
        $nas = NasDevice::factory()->for($site)->create([
            'tenant_id' => $tenant->id,
            'nasname' => '10.10.10.1',
        ]);
        $session = RadAcct::factory()->create([
            'nasipaddress' => '10.10.10.1',
            'username' => 'KASTESTCODE',
        ]);

        $ok = app(CoaDispatcher::class)->disconnect($session, $nas, 'test');

        $this->assertTrue($ok);
    }

    public function test_quota_enforcer_disconnects_an_overrun_session(): void
    {
        $tenant = Tenant::factory()->create(['code_prefix' => 'KAS']);
        $this->actingForTenant($tenant);
        $site = Site::factory()->for($tenant)->create();
        $nas = NasDevice::factory()->for($site)->create([
            'tenant_id' => $tenant->id,
            'nasname' => '10.10.10.2',
        ]);
        $plan = Plan::factory()->for($tenant)->withDataCap(1_000_000)->create();
        $voucher = app(VoucherIssuer::class)->issueOne($plan);
        $voucher->update(['status' => VoucherStatus::Active]);

        RadAcct::factory()->forUsername($voucher->code)->usingBytes(800_000, 800_000)->create([
            'nasipaddress' => $nas->nasname,
        ]);

        Process::fake([
            '*' => Process::result(output: 'Sent Disconnect-Request'),
        ]);

        $acted = app(QuotaEnforcer::class)->enforce();

        $this->assertSame(1, $acted);
        $this->assertSame(VoucherStatus::Exhausted, $voucher->fresh()->status);
    }

    public function test_binding_a_mac_writes_calling_station_id_not_a_username(): void
    {
        $tenant = Tenant::factory()->create(['code_prefix' => 'KAS']);
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->sharedDevices(2)->create();
        $voucher = app(VoucherIssuer::class)->issueOne($plan);
        $voucher->update(['status' => VoucherStatus::Active]);

        $device = app(MacBinder::class)->bind($voucher, 'aa:bb:cc:dd:ee:ff', 'portal', 'TV');

        $this->assertSame('AA:BB:CC:DD:EE:FF', $device->mac);
        $this->assertDatabaseMissing('radcheck', [
            'username' => 'AA:BB:CC:DD:EE:FF',
        ]);
        $this->assertDatabaseMissing('radcheck', [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::CALLING_STATION_ID,
        ]);

        $single = app(VoucherIssuer::class)->issueOne(Plan::factory()->for($tenant)->create(['device_limit' => 1]));
        $single->update(['status' => VoucherStatus::Active]);
        app(MacBinder::class)->bind($single, '11:22:33:44:55:66', 'portal');

        $this->assertDatabaseHas('radcheck', [
            'username' => $single->code,
            'attribute' => RadiusAttribute::CALLING_STATION_ID,
            'value' => '11:22:33:44:55:66',
        ]);
    }

    public function test_the_dashboard_reports_concurrent_sessions_and_revenue(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->owner()->create();
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create();
        $voucher = app(VoucherIssuer::class)->issueOne($plan);

        RadAcct::factory()->forUsername($voucher->code)->create();

        $this->actingAs($owner)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('concurrent_sessions', 1)
            ->assertJsonStructure(['revenue_today_minor', 'revenue_month_minor', 'vouchers_activated_today', 'revenue_series']);
    }

    public function test_an_agent_cannot_open_the_dashboard(): void
    {
        $tenant = Tenant::factory()->create();
        $agent = User::factory()->for($tenant)->agent()->create();

        $this->actingAs($agent)
            ->getJson('/api/v1/dashboard')
            ->assertForbidden();
    }
}
