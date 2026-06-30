#!/usr/bin/env bash
# Backwards-compatible wrapper — use scripts/cron-queue-watchdog-daemon.sh
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/cron-queue-watchdog-daemon.sh" "$@"
