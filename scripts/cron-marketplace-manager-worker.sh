#!/usr/bin/env bash
# Ensures the shared Marketplace Manager queue worker is running (crontab watchdog).
#
# Processes ALL Marketplace Manager jobs (AliExpress, Alibaba, Reverb, future channels):
#   - order import to Shopify
#   - inventory sync from Shopify webhooks
#
# Queue name: marketplace-manager
# queue:work is long-lived; cron only starts it when no matching process exists.
#
# Crontab example (inventory_5c_usr, every 5 minutes):
#   */5 * * * * /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/scripts/cron-marketplace-manager-worker.sh #marketplace-manager queue worker
#
# Local:
#   php artisan queue:work database --queue=marketplace-manager
#
# chmod +x scripts/cron-marketplace-manager-worker.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php}"
QUEUE="marketplace-manager"
LOG="${ROOT}/storage/logs/marketplace-manager-worker.log"
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
  --tries=5 \
  --timeout=900 \
  --max-time=7200 \
  >>"$LOG" 2>&1 &
echo "$(ts) spawned pid $!" >>"$LOG"
