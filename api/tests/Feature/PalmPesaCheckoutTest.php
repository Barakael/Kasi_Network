<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Billing\OrderStatus;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PalmPesaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('kasi.palmpesa.base_url', 'https://palmpesa.drmlelwa.co.tz');
        config()->set('app.url', 'https://net.wayda.co.tz');
    }

    protected function tearDown(): void
    {
        Http::allowStrayRequests();

        parent::tearDown();
    }

    public function test_lipia_starts_a_ussd_push_and_returns_awaiting_payment(): void
    {
        Http::fake([
            'https://palmpesa.drmlelwa.co.tz/api/palmpesa/initiate' => Http::response([
                'order_id' => '441122',
            ], 200),
        ]);

        [$token, $plan, $tenant] = $this->portalReady();

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->postJson('/api/portal/orders', [
                'plan_id' => $plan->id,
                'phone' => '0712345678',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_payment');

        $order = Order::withoutTenantScope()->where('plan_id', $plan->id)->first();

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('441122', $order->palmpesa_order_id);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame('255712345678', $order->phone);

        Http::assertSent(fn ($request) => $request->url() === 'https://palmpesa.drmlelwa.co.tz/api/palmpesa/initiate'
            && $request['transaction_id'] === $order->uuid
            && $request['callback_url'] === 'https://net.wayda.co.tz/webhooks/palmpesa/'.$tenant->uuid
            && $request['amount'] === 500);
    }

    public function test_a_webhook_issues_a_voucher_only_after_order_status_is_completed(): void
    {
        Http::fake([
            'https://palmpesa.drmlelwa.co.tz/api/order-status' => Http::response([
                'data' => [['payment_status' => 'COMPLETED']],
            ], 200),
        ]);

        $tenant = Tenant::factory()->withPalmPesa()->create(['code_prefix' => 'WYD']);
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->hourly()->create(['price_minor' => 500]);
        $order = Order::factory()->for($plan)->awaitingPalmPesa()->create([
            'tenant_id' => $tenant->id,
            'amount_minor' => 500,
            'palmpesa_order_id' => '778899',
        ]);

        $payload = [
            'order_id' => '778899',
            'transaction_id' => $order->uuid,
            'payment_status' => 'COMPLETED',
        ];

        $this->postJson('/webhooks/palmpesa/'.$tenant->uuid, $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(OrderStatus::Fulfilled, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->voucher_id);
        $this->assertSame(1, Voucher::withoutTenantScope()->where('plan_id', $plan->id)->count());

        $this->postJson('/webhooks/palmpesa/'.$tenant->uuid, $payload)->assertOk();

        $this->assertSame(1, Voucher::withoutTenantScope()->where('plan_id', $plan->id)->count());
    }

    public function test_a_completed_callback_is_ignored_while_order_status_is_still_pending(): void
    {
        Http::fake([
            'https://palmpesa.drmlelwa.co.tz/api/order-status' => Http::response([
                'data' => [['payment_status' => 'PENDING']],
            ], 200),
        ]);

        $tenant = Tenant::factory()->withPalmPesa()->create(['code_prefix' => 'WYD']);
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create();
        $order = Order::factory()->for($plan)->awaitingPalmPesa()->create([
            'tenant_id' => $tenant->id,
            'palmpesa_order_id' => '112233',
        ]);

        $this->postJson('/webhooks/palmpesa/'.$tenant->uuid, [
            'order_id' => '112233',
            'transaction_id' => $order->uuid,
            'payment_status' => 'COMPLETED',
        ])->assertOk();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->voucher_id);
    }

    public function test_a_failed_order_status_does_not_issue_a_voucher(): void
    {
        Http::fake([
            'https://palmpesa.drmlelwa.co.tz/api/order-status' => Http::response([
                'data' => [['payment_status' => 'FAILED']],
            ], 200),
        ]);

        $tenant = Tenant::factory()->withPalmPesa()->create(['code_prefix' => 'WYD']);
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->create();
        $order = Order::factory()->for($plan)->awaitingPalmPesa()->create([
            'tenant_id' => $tenant->id,
            'palmpesa_order_id' => '556677',
        ]);

        $this->postJson('/webhooks/palmpesa/'.$tenant->uuid, [
            'order_id' => '556677',
            'transaction_id' => $order->uuid,
            'payment_status' => 'FAILED',
        ])->assertOk();

        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
        $this->assertSame(0, Voucher::withoutTenantScope()->where('plan_id', $plan->id)->count());
    }

    public function test_reconcile_fulfills_a_late_completed_palmpesa_order(): void
    {
        Http::fake([
            'https://palmpesa.drmlelwa.co.tz/api/order-status' => Http::response([
                'data' => [['payment_status' => 'COMPLETED']],
            ], 200),
        ]);

        $tenant = Tenant::factory()->withPalmPesa()->create(['code_prefix' => 'WYD']);
        $this->actingForTenant($tenant);
        $plan = Plan::factory()->for($tenant)->hourly()->create(['price_minor' => 500]);
        $order = Order::factory()->for($plan)->awaitingPalmPesa()->create([
            'tenant_id' => $tenant->id,
            'amount_minor' => 500,
            'palmpesa_order_id' => '990011',
        ]);

        $this->artisan('kasi:reconcile-orders')->assertSuccessful();

        $this->assertSame(OrderStatus::Fulfilled, $order->fresh()->status);
        $this->assertSame(1, Voucher::withoutTenantScope()->where('plan_id', $plan->id)->count());
    }

    public function test_polling_the_order_issues_a_voucher_once_palm_pesa_reports_completed(): void
    {
        Http::fake([
            'https://palmpesa.drmlelwa.co.tz/api/order-status' => Http::response([
                'data' => [['payment_status' => 'COMPLETED']],
            ], 200),
        ]);

        [$token, $plan, $tenant] = $this->portalReady();
        $this->actingForTenant($tenant);
        $order = Order::factory()->for($plan)->awaitingPalmPesa()->create([
            'tenant_id' => $tenant->id,
            'amount_minor' => 200,
            'palmpesa_order_id' => 'PALMPESA-POLL-1',
        ]);

        $this->withHeader('X-Kasi-Portal-Token', $token)
            ->getJson('/api/portal/orders/'.$order->uuid)
            ->assertOk()
            ->assertJsonPath('data.status', 'fulfilled')
            ->assertJsonPath('data.code', fn ($code) => is_string($code) && $code !== '');

        $this->assertSame(1, Voucher::withoutTenantScope()->where('plan_id', $plan->id)->count());
    }

    public function test_an_unknown_tenant_webhook_is_not_found(): void
    {
        $this->postJson('/webhooks/palmpesa/'.fake()->uuid(), [
            'order_id' => '1',
        ])->assertNotFound();
    }

    /**
     * @return array{0: string, 1: Plan, 2: Tenant}
     */
    private function portalReady(): array
    {
        $tenant = Tenant::factory()->withPalmPesa()->create();
        Site::factory()->for($tenant)->create(['nas_identifier' => 'site-palm']);
        $this->actingForTenant($tenant);

        $plan = Plan::factory()->for($tenant)->hourly()->create([
            'name' => 'Masaa 4',
            'price_minor' => 500,
            'is_active' => true,
            'is_sold_online' => true,
        ]);

        $token = $this->identifiedPortalToken('site-palm');

        return [$token, $plan, $tenant];
    }
}
