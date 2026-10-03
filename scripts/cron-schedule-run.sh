#!/usr/bin/env bash
# Laravel scheduler. Records the minute cron fired so a slow boot does not
# skip every daily job that was due in that minute.
#
# /var/spool/cron/crontabs/inventory_5c_usr:
#   * * * * * /var/www/.../scripts/cron-schedule-run.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -x /usr/bin/php8.3 ]]; then
  PHP_BIN="${PHP_BIN:-/usr/bin/php8.3}"
else
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
fi

export SCHEDULE_RUN_AT
SCHEDULE_RUN_AT="$(TZ=Asia/Kolkata date '+%Y-%m-%d %H:%M:%S')"

exec "$PHP_BIN" "$ROOT/artisan" schedule:run
