<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Tenancy\PortalContext;
use App\Domain\Tenancy\PortalToken;
use App\Models\NasDevice;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PortalBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_receives_a_token_branding_and_the_bundle_list(): void
    {
        $tenant = Tenant::factory()->create(['portal_name' => 'Kariakoo WiFi']);
        $site = Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);
        Plan::factory()->for($tenant)->count(3)->create();

        $response = $this->postJson('/api/portal/bootstrap', [
            'site' => 'site-abc',
            'mac' => 'aa-bb-cc-dd-ee-ff',
            'ip' => '10.5.50.20',
        ]);

        $response->assertOk()
            ->assertJsonPath('branding.operator', 'Kariakoo WiFi')
            ->assertJsonPath('site.name', $site->name)
            // Normalised from the dashed form the router sent.
            ->assertJsonPath('client.mac', 'AA:BB:CC:DD:EE:FF')
            ->assertJsonCount(3, 'plans');

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_bundles_that_are_inactive_or_not_sold_online_are_hidden(): void
    {
        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);

        Plan::factory()->for($tenant)->create(['is_active' => true, 'is_sold_online' => true]);
        Plan::factory()->for($tenant)->create(['is_active' => false, 'is_sold_online' => true]);
        // Sold only as printed vouchers at the counter.
        Plan::factory()->for($tenant)->create(['is_active' => true, 'is_sold_online' => false]);

        $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])
            ->assertOk()
            ->assertJsonCount(1, 'plans')
            ->assertJsonMissingPath('plans.0.rate_limit_down_kbps');
    }

    public function test_another_operators_bundles_are_never_listed(): void
    {
        $mine = Tenant::factory()->create();
        $theirs = Tenant::factory()->create();

        Site::factory()->for($mine)->create(['nas_identifier' => 'site-mine']);
        Plan::factory()->for($mine)->count(2)->create();
        Plan::factory()->for($theirs)->count(5)->create();

        $this->postJson('/api/portal/bootstrap', ['site' => 'site-mine'])
            ->assertOk()
            ->assertJsonCount(2, 'plans');
    }

    public function test_an_unknown_site_is_rejected(): void
    {
        $this->postJson('/api/portal/bootstrap', ['site' => 'does-not-exist'])
            ->assertNotFound()
            ->assertJsonPath('code', 'site_unavailable');
    }

    public function test_a_suspended_operator_cannot_serve_a_portal(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);

        // Same response as an unknown site: which of the two it was is not
        // something an unauthenticated client needs to learn.
        $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])
            ->assertNotFound()
            ->assertJsonPath('code', 'site_unavailable');
    }

    public function test_device_discovery_is_only_advertised_when_the_router_is_reachable(): void
    {
        $tenant = Tenant::factory()->create();
        $site = Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);

        $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])
            ->assertOk()
            ->assertJsonPath('capabilities.device_discovery', false);

        NasDevice::factory()->for($site)->withApiAccess()->create(['tenant_id' => $tenant->id]);

        // Listing nearby devices to bind a TV to requires RouterOS API access.
        $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])
            ->assertOk()
            ->assertJsonPath('capabilities.device_discovery', true);
    }

    public function test_online_payments_are_only_advertised_when_credentials_exist(): void
    {
        $without = Tenant::factory()->create();
        Site::factory()->for($without)->create(['nas_identifier' => 'site-no-pay']);

        $this->postJson('/api/portal/bootstrap', ['site' => 'site-no-pay'])
            ->assertOk()
            ->assertJsonPath('capabilities.online_payments', false);

        $with = Tenant::factory()->withPayments()->create();
        Site::factory()->for($with)->create(['nas_identifier' => 'site-pay']);

        $this->postJson('/api/portal/bootstrap', ['site' => 'site-pay'])
            ->assertOk()
            ->assertJsonPath('capabilities.online_payments', true);
    }

    public function test_a_token_resolves_back_to_the_site_it_was_issued_for(): void
    {
        $tenant = Tenant::factory()->create();
        $site = Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);

        $token = $this->postJson('/api/portal/bootstrap', [
            'site' => 'site-abc',
            'mac' => 'AA:BB:CC:DD:EE:FF',
        ])->json('token');

        $context = app(PortalToken::class)->parse($token);

        $this->assertInstanceOf(PortalContext::class, $context);
        $this->assertSame($site->id, $context->site->id);
        $this->assertSame($tenant->id, $context->tenant->id);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $context->clientMac);
    }

    public function test_a_tampered_token_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);

        $token = $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])->json('token');

        /*
         * The whole point of signing the token: a client cannot edit it to claim
         * a different site and redeem another operator's vouchers.
         */
        $this->assertNull(app(PortalToken::class)->parse($token.'x'));
        $this->assertNull(app(PortalToken::class)->parse(base64_encode('{"t":1,"s":1,"x":99999999999}')));
        $this->assertNull(app(PortalToken::class)->parse('not-a-token'));
        $this->assertNull(app(PortalToken::class)->parse(null));
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);

        $token = $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])->json('token');

        Carbon::setTestNow(now()->addHours(2));

        $this->assertNull(app(PortalToken::class)->parse($token));

        Carbon::setTestNow();
    }

    public function test_a_token_stops_working_once_its_operator_is_suspended(): void
    {
        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);

        $token = $this->postJson('/api/portal/bootstrap', ['site' => 'site-abc'])->json('token');

        $tenant->update(['status' => 'suspended']);

        // Suspension has to take effect immediately, not whenever tokens expire.
        $this->assertNull(app(PortalToken::class)->parse($token));
    }

    public function test_the_site_identifier_is_required(): void
    {
        $this->postJson('/api/portal/bootstrap', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('site');
    }

    public function test_an_unreadable_mac_is_reported_rather_than_stored(): void
    {
        $tenant = Tenant::factory()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-abc']);

        $this->postJson('/api/portal/bootstrap', [
            'site' => 'site-abc',
            'mac' => 'not-a-mac',
        ])
            ->assertOk()
            ->assertJsonPath('client.mac', null)
            // The portal uses this to decide whether binding a TV is even
            // offerable, rather than failing later with no explanation.
            ->assertJsonPath('client.mac_known', false);
    }
}
