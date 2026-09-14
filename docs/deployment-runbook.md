# Deployment runbook

Kasi Network is a multi-tenant MikroTik hotspot billing platform: Laravel API, React console PWA, Preact captive portal, MySQL 8, Redis 7, FreeRADIUS 3.2.

## Stack

| Service | Role |
|---|---|
| `api` | Laravel 13 / PHP 8.4 JSON API, Sanctum, Octane-capable |
| `portal` | Captive portal, Vite port 5173, gzip budget 100 KB |
| `console` | Operator PWA, Vite port 5174 |
| MySQL 8 | App tables and FreeRADIUS `rad*` tables in one database |
| Redis 7 | Cache, queues, Snippe 60 rpm token bucket |
| FreeRADIUS 3.2 | Auth, accounting, sqlcounter quotas, MAC rewrite |

Start the stack:

```bash
docker compose -f docker/compose.yaml up -d --build
cd api && php artisan migrate --seed
```

Console login after seed: `owner@kasi.test` / `password`. Agent: `agent@kasi.test` / `password`.

## FreeRADIUS

The image overlays `docker/freeradius/config` onto stock `raddb`. Thread pool is `start_servers=32`, `max_servers=256`. SQL uses the restricted `radius` MySQL user from `docker/mysql/init`.

Do not run `radiusd -X` in production: it is single-threaded. Use `-X` only when tracing a single Access-Request.

sqlcounter reads `voucher_usage`. The API seeds empty rows at issue time and the `kasi:rollup-usage` schedule folds closed `radacct` into that table every minute. Open sessions are a small delta added at auth time.

CoA and Disconnect-Request are sent with `radclient` to UDP 3799. The router must have `/radius incoming set accept=yes port=3799`.

## Scheduler

Laravel 13 schedules these in `api/routes/console.php`:

- `kasi:rollup-usage` every minute
- `kasi:enforce-quotas` every minute
- `kasi:reconcile-orders` every five minutes
- `kasi:radacct-archive` hourly
- `kasi:radacct-partitions` daily

Run `php artisan schedule:work` (compose `scheduler` service) in production.

## radacct growth

`radacct` is partitioned by month. `kasi:radacct-archive` copies closed rows older than the configured window into `radacct_archive` and deletes them from the live table so auth-time sqlcounter stays on a small working set.

## Captive portal

Serve `portal` on a host listed in every hotspot walled garden. MikroTik `login.html` (generated from the console, also in `router-config/login.html`) redirects to `/?site=<NAS-Identifier>&mac=…&link_login=…&chap_id=…&chap_challenge=…`.

QR codes on printed vouchers deep-link to `/r/<CODE>?site=<NAS-Identifier>`.

CHAP is computed in the browser with MD5 (Web Crypto does not expose MD5). The voucher never crosses the LAN in cleartext when CHAP is available.

## Console PWA

The operator app is installable. Session and revenue API calls are `NetworkOnly` in the service worker so a stale cache cannot show yesterday's concurrent count.

Print opens HTML from `/api/print/voucher-batches/{id}` as a blob: Sanctum tokens do not ride in a normal navigation.

## Payments

Each tenant holds encrypted Snippe keys. Webhooks hit `POST /webhooks/snippe/{tenantUuid}` and are verified with HMAC-SHA256 over `timestamp.rawBody` (`App\Domain\Billing\SnippeSignatureVerifier`). The official SDK does not verify signatures.

Throttle: Redis rate limiter, 60 requests per minute per tenant, matching Snippe's documented cap.

## Load test

From a host that can reach UDP 1812:

```bash
./scripts/radius-load-test.sh
```

See that script for `radclient` parallelism targeting 1000 concurrent accounting sessions.

## Secrets

Never commit `.env`. `KASI_VOUCHER_HASH_KEY` cannot be rotated without re-hashing `vouchers.code_hash` in the same transaction. NAS shared secrets live encrypted on `nas_devices` and in cleartext on FreeRADIUS `nas` (reachable only by the `radius` DB user).
