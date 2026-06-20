#!/usr/bin/env bash
# Ensures the shopify-image-pull queue worker is running (crontab watchdog).
#
# queue:work is long-lived; cron only starts it when no matching process exists.
#
# Crontab example (inventory_5c_usr, every 5 minutes):
#   */5 * * * * /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/scripts/cron-shopify-image-pull-worker.sh #shopify-image-pull queue worker
#
# chmod +x scripts/cron-shopify-image-pull-worker.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php}"
QUEUE="shopify-image-pull"
LOG="${ROOT}/storage/logs/shopify-image-pull-worker.log"
PATTERN="artisan queue:work.*--queue=${QUEUE}"

mkdir -p "$(dirname "$LOG")"
ts() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }

if pgrep -f "$PATTERN" >/dev/null 2>&1; then
  exit 0
fi

echo "$(ts) starting queue worker (${QUEUE})" >>"$LOG"
nohup "$PHP_BIN" "$ROOT/artisan" queue:work database \
  --queue="$QUEUE" \
  --sleep=3 \
  --tries=1 \
  --timeout=14400 \
  --max-time=14400 \
  >>"$LOG" 2>&1 &
echo "$(ts) spawned pid $!" >>"$LOG"
