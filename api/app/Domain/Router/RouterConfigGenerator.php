<?php

declare(strict_types=1);

namespace App\Domain\Router;

use App\Models\NasDevice;
use App\Models\Site;
use App\Models\Tenant;

/**
 * MikroTik RouterOS snippets used to onboard a hotspot onto Kasi.
 */
final readonly class RouterConfigGenerator
{
    public function rsc(NasDevice $device, Site $site, Tenant $tenant, string $radiusHost): string
    {
        $secret = $device->shared_secret;
        $nasId = $site->nas_identifier;
        $portal = rtrim((string) config('kasi.portal_url'), '/');
        $api = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'api.kasi.test';
        $portalHost = parse_url($portal, PHP_URL_HOST) ?: 'portal.kasi.test';

        return <<<RSC
# Kasi Network — hotspot RADIUS provisioning for {$device->name}
# Generated for site {$site->name} ({$nasId}). Review before import.

/radius
add address={$radiusHost} secret="{$secret}" service=hotspot authentication-port=1812 accounting-port=1813 timeout=3000ms src-address={$device->nasname}

/radius incoming
set accept=yes port={$device->coa_port}

/ip hotspot profile
set [find] use-radius=yes radius-accounting=yes radius-interim-update=5m \\
    radius-location-id="{$nasId}" login-by=cookie,http-chap,mac-cookie,https \\
    http-cookie-lifetime=1d mac-cookie-timeout=4w2d \\
    radius-mac-format=XX:XX:XX:XX:XX:XX radius-mac-authentication=yes

/ip hotspot walled-garden
add dst-host={$portalHost} comment="Kasi portal"
add dst-host={$api} comment="Kasi API"

# Copy router-config/login.html onto the router and set it as the hotspot login page:
# /ip hotspot profile set [find] html-directory=hotspot
RSC;
    }

    public function loginHtml(Site $site): string
    {
        $portal = rtrim((string) config('kasi.portal_url'), '/');
        $nasId = e($site->nas_identifier);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connecting…</title>
  <style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:grid;place-items:center;min-height:100vh;margin:0}</style>
</head>
<body>
  <p>Opening the login page…</p>
  <script>
    (function () {
      var params = new URLSearchParams({
        site: "{$nasId}",
        mac: "\$(mac)",
        ip: "\$(ip)",
        link_login: "\$(link-login-only)",
        link_orig: "\$(link-orig)",
        chap_id: "\$(chap-id)",
        chap_challenge: "\$(chap-challenge)",
        error: "\$(error)"
      });
      window.location.replace("{$portal}/?" + params.toString());
    })();
  </script>
  <noscript>
    <a href="{$portal}/?site={$nasId}">Continue</a>
  </noscript>
</body>
</html>
HTML;
    }
}
