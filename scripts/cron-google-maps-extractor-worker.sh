#!/usr/bin/env bash
# Ensures the google-maps-extractor queue worker is running (crontab watchdog).
#
# Used by CRM > Data Extractor (RunGoogleMapsExtractionJob / RunGoogleMapsEnrichmentJob).
# Prefer Laravel scheduler: queue:ensure-worker google-maps-extractor (every minute in app/Console/Kernel.php).
# This script remains as a fallback when invoked from deploy.sh or manual ops.
#   */5 * * * * /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/scripts/cron-google-maps-extractor-worker.sh #google-maps-extractor queue worker
#
# chmod +x scripts/cron-google-maps-extractor-worker.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php}"
QUEUE="google-maps-extractor"
LOG="${ROOT}/storage/logs/google-maps-extractor-worker.log"
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
  --timeout=3700 \
  --max-time=7200 \
  >>"$LOG" 2>&1 &
echo "$(ts) spawned pid $!" >>"$LOG"
