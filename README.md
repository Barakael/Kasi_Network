# Kasi Network

Multi-tenant Wi-Fi hotspot billing platform for MikroTik networks. Sells time- and
data-limited bundles through printable one-time vouchers and Tanzanian mobile money,
and binds vouchers to MAC addresses so devices that cannot render a captive portal
(smart TVs, consoles, IoT) can still be brought online.

## Architecture

MikroTik routers act as the hotspot NAS and authenticate every client against
FreeRADIUS, which shares a MySQL database with the Laravel API. Quotas are enforced
at three layers: `sqlcounter` modules cap each session at authentication time, a
`post-auth` hook starts a bundle's clock on first use, and a scheduled sweep sends
RADIUS CoA/Disconnect messages to cut off sessions that exhaust their quota mid-flight.

```
Client devices -> MikroTik hotspot -> FreeRADIUS -> MySQL <- Laravel API <- React portal / console
                        ^                                                          |
                        +------------------ CoA :3799 ------------------------------+
```

| Path | Contents |
| --- | --- |
| `api/` | Laravel API, domain code under `app/Domain/{Tenancy,Voucher,Radius,Billing,Router}` |
| `portal/` | Captive portal served to unauthenticated clients (React API on Preact runtime, budget-enforced) |
| `console/` | Operator and agent console, installable PWA |
| `docker/` | Dev stack plus the version-controlled FreeRADIUS configuration |
| `router-config/` | MikroTik `login.html` template and generated per-site `.rsc` provisioning scripts |
| `docs/` | Deployment runbook, RouterOS setup guide, load-test results |

## Requirements

- PHP 8.4 with `bcmath`, `gd`, `pcntl`, `pdo_mysql`, `zip`
- Composer 2
- Node 22+ and pnpm 10
- MySQL 8.4+ and Redis 7
- FreeRADIUS 3.2 with `freeradius-utils` (provides `radclient`) for CoA dispatch

## Local setup

Either bring up the full stack in containers:

```sh
docker compose -f docker/compose.yaml up -d
```

Or run against local services:

```sh
# API
cd api
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan kasi:provision-radius-user   # grants FreeRADIUS its restricted MySQL access
php artisan serve

# Interfaces
pnpm install
pnpm dev:portal    # http://localhost:5173
pnpm dev:console   # http://localhost:5174
```

Background work must be running for payments and quota enforcement to function:

```sh
cd api
php artisan queue:work
php artisan schedule:work
```

## Tests

```sh
cd api && php artisan test          # PHPUnit, runs against MySQL
cd api && vendor/bin/pint           # formatting
pnpm typecheck && pnpm build        # interfaces
node portal/scripts/check-bundle-size.mjs
```

The captive portal has an enforced 100 KB gzipped budget, checked in CI. It is fetched
over the hotspot link before a client has paid, so bundle regressions affect every
connection attempt.

## Connecting a router

Add the site in the console, then paste the generated `.rsc` script into the router's
terminal. It configures the hotspot profile to use RADIUS, enables MAC authentication
for voucher-bound devices, opens the CoA listener on UDP 3799, and installs the walled
garden entries the portal needs. See [docs/routeros-setup.md](docs/routeros-setup.md).
