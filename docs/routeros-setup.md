# RouterOS hotspot setup

Use the snippet the console generates for a NAS (`Routers → Snippet`). It is also produced by `App\Domain\Router\RouterConfigGenerator`. Review before import.

## RADIUS client

```
/radius
add address=<kasi-radius-host> secret="<shared-secret>" service=hotspot \
    authentication-port=1812 accounting-port=1813 timeout=3000ms

/radius incoming
set accept=yes port=3799
```

Incoming CoA is required. Without it, quota enforcement can refuse the next login but cannot cut a session that is already open.

## Hotspot profile

```
/ip hotspot profile
set [find] use-radius=yes radius-accounting=yes radius-interim-update=5m \
    login-by=cookie,http-chap,mac-cookie,https \
    http-cookie-lifetime=1d mac-cookie-timeout=4w2d \
    radius-mac-format=XX:XX:XX:XX:XX:XX radius-mac-authentication=yes \
    radius-location-id="<site nas_identifier>"
```

`radius-mac-format` must match `kasi.radius.mac_format` (default `XX:XX:XX:XX:XX:XX`). A mismatch makes MAC binding fail silently.

`mac-cookie` lets a phone that already paid reconnect without typing the voucher. Bound TVs authenticate as their MAC; FreeRADIUS rewrites that User-Name onto the parent voucher, scoped by NAS-IP-Address, so two operators never collide on the same physical device.

Interim updates of 2 minutes are pushed per-voucher via `Acct-Interim-Interval` for data-capped bundles (5 minutes for time-only). The profile default of 5m is the fallback.

## Walled garden

Allow the portal and API hosts before authentication:

```
/ip hotspot walled-garden
add dst-host=portal.example.com comment="Kasi portal"
add dst-host=api.example.com comment="Kasi API"
```

## login.html

Copy the generated `login.html` into the hotspot HTML directory (`/ip hotspot profile set [find] html-directory=hotspot`). MikroTik substitutes `$(mac)`, `$(chap-id)`, `$(chap-challenge)`, `$(link-login-only)` before the browser runs the redirect.

A static example lives at `router-config/login.html`. The console fills in the real portal origin and NAS-Identifier.

## RouterOS API (optional)

Used only for “nearby devices” when binding a TV from the portal. Create a dedicated user with read access to `/ip hotspot host`. Store host, port, username and password on the NAS in the console. Leave them empty if the site only sells printed vouchers.

## Checklist

1. RADIUS ping from the router to UDP 1812/1813.
2. `/radius incoming` accepting on 3799.
3. Walled garden covers portal + API.
4. `login.html` redirects with `site=<nas_identifier>`.
5. Print a one-sheet batch and redeem on a phone.
6. Disconnect that session from the console and confirm the client drops.
