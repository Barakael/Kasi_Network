<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Tenant;

/**
 * Verifies Snippe webhook signatures.
 *
 * snippe/snippe-php v1 documents verification but does not implement it, so a
 * captured callback would be accepted as paid. HMAC-SHA256 over the timestamp
 * and raw body is what actually authenticates the request. The body must not be
 * re-encoded: json_encode of decoded JSON is not byte-identical and would fail
 * every genuine webhook.
 */
final readonly class SnippeSignatureVerifier
{
    public function verify(string $rawBody, string $signature, string $timestamp, Tenant $tenant): bool
    {
        $secret = $tenant->snippe_webhook_secret;

        if ($secret === null || $secret === '') {
            return false;
        }

        $tolerance = (int) config('kasi.snippe.webhook_tolerance_seconds');

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public static function sign(string $rawBody, string $secret, string $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }
}
