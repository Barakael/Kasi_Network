<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RadAcct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Accounting rows as FreeRADIUS would have written them.
 *
 * Lets the rollup, quota enforcement and live-session code be tested without a
 * router in the loop.
 *
 * @extends Factory<RadAcct>
 */
class RadAcctFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'acctsessionid' => Str::upper(Str::random(16)),
            'acctuniqueid' => Str::lower(Str::random(32)),
            'username' => 'KAS'.Str::upper(Str::random(11)),
            'realm' => '',
            'nasipaddress' => fake()->localIpv4(),
            'nasportid' => 'hotspot1',
            'nasporttype' => 'Wireless-802.11',
            'acctstarttime' => now()->subMinutes(30),
            'acctupdatetime' => now(),
            'acctstoptime' => null,
            'acctinterval' => 300,
            'acctsessiontime' => 1800,
            'acctauthentic' => 'RADIUS',
            'acctinputoctets' => 10 * 1024 * 1024,
            'acctoutputoctets' => 50 * 1024 * 1024,
            'calledstationid' => 'hotspot1',
            'callingstationid' => 'AA:BB:CC:DD:EE:FF',
            'acctterminatecause' => '',
            'servicetype' => 'Login-User',
            'framedipaddress' => '10.5.50.'.fake()->numberBetween(2, 250),
            'framedipv6address' => '',
            'framedipv6prefix' => '',
            'framedinterfaceid' => '',
            'delegatedipv6prefix' => '',
        ];
    }

    /**
     * A session the router has closed.
     */
    public function closed(?int $sessionSeconds = null): static
    {
        return $this->state(function (array $attributes) use ($sessionSeconds): array {
            $seconds = $sessionSeconds ?? (int) $attributes['acctsessiontime'];
            $start = $attributes['acctstarttime'];

            return [
                'acctsessiontime' => $seconds,
                'acctstoptime' => $start->copy()->addSeconds($seconds),
                'acctterminatecause' => 'User-Request',
            ];
        });
    }

    public function forUsername(string $username): static
    {
        return $this->state(fn (array $attributes): array => [
            'username' => $username,
        ]);
    }

    public function usingBytes(int $in, int $out): static
    {
        return $this->state(fn (array $attributes): array => [
            'acctinputoctets' => $in,
            'acctoutputoctets' => $out,
        ]);
    }

    /**
     * A brand new session, before the router has sent any interim update. Its
     * Acct-Session-Time is absent, which is why elapsed time has to fall back to
     * the wall clock.
     */
    public function justStarted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'acctstarttime' => now(),
            'acctupdatetime' => null,
            'acctsessiontime' => null,
            'acctinputoctets' => 0,
            'acctoutputoctets' => 0,
        ]);
    }
}
