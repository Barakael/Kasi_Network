<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\SnippeSignatureVerifier;
use App\Jobs\ProcessSnippeWebhook;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Snippe\Webhook;

class SnippeWebhookController
{
    public function __invoke(
        Request $request,
        string $tenantUuid,
        SnippeSignatureVerifier $verifier,
    ): JsonResponse {
        $tenant = Tenant::query()->where('uuid', $tenantUuid)->first();

        if ($tenant === null) {
            return response()->json(['ok' => false], 404);
        }

        $raw = $request->getContent();
        $signature = (string) $request->header('X-Webhook-Signature', '');
        $timestamp = (string) $request->header('X-Webhook-Timestamp', '');

        if (! $verifier->verify($raw, $signature, $timestamp, $tenant)) {
            return response()->json(['ok' => false], 401);
        }

        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = is_array($values) ? ($values[0] ?? '') : $values;
        }

        $captured = Webhook::fromRaw($raw, $headers);
        $payload = $captured->payload();
        $eventId = (string) (data_get($payload, 'id') ?? data_get($payload, 'event.id') ?? hash('sha256', $raw));

        $event = DB::transaction(function () use ($tenant, $captured, $payload, $eventId): WebhookEvent {
            $existing = WebhookEvent::query()->where('event_id', $eventId)->first();

            if ($existing instanceof WebhookEvent) {
                return $existing;
            }

            return WebhookEvent::query()->create([
                'tenant_id' => $tenant->id,
                'event_id' => $eventId,
                'event_type' => $captured->eventType() ?: (string) data_get($payload, 'type', 'payment.updated'),
                'reference' => $captured->reference(),
                'payload' => $payload,
                'signature_verified' => true,
            ]);
        });

        if (! $event->isProcessed()) {
            ProcessSnippeWebhook::dispatch($event);
        }

        return response()->json(['ok' => true]);
    }
}
