<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Billing\SnippeSignatureVerifier;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnippeSignatureVerifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_a_fresh_hmac(): void
    {
        $tenant = Tenant::factory()->withPayments()->create();
        $raw = '{"id":"evt_1"}';
        $timestamp = (string) time();
        $signature = SnippeSignatureVerifier::sign($raw, (string) $tenant->snippe_webhook_secret, $timestamp);

        $this->assertTrue(app(SnippeSignatureVerifier::class)->verify($raw, $signature, $timestamp, $tenant));
    }

    public function test_it_rejects_a_re_encoded_body(): void
    {
        $tenant = Tenant::factory()->withPayments()->create();
        $raw = '{ "id": "evt_1" }';
        $timestamp = (string) time();
        $signature = SnippeSignatureVerifier::sign($raw, (string) $tenant->snippe_webhook_secret, $timestamp);
        $reencoded = json_encode(json_decode($raw, true), JSON_THROW_ON_ERROR);

        $this->assertNotSame($raw, $reencoded);
        $this->assertFalse(app(SnippeSignatureVerifier::class)->verify($reencoded, $signature, $timestamp, $tenant));
    }
}
