#!/usr/bin/env bash
# Daily Jungle Scout product data sync → junglescout_product_data (incl. listing_quality_score / LQS).
#
# This refreshes LQS, rating, reviews, price, etc. for every ASIN in amazon_datsheets joined to product_master.
# Drives the /lqs-data and /cvr-master pages — without this, LQS edits made on Amazon never reflect in the app.
#
# Use from crontab; equivalent of: php artisan app:process-jungle-scout-sheet-data
#
# Crontab example — daily at 00:30 America/Los_Angeles (= 13:00 IST):
#   30 0 * * * TZ=America/Los_Angeles /Applications/XAMPP/xamppfiles/htdocs/invent/scripts/cron-jungle-scout-daily.sh
#
# chmod once: chmod +x scripts/cron-jungle-scout-daily.sh
# Override PHP:  PHP_BIN=/usr/bin/php /path/to/this/script.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/Applications/XAMPP/xamppfiles/bin/php}"
LOG="${ROOT}/storage/logs/cron-jungle-scout-daily.log"
LOCKDIR="${ROOT}/storage/framework/jungle-scout-daily.lockdir"

mkdir -p "$(dirname "$LOG")"
mkdir -p "$(dirname "$LOCKDIR")"

ts() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }

if ! mkdir "$LOCKDIR" 2>/dev/null; then
  echo "$(ts) skip: another run still active (lock: ${LOCKDIR})" >>"$LOG"
  exit 0
fi
trap 'rmdir "$LOCKDIR" 2>/dev/null || true' EXIT INT TERM HUP

echo "$(ts) start app:process-jungle-scout-sheet-data" >>"$LOG"
set +e
"$PHP_BIN" "$ROOT/artisan" app:process-jungle-scout-sheet-data >>"$LOG" 2>&1
code=$?
set -e
echo "$(ts) exit code ${code}" >>"$LOG"
exit "$code"
