<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Billing\PalmPesaException;
use App\Domain\Billing\PalmPesaGateway;
use App\Domain\Billing\PalmPesaPaymentStatus;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PalmPesaGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('kasi.palmpesa.base_url', 'https://palmpesa.drmlelwa.co.tz');
        config()->set('kasi.palmpesa.customer_email', 'hotspot@wayda.co.tz');
        config()->set('kasi.palmpesa.address', 'Dar es Salaam');
        config()->set('kasi.palmpesa.postcode', '11111');
    }

    protected function tearDown(): void
    {
        Http::allowStrayRequests();

        parent::tearDown();
    }

    public function test_initiate_posts_the_ussd_payload_and_returns_the_order_id(): void
    {
        Http::fake([
            'https://palmpesa.drmlelwa.co.tz/api/palmpesa/initiate' => Http::response([
                'order_id' => '998877',
            ], 200),
        ]);

        $tenant = Tenant::factory()->withPalmPesa()->create([
            'portal_name' => 'Wayda Hotspot',
        ]);
        $this->actingForTenant($tenant);

        $order = Order::factory()->for(Plan::factory()->for($tenant))->create([
            'tenant_id' => $tenant->id,
            'amount_minor' => 500,
            'phone' => '255712345678',
            'customer_name' => 'Wayda Magomeni',
            'customer_email' => 'hotspot@wayda.co.tz',
        ]);

        $orderId = app(PalmPesaGateway::class)->initiate(
            $tenant,
            $order,
            'https://net.wayda.co.tz/webhooks/palmpesa/'.$tenant->uuid,
        );

        $this->assertSame('998877', $orderId);

        Http::assertSent(function (Request $request) use ($tenant, $order): bool {
            return $request->url() === 'https://palmpesa.drmlelwa.co.tz/api/palmpesa/initiate'
                && $request->hasHeader('Authorization', 'Bearer '.$tenant->palmpesaApiToken())
                && $request['name'] === 'Wayda Magomeni'
                && $request['email'] === 'hotspot@wayda.co.tz'
                && $request['phone'] === '0712345678'
                && $request['amount'] === 500
                && $request['transaction_id'] === $order->uuid
                && $request['address'] === 'Dar es Salaam'
                && $request['postcode'] === '11111'
                && $request['callback_url'] === 'https://net.wayda.co.tz/webhooks/palmpesa/'.$tenant->uuid;
        });
    }

    public function test_initiate_throws_when_palm_pesa_rejects_the_push(): void
    {
        Http::fake([
            'https://palmpesa.drmlelwa.co.tz/api/palmpesa/initiate' => Http::response([
                'message' => 'Invalid token',
            ], 401),
        ]);

        $tenant = Tenant::factory()->withPalmPesa()->create();
        $this->actingForTenant($tenant);
        $order = Order::factory()->for(Plan::factory()->for($tenant))->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->expectException(PalmPesaException::class);

        app(PalmPesaGateway::class)->initiate($tenant, $order, 'https://example.test/cb');
    }

    public function test_status_maps_completed_failed_and_pending(): void
    {
        $tenant = Tenant::factory()->withPalmPesa()->create();

        Http::fake([
            'https://palmpesa.drmlelwa.co.tz/api/order-status' => Http::sequence()
                ->push(['data' => [['payment_status' => 'COMPLETED']]], 200)
                ->push(['data' => [['payment_status' => 'FAILED']]], 200)
                ->push(['data' => [['payment_status' => 'PENDING']]], 200),
        ]);

        $gateway = app(PalmPesaGateway::class);

        $this->assertSame(PalmPesaPaymentStatus::Completed, $gateway->status($tenant, '1'));
        $this->assertSame(PalmPesaPaymentStatus::Failed, $gateway->status($tenant, '1'));
        $this->assertSame(PalmPesaPaymentStatus::Pending, $gateway->status($tenant, '1'));
    }
}
