#!/usr/bin/env bash
# Run Ohio live inventory sync (GraphQL → shopify_skus). Use this from crontab instead of Laravel schedule.
#
# Crontab example (every 30 minutes UTC):
#   */30 * * * * /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/scripts/cron-shopify-sync-live-inventory.sh
#
# chmod once: chmod +x scripts/cron-shopify-sync-live-inventory.sh
#
# Override PHP:  PHP_BIN=/opt/php82/bin/php /path/to/this/script.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php}"
LOG="${ROOT}/storage/logs/cron-shopify-sync-live-inventory.log"
LOCKDIR="${ROOT}/storage/framework/shopify-sync-live-inventory.lockdir"

mkdir -p "$(dirname "$LOG")"
mkdir -p "$(dirname "$LOCKDIR")"

ts() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }

if ! mkdir "$LOCKDIR" 2>/dev/null; then
  echo "$(ts) skip: another sync still running (lock: ${LOCKDIR})" >>"$LOG"
  exit 0
fi
trap 'rmdir "$LOCKDIR" 2>/dev/null || true' EXIT INT TERM HUP

echo "$(ts) start shopify:sync-live-inventory" >>"$LOG"
set +e
"$PHP_BIN" "$ROOT/artisan" shopify:sync-live-inventory >>"$LOG" 2>&1
code=$?
set -e
echo "$(ts) exit code ${code}" >>"$LOG"
exit "$code"
