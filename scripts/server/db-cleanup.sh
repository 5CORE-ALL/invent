#!/usr/bin/env bash
# Prunes the tables that grew without limit. Safe to re-run; deletes in small batches so MySQL is not blocked.
#
#   scripts/server/db-cleanup.sh            dry run: only counts what would be deleted
#   scripts/server/db-cleanup.sh --run      deletes
#
# What it removes (older than DAYS, default 14):
#   failed_jobs         2.0 million rows / 10 GB; Laravel keeps them only for debugging
#   mm_webhook_events   13 GB; raw webhook payloads, already processed
# It does NOT touch the `jobs` table: 1.1 million jobs sit on the `default` queue and 300,000 on `mm-ingress` because
# no worker listens to those queues. Decide per job type (see the notes printed at the end).

set -u
DB="${DB:-inventory_5c}"
DAYS="${DAYS:-14}"
BATCH="${BATCH:-5000}"
RUN=0
[ "${1:-}" = "--run" ] && RUN=1

q() { mysql -N -B "$DB" -e "$1"; }

# Find the first id newer than the cutoff by binary search on the primary key (the date column has no index).
boundary_id() {
  local table="$1" col="$2" lo hi mid ts
  lo=$(q "SELECT COALESCE(MIN(id),0) FROM $table")
  hi=$(q "SELECT COALESCE(MAX(id),0) FROM $table")
  [ "$hi" -eq 0 ] && { echo 0; return; }
  while [ $((hi - lo)) -gt 1000 ]; do
    mid=$(( (lo + hi) / 2 ))
    ts=$(q "SELECT UNIX_TIMESTAMP($col) FROM $table WHERE id >= $mid ORDER BY id LIMIT 1")
    if [ -z "$ts" ] || [ "$ts" = "NULL" ]; then hi=$mid; continue; fi
    if [ "$ts" -lt "$(date -d "-$DAYS days" +%s)" ]; then lo=$mid; else hi=$mid; fi
  done
  echo "$lo"
}

prune() {
  local table="$1" col="$2" cutoff total
  cutoff=$(boundary_id "$table" "$col")
  total=$(q "SELECT COUNT(*) FROM $table WHERE id < $cutoff")
  echo "$table: $total rows older than $DAYS days (id < $cutoff)"
  [ "$RUN" -eq 1 ] || return
  while :; do
    n=$(q "DELETE FROM $table WHERE id < $cutoff LIMIT $BATCH; SELECT ROW_COUNT();" | tail -1)
    echo "  deleted $n"
    [ "${n:-0}" -lt "$BATCH" ] && break
    sleep 0.3
  done
}

prune failed_jobs failed_at
prune mm_webhook_events created_at

echo
echo "Disk space is only returned to the OS after: OPTIMIZE TABLE failed_jobs; OPTIMIZE TABLE mm_webhook_events;"
echo "(run it in a quiet hour; it rebuilds the table)."
echo
echo "Queues nobody works on (jobs table):"
mysql "$DB" -e "SELECT queue, COUNT(*) AS waiting, FROM_UNIXTIME(MIN(created_at)) AS oldest FROM jobs GROUP BY queue ORDER BY waiting DESC LIMIT 6;"
