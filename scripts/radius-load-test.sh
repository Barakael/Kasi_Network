#!/usr/bin/env bash
# Drive FreeRADIUS with radclient toward 1000+ concurrent accounting sessions.
#
# Usage:
#   RADIUS_HOST=127.0.0.1 RADIUS_SECRET=testing123 ./scripts/radius-load-test.sh
#
# Requires FreeRADIUS' radclient. Accounting-Request is used because it is the
# write path that actually stresses MySQL (radacct inserts / updates). Auth is
# a lighter SELECT against radcheck + voucher_usage.

set -euo pipefail

HOST="${RADIUS_HOST:-127.0.0.1}"
SECRET="${RADIUS_SECRET:-testing123}"
COUNT="${SESSION_COUNT:-1000}"
ACCT_PORT="${RADIUS_ACCT_PORT:-1813}"
AUTH_PORT="${RADIUS_AUTH_PORT:-1812}"
USER_PREFIX="${VOUCHER_PREFIX:-LOAD}"

if ! command -v radclient >/dev/null; then
  echo "radclient is not on PATH. Install FreeRADIUS utils (freeradius-utils)." >&2
  exit 1
fi

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

echo "Sending $COUNT Accounting-Start packets to $HOST:$ACCT_PORT"

for i in $(seq 1 "$COUNT"); do
  cat >>"$tmp" <<PKT
User-Name = "${USER_PREFIX}$(printf '%04d' "$i")"
Acct-Session-Id = "load-$i"
Acct-Status-Type = Start
NAS-IP-Address = 192.0.2.1
Framed-IP-Address = 10.5.$((i / 250)).$((i % 250))
Calling-Station-Id = "AA:BB:CC:DD:$(printf '%02X' $((i / 256))):$(printf '%02X' $((i % 256)))"
Acct-Unique-Session-Id = "$(printf 'load%028d' "$i")"

PKT
done

# -p: parallel outstanding packets. 256 matches the FreeRADIUS max_servers tune.
time radclient -x -s -p 256 "$HOST:$ACCT_PORT" acct "$SECRET" <"$tmp"

echo
echo "Optional auth sample (10 requests):"
for i in 1 2 3 4 5 6 7 8 9 10; do
  printf 'User-Name = "%s%04d"\nCleartext-Password = "%s%04d"\n\n' "$USER_PREFIX" "$i" "$USER_PREFIX" "$i"
done | radclient -x -p 10 "$HOST:$AUTH_PORT" auth "$SECRET" || true

echo "Done. Inspect radacct row counts and FreeRADIUS CPU before raising COUNT."
