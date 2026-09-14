<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Billing\OrderFulfiller;
use App\Domain\Billing\OrderStatus;
use App\Domain\Tenancy\CurrentTenant;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessSnippeWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public WebhookEvent $event) {}

    public function uniqueId(): string
    {
        return $this->event->event_id;
    }

    public function handle(OrderFulfiller $fulfiller): void
    {
        if ($this->event->isProcessed()) {
            return;
        }

        $order = Order::withoutTenantScope()
            ->where('snippe_reference', $this->event->reference)
            ->first();

        if ($order === null) {
            $this->event->update([
                'processed_at' => now(),
                'processing_error' => 'No order matched this payment reference.',
            ]);

            return;
        }

        $payload = $this->event->payload;
        $snippeStatus = (string) data_get($payload, 'data.status', $this->event->event_type);
        $status = OrderStatus::fromSnippeStatus(
            str_contains($snippeStatus, '.') ? substr($snippeStatus, strrpos($snippeStatus, '.') + 1) : $snippeStatus,
        );

        $tenant = Tenant::query()->find($order->tenant_id);

        $apply = function () use ($fulfiller, $order, $payload, $status): void {
            if ($this->event->event_type === 'payment.completed' || $status === OrderStatus::Paid) {
                $order->update([
                    'status' => OrderStatus::Paid,
                    'paid_at' => now(),
                    'channel_provider' => data_get($payload, 'data.channel.provider'),
                    'fees_minor' => data_get($payload, 'data.settlement.fees.value'),
                    'net_minor' => data_get($payload, 'data.settlement.net.value'),
                ]);

                $fulfiller->fulfill($order->fresh(['plan']));
            } elseif ($status->releasesVoucher()) {
                $order->update([
                    'status' => $status,
                    'failure_reason' => data_get($payload, 'data.failure_reason', $this->event->event_type),
                ]);
                $fulfiller->release($order);
            }
        };

        if ($tenant instanceof Tenant) {
            app(CurrentTenant::class)->forTenant($tenant, $apply);
        } else {
            $apply();
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
