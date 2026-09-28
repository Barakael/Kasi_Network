<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * PalmPesa USSD initiate and order-status client.
 *
 * Hosted checkout is intentionally unused: it would send a captive-portal
 * phone off-hotspot. Callbacks are treated as a hint only; settlement always
 * confirms via order-status before a voucher is issued.
 */
final readonly class PalmPesaGateway
{
    public function tokenFor(Tenant $tenant): string
    {
        $token = $tenant->palmpesaApiToken();

        if ($token === null) {
            throw new PalmPesaException('This operator has no PalmPesa API token configured.');
        }

        return $token;
    }

    /**
     * Starts a USSD / STK prompt. Returns PalmPesa's order_id.
     */
    public function initiate(Tenant $tenant, Order $order, string $callbackUrl): string
    {
        $response = $this->http($tenant)->post('/api/palmpesa/initiate', [
            'name' => $order->customer_name ?: $tenant->portal_name ?: $tenant->name,
            'email' => $order->customer_email ?: (string) config('kasi.palmpesa.customer_email'),
            'phone' => TanzanianPhone::toLocal($order->phone),
            'amount' => $order->amount_minor,
            'transaction_id' => $order->uuid,
            'address' => (string) config('kasi.palmpesa.address'),
            'postcode' => (string) config('kasi.palmpesa.postcode'),
            'callback_url' => $callbackUrl,
        ]);

        if ($response->failed()) {
            throw new PalmPesaException(
                'PalmPesa could not start the payment.'.$this->errorHint($response),
                $response->status(),
            );
        }

        $orderId = $response->json('order_id');

        if ($orderId === null || $orderId === '') {
            throw new PalmPesaException('PalmPesa did not return an order_id.');
        }

        return (string) $orderId;
    }

    /**
     * Authoritative payment state. Used after an unsigned callback and by the
     * reconcile sweep, so a forged webhook cannot issue a voucher on its own.
     */
    public function status(Tenant $tenant, string $orderId): PalmPesaPaymentStatus
    {
        $response = $this->http($tenant)
            ->retry(2, 200, function (mixed $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response?->serverError());
            })
            ->post('/api/order-status', [
                'order_id' => $orderId,
            ]);

        if ($response->failed()) {
            throw new PalmPesaException(
                'PalmPesa order-status failed.'.$this->errorHint($response),
                $response->status(),
            );
        }

        $body = $response->json() ?? [];
        $raw = data_get($body, 'data.0.payment_status')
            ?? data_get($body, 'payment_status')
            ?? data_get($body, 'status')
            ?? 'PENDING';

        return PalmPesaPaymentStatus::fromRemote((string) $raw);
    }

    private function http(Tenant $tenant): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('kasi.palmpesa.base_url'), '/'))
            ->withToken($this->tokenFor($tenant))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20);
    }

    private function errorHint(Response $response): string
    {
        $hint = trim((string) (
            $response->json('message')
            ?? $response->json('error')
            ?? $response->json('msg')
            ?? ''
        ));

        if ($hint === '') {
            return '';
        }

        return ' '.substr($hint, 0, 180);
    }
}
