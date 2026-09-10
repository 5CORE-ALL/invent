#!/usr/bin/env bash
# Remaining Dil pages (not eBay / Amazon / Shopify B2C / Macys / PP):
# save Sprc Dil → SPRICE, then queue S PRC → live listing.
# Direct crontab — schedule:run is overloaded and has missed IST slots.
#
# /etc/cron.d (server TZ Asia/Kolkata):
#   45 15 * * * inventory_5c_usr .../scripts/cron-dil-rest-sprice-daily.sh apply
#    5 16 * * * inventory_5c_usr .../scripts/cron-dil-rest-sprice-daily.sh push
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
  apply)
    CMD=(dil:rule-sprice-apply)
    LOCKDIR="${ROOT}/storage/framework/dil-rule-sprice-apply.lockdir"
    LOG="${ROOT}/storage/logs/cron-dil-rule-sprice-apply.log"
    ;;
  push)
    CMD=(channel:push-sprice-daily dil)
    LOCKDIR="${ROOT}/storage/framework/channel-push-sprice-daily-dil.lockdir"
    LOG="${ROOT}/storage/logs/cron-channel-push-sprice-daily-dil.log"
    ;;
  *)
    echo "usage: $0 apply|push" >&2
    exit 2
    ;;
esac

mkdir -p "$(dirname "$LOG")"
mkdir -p "$(dirname "$LOCKDIR")"

ts() { TZ=Asia/Kolkata date "+%Y-%m-%d %H:%M:%S %Z"; }

if ! mkdir "$LOCKDIR" 2>/dev/null; then
  echo "$(ts) skip ${MODE}: already running (${LOCKDIR})" >>"$LOG"
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
