#!/usr/bin/env bash
# Deprecated: Alibaba jobs now use the shared marketplace-manager queue.
# Kept so existing crontab entries keep working — delegates to the shared worker.
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/cron-marketplace-manager-worker.sh"
