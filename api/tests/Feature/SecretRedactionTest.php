<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Nas;
use App\Models\NasDevice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the secrets that must never appear in a serialised model.
 *
 * These assertions look trivial, and they exist because the failure they catch is
 * silent. A model that declares its hidden attributes through something Eloquent
 * does not actually call -- a `hidden()` method rather than the `$hidden` property
 * or the Hidden attribute -- looks correct on the page, throws no error, and
 * quietly publishes voucher codes and RADIUS shared secrets in every JSON
 * response that touches the model.
 */
class SecretRedactionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_voucher_never_serialises_its_code(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);

        $voucher = Voucher::factory()->for(Plan::factory()->for($tenant))->create();

        $serialised = $voucher->toArray();

        $this->assertArrayNotHasKey('code', $serialised);
        $this->assertArrayNotHasKey('code_hash', $serialised);

        // Still readable in application code; it is only the serialised form that
        // withholds it.
        $this->assertNotEmpty($voucher->code);
    }

    #[Test]
    public function a_tenant_never_serialises_its_payment_credentials(): void
    {
        $tenant = Tenant::factory()->withPayments()->create();

        $serialised = $tenant->toArray();

        $this->assertArrayNotHasKey('snippe_api_key', $serialised);
        $this->assertArrayNotHasKey('snippe_webhook_secret', $serialised);
        $this->assertNotEmpty($tenant->snippe_api_key);
    }

    #[Test]
    public function a_router_never_serialises_its_shared_secret_or_api_password(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingForTenant($tenant);

        $device = NasDevice::factory()->for($tenant)->withApiAccess()->create();

        $serialised = $device->toArray();

        $this->assertArrayNotHasKey('shared_secret', $serialised);
        $this->assertArrayNotHasKey('api_password', $serialised);
        $this->assertNotEmpty($device->shared_secret);
    }

    #[Test]
    public function the_radius_client_table_never_serialises_its_secret(): void
    {
        $nas = Nas::query()->create([
            'nasname' => '10.0.0.1',
            'shortname' => 'test-router',
            'type' => 'other',
            'secret' => 'a-shared-secret',
            'description' => 'Test',
        ]);

        $this->assertArrayNotHasKey('secret', $nas->toArray());
    }
}
