#!/usr/bin/env bash
# Ensures one dedicated queue worker per Marketplace Manager channel (parallel).
# Queues: marketplace-manager (legacy) + mm-{slug} for each Registry channel,
# plus "default" (price rules, pricing errors, page rebuilds) and the Shopify
# webhook queue (mm-ingress). Each worker exits after --max-time; cron starts
# a fresh one.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-/usr/bin/php}"
LOG_DIR="${ROOT}/storage/logs"
mkdir -p "$LOG_DIR"
ts() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }

QUEUES="$("$PHP_BIN" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$names = array_merge(
    ["marketplace-manager", App\Services\MarketplaceManager\MarketplaceManagerRegistry::QUEUE_TRACKING],
    App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueNames()
);
echo implode("\n", array_values(array_unique($names)));
')"

start_worker() {
  local QUEUE="$1"
  local TIMEOUT="$2"
  local TRIES="$3"
  [ -z "$QUEUE" ] && return 0
  local PATTERN="artisan queue:work.*--queue=${QUEUE}( |$)"
  local LOG="${LOG_DIR}/mm-worker-${QUEUE}.log"
  if pgrep -f "$PATTERN" >/dev/null 2>&1; then
    return 0
  fi
  echo "$(ts) starting queue worker (${QUEUE})" >>"$LOG"
  nohup "$PHP_BIN" "$ROOT/artisan" queue:work database \
    --queue="$QUEUE" \
    --sleep=3 \
    --tries="$TRIES" \
    --timeout="$TIMEOUT" \
    --max-time=7200 \
    >>"$LOG" 2>&1 &
  echo "$(ts) spawned pid $! for ${QUEUE}" >>"$LOG"
}

while IFS= read -r QUEUE; do
  start_worker "$QUEUE" 1800 5
done <<< "$QUEUES"

# Price-rule jobs set their own timeout to 7200, so the default worker must allow that.
start_worker "default" 7200 3

WEBHOOK_QUEUE="$("$PHP_BIN" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo (string) config("marketplace_manager.webhook_queue", "mm-ingress");
')"
start_worker "${WEBHOOK_QUEUE:-mm-ingress}" 1800 3
