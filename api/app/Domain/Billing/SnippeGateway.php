<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Tenant;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Snippe\Payment;
use Snippe\Snippe;
use Snippe\SnippeException;

/**
 * Per-tenant Snippe client with the 60 requests/minute cap in front of it.
 *
 * Snippe returns HTTP 429 above that rate. Jobs retry, but a burst of USSD
 * pushes at a busy hotspot would still 429 without a local throttle.
 */
final readonly class SnippeGateway
{
    public function client(Tenant $tenant): Snippe
    {
        $key = $tenant->snippe_api_key ?: (string) config('kasi.snippe.api_key');

        if ($key === '') {
            throw new RuntimeException('This operator has no Snippe API key configured.');
        }

        $snippe = new Snippe($key);
        $snippe->setBaseUrl((string) config('kasi.snippe.base_url'));

        return $snippe;
    }

    /**
     * @param  callable(): Payment  $callback
     */
    public function send(Tenant $tenant, callable $callback): Payment
    {
        $max = (int) config('kasi.snippe.rate_limit_per_minute');
        $key = 'snippe:'.$tenant->id;

        $payment = RateLimiter::attempt($key, $max, $callback);

        if ($payment === false) {
            throw new SnippeException('Snippe rate limit reached for this operator.', 429);
        }

        return $payment;
    }
}
