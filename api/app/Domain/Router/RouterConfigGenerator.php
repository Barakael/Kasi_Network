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
        $hotspotIp = $device->nasname;

        return <<<RSC
# Kasi Network — hotspot RADIUS provisioning for {$device->name}
# Generated for site {$site->name} ({$nasId}). Review before import.

/radius
add address={$radiusHost} secret="{$secret}" service=hotspot authentication-port=1812 accounting-port=1813 timeout=3000ms src-address={$hotspotIp}

/radius incoming
set accept=yes port={$device->coa_port}

/ip hotspot profile
set [find] use-radius=yes radius-accounting=yes radius-interim-update=5m \\
    radius-location-id="{$nasId}" login-by=cookie,http-chap,mac-cookie,https \\
    http-cookie-lifetime=1d mac-cookie-timeout=4w2d \\
    radius-mac-format=XX:XX:XX:XX:XX:XX radius-mac-authentication=yes \\
    dns-name="" html-directory=hotspot

/ip hotspot walled-garden
add dst-host={$portalHost} comment="Kasi portal"
add dst-host={$api} comment="Kasi API"

# Windows must resolve this to 131.107.255.255. Do not hijack
# www.msftconnecttest.com — that turns the Windows probe into a 404
# and hides the Sign in link. dns-name keeps the 302 on the same host.
/ip dns set allow-remote-requests=yes
/ip dns static
add name=dns.msftncsi.com address=131.107.255.255 ttl=30s comment="Kasi NCSI DNS"

/ip firewall filter
add chain=forward protocol=udp dst-port=853 action=drop comment="Kasi block DoH"
add chain=forward protocol=tcp dst-port=853 action=drop comment="Kasi block DoH"

/ip firewall nat
add chain=dstnat in-interface=bridge protocol=udp dst-port=53 action=redirect to-ports=53 comment="Kasi force DNS"
add chain=dstnat in-interface=bridge protocol=tcp dst-port=53 action=redirect to-ports=53 comment="Kasi force DNS"

/ip dhcp-server option
add name=kasi-capport code=114 value="'http://{$hotspotIp}/captive.json'"

/ip dhcp-server network
set [find] dhcp-option=kasi-capport dns-server={$hotspotIp}

# Copy api/public/hotspot-login.html to hotspot/login.html and hotspot/redirect.html
# Copy api/public/captive-portal.json to hotspot/captive.json
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
  <script src="/md5.js"></script>
  <style>
    html{color-scheme:light only}
    body{margin:0;min-height:100vh;padding:20px 16px;background:#e8eef6;color:#0b1220;font-family:system-ui,sans-serif}
    .card{width:100%;max-width:22rem;margin:0 auto;background:#fff;border:1px solid #cbd5e1;border-radius:16px;padding:22px 18px}
    h1{margin:0;font-size:1.35rem;text-align:center}
    .lead{margin:8px 0 16px;text-align:center;color:#334155}
    label{display:block;margin:0 0 6px;font-weight:650}
    input{width:100%;box-sizing:border-box;min-height:44px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;font:inherit}
    .go{display:block;width:100%;margin-top:12px;min-height:48px;border:0;border-radius:12px;background:#1d4ed8;color:#fff;font-weight:800}
    .note{margin:12px 0 0;color:#64748b;font-size:0.8rem;text-align:center;word-break:break-all}
  </style>
</head>
<body>
  <div class="card">
    <h1>Wi-Fi login</h1>
    <p class="lead">Weka voucher hapa. Dirisha la Microsoft si ukurasa wa paketi.</p>
    <form onsubmit="return doLogin(event)">
      <label for="user">Namba ya voucher</label>
      <input id="user" maxlength="32" autocapitalize="characters" autocomplete="off" placeholder="XXXXX-XXXXX" required>
      <button class="go" type="submit">Pokea Wi-Fi</button>
    </form>
    <p class="note">{$portal}/?site={$nasId}</p>
  </div>
  <form name="sendin" action="\$(link-login-only)" method="post" style="display:none">
    <input type="hidden" name="username">
    <input type="hidden" name="password">
    <input type="hidden" name="dst" value="\$(link-orig)">
    <input type="hidden" name="popup" value="true">
  </form>
  <script>
    function doLogin(ev) {
      ev.preventDefault();
      var code = document.getElementById('user').value.replace(/\\s+/g, '').toUpperCase();
      document.sendin.username.value = code;
      document.sendin.password.value = (typeof hexMD5 === 'function')
        ? hexMD5('\$(chap-id)' + code + '\$(chap-challenge)')
        : code;
      document.sendin.submit();
      return false;
    }
  </script>
</body>
</html>
HTML;
    }
}
