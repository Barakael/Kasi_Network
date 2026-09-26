<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Radius\RadiusAttribute;
use App\Domain\Voucher\MacBinder;
use App\Domain\Voucher\VoucherStatus;
use App\Models\NasDevice;
use App\Models\Plan;
use App\Models\RadAcct;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SessionDisconnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_disconnect_finds_the_router_by_api_host_and_releases_the_phone(): void
    {
        Process::fake([
            '*' => Process::result(exitCode: 1, errorOutput: 'timeout'),
        ]);

        [$owner, $voucher, $session] = $this->boundSession(
            nasname: '102.202.75.144',
            apiHost: '192.168.88.1',
            accountingIp: '192.168.88.1',
        );

        $this->actingAs($owner)
            ->postJson('/api/v1/sessions/disconnect', ['acctuniqueid' => $session->acctuniqueid])
            ->assertOk()
            ->assertJsonPath('disconnected', false)
            ->assertJsonPath('session_closed', true)
            ->assertJsonPath('mac_released', true);

        $this->assertNotNull(
            RadAcct::query()->where('radacctid', $session->radacctid)->value('acctstoptime'),
        );
        $this->assertNull($voucher->fresh()->bound_mac);
        $this->assertSame('revoked', $voucher->devices()->first()?->status);
        $this->assertDatabaseMissing('radcheck', [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::CALLING_STATION_ID,
        ]);
    }

    public function test_disconnect_still_releases_the_phone_when_the_router_ip_is_unknown(): void
    {
        [$owner, $voucher, $session] = $this->boundSession(
            nasname: '102.202.75.144',
            apiHost: '192.168.88.1',
            accountingIp: '10.9.9.9',
        );

        $this->actingAs($owner)
            ->postJson('/api/v1/sessions/disconnect', ['acctuniqueid' => $session->acctuniqueid])
            ->assertOk()
            ->assertJsonPath('disconnected', false)
            ->assertJsonMissingPath('message');

        $this->assertNotNull(
            RadAcct::query()->where('radacctid', $session->radacctid)->value('acctstoptime'),
        );
        $this->assertNull($voucher->fresh()->bound_mac);
    }

    /**
     * @return array{0: User, 1: Voucher, 2: RadAcct}
     */
    private function boundSession(string $nasname, string $apiHost, string $accountingIp): array
    {
        $tenant = Tenant::factory()->create(['code_prefix' => 'KAS']);
        $this->actingForTenant($tenant);
        $owner = User::factory()->owner()->for($tenant)->create();
        $site = Site::factory()->for($tenant)->create();
        NasDevice::factory()->for($site)->create([
            'tenant_id' => $tenant->id,
            'nasname' => $nasname,
            'api_host' => $apiHost,
        ]);
        $plan = Plan::factory()->for($tenant)->create(['device_limit' => 1]);
        $voucher = Voucher::factory()->forPlan($plan)->create([
            'status' => VoucherStatus::Active,
            'first_used_at' => now()->subMinutes(5),
        ]);
        app(MacBinder::class)->bind($voucher, 'aa:bb:cc:dd:ee:ff', 'portal');
        VoucherUsage::factory()->create([
            'tenant_id' => $tenant->id,
            'voucher_id' => $voucher->id,
            'username' => $voucher->code,
        ]);
        $session = RadAcct::factory()->forUsername($voucher->code)->create([
            'nasipaddress' => $accountingIp,
            'callingstationid' => 'AA:BB:CC:DD:EE:FF',
        ]);

        return [$owner, $voucher->fresh(), $session];
    }
}
