#!/usr/bin/env bash
# Clears job queues that nobody works, in small batches so MySQL is not blocked.
#
#   scripts/server/queue-cleanup.sh                dry run: lists what is waiting
#   scripts/server/queue-cleanup.sh --run          deletes the stale `default` queue (Meta Ads sync + cron alert mails)
#   scripts/server/queue-cleanup.sh --run --ingress  also deletes the stale `mm-ingress` queue (see warning)
#
# `default`: ~1.1 million Meta Ads sync jobs, 3+ weeks old. The sync is re-queued on its next run.
# `mm-ingress`: ~300,000 Shopify catalog webhook jobs from 5 Oct. If a worker ran them now, they would replay old
#   product payloads in order and leave the catalog as it was on 5 Oct. Delete them, then run a fresh Shopify catalog
#   sync, instead of replaying.
# Only jobs nobody has reserved are deleted.

set -u
DB="${DB:-inventory_5c}"
BATCH="${BATCH:-10000}"
RUN=0; INGRESS=0
for a in "$@"; do
  [ "$a" = "--run" ] && RUN=1
  [ "$a" = "--ingress" ] && INGRESS=1
done

mysql "$DB" -e "SELECT queue, COUNT(*) AS waiting, FROM_UNIXTIME(MIN(created_at)) AS oldest, SUM(reserved_at IS NOT NULL) AS being_worked FROM jobs GROUP BY queue ORDER BY waiting DESC LIMIT 8;"
[ "$RUN" -eq 1 ] || { echo "Dry run. Add --run to delete."; exit 0; }

purge() {
  local queue="$1" n total=0
  while :; do
    n=$(mysql -N -B "$DB" -e "DELETE FROM jobs WHERE queue='$queue' AND reserved_at IS NULL LIMIT $BATCH; SELECT ROW_COUNT();" | tail -1)
    total=$((total + ${n:-0}))
    echo "  $queue: deleted $total"
    [ "${n:-0}" -lt "$BATCH" ] && break
    sleep 0.3
  done
}

purge default
[ "$INGRESS" -eq 1 ] && purge mm-ingress
echo "Done. Space is returned after: OPTIMIZE TABLE jobs;  (quiet hour)"
