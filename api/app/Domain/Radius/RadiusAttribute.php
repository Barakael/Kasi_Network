<?php

declare(strict_types=1);

namespace App\Domain\Radius;

/**
 * The RADIUS attributes Kasi reads and writes.
 *
 * Named here rather than spelled out at each call site: these strings are matched
 * literally by FreeRADIUS and by RouterOS, so a typo produces a session with no
 * speed limit or no expiry instead of an error.
 */
final class RadiusAttribute
{
    /**
     * The voucher code itself. MikroTik's hotspot authenticates with CHAP, so the
     * server must hold the password rather than a hash of it.
     */
    public const string CLEARTEXT_PASSWORD = 'Cleartext-Password';

    /**
     * How many devices may hold a session on one code at once. Together with MAC
     * binding this is what makes a printed voucher single-use.
     */
    public const string SIMULTANEOUS_USE = 'Simultaneous-Use';

    /**
     * Wall-clock end of a voucher's validity, stamped on first authentication
     * rather than at issue, so a bundle bought in the morning is still whole in
     * the evening. FreeRADIUS's expiration module rejects past this and reduces
     * Session-Timeout to the remainder.
     */
    public const string EXPIRATION = 'Expiration';

    /**
     * The client MAC a code became locked to. Compared on later authentications
     * so a code read off someone else's card is refused.
     */
    public const string CALLING_STATION_ID = 'Calling-Station-Id';

    /**
     * Metered online-time budget, consumed by the kasi_time sqlcounter.
     * Distinct from Expiration, which is the wall-clock window.
     */
    public const string MAX_ALL_SESSION = 'Max-All-Session';

    /**
     * Byte budget, consumed by the kasi_data sqlcounter. Not a stock RADIUS
     * attribute; only our counter module reads it.
     */
    public const string MAX_DATA = 'Max-Data';

    /** Remaining online time, computed per request by sqlcounter. */
    public const string SESSION_TIMEOUT = 'Session-Timeout';

    /** "upload/download", e.g. "2048k/8192k". Note the order. */
    public const string MIKROTIK_RATE_LIMIT = 'Mikrotik-Rate-Limit';

    /** Remaining bytes for a data-capped bundle, in and out combined. */
    public const string MIKROTIK_TOTAL_LIMIT = 'Mikrotik-Total-Limit';

    /**
     * How often the router reports usage mid-session. Quota overrun is bounded by
     * this interval, so data-capped bundles report more often than time-only ones.
     */
    public const string ACCT_INTERIM_INTERVAL = 'Acct-Interim-Interval';

    /** Reason sent with a Disconnect-Request, visible in the router's log. */
    public const string TERMINATION_ACTION = 'Termination-Action';
}
