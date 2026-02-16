#!/bin/bash

echo "================================================="
echo "FIXING AMAZON SALES DATA SYNC"
echo "================================================="
echo ""

echo "Step 1: Re-sync failed dates (Feb 3-14, 2026)"
echo "This will fetch orders AND items for the failed days..."
echo ""

php artisan app:fetch-amazon-orders --resync-last-days=14 --with-items

echo ""
echo "Step 2: Fix items with $0 price (44 items found)"
echo ""

php artisan app:fetch-amazon-orders --fix-zero-prices

echo ""
echo "Step 3: Verify sync status"
echo ""

php artisan app:fetch-amazon-orders --status | head -50

echo ""
echo "================================================="
echo "SYNC FIX COMPLETE!"
echo "================================================="
echo ""
echo "Please refresh your Amazon sales page to see updated data."
echo ""
