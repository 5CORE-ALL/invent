#!/usr/bin/env bash
# Products / orders snapshot → shopify_skus (saveDailyInventory). Use from crontab; not Laravel schedule.
#
# Same idea as:
#   /usr/bin/php /var/www/.../artisan shopify:save-daily-inventory
#
# Crontab example — every 4 hours at :00 UTC (was previous Kernel schedule):
#   0 */4 * * * /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/scripts/cron-shopify-save-daily-inventory.sh
#
# chmod +x scripts/cron-shopify-save-daily-inventory.sh
# PHP_BIN=/usr/bin/php  (override if needed)
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php}"
LOG="${ROOT}/storage/logs/cron-shopify-save-daily-inventory.log"
LOCKDIR="${ROOT}/storage/framework/shopify-save-daily-inventory.lockdir"

mkdir -p "$(dirname "$LOG")"
mkdir -p "$(dirname "$LOCKDIR")"

ts() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }

if ! mkdir "$LOCKDIR" 2>/dev/null; then
  echo "$(ts) skip: another run still active (lock: ${LOCKDIR})" >>"$LOG"
  exit 0
fi
trap 'rmdir "$LOCKDIR" 2>/dev/null || true' EXIT INT TERM HUP

echo "$(ts) start shopify:save-daily-inventory" >>"$LOG"
set +e
"$PHP_BIN" "$ROOT/artisan" shopify:save-daily-inventory >>"$LOG" 2>&1
code=$?
set -e
echo "$(ts) exit code ${code}" >>"$LOG"
exit "$code"
