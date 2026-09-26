#!/usr/bin/env bash
# Page-less Dil → SPRICE for channels that still show Dil in the cell
# while DB SPRICE stays stale (same class of bug as eBay 3).
#
# /etc/cron.d/dil-sprice-apply (server TZ Asia/Kolkata):
#   0 4,20 * * *  ... cron-dil-sprice-apply.sh amazon
#   10 4,20 * * * ... cron-dil-sprice-apply.sh newtemuone
#   20 4,20 * * * ... cron-dil-sprice-apply.sh ebay1
#   35 4,20 * * * ... cron-dil-sprice-apply.sh ebay2
#   50 4,20 * * * ... cron-dil-sprice-apply.sh ebay3
#   5 5,21 * * *  ... cron-dil-sprice-apply.sh bestbuy
#   15 5,21 * * * ... cron-dil-sprice-apply.sh aliexpress
#   25 5,21 * * * ... cron-dil-sprice-apply.sh newegg
#   35 5,21 * * * ... cron-dil-sprice-apply.sh temu
#   45 5,21 * * * ... cron-dil-sprice-apply.sh temu2
#   55 5,21 * * * ... cron-dil-sprice-apply.sh temu3
#   5 6,22 * * *  ... cron-dil-sprice-apply.sh reverb
#   15 6,22 * * * ... cron-dil-sprice-apply.sh tiktok
#   25 6,22 * * * ... cron-dil-sprice-apply.sh tiktok2
#   35 6,22 * * * ... cron-dil-sprice-apply.sh doba
#   40 5,21 * * * ... cron-dil-sprice-apply.sh shopify_b2c push
#   45 6,22 * * * ... cron-dil-sprice-apply.sh faire
#   50 5,21 * * * ... cron-dil-sprice-apply.sh macys push
#   55 6,22 * * * ... cron-dil-sprice-apply.sh shein
#   0 6,22 * * *  ... cron-dil-sprice-apply.sh purchasing_power
#   5 7,23 * * *  ... cron-dil-sprice-apply.sh wayfair
#   15 7,23 * * * ... cron-dil-sprice-apply.sh topdawg
#   25 7,23 * * * ... cron-dil-sprice-apply.sh fb_marketplace
#   35 7,23 * * * ... cron-dil-sprice-apply.sh walmart
#   45 7,23 * * * ... cron-dil-sprice-apply.sh pls
#
# Hourly Macys / Purchasing Power and the 13:40 Shopify save stay without "push".
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
PUSH="${2:-}"
case "$MODE" in
  amazon)
    CMD=(amazon:sprc-dil-auto-push)
    ;;
  newtemuone|temu1)
    CMD=(newtemuone:sprc-dil-auto-push)
    MODE=newtemuone
    ;;
  ebay1|ebay2|ebay3)
    CMD=(ebay:rule-sprice-apply "$MODE" --push)
    ;;
  shopify_b2c|shopify-b2c)
    CMD=(shopify-b2c:rule-sprice-apply)
    if [[ "$PUSH" == "push" || "$PUSH" == "--push" ]]; then
      CMD+=(--push)
    fi
    MODE=shopify_b2c
    ;;
  macys|macy)
    CMD=(macys:rule-sprice-apply)
    if [[ "$PUSH" == "push" || "$PUSH" == "--push" ]]; then
      CMD+=(--push)
    fi
    MODE=macys
    ;;
  purchasing_power|pp)
    CMD=(purchasing-power:rule-sprice-apply)
    MODE=purchasing_power
    ;;
  bestbuy|aliexpress|newegg|temu|temu2|temu3|reverb|tiktok|tiktok2|doba|faire|shein|wayfair|topdawg|walmart|pls)
    CMD=(dil:rule-sprice-apply "$MODE" --push)
    ;;
  fb|fb_marketplace)
    CMD=(dil:rule-sprice-apply fb_marketplace)
    MODE=fb_marketplace
    ;;
  *)
    echo "usage: $0 amazon|newtemuone|ebay1|ebay2|ebay3|shopify_b2c|macys|purchasing_power|bestbuy|aliexpress|newegg|temu|temu2|temu3|reverb|tiktok|tiktok2|doba|faire|shein|wayfair|topdawg|fb_marketplace|walmart|pls [push]" >&2
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
