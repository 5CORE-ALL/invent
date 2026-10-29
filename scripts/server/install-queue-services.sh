#!/usr/bin/env bash
# One-time, idempotent production setup that makes background processing survive restarts,
# crashes and the 2-hour --max-time exit of workers:
#
#   1. systemd service  invent-queue-watchdog  (Restart=always, starts on boot)
#      -> runs `php artisan queue:watchdog`, which keeps one queue:work per queue alive
#         (all mm-{slug}, mm-{slug}-listings, mm-tracking, mm-ingress, ... see config/queue_workers.php)
#   2. cron entry for the app user running the Laravel scheduler every minute
#      (scripts/cron-schedule-run.sh) - only added when no schedule entry exists
#   3. hands any root-owned workers/logs over to the app user
#
# Run as root from the project directory:   bash scripts/server/install-queue-services.sh
set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root." >&2
  exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APP_USER="$(stat -c %U "$ROOT/artisan")"
if [[ -z "$APP_USER" || "$APP_USER" == "root" ]]; then
  echo "Could not determine the app user from $ROOT/artisan (got '${APP_USER}')." >&2
  exit 1
fi

if [[ -x /usr/bin/php8.3 ]]; then
  PHP_BIN=/usr/bin/php8.3
else
  PHP_BIN=/usr/bin/php
fi

echo "Project : $ROOT"
echo "App user: $APP_USER"
echo "PHP     : $PHP_BIN"

chmod +x "$ROOT"/scripts/*.sh 2>/dev/null || true
sed -i 's/\r$//' "$ROOT"/scripts/*.sh 2>/dev/null || true

# 1. systemd service for the watchdog -------------------------------------------------------------
cat >/etc/systemd/system/invent-queue-watchdog.service <<EOF
[Unit]
Description=Invent queue worker watchdog (keeps every dedicated queue worker alive)
After=network-online.target mysql.service mariadb.service
Wants=network-online.target

[Service]
Type=simple
User=${APP_USER}
WorkingDirectory=${ROOT}
ExecStart=${PHP_BIN} ${ROOT}/artisan queue:watchdog
Restart=always
RestartSec=5
# The watchdog spawns the queue:work processes itself. Never kill them when the watchdog restarts,
# so a deploy/restart of the watchdog does not interrupt jobs that are running.
KillMode=process
SendSIGKILL=no
StandardOutput=append:${ROOT}/storage/logs/queue-watchdog-daemon.log
StandardError=append:${ROOT}/storage/logs/queue-watchdog-daemon.log

[Install]
WantedBy=multi-user.target
EOF

# 2. scheduler cron for the app user -------------------------------------------------------------
CURRENT_CRON="$(crontab -u "$APP_USER" -l 2>/dev/null || true)"
if grep -Eq 'cron-schedule-run\.sh|artisan[[:space:]]+schedule:run' <<<"$CURRENT_CRON"; then
  echo "Scheduler cron already present for $APP_USER - left unchanged."
else
  {
    [[ -n "$CURRENT_CRON" ]] && printf '%s\n' "$CURRENT_CRON"
    echo "* * * * * ${ROOT}/scripts/cron-schedule-run.sh >> ${ROOT}/storage/logs/scheduler.log 2>&1"
  } | crontab -u "$APP_USER" -
  echo "Added scheduler cron for $APP_USER."
fi

# 3. clean up root-owned leftovers -------------------------------------------------------------
# Workers started by root would write root-owned logs/cache files the web user cannot touch.
if pgrep -u root -f 'artisan queue:work' >/dev/null 2>&1; then
  echo "Stopping root-owned queue workers (they finish their current job first)..."
  pkill -TERM -u root -f 'artisan queue:work' || true
  sleep 5
fi
find "$ROOT/storage" "$ROOT/bootstrap/cache" -user root -exec chown "$APP_USER":"$(id -gn "$APP_USER")" {} + 2>/dev/null || true

# 4. enable + start -------------------------------------------------------------------------------
systemctl daemon-reload
systemctl enable invent-queue-watchdog.service
systemctl restart invent-queue-watchdog.service
sleep 8

echo
systemctl --no-pager --lines=0 status invent-queue-watchdog.service | head -n 5 || true
echo
echo "queue:work processes now running: $(pgrep -f 'artisan queue:work' | wc -l)"
echo "Doba worker: $(pgrep -af 'queue:work.*--queue=mm-doba( |$)' | head -n 1)"
echo
echo "Scheduler cron for ${APP_USER}:"
crontab -u "$APP_USER" -l | grep -E 'schedule' || true
