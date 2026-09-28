<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Billing\PalmPesaSettlement;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessPalmPesaWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public WebhookEvent $event) {}

    public function uniqueId(): string
    {
        return $this->event->event_id;
    }

    public function handle(PalmPesaSettlement $settlement): void
    {
        if ($this->event->isProcessed()) {
            return;
        }

        $payload = $this->event->payload;
        $palmOrderId = (string) (data_get($payload, 'order_id')
            ?? data_get($payload, 'data.order_id')
            ?? '');
        $transactionId = (string) (data_get($payload, 'transaction_id')
            ?? data_get($payload, 'data.transaction_id')
            ?? $this->event->reference
            ?? '');

        $tenant = Tenant::query()->find($this->event->tenant_id);

        if (! $tenant instanceof Tenant) {
            $this->event->update([
                'processed_at' => now(),
                'processing_error' => 'No tenant matched this PalmPesa callback.',
            ]);

            return;
        }

        $query = Order::withoutTenantScope()->where('tenant_id', $tenant->id);
        $order = null;

        if ($palmOrderId !== '') {
            $order = (clone $query)->where('palmpesa_order_id', $palmOrderId)->first();
        }

        if ($order === null && $transactionId !== '') {
            $order = (clone $query)->where('uuid', $transactionId)->first();
        }

        if (! $order instanceof Order) {
            if ($this->attempts() < $this->tries) {
                $this->release(3);

                return;
            }

            $this->event->update([
                'processed_at' => now(),
                'processing_error' => 'No order matched this PalmPesa callback.',
            ]);

            return;
        }

        if (! filled($order->palmpesa_order_id) && $palmOrderId !== '') {
            $order->update(['palmpesa_order_id' => $palmOrderId]);
            $order = $order->fresh() ?? $order;
        }

        $fresh = $settlement->sync($order, $tenant);

        if (! $fresh->status->isSettled()) {
            return;
        }

        $this->event->update(['processed_at' => now(), 'processing_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->event->update([
            'processing_error' => $exception?->getMessage(),
        ]);
    }
}
