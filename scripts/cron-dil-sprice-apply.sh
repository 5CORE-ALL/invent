#!/usr/bin/env bash
# Page-less Dil → SPRICE for channels that still show Dil in the cell
# while DB SPRICE stays stale (same class of bug as eBay 3).
#
# /etc/cron.d/dil-sprice-apply (server TZ Asia/Kolkata):
#   0 4,20 * * * ... cron-dil-sprice-apply.sh amazon
#   40 13 * * *  ... cron-dil-sprice-apply.sh shopify_b2c   # ~04:10 ET
#   0 * * * *    ... cron-dil-sprice-apply.sh macys
#   0 * * * *    ... cron-dil-sprice-apply.sh purchasing_power
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -x /usr/bin/php8.3 ]]; then
  PHP_BIN="${PHP_BIN:-/usr/bin/php8.3}"
else
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
fi

MODE="${1:-}"
case "$MODE" in
  amazon)
    CMD=(amazon:sprc-dil-auto-push)
    ;;
  shopify_b2c|shopify-b2c)
    CMD=(shopify-b2c:rule-sprice-apply)
    MODE=shopify_b2c
    ;;
  macys|macy)
    CMD=(macys:rule-sprice-apply)
    MODE=macys
    ;;
  purchasing_power|pp)
    CMD=(purchasing-power:rule-sprice-apply)
    MODE=purchasing_power
    ;;
  *)
    echo "usage: $0 amazon|shopify_b2c|macys|purchasing_power" >&2
    exit 2
    ;;
esac

LOCKDIR="${ROOT}/storage/framework/dil-sprice-apply-${MODE}.lockdir"
LOG="${ROOT}/storage/logs/cron-dil-sprice-apply-${MODE}.log"

mkdir -p "$(dirname "$LOG")"
mkdir -p "$(dirname "$LOCKDIR")"

ts() { TZ=Asia/Kolkata date "+%Y-%m-%d %H:%M:%S %Z"; }

if ! mkdir "$LOCKDIR" 2>/dev/null; then
  echo "$(ts) skip ${MODE}: already running" >>"$LOG"
  exit 0
fi
trap 'rmdir "$LOCKDIR" 2>/dev/null || true' EXIT INT TERM HUP

echo "$(ts) start ${CMD[*]}" >>"$LOG"
set +e
"$PHP_BIN" "$ROOT/artisan" "${CMD[@]}" >>"$LOG" 2>&1
code=$?
set -e
echo "$(ts) exit code ${code}" >>"$LOG"
exit "$code"
