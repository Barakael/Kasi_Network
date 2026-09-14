<?php

declare(strict_types=1);

namespace App\Domain\Radius;

use App\Models\NasDevice;
use App\Models\RadAcct;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Sends Disconnect-Request and CoA packets to a MikroTik NAS via radclient.
 *
 * sqlcounter can refuse the next authentication; it cannot cut a session that
 * is already open. That is this class's job. The router must have
 * `/radius incoming set accept=yes port=3799`.
 */
final readonly class CoaDispatcher
{
    /**
     * Ends an open session immediately.
     */
    public function disconnect(RadAcct $session, NasDevice $nas, string $reason = 'quota-exhausted'): bool
    {
        return $this->send($nas, [
            'User-Name' => $session->username,
            'Acct-Session-Id' => $session->acctsessionid,
            'NAS-IP-Address' => $session->nasipaddress,
            'Framed-IP-Address' => $session->framedipaddress,
            'Event-Timestamp' => (string) time(),
        ], disconnect: true, context: ['reason' => $reason, 'session' => $session->acctuniqueid]);
    }

    /**
     * Applies a reduced Mikrotik-Rate-Limit without dropping the session.
     */
    public function throttle(RadAcct $session, NasDevice $nas, string $rateLimit): bool
    {
        return $this->send($nas, [
            'User-Name' => $session->username,
            'Acct-Session-Id' => $session->acctsessionid,
            'NAS-IP-Address' => $session->nasipaddress,
            'Framed-IP-Address' => $session->framedipaddress,
            'Mikrotik-Rate-Limit' => $rateLimit,
        ], disconnect: false, context: ['rate' => $rateLimit, 'session' => $session->acctuniqueid]);
    }

    /**
     * @param  array<string, string|null>  $attributes
     * @param  array<string, mixed>  $context
     */
    public function send(NasDevice $nas, array $attributes, bool $disconnect, array $context = []): bool
    {
        $lines = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $lines[] = $name.' = '.$value;
        }

        $payload = implode("\n", $lines)."\n";
        $code = $disconnect ? 'disconnect' : 'coa';
        $target = $nas->nasname.':'.$nas->coa_port;

        $result = Process::timeout((int) config('kasi.radius.radclient_timeout'))
            ->input($payload)
            ->run([
                (string) config('kasi.radius.radclient_bin'),
                '-x',
                $target,
                $code,
                $nas->shared_secret,
            ]);

        if (! $result->successful()) {
            Log::warning('RADIUS CoA failed', [
                ...$context,
                'nas' => $nas->nasname,
                'code' => $code,
                'error' => $result->errorOutput() ?: $result->output(),
            ]);

            return false;
        }

        return true;
    }
}
