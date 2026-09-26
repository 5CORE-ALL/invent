#!/usr/bin/env bash
# Remaining Dil pages (not eBay / Amazon / Shopify B2C / Macys / PP).
# apply: each page saves S PRC, then pushes its live price (FB saves only).
# push: catch-up for any listing still different after apply.
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

set +e
if [[ "$MODE" == "apply" ]]; then
  code=0
  for ch in bestbuy aliexpress newegg temu temu2 temu3 reverb tiktok tiktok2 doba faire shein wayfair topdawg fb_marketplace walmart pls; do
    if [[ "$ch" == "fb_marketplace" ]]; then
      cmd=(dil:rule-sprice-apply "$ch")
    else
      cmd=(dil:rule-sprice-apply "$ch" --push)
    fi
    echo "$(ts) start ${cmd[*]}" >>"$LOG"
    "$PHP_BIN" "$ROOT/artisan" "${cmd[@]}" >>"$LOG" 2>&1
    one=$?
    echo "$(ts) ${ch} exit code ${one}" >>"$LOG"
    if [[ $one -ne 0 ]]; then
      code=$one
    fi
  done
  echo "$(ts) exit code ${code}" >>"$LOG"
  exit "$code"
fi

echo "$(ts) start ${CMD[*]}" >>"$LOG"
"$PHP_BIN" "$ROOT/artisan" "${CMD[@]}" >>"$LOG" 2>&1
code=$?
echo "$(ts) exit code ${code}" >>"$LOG"
exit "$code"
