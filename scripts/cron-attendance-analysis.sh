#!/usr/bin/env bash
# Daily attendance analysis — run via cron after work hours (e.g. 11 PM IST)
# Example crontab: 0 23 * * * /path/to/5core/scripts/cron-attendance-analysis.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_DIR"

php artisan attendance:analyze "$@"
