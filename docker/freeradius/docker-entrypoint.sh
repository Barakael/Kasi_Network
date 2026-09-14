#!/bin/sh
set -eu

# Overlay version-controlled config from the Compose mount when present, then
# substitute database coordinates. FreeRADIUS does not expand environment
# variables in module config, so sed is the only way a Compose-supplied
# password reaches the restricted account.

if [ -d /opt/kasi-raddb ]; then
    cp -a /opt/kasi-raddb/. /etc/raddb/
fi

SQL_FILE=/etc/raddb/mods-available/sql

if [ -f "${SQL_FILE}.template" ]; then
    sed \
        -e "s|__RADIUS_DB_HOST__|${RADIUS_DB_HOST:-mysql}|g" \
        -e "s|__RADIUS_DB_PORT__|${RADIUS_DB_PORT:-3306}|g" \
        -e "s|__RADIUS_DB_NAME__|${RADIUS_DB_NAME:-kasi}|g" \
        -e "s|__RADIUS_DB_USER__|${RADIUS_DB_USER:-radius}|g" \
        -e "s|__RADIUS_DB_PASSWORD__|${RADIUS_DB_PASSWORD:-radius_secret}|g" \
        "${SQL_FILE}.template" > "${SQL_FILE}"
fi

exec "$@"
