#!/bin/bash
cd /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com
echo ---MISMATCH_LINES---
grep MISMATCH storage/logs/mm-sku-sync-check.log
echo ---FIX_FAIL_LINES---
grep FIX_FAIL storage/logs/mm-sku-sync-check.log
echo ---LATEST_PROGRESS---
grep PROGRESS storage/logs/mm-sku-sync-check.log | tail -n 3
grep SUMMARY storage/logs/mm-sku-sync-check.log || true
pgrep -af mm-run-sku-check.php || echo NO_PROC
