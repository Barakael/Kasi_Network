<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Interface origins
    |---------------------------------------------------------------------------
    |
    | The captive portal and operator console are separate single-page apps.
    | Their origins are needed for CORS and for building the QR redemption
    | links printed onto voucher cards.
    |
    */

    'portal_url' => rtrim((string) env('PORTAL_URL', 'http://localhost:5173'), '/'),
    'console_url' => rtrim((string) env('CONSOLE_URL', 'http://localhost:5174'), '/'),

    /*
    |---------------------------------------------------------------------------
    | Voucher codes
    |---------------------------------------------------------------------------
    |
    | Codes are read off printed cards and typed on phone keyboards, so the
    | alphabet is Crockford base32: no O/0, I/1 or L/1 confusion. A trailing
    | check character lets the portal reject typos without a round trip. The
    | alphabet itself lives in App\Domain\Voucher\VoucherCode, not here: its
    | size has to stay prime for the check character to catch every typo, so it
    | is not a setting an operator should be able to change.
    |
    */

    'voucher' => [
        /*
         * Keys the HMAC that voucher codes are looked up by. Separate from
         * APP_KEY because it cannot be rotated the way an encryption key can:
         * changing it orphans every existing code_hash, so rotating it means
         * re-hashing the vouchers table in the same transaction.
         */
        'hash_key' => env('KASI_VOUCHER_HASH_KEY') ?: env('APP_KEY', ''),

        /*
         * Random body length before the check character. Default 9 → a 10-character
         * printed code (body + check). No shared tenant prefix is embedded in the
         * code; operators are distinguished by tenant_id and the HMAC lookup.
         */
        'body_length' => (int) env('KASI_VOUCHER_BODY_LENGTH', 9),
        'group_size' => (int) env('KASI_VOUCHER_GROUP_SIZE', 5),
        // Bulk generation insert chunk. Large enough to keep a 10k batch fast,
        // small enough to stay well inside max_allowed_packet.
        'insert_chunk' => (int) env('KASI_VOUCHER_INSERT_CHUNK', 1000),
        // How many times a chunk is regenerated when a code collides with one
        // already issued. Collisions are vanishingly rare, so more than a few
        // failures means the keyspace or the generator is wrong, not bad luck.
        'collision_retries' => (int) env('KASI_VOUCHER_COLLISION_RETRIES', 3),
        // Largest batch an operator can request in one go. Above this, printing
        // becomes unmanageable long before the database notices.
        'max_batch_quantity' => (int) env('KASI_VOUCHER_MAX_BATCH', 10000),
        // Redemption attempts allowed per client MAC per minute.
        'redeem_attempts_per_minute' => (int) env('KASI_VOUCHER_REDEEM_ATTEMPTS', 8),
        // Voucher cards per printed A4 sheet, as columns x rows.
        'print_columns' => (int) env('KASI_VOUCHER_PRINT_COLUMNS', 3),
        'print_rows' => (int) env('KASI_VOUCHER_PRINT_ROWS', 8),
    ],

    /*
    |---------------------------------------------------------------------------
    | RADIUS
    |---------------------------------------------------------------------------
    |
    | Quotas are enforced at authentication time by sqlcounter, but a session
    | already in flight can only be stopped with a CoA or Disconnect message,
    | which RouterOS accepts on UDP 3799 once `/radius incoming` is enabled.
    |
    */

    'radius' => [
        'coa_port' => (int) env('RADIUS_COA_PORT', 3799),
        'radclient_bin' => env('RADIUS_RADCLIENT_BIN', 'radclient'),
        'radclient_timeout' => (int) env('RADIUS_RADCLIENT_TIMEOUT', 5),
        // How far back the accounting rollup re-reads sessions, covering
        // interim updates that landed while the previous sweep was running.
        'rollup_lookback_minutes' => (int) env('RADIUS_ROLLUP_LOOKBACK', 15),
        // MAC format RouterOS is configured to send. Must match the router's
        // `radius-mac-format`, or MAC authentication silently stops matching.
        'mac_format' => env('RADIUS_MAC_FORMAT', 'XX:XX:XX:XX:XX:XX'),
        // Restricted MySQL account FreeRADIUS connects as.
        'db_user' => env('RADIUS_DB_USER', 'radius'),
        'db_password' => env('RADIUS_DB_PASSWORD', 'radius_secret'),
        'db_host_pattern' => env('RADIUS_DB_HOST_PATTERN', '%'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Snippe payments
    |---------------------------------------------------------------------------
    |
    | Each tenant holds their own Snippe account, so live credentials are stored
    | encrypted per tenant. The values here are platform-level fallbacks for
    | local development.
    |
    | Note: snippe/snippe-php v1 does NOT verify webhook signatures despite what
    | its documentation claims, so verification is implemented in this app. See
    | App\Domain\Billing\SnippeSignatureVerifier.
    |
    */

    'snippe' => [
        'api_key' => env('SNIPPE_API_KEY'),
        'webhook_secret' => env('SNIPPE_WEBHOOK_SECRET'),
        'base_url' => env('SNIPPE_BASE_URL', 'https://api.snippe.sh/v1'),
        'rate_limit_per_minute' => (int) env('SNIPPE_RATE_LIMIT_PER_MINUTE', 60),
        // Reject webhooks whose signed timestamp is older than this, to stop
        // a captured request being replayed for free access later.
        'webhook_tolerance_seconds' => (int) env('SNIPPE_WEBHOOK_TOLERANCE', 300),
        // Snippe expires an unauthorised mobile-money push; after this we stop
        // waiting and release the voucher we had reserved for the order.
        'payment_timeout_minutes' => (int) env('SNIPPE_PAYMENT_TIMEOUT', 60),
        'currency' => env('SNIPPE_CURRENCY', 'TZS'),
    ],

    /*
    | When Snippe is not configured, the portal can still issue a voucher after
    | the customer picks a package. Turn this off in production unless you
    | intend to give away codes without payment.
    */
    'demo_checkout' => filter_var(
        env('KASI_PORTAL_DEMO_CHECKOUT', env('APP_ENV') === 'local'),
        FILTER_VALIDATE_BOOL,
    ),

];
