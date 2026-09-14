<?php

declare(strict_types=1);

namespace App\Domain\Router;

use App\Models\NasDevice;
use RouterOS\Client;
use RouterOS\Query;
use Throwable;

/**
 * Reads unauthenticated hosts from a MikroTik hotspot so a phone can bind a TV.
 */
final readonly class HotspotHostReader
{
    public function __construct(private OuiLookup $oui) {}

    /**
     * @return array<int, array{mac: string, ip: ?string, vendor: ?string, authorized: bool, bypassed: bool}>
     */
    public function unauthenticated(NasDevice $device): array
    {
        if (! $device->hasApiCredentials()) {
            return [];
        }

        try {
            $client = $this->connect($device);
            $rows = $client->query(new Query('/ip/hotspot/host/print'))->read();
        } catch (Throwable) {
            return [];
        }

        $hosts = [];

        foreach ($rows as $row) {
            $mac = strtoupper((string) ($row['mac-address'] ?? ''));
            $authorized = ($row['authorized'] ?? 'false') === 'true';
            $bypassed = ($row['bypassed'] ?? 'false') === 'true';

            if ($mac === '' || $authorized || $bypassed) {
                continue;
            }

            $hosts[] = [
                'mac' => $mac,
                'ip' => $row['address'] ?? null,
                'vendor' => $this->oui->vendor($mac),
                'authorized' => false,
                'bypassed' => false,
            ];
        }

        return $hosts;
    }

    public function connect(NasDevice $device): Client
    {
        return new Client([
            'host' => $device->api_host,
            'user' => $device->api_username,
            'pass' => $device->api_password,
            'port' => $device->api_port,
            'ssl' => $device->api_uses_tls,
            'timeout' => 5,
        ]);
    }
}
