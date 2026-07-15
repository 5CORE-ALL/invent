#!/usr/bin/env bash
# Ensures one dedicated queue worker per Marketplace Manager channel (parallel).
# Queues: marketplace-manager (legacy) + mm-{slug} for each Registry channel.
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
    ["marketplace-manager"],
    App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueNames()
);
echo implode("\n", array_values(array_unique($names)));
')"

while IFS= read -r QUEUE; do
  [ -z "$QUEUE" ] && continue
  PATTERN="artisan queue:work.*--queue=${QUEUE}"
  LOG="${LOG_DIR}/mm-worker-${QUEUE}.log"
  if pgrep -f "$PATTERN" >/dev/null 2>&1; then
    continue
  fi
  echo "$(ts) starting queue worker (${QUEUE})" >>"$LOG"
  nohup "$PHP_BIN" "$ROOT/artisan" queue:work database \
    --queue="$QUEUE" \
    --sleep=3 \
    --tries=5 \
    --timeout=1800 \
    --max-time=7200 \
    >>"$LOG" 2>&1 &
  echo "$(ts) spawned pid $! for ${QUEUE}" >>"$LOG"
done <<< "$QUEUES"
