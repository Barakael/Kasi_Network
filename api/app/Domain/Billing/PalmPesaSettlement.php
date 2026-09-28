<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Tenancy\CurrentTenant;
use App\Models\Order;
use App\Models\Tenant;

/**
 * Confirms a PalmPesa payment against order-status, then issues or releases
 * the voucher. Webhooks and the reconcile sweep both go through here so a
 * forged callback cannot fulfill on its own.
 */
final readonly class PalmPesaSettlement
{
    public function __construct(
        private PalmPesaGateway $gateway,
        private OrderFulfiller $fulfiller,
    ) {}

    public function sync(Order $order, Tenant $tenant): Order
    {
        return app(CurrentTenant::class)->forTenant($tenant, function () use ($order, $tenant): Order {
            $order = Order::withoutTenantScope()->findOrFail($order->id);

            if (! filled($order->palmpesa_order_id)) {
                return $order;
            }

            $status = $this->gateway->status($tenant, (string) $order->palmpesa_order_id);

            return $this->apply($order, $status);
        });
    }

    public function apply(Order $order, PalmPesaPaymentStatus $status): Order
    {
        if ($status === PalmPesaPaymentStatus::Completed) {
            if ($order->status === OrderStatus::Fulfilled && $order->voucher_id !== null) {
                return $order;
            }

            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => $order->paid_at ?? now(),
                'channel_provider' => $order->channel_provider ?: 'palmpesa',
            ]);

            return $this->fulfiller->fulfill($order->fresh(['plan']));
        }

        if (
            $status === PalmPesaPaymentStatus::Failed
            && in_array($order->status, [OrderStatus::Pending, OrderStatus::AwaitingPayment], true)
        ) {
            $order->update([
                'status' => OrderStatus::Failed,
                'failure_reason' => $order->failure_reason ?: 'PalmPesa reported FAILED',
            ]);
            $this->fulfiller->release($order);
        }

        return $order->fresh() ?? $order;
    }
}
