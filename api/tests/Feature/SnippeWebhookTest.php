<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Billing\OrderStatus;
use App\Domain\Billing\SnippeSignatureVerifier;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SnippeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_payment_completed_event_issues_a_voucher(): void
    {
        $tenant = Tenant::factory()->withPayments()->create(['code_prefix' => 'KAS']);
        $plan = Plan::factory()->for($tenant)->create();
        $order = Order::factory()->for($plan)->awaitingPayment()->create([
            'tenant_id' => $tenant->id,
            'amount_minor' => $plan->price_minor,
        ]);

        $raw = json_encode([
            'id' => 'evt_test_completed',
            'type' => 'payment.completed',
            'data' => [
                'reference' => $order->snippe_reference,
                'status' => 'completed',
                'amount' => ['value' => $plan->price_minor, 'currency' => 'TZS'],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->signedWebhook($tenant, $raw, 'payment.completed');

        $response->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(OrderStatus::Fulfilled, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->voucher_id);
        $this->assertSame(1, Voucher::withoutTenantScope()->where('plan_id', $plan->id)->count());
    }

    public function test_a_replayed_event_does_not_issue_a_second_voucher(): void
    {
        $tenant = Tenant::factory()->withPayments()->create(['code_prefix' => 'KAS']);
        $plan = Plan::factory()->for($tenant)->create();
        $order = Order::factory()->for($plan)->awaitingPayment()->create([
            'tenant_id' => $tenant->id,
        ]);

        $raw = json_encode([
            'id' => 'evt_replay',
            'type' => 'payment.completed',
            'data' => [
                'reference' => $order->snippe_reference,
                'status' => 'completed',
            ],
        ], JSON_THROW_ON_ERROR);

        $this->signedWebhook($tenant, $raw, 'payment.completed')->assertOk();
        $this->signedWebhook($tenant, $raw, 'payment.completed')->assertOk();

        $this->assertSame(1, Voucher::withoutTenantScope()->where('plan_id', $plan->id)->count());
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        $tenant = Tenant::factory()->withPayments()->create();

        $this->call('POST', '/webhooks/snippe/'.$tenant->uuid, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'deadbeef',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) time(),
            'HTTP_X_WEBHOOK_EVENT' => 'payment.completed',
        ], content: '{"id":"evt_x"}')->assertUnauthorized();
    }

    private function signedWebhook(Tenant $tenant, string $raw, string $event): TestResponse
    {
        $timestamp = (string) time();
        $signature = SnippeSignatureVerifier::sign(
            $raw,
            (string) $tenant->snippe_webhook_secret,
            $timestamp,
        );

        return $this->call('POST', '/webhooks/snippe/'.$tenant->uuid, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_X_WEBHOOK_EVENT' => $event,
        ], content: $raw);
    }
}
