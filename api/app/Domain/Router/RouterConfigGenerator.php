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
        $loginUri = 'http://'.$device->nasname.'/login';

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

# Android 11+ / iOS 14+ read this on Join and open the login sheet
# without the user opening Wi-Fi settings.
/ip dhcp-server option
add name=kasi-capport code=114 value="'{$loginUri}'"

/ip dhcp-server network
set [find] dhcp-option=kasi-capport

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
  <title>Wi-Fi login</title>
  <style>
    html{color-scheme:light only}
    body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#e8eef6;color:#0b1220;font-family:system-ui,sans-serif;text-align:center}
    .card{width:100%;max-width:22rem;background:#fff;border:1px solid #cbd5e1;border-radius:16px;padding:28px 20px}
    h1{margin:0;font-size:1.45rem}
    p{margin:12px 0 0;color:#1e293b;line-height:1.45}
    a{display:block;margin-top:22px;padding:16px 18px;background:#1d4ed8;color:#fff;font-weight:800;text-decoration:none;border-radius:12px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Wi-Fi login</h1>
    <p>You are on the hotspot. Tap continue to see packages and enter a voucher.</p>
    <a href="{$portal}/?site={$nasId}&amp;mac=\$(mac)&amp;ip=\$(ip)&amp;link_login=\$(link-login-only)&amp;link_orig=\$(link-orig)&amp;chap_id=\$(chap-id)&amp;chap_challenge=\$(chap-challenge)">Continue / Endelea</a>
  </div>
</body>
</html>
HTML;
    }
}
