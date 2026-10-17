<?php

declare(strict_types=1);

namespace App\Domain\Router;

use App\Domain\Support\MacAddress;
use App\Models\NasDevice;
use Illuminate\Support\Facades\Log;
use RouterOS\Query;
use Throwable;

/**
 * Logs a bound MAC into the MikroTik hotspot so the device gets internet
 * without toggling Wi‑Fi (phones bind from the portal; TVs/tablets stay silent).
 */
final readonly class HotspotHostAuthorizer
{
    public function __construct(private HotspotHostReader $hosts) {}

    /**
     * @return array{authorized: bool, detail: string|null}
     */
    public function authorize(NasDevice $device, string $rawMac): array
    {
        if (! $device->hasApiCredentials()) {
            return ['authorized' => false, 'detail' => 'no_api'];
        }

        try {
            $mac = MacAddress::parse($rawMac)->toString();
        } catch (Throwable) {
            return ['authorized' => false, 'detail' => 'bad_mac'];
        }

        try {
            $client = $this->hosts->connect($device);
            $rows = $client->query(
                (new Query('/ip/hotspot/host/print'))
                    ->where('mac-address', $mac),
            )->read();

            if ($rows === []) {
                // Case / separator mismatch — scan all hosts.
                $rows = $client->query(new Query('/ip/hotspot/host/print'))->read();
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row): bool => strtoupper((string) ($row['mac-address'] ?? '')) === $mac,
                ));
            }

            if ($rows === []) {
                return ['authorized' => false, 'detail' => 'host_not_found'];
            }

            $host = $rows[0];
            $hostId = (string) ($host['.id'] ?? '');
            $ip = (string) ($host['address'] ?? '');

            if (($host['authorized'] ?? 'false') === 'true') {
                return ['authorized' => true, 'detail' => 'already_authorized'];
            }

            // Prefer an explicit hotspot login so RADIUS MAC-auth runs.
            if ($ip !== '') {
                try {
                    $client->query(
                        (new Query('/ip/hotspot/active/login'))
                            ->equal('ip', $ip)
                            ->equal('mac-address', $mac)
                            ->equal('user', $mac)
                            ->equal('password', $mac),
                    )->read();

                    return ['authorized' => true, 'detail' => 'logged_in'];
                } catch (Throwable $loginError) {
                    Log::info('Hotspot active login failed; removing host to force MAC-auth', [
                        'mac' => $mac,
                        'nas' => $device->id,
                        'error' => $loginError->getMessage(),
                    ]);
                }
            }

            if ($hostId !== '') {
                $client->query(
                    (new Query('/ip/hotspot/host/remove'))
                        ->equal('.id', $hostId),
                )->read();

                return ['authorized' => true, 'detail' => 'host_removed'];
            }

            return ['authorized' => false, 'detail' => 'no_host_id'];
        } catch (Throwable $e) {
            Log::warning('Hotspot authorize failed', [
                'mac' => $rawMac,
                'nas' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return ['authorized' => false, 'detail' => 'api_error'];
        }
    }
}
