#!/usr/bin/env bash
set -euo pipefail
if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as: sudo $0"
  exit 1
fi

src="$(cd "$(dirname "$0")" && pwd)/pay.teratech.co.tz.conf"
cp "$src" /etc/nginx/sites-available/pay.teratech.co.tz.conf
ln -sfn /etc/nginx/sites-available/pay.teratech.co.tz.conf /etc/nginx/sites-enabled/pay.teratech.co.tz.conf
nginx -t
systemctl reload nginx
echo "HTTP callback: http://pay.teratech.co.tz/callback.php"
echo "Optional TLS: sudo certbot --nginx -d pay.teratech.co.tz"
