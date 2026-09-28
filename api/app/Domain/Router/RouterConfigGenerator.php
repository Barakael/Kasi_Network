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
    radius-location-id="{$nasId}" login-by=http-pap,http-chap,cookie,mac-cookie \\
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

        return <<<HTML
<!DOCTYPE html>
<html lang="sw">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kasi</title>
</head>
<body>
  <script>
    var err = "\$(error)";
    var params = new URLSearchParams({
      site: "{$nasId}",
      view: "packages",
      mac: "\$(mac)",
      ip: "\$(ip)",
      link_login: "\$(link-login-only)",
      link_orig: "\$(link-orig)",
      chap_id: "\$(chap-id)",
      chap_challenge: "\$(chap-challenge)"
    });
    if (err && err.indexOf("\$(") === -1) {
      params.set("error", err);
    }
    window.location.replace("{$portal}/?" + params.toString());
  </script>
</body>
</html>
HTML;
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
