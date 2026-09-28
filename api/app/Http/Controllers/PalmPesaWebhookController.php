<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessPalmPesaWebhook;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Accepts PalmPesa payment callbacks. The payload is unsigned, so this endpoint
 * only records the event and returns 200; fulfillment waits on order-status.
 */
class PalmPesaWebhookController
{
    public function __invoke(Request $request, string $tenantUuid): JsonResponse
    {
        $tenant = Tenant::query()->where('uuid', $tenantUuid)->first();

        if ($tenant === null) {
            return response()->json(['ok' => false], 404);
        }

        $raw = $request->getContent();
        $payload = is_array($request->json()->all()) ? $request->json()->all() : [];

        if ($payload === []) {
            $payload = $request->all();
        }

        if ($payload === [] && $raw !== '') {
            $decoded = json_decode($raw, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $orderId = (string) (data_get($payload, 'order_id') ?? data_get($payload, 'data.order_id') ?? '');
        $transactionId = (string) (data_get($payload, 'transaction_id')
            ?? data_get($payload, 'data.transaction_id')
            ?? '');
        $status = (string) (data_get($payload, 'payment_status')
            ?? data_get($payload, 'data.0.payment_status')
            ?? data_get($payload, 'status')
            ?? 'callback');

        $eventId = (string) (data_get($payload, 'id')
            ?? data_get($payload, 'event_id')
            ?? 'palmpesa:'.hash('sha256', $raw !== '' ? $raw : json_encode($payload)));

        $event = DB::transaction(function () use ($tenant, $payload, $eventId, $orderId, $transactionId, $status): WebhookEvent {
            $existing = WebhookEvent::query()->where('event_id', $eventId)->first();

            if ($existing instanceof WebhookEvent) {
                return $existing;
            }

            return WebhookEvent::query()->create([
                'tenant_id' => $tenant->id,
                'event_id' => $eventId,
                'event_type' => 'palmpesa.'.strtolower($status),
                'reference' => $transactionId !== '' ? $transactionId : ($orderId !== '' ? $orderId : null),
                'payload' => $payload,
                'signature_verified' => false,
            ]);
        });

        if (! $event->isProcessed()) {
            ProcessPalmPesaWebhook::dispatch($event);
        }

        return response()->json(['ok' => true]);
    }
}
