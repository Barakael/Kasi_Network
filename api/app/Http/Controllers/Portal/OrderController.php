<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Billing\OrderStatus;
use App\Domain\Billing\SnippeGateway;
use App\Domain\Tenancy\PortalContext;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Snippe\PaymentBuilder;
use Snippe\SnippeException;

class OrderController
{
    public function store(
        Request $request,
        PortalContext $context,
        SnippeGateway $gateway,
    ): JsonResponse {
        if (! $context->tenant->acceptsOnlinePayments()) {
            return response()->json([
                'message' => 'Online payments are not available on this hotspot.',
                'code' => 'payments_unavailable',
            ], 422);
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $plan = Plan::query()
            ->where('is_active', true)
            ->where('is_sold_online', true)
            ->findOrFail($validated['plan_id']);

        if ($plan->price_minor < 1) {
            return response()->json(['message' => 'This bundle is not sold online.'], 422);
        }

        $order = Order::create([
            'site_id' => $context->site->id,
            'plan_id' => $plan->id,
            'nas_device_id' => $context->nasDevice?->id,
            'status' => OrderStatus::Pending,
            'amount_minor' => $plan->price_minor,
            'currency' => $context->tenant->currency,
            'phone' => PaymentBuilder::normalizePhone($validated['phone']),
            'client_mac' => $context->clientMac,
            'client_ip' => $context->clientIp,
            'expires_at' => now()->addMinutes((int) config('kasi.snippe.payment_timeout_minutes')),
        ]);

        $webhookUrl = url('/webhooks/snippe/'.$context->tenant->uuid);

        try {
            $payment = $gateway->send($context->tenant, function () use ($gateway, $context, $order, $plan, $webhookUrl) {
                return $gateway->client($context->tenant)
                    ->mobileMoney($order->amount_minor, $order->phone)
                    ->customer($context->site->name, 'hotspot@'.$context->tenant->slug.'.kasi')
                    ->description($plan->name)
                    ->webhook($webhookUrl)
                    ->metadata(['order_id' => $order->uuid, 'tenant_id' => $context->tenant->uuid])
                    ->idempotencyKey($order->idempotencyKey())
                    ->send();
            });
        } catch (SnippeException $e) {
            $order->update([
                'status' => OrderStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not start the payment. Try again in a moment.',
                'code' => 'payment_start_failed',
            ], 502);
        }

        $order->update([
            'status' => OrderStatus::AwaitingPayment,
            'snippe_reference' => $payment->reference(),
        ]);

        return (new OrderResource($order->fresh('plan')))->response()->setStatusCode(201);
    }

    public function show(Order $order, PortalContext $context): OrderResource
    {
        abort_unless($order->tenant_id === $context->tenant->id, 404);

        return new OrderResource($order->load(['plan', 'voucher']));
    }
}
