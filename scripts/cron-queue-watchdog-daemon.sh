#!/usr/bin/env bash
# Keeps the permanent dedicated-queue watchdog daemon running.
#
# The daemon (php artisan queue:watchdog) only starts workers like:
#   queue:work database --queue=google-maps-extractor
#   queue:work database --queue=shopify-image-pull
#   ... (see config/queue_workers.php)
# It never runs a generic queue:work that could process the default queue.
#
# Crontab example (every 2 minutes, safety net if the daemon dies):
#   */2 * * * * /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/scripts/cron-queue-watchdog-daemon.sh
#
# chmod +x scripts/cron-queue-watchdog-daemon.sh
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
  WD_PID="$(pgrep -f "$PATTERN" | head -n 1)"
  WD_START=0
  CFG_MTIME=0
  CODE_MTIME=0
  if [[ -n "${WD_PID}" && -d "/proc/${WD_PID}" ]]; then
    WD_START="$(stat -c %Y "/proc/${WD_PID}" 2>/dev/null || echo 0)"
  fi
  if [[ -f "${ROOT}/config/queue_workers.php" ]]; then
    CFG_MTIME="$(stat -c %Y "${ROOT}/config/queue_workers.php" 2>/dev/null || echo 0)"
  fi
  if [[ -f "${ROOT}/app/Console/Commands/RunQueueWorkerWatchdogCommand.php" ]]; then
    CODE_MTIME="$(stat -c %Y "${ROOT}/app/Console/Commands/RunQueueWorkerWatchdogCommand.php" 2>/dev/null || echo 0)"
  fi
  # A daemon started before a new queue was added never watches that queue.
  if [[ "${WD_START}" -ge "${CFG_MTIME}" && "${WD_START}" -ge "${CODE_MTIME}" ]]; then
    exit 0
  fi
  echo "$(ts) restarting queue:watchdog pid ${WD_PID} (config or code is newer)" >>"$LOG"
  kill "${WD_PID}" >/dev/null 2>&1 || true
  sleep 1
fi

echo "$(ts) starting queue:watchdog daemon (all dedicated queues)" >>"$LOG"
nohup "$PHP_BIN" "$ROOT/artisan" queue:watchdog >>"$LOG" 2>&1 &
echo "$(ts) spawned watchdog pid $!" >>"$LOG"
