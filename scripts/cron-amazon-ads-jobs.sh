#!/usr/bin/env bash
# Amazon Ads background jobs, run every 10 minutes straight from cron (not through the overloaded schedule:run).
#   amazon-ads:warm-grid   rebuilds the cached first page of /amazon-ads/all
#   amazon-ads:bgt-counts  recounts the BGT rule bands and the SUGG DAILY / MONTHLY BGT total
# Each job has its own lock, so a slow run is skipped instead of piling up, and the next slot starts on time.
# Install: see /etc/cron.d/amazon-ads-jobs (runs this as inventory_5c_usr).

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG="$APP_DIR/storage/logs/amazon-ads-jobs.log"
cd "$APP_DIR" || exit 1

run_job() {
  local name="$1"
  local lock="/tmp/amazon-ads-${name}.lock"
  (
    flock -n 9 || { echo "$(date '+%F %T') $name still running, skipped" >> "$LOG"; exit 0; }
    echo "$(date '+%F %T') $name start" >> "$LOG"
    nice -n 10 php -d memory_limit=2G artisan "amazon-ads:${name}" >> "$LOG" 2>&1
    echo "$(date '+%F %T') $name done (exit $?)" >> "$LOG"
  ) 9>"$lock"
}

# In parallel: they read different data and neither should wait on the other.
run_job warm-grid &
run_job bgt-counts &
wait
