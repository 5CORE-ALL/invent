#!/usr/bin/env bash
# eBay 1/2/3 Dil SPRICE + daily S PRC push — direct crontab, not Laravel schedule:run.
# schedule:run is overloaded and has missed 17:00 IST (e.g. 10 Sep 2026).
#
# /etc/cron.d (server TZ Asia/Kolkata):
#   45 16 * * * inventory_5c_usr .../scripts/cron-ebay3-sprice-daily.sh apply
#    0 17 * * * inventory_5c_usr .../scripts/cron-ebay3-sprice-daily.sh push
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
    CMD=(ebay:rule-sprice-apply)
    LOCKDIR="${ROOT}/storage/framework/ebay-rule-sprice-apply.lockdir"
    LOG="${ROOT}/storage/logs/cron-ebay-rule-sprice-apply.log"
    ;;
  push)
    CMD=(channel:push-sprice-daily)
    LOCKDIR="${ROOT}/storage/framework/channel-push-sprice-daily.lockdir"
    LOG="${ROOT}/storage/logs/cron-channel-push-sprice-daily.log"
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
