<?php

declare(strict_types=1);

namespace App\Domain\Radius;

use App\Models\Plan;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writes vouchers into the tables FreeRADIUS authenticates against.
 *
 * Provisioning happens when a voucher is issued, not when it is redeemed. A
 * printed card has to work at the router even if this application is down or
 * unreachable, which is the main reason the platform keeps RADIUS state in the
 * database rather than answering authentication requests itself.
 *
 * Attributes that depend on how much of a bundle is left -- remaining time and
 * remaining bytes -- are deliberately absent. Those are computed per request by
 * the sqlcounter modules, because a value written here would be stale the moment
 * the client starts using it.
 */
final readonly class RadiusProvisioner
{
    /**
     * Interim accounting interval, in seconds, for bundles with no data cap.
     *
     * Each update is a write to radacct, so the interval trades database load
     * against how far a client can overrun before enforcement notices.
     */
    private const int INTERIM_INTERVAL_TIME_ONLY = 300;

    /** Data-capped bundles report more often, since bytes accrue much faster. */
    private const int INTERIM_INTERVAL_DATA_CAPPED = 120;

    /**
     * Provisions a single voucher.
     */
    public function provision(Voucher $voucher): void
    {
        $this->provisionMany(collect([$voucher]));
    }

    /**
     * Provisions vouchers in bulk.
     *
     * Batch issuance can be ten thousand codes at a time, so rows are built in
     * memory and inserted in one statement per table rather than looping.
     *
     * @param  Collection<int, Voucher>  $vouchers
     */
    public function provisionMany(Collection $vouchers): void
    {
        if ($vouchers->isEmpty()) {
            return;
        }

        $checks = [];
        $replies = [];

        foreach ($vouchers as $voucher) {
            foreach ($this->checkRowsFor($voucher) as $row) {
                $checks[] = $row;
            }

            foreach ($this->replyRowsFor($voucher) as $row) {
                $replies[] = $row;
            }
        }

        DB::table('radcheck')->insert($checks);

        if ($replies !== []) {
            DB::table('radreply')->insert($replies);
        }
    }

    /**
     * Removes a voucher's authorisation.
     *
     * Used when a code is disabled or when a voucher reserved for an order is
     * released. Any session already running is separate: it has to be ended with
     * a Disconnect-Request, since deleting these rows only affects the next
     * authentication.
     */
    public function revoke(Voucher $voucher): void
    {
        $this->revokeUsernames([$voucher->code]);
    }

    /**
     * @param  array<int, string>  $usernames
     */
    public function revokeUsernames(array $usernames): void
    {
        if ($usernames === []) {
            return;
        }

        DB::table('radcheck')->whereIn('username', $usernames)->delete();
        DB::table('radreply')->whereIn('username', $usernames)->delete();
    }

    /**
     * Re-applies a voucher's reply attributes, discarding whatever was there.
     *
     * Needed after a plan's speed is changed and the operator chooses to apply it
     * to outstanding vouchers, and after a throttle is lifted.
     */
    public function refreshReplies(Voucher $voucher): void
    {
        DB::table('radreply')->where('username', $voucher->code)->delete();

        $rows = $this->replyRowsFor($voucher);

        if ($rows !== []) {
            DB::table('radreply')->insert($rows);
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function checkRowsFor(Voucher $voucher): array
    {
        $rows = [[
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::CLEARTEXT_PASSWORD,
            'op' => ':=',
            'value' => $voucher->code,
        ]];

        /*
         * Omitted entirely when the bundle allows a single device, because MAC
         * binding already restricts it to one and FreeRADIUS's simultaneous-use
         * check costs a query against radacct on every authentication.
         */
        if ($voucher->device_limit > 1) {
            $rows[] = [
                'username' => $voucher->code,
                'attribute' => RadiusAttribute::SIMULTANEOUS_USE,
                'op' => ':=',
                'value' => (string) $voucher->device_limit,
            ];
        }

        if ($voucher->duration_seconds !== null) {
            $rows[] = [
                'username' => $voucher->code,
                'attribute' => RadiusAttribute::MAX_ALL_SESSION,
                'op' => ':=',
                'value' => (string) $voucher->duration_seconds,
            ];
        }

        if ($voucher->data_cap_bytes !== null) {
            $rows[] = [
                'username' => $voucher->code,
                'attribute' => RadiusAttribute::MAX_DATA,
                'op' => ':=',
                'value' => (string) $voucher->data_cap_bytes,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function replyRowsFor(Voucher $voucher): array
    {
        $rows = [];

        $rateLimit = Plan::formatRateLimit($voucher->rate_limit_up_kbps, $voucher->rate_limit_down_kbps);

        if ($rateLimit !== null) {
            $rows[] = [
                'username' => $voucher->code,
                'attribute' => RadiusAttribute::MIKROTIK_RATE_LIMIT,
                'op' => ':=',
                'value' => $rateLimit,
            ];
        }

        $rows[] = [
            'username' => $voucher->code,
            'attribute' => RadiusAttribute::ACCT_INTERIM_INTERVAL,
            'op' => ':=',
            'value' => (string) ($voucher->data_cap_bytes === null
                ? self::INTERIM_INTERVAL_TIME_ONLY
                : self::INTERIM_INTERVAL_DATA_CAPPED),
        ];

        return $rows;
    }

    /**
     * Locks a code to the MAC that first authenticated it.
     *
     * Written into radcheck so later authentications from any other device fail
     * at the RADIUS server, even if this application is unreachable.
     */
    public function bindCallingStation(Voucher $voucher, string $mac): void
    {
        DB::table('radcheck')->updateOrInsert(
            [
                'username' => $voucher->code,
                'attribute' => RadiusAttribute::CALLING_STATION_ID,
            ],
            [
                'op' => '==',
                'value' => $mac,
            ],
        );
    }

    /**
     * Writes the wall-clock Expiration attribute when a voucher is first used.
     *
     * Matches the FreeRADIUS post-auth stamp so portal-first redemption (lab
     * testing without a MikroTik) and RADIUS-first redemption stay consistent.
     * Format must match FreeRADIUS Expiration module: "Mon DD YYYY HH:MM:SS".
     */
    public function stampExpiration(Voucher $voucher): void
    {
        if ($voucher->expires_at === null) {
            return;
        }

        DB::table('radcheck')->updateOrInsert(
            [
                'username' => $voucher->code,
                'attribute' => RadiusAttribute::EXPIRATION,
            ],
            [
                'op' => ':=',
                'value' => $voucher->expires_at->utc()->format('M j Y H:i:s'),
            ],
        );
    }
}
