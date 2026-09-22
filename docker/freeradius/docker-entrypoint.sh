#!/bin/sh
set -eu

# Overlay version-controlled config from the Compose mount when present, then
# substitute database coordinates. FreeRADIUS does not expand environment
# variables in module config, so sed is the only way a Compose-supplied
# password reaches the restricted account.

# Official Alpine image: /opt/etc/raddb. Debian images: /etc/raddb.
RADDB=/opt/etc/raddb
if [ ! -d "${RADDB}" ]; then
    RADDB=/etc/raddb
fi

# Overlay version-controlled site, policy and module files. Do not copy
# mods-config/sql/.../queries.conf — FreeRADIUS 3.2 parses that include at
# the wrong scope when it is overlaid, and the stock MySQL queries match
# Kasi's radacct schema.
if [ -d /opt/kasi-raddb ]; then
    cp -a /opt/kasi-raddb/sites-available/. "${RADDB}/sites-available/" 2>/dev/null || true
    cp -a /opt/kasi-raddb/policy.d/. "${RADDB}/policy.d/" 2>/dev/null || true
    cp -a /opt/kasi-raddb/mods-available/. "${RADDB}/mods-available/" 2>/dev/null || true
    if [ -f /opt/kasi-raddb/dictionary.mikrotik ]; then
        cp -a /opt/kasi-raddb/dictionary.mikrotik "${RADDB}/dictionary.mikrotik"
    fi
fi

SQL_FILE="${RADDB}/mods-available/sql"

# Prefer patching the stock module so $INCLUDE of queries.conf stays nested
# inside sql { }. Replacing that file with a short template made FreeRADIUS
# parse queries.conf at the top level and die on ${acct_table1}.
if grep -q 'driver = "rlm_sql_null"' "${SQL_FILE}" 2>/dev/null; then
    RADIUS_DB_HOST="${RADIUS_DB_HOST:-mysql}" \
    RADIUS_DB_PORT="${RADIUS_DB_PORT:-3306}" \
    RADIUS_DB_NAME="${RADIUS_DB_NAME:-kasi}" \
    RADIUS_DB_USER="${RADIUS_DB_USER:-radius}" \
    RADIUS_DB_PASSWORD="${RADIUS_DB_PASSWORD:-radius_secret}" \
    awk '
        /^[[:space:]]*dialect = "sqlite"/ {
            print "\tdialect = \"mysql\""
            next
        }
        /^[[:space:]]*driver = "rlm_sql_null"/ {
            print "#\tdriver = \"rlm_sql_null\""
            next
        }
        /^#[[:space:]]*driver = "rlm_sql_\$\{dialect\}"/ {
            print "\tdriver = \"rlm_sql_${dialect}\""
            next
        }
        /^#[[:space:]]*server = "localhost"/ {
            print "\tserver = \"" ENVIRON["RADIUS_DB_HOST"] "\""
            next
        }
        /^#[[:space:]]*port = 3306/ {
            print "\tport = " ENVIRON["RADIUS_DB_PORT"]
            next
        }
        /^#[[:space:]]*login = "radius"/ {
            print "\tlogin = \"" ENVIRON["RADIUS_DB_USER"] "\""
            next
        }
        /^#[[:space:]]*password = "radpass"/ {
            print "\tpassword = \"" ENVIRON["RADIUS_DB_PASSWORD"] "\""
            next
        }
        /^[[:space:]]*radius_db = "radius"/ {
            print "\tradius_db = \"" ENVIRON["RADIUS_DB_NAME"] "\""
            next
        }
        /^#[[:space:]]*read_clients = yes/ {
            print "\tread_clients = yes"
            next
        }
        /^[[:space:]]*mysql \{/ {
            print
            print "\t\ttls {"
            print "\t\t\tca_file = \"/mysql-certs/ca.pem\""
            print "\t\t\ttls_required = yes"
            print "\t\t\ttls_check_cert = yes"
            print "\t\t\ttls_check_cert_cn = yes"
            print "\t\t}"
            next
        }
        /^[[:space:]]*tls \{/ {
            in_tls = 1
            print "#" $0
            next
        }
        in_tls {
            print "#" $0
            if ($0 ~ /^[[:space:]]*\}/) in_tls = 0
            next
        }
        { print }
    ' "${SQL_FILE}" > "${SQL_FILE}.kasi" && mv "${SQL_FILE}.kasi" "${SQL_FILE}"
elif [ -f "${SQL_FILE}.template" ]; then
    RADIUS_DB_HOST="${RADIUS_DB_HOST:-mysql}" \
    RADIUS_DB_PORT="${RADIUS_DB_PORT:-3306}" \
    RADIUS_DB_NAME="${RADIUS_DB_NAME:-kasi}" \
    RADIUS_DB_USER="${RADIUS_DB_USER:-radius}" \
    RADIUS_DB_PASSWORD="${RADIUS_DB_PASSWORD:-radius_secret}" \
    awk '
        {
            gsub(/__RADIUS_DB_HOST__/, ENVIRON["RADIUS_DB_HOST"])
            gsub(/__RADIUS_DB_PORT__/, ENVIRON["RADIUS_DB_PORT"])
            gsub(/__RADIUS_DB_NAME__/, ENVIRON["RADIUS_DB_NAME"])
            gsub(/__RADIUS_DB_USER__/, ENVIRON["RADIUS_DB_USER"])
            gsub(/__RADIUS_DB_PASSWORD__/, ENVIRON["RADIUS_DB_PASSWORD"])
            print
        }
    ' "${SQL_FILE}.template" > "${SQL_FILE}"
fi

# Re-apply site/module links after the overlay copy.
rm -f "${RADDB}/sites-enabled/default" "${RADDB}/sites-enabled/inner-tunnel" "${RADDB}/mods-enabled/eap"
ln -sf "${RADDB}/sites-available/kasi" "${RADDB}/sites-enabled/kasi"
ln -sf "${RADDB}/mods-available/sql" "${RADDB}/mods-enabled/sql"
ln -sf "${RADDB}/mods-available/sqlcounter" "${RADDB}/mods-enabled/sqlcounter"

# Official Alpine image puts radiusd in /opt/sbin and exports PATH from its
# own entrypoint. Replacing ENTRYPOINT drops that, so restore it here.
export PATH="/opt/sbin:/usr/sbin:/usr/local/sbin:/sbin:${PATH}"

if [ "${1:-}" = "radiusd" ] && ! command -v radiusd >/dev/null 2>&1; then
    if command -v freeradius >/dev/null 2>&1; then
        shift
        set -- freeradius "$@"
    fi
fi

exec "$@"
