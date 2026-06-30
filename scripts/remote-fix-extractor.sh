#!/usr/bin/env bash
set -eu

cd /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com

echo "=== Killing stuck google-maps-extractor workers ==="
pkill -9 -f 'artisan queue:work.*--queue=google-maps-extractor' 2>/dev/null || true
sleep 2

echo "=== Fixing storage permissions ==="
chown -R inventory_5c_usr:inventory_5c_usr storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "=== Ensuring site is up ==="
php artisan up 2>/dev/null || true

echo "=== Restarting watchdog and extractor worker ==="
php artisan queue:ensure-watchdog-daemon
php artisan queue:ensure-worker google-maps-extractor

sleep 3

echo "=== WATCHDOG ==="
pgrep -af 'queue:watchdog' || echo "NOT RUNNING"

echo "=== EXTRACTOR WORKER ==="
pgrep -af 'google-maps-extractor' || echo "NOT RUNNING"

echo "=== PENDING JOBS ==="
php artisan tinker --execute='echo DB::table("jobs")->where("queue","google-maps-extractor")->count();'

echo "=== FAILED JOBS (extractor) ==="
php artisan tinker --execute='echo DB::table("failed_jobs")->where("queue","google-maps-extractor")->count();'

echo "=== EXTRACTOR LOG (last 8) ==="
tail -8 storage/logs/google-maps-extractor-worker.log 2>/dev/null || true
