#!/bin/bash
set -e
cd /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com
pkill -f mm-run-sku-check.php || true
nohup env MM_FIX=1 MM_LIMIT=0 php storage/logs/mm-run-sku-check.php > storage/logs/mm-sku-sync-check.out 2>&1 &
sleep 2
pgrep -af mm-run-sku-check.php || echo NO_PROC
echo ---LOG---
tail -n 15 storage/logs/mm-sku-sync-check.log || echo NO_LOG_YET
echo ---OUT---
tail -n 15 storage/logs/mm-sku-sync-check.out || echo NO_OUT_YET
