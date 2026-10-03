#!/usr/bin/env bash
# Re-run Kernel jobs that were due today but never started.
# Direct crontab — schedule:run has been skipping the 12:15 and 20:15 slots.
#
# /etc/cron.d/cron-run-missed (CRON_TZ=Asia/Kolkata):
#   40 10 * * * inventory_5c_usr .../scripts/cron-run-missed.sh
#   10 13 * * * inventory_5c_usr .../scripts/cron-run-missed.sh
#   40 16 * * * inventory_5c_usr .../scripts/cron-run-missed.sh
#    5 20 * * * inventory_5c_usr .../scripts/cron-run-missed.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -x /usr/bin/php8.3 ]]; then
  PHP_BIN="${PHP_BIN:-/usr/bin/php8.3}"
else
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
fi

LOCKDIR="${ROOT}/storage/framework/cron-run-missed.lockdir"
LOG="${ROOT}/storage/logs/cron-run-missed.log"

mkdir -p "$(dirname "$LOG")" "$(dirname "$LOCKDIR")"

ts() { TZ=Asia/Kolkata date "+%Y-%m-%d %H:%M:%S %Z"; }

if ! mkdir "$LOCKDIR" 2>/dev/null; then
  echo "$(ts) skip: already running" >>"$LOG"
  exit 0
fi
trap 'rmdir "$LOCKDIR" 2>/dev/null || true' EXIT INT TERM HUP

echo "$(ts) start" >>"$LOG"
"$PHP_BIN" "$ROOT/artisan" cron:run-missed >>"$LOG" 2>&1
code=$?
echo "$(ts) exit code ${code}" >>"$LOG"
exit "$code"
