<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Models\NasDevice;
use App\Models\Site;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

/**
 * Binds a captive-portal client to the site it connected from.
 *
 * The portal runs on a device that has not authenticated and cannot hold a
 * secret, so it cannot be trusted to state which operator or site a request
 * belongs to. Instead the bootstrap endpoint resolves that from the router
 * identifier the hotspot passed in, then hands back this token; every later call
 * presents it.
 *
 * Without it, a client could bootstrap against their own operator and then post a
 * redemption naming a different one, redeeming codes across tenant boundaries.
 *
 * Laravel's encrypter is authenticated, so a tampered token fails to decrypt
 * rather than decoding into something attacker-chosen. The payload is kept small
 * because it travels on every portal request over a hotspot link.
 */
final readonly class PortalToken
{
    /**
     * Long enough to choose a bundle and complete a mobile money push, short
     * enough that a captured token is not a lasting foothold.
     */
    private const int TTL_SECONDS = 3600;

    public function __construct(private CurrentTenant $currentTenant) {}

    public function issue(
        Site $site,
        ?NasDevice $nasDevice = null,
        ?string $clientMac = null,
        ?string $clientIp = null,
    ): string {
        return Crypt::encryptString(json_encode([
            't' => $site->tenant_id,
            's' => $site->id,
            'n' => $nasDevice?->id,
            'm' => $clientMac,
            'i' => $clientIp,
            'x' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Rebuilds the context from a token, or null if it is invalid or expired.
     */
    public function parse(?string $token): ?PortalContext
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode(
                Crypt::decryptString($token),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_numeric($payload['x'] ?? null) || (int) $payload['x'] < now()->getTimestamp()) {
            return null;
        }

        /*
         * Loaded without the tenant scope because no tenant is resolved yet --
         * this is the call that resolves it.
         */
        return $this->currentTenant->withoutTenant(function () use ($payload): ?PortalContext {
            $site = Site::query()->with('tenant')->find($payload['s'] ?? null);

            if (! $site instanceof Site || $site->tenant_id !== ($payload['t'] ?? null)) {
                return null;
            }

            if (! $site->tenant->isActive() || ! $site->isActive()) {
                return null;
            }

            $nasDevice = $payload['n'] === null
                ? null
                : NasDevice::query()
                    ->where('tenant_id', $site->tenant_id)
                    ->find($payload['n']);

            return new PortalContext(
                tenant: $site->tenant,
                site: $site,
                nasDevice: $nasDevice,
                clientMac: is_string($payload['m'] ?? null) ? $payload['m'] : null,
                clientIp: is_string($payload['i'] ?? null) ? $payload['i'] : null,
            );
        });
    }
}
