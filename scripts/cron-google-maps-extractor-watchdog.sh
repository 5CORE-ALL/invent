#!/usr/bin/env bash
# Keeps the permanent Google Maps extractor queue watchdog daemon running.
#
# The daemon (php artisan queue:watchdog) only starts:
#   queue:work database --queue=google-maps-extractor
# It never runs a generic queue:work that could process other jobs.
#
# Crontab example (every 2 minutes, as a safety net if the daemon dies):
#   */2 * * * * /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/scripts/cron-google-maps-extractor-watchdog.sh
#
# chmod +x scripts/cron-google-maps-extractor-watchdog.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php}"
LOG="${ROOT}/storage/logs/queue-watchdog-daemon.log"
PATTERN="artisan queue:watchdog"

mkdir -p "$(dirname "$LOG")"
ts() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }

if pgrep -f "$PATTERN" >/dev/null 2>&1; then
  exit 0
fi

echo "$(ts) starting queue:watchdog daemon" >>"$LOG"
nohup "$PHP_BIN" "$ROOT/artisan" queue:watchdog >>"$LOG" 2>&1 &
echo "$(ts) spawned watchdog pid $!" >>"$LOG"
