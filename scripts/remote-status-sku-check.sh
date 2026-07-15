#!/bin/bash
cd /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com
pgrep -af mm-run-sku-check.php || echo NO_PROC
echo ---TAIL_LOG---
tail -n 25 storage/logs/mm-sku-sync-check.log || echo NO_LOG
echo ---GREP_SUMMARY---
grep -E 'SUMMARY|PROGRESS|DONE' storage/logs/mm-sku-sync-check.log | tail -n 20
echo ---MISMATCH_COUNT---
grep -c MISMATCH storage/logs/mm-sku-sync-check.log || true
echo ---FIXED_COUNT---
grep -c '^\[.*\] FIXED ' storage/logs/mm-sku-sync-check.log || true
