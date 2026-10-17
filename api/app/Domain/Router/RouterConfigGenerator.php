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
        $portalHost = parse_url($portal, PHP_URL_HOST) ?: 'portal.kasi.test';
        $lanIp = $this->hotspotLanIp($device);

        $walledGarden = "add dst-host={$portalHost} comment=\"Kasi portal\"";

        return <<<RSC
# Kasi Network — hotspot RADIUS provisioning for {$device->name}
# Generated for site {$site->name} ({$nasId}). Review before import.
#
# nasname in Kasi is the public IP FreeRADIUS sees (after NAT), not the LAN
# address. Do not set /radius src-address to a private IP or packets never leave.

/radius
add address={$radiusHost} secret="{$secret}" service=hotspot authentication-port=1812 accounting-port=1813 timeout=3000ms

/radius incoming
set accept=yes port={$device->coa_port}

/ip hotspot profile
set [find] use-radius=yes radius-accounting=yes radius-interim-update=5m \\
    radius-location-id="{$nasId}" login-by=cookie,http-chap,https,mac,mac-cookie \\
    http-cookie-lifetime=1d mac-cookie-timeout=4w2d \\
    radius-mac-format=XX:XX:XX:XX:XX:XX radius-mac-authentication=yes \\
    dns-name="" html-directory=hotspot

/ip hotspot walled-garden
{$walledGarden}

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
add name=kasi-capport code=114 value="'http://{$lanIp}/captive.json'"

/ip dhcp-server network
set [find] dhcp-option=kasi-capport dns-server={$lanIp}

# Upload the generated login.html as hotspot/login.html and hotspot/redirect.html
# Copy api/public/captive-portal.json to hotspot/captive.json
RSC;
    }

    public function loginHtml(Site $site): string
    {
        $portal = rtrim((string) config('kasi.portal_url'), '/');
        $nasId = e($site->nas_identifier);
        $site->loadMissing('tenant');
        $tenant = $site->tenant;
        $brand = e($tenant?->portal_name ?: $tenant?->name ?: 'Kasi-Net');
        $logoUrl = $tenant?->logoUrl();
        $logoBlock = is_string($logoUrl) && $logoUrl !== ''
            ? '<img class="logo" src="'.e($logoUrl).'" alt="'.$brand.'">'
            : $this->fallbackLogoMark();

        return <<<HTML
<!DOCTYPE html>
<html lang="sw">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#2f5f3d">
  <meta http-equiv="refresh" content="3; url={$portal}/?site={$nasId}">
  <title>Inasubiri…</title>
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; }
    body {
      min-height: 100dvh;
      display: grid;
      place-items: center;
      font-family: "Avenir Next", "Segoe UI", system-ui, sans-serif;
      color: #eaf6ee;
      background:
        radial-gradient(120% 80% at 50% -10%, #6db87f 0%, transparent 55%),
        radial-gradient(90% 70% at 100% 100%, #1c3326 0%, transparent 50%),
        linear-gradient(165deg, #3a7a4c 0%, #2f5f3d 48%, #1c3326 100%);
      overflow: hidden;
    }
    main {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.35rem;
      padding: max(1.5rem, env(safe-area-inset-top)) 1.5rem max(1.5rem, env(safe-area-inset-bottom));
      text-align: center;
    }
    .glow {
      position: absolute;
      width: 16rem;
      height: 16rem;
      border-radius: 50%;
      background: radial-gradient(circle, rgb(255 255 255 / 0.22) 0%, transparent 68%);
      filter: blur(2px);
      animation: breathe 2.4s ease-in-out infinite;
      pointer-events: none;
    }
    .mark {
      position: relative;
      z-index: 1;
      width: 7.25rem;
      height: 7.25rem;
      display: grid;
      place-items: center;
      border-radius: 1.75rem;
      background: rgb(255 255 255 / 0.14);
      border: 1px solid rgb(255 255 255 / 0.28);
      box-shadow:
        0 18px 40px rgb(16 36 24 / 0.35),
        inset 0 1px 0 rgb(255 255 255 / 0.25);
      backdrop-filter: blur(8px);
      animation: rise 0.7s ease-out both;
    }
    .logo, .mark svg {
      width: 4.25rem;
      height: 4.25rem;
      object-fit: contain;
      display: block;
    }
    .brand {
      margin: 0;
      font-size: 1.35rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      animation: rise 0.7s ease-out 0.08s both;
    }
    .status {
      margin: 0;
      font-size: 0.95rem;
      letter-spacing: 0.04em;
      opacity: 0.9;
      animation: pulse 1.6s ease-in-out infinite;
    }
    .dots::after {
      content: "";
      display: inline-block;
      width: 1.1em;
      text-align: left;
      animation: dots 1.4s steps(3, end) infinite;
    }
    a {
      color: #eaf6ee;
      font-size: 0.85rem;
      opacity: 0.85;
    }
    @keyframes breathe {
      0%, 100% { transform: scale(0.92); opacity: 0.55; }
      50% { transform: scale(1.08); opacity: 0.9; }
    }
    @keyframes rise {
      from { opacity: 0; transform: translateY(10px) scale(0.96); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes pulse {
      0%, 100% { opacity: 0.7; }
      50% { opacity: 1; }
    }
    @keyframes dots {
      0% { content: ""; }
      33% { content: "."; }
      66% { content: ".."; }
      100% { content: "..."; }
    }
  </style>
</head>
<body>
  <main>
    <div class="glow" aria-hidden="true"></div>
    <div class="mark">{$logoBlock}</div>
    <p class="brand">{$brand}</p>
    <p class="status">Inasubiri ipakie<span class="dots"></span></p>
  </main>
  <script>
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
  </script>
  <noscript>
    <p style="text-align:center"><a href="{$portal}/?site={$nasId}">Endelea</a></p>
  </noscript>
</body>
</html>
HTML;
    }

    private function fallbackLogoMark(): string
    {
        return <<<'SVG'
<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.12)"/>
  <path d="M32 14c8.5 6.2 12.5 12.8 12.5 19.2 0 7.4-5.6 12.8-12.5 12.8S19.5 40.6 19.5 33.2C19.5 26.8 23.5 20.2 32 14Z" fill="#eaf6ee"/>
  <path d="M32 22v24" stroke="#2f5f3d" stroke-width="2.5" stroke-linecap="round"/>
  <path d="M22 36c6 2.5 14 2.5 20 0" stroke="#2f5f3d" stroke-width="2" stroke-linecap="round" opacity="0.7"/>
</svg>
SVG;
    }

    /**
     * DHCP option 114 and the hotspot DNS server must be the LAN gateway phones
     * can reach. nasname is often the public WAN IP FreeRADIUS matches, which
     * is the wrong address to hand to clients.
     */
    private function hotspotLanIp(NasDevice $device): string
    {
        $apiHost = $device->api_host;

        if (is_string($apiHost) && filter_var($apiHost, FILTER_VALIDATE_IP)) {
            return $apiHost;
        }

        if ($this->isPrivateIp($device->nasname)) {
            return $device->nasname;
        }

        return '192.168.10.1';
    }

    private function isPrivateIp(string $ip): bool
    {
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
}
