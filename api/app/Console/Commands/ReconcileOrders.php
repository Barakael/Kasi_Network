<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\OrderFulfiller;
use App\Domain\Billing\OrderStatus;
use App\Domain\Billing\SnippeGateway;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Snippe\SnippeException;

class ReconcileOrders extends Command
{
    protected $signature = 'kasi:reconcile-orders';

    protected $description = 'Refresh stale pending Snippe orders against the payment API';

    public function handle(SnippeGateway $gateway, OrderFulfiller $fulfiller): int
    {
        $orders = Order::withoutTenantScope()
            ->whereIn('status', [OrderStatus::Pending, OrderStatus::AwaitingPayment])
            ->whereNotNull('snippe_reference')
            ->where('expires_at', '<=', now()->addMinutes(5))
            ->limit(100)
            ->get();

        foreach ($orders as $order) {
            $tenant = Tenant::query()->find($order->tenant_id);

            if ($tenant === null) {
                continue;
            }

            try {
                $payment = $gateway->client($tenant)->find($order->snippe_reference);
            } catch (SnippeException) {
                continue;
            }

            $status = OrderStatus::fromSnippeStatus((string) $payment->status());

            if ($payment->isCompleted() || $status === OrderStatus::Paid) {
                $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
                $fulfiller->fulfill($order->fresh());

                continue;
            }

            if ($status->releasesVoucher() || $order->expires_at?->isPast()) {
                $order->update([
                    'status' => $order->expires_at?->isPast() ? OrderStatus::Expired : $status,
                ]);
                $fulfiller->release($order);
            }
        }

        $this->components->info('Reconciled '.$orders->count().' order(s).');

        return self::SUCCESS;
    }
}
