#!/bin/bash
# Run on 147.79.101.245 as root:
#   sudo bash /var/www/config/Kasi_Network/deploy/install-teratech.sh
set -euo pipefail

SRC=/var/www/config/Kasi_Network
DEST=/var/config/Kasi_Network
WWW=/var/www/hasnetworks.co.tz

if [ "$(id -u)" -ne 0 ]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

if [ ! -d "$SRC" ]; then
  echo "Missing $SRC" >&2
  exit 1
fi

mkdir -p /var/config
if [ ! -e "$DEST" ]; then
  mv "$SRC" "$DEST"
  mkdir -p /var/www/config
  ln -sfn "$DEST" "$SRC"
fi

chown -R teratech:teratech "$DEST" /var/www/config "$WWW"
chmod 600 "$DEST/api/.env" "$DEST/docker/.env"
usermod -aG docker teratech

cd "$DEST/docker"
docker compose -f compose.prod.yaml --env-file .env up -d --build

echo "Waiting for MySQL..."
for i in $(seq 1 60); do
  if docker compose -f compose.prod.yaml --env-file .env exec -T mysql mysqladmin ping -h 127.0.0.1 --silent; then
    break
  fi
  sleep 2
done

set -a
# shellcheck disable=SC1091
. "$DEST/docker/.env"
set +a

if [ -f "$DEST/kasi-mysql.sql.gz" ]; then
  gunzip -c "$DEST/kasi-mysql.sql.gz" | docker compose -f compose.prod.yaml --env-file .env exec -T mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD"
  # The dump already has production tables. Record later migration files so
  # artisan does not try to CREATE them again.
  docker compose -f compose.prod.yaml --env-file .env exec -T mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" kasi -e "
INSERT INTO migrations (migration, batch)
SELECT m.migration, (SELECT IFNULL(MAX(batch),0)+1 FROM migrations)
FROM (
  SELECT '2026_09_28_204700_operator_shop_modules' AS migration
  UNION SELECT '2026_09_29_114500_drop_staff_role'
  UNION SELECT '2026_09_29_120000_console_simplification'
) m
WHERE NOT EXISTS (SELECT 1 FROM migrations x WHERE x.migration = m.migration);
"
fi

docker compose -f compose.prod.yaml --env-file .env exec -T api php artisan migrate --force
docker compose -f compose.prod.yaml --env-file .env exec -T api php artisan kasi:provision-radius-user
docker compose -f compose.prod.yaml --env-file .env exec -T api php artisan config:clear

install -m 644 "$DEST/deploy/nginx/hasnetworks.co.tz.host.conf" /etc/nginx/sites-available/hasnetworks.co.tz.conf
ln -sfn /etc/nginx/sites-available/hasnetworks.co.tz.conf /etc/nginx/sites-enabled/hasnetworks.co.tz.conf
nginx -t
systemctl reload nginx

if command -v certbot >/dev/null 2>&1; then
  certbot --nginx -d hasnetworks.co.tz --non-interactive --agree-tos --redirect --keep-until-expiring || \
    echo "certbot skipped or failed; DNS may not point here yet" >&2
fi

if command -v ufw >/dev/null 2>&1; then
  ufw allow 80/tcp
  ufw allow 443/tcp
  ufw allow 1812/udp
  ufw allow 1813/udp
  ufw allow 2083/tcp
fi

echo "Kasi is on $DEST"
echo "Portal files $WWW"
curl -fsS -o /dev/null -w "local_portal %{http_code}\n" -H "Host: hasnetworks.co.tz" http://127.0.0.1/ || true
curl -fsS -o /dev/null -w "local_api %{http_code}\n" -H "Host: hasnetworks.co.tz" http://127.0.0.1/api/v1/health || true
curl -fsS -o /dev/null -w "api_up %{http_code}\n" http://127.0.0.1:8020/up || true
