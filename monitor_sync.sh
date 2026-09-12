#!/bin/bash

echo "🔄 Amazon Data Sync - Live Monitor"
echo "=================================="
echo ""

while true; do
    clear
    echo "🔄 Amazon Data Sync - Live Monitor"
    echo "=================================="
    echo "Time: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
    
    # Check if sync process is running
    if pgrep -f "fetch-amazon-orders.*resync-last-days" > /dev/null; then
        echo "✅ Sync process is RUNNING"
        echo ""
        
        # Show recent sync activity from database
        echo "📊 Recent Sync Activity:"
        php artisan app:fetch-amazon-orders --status | head -20
        
        echo ""
        echo "⏳ Waiting for sync to complete..."
        echo "   (This may take 10-15 minutes due to API rate limits)"
    else
        echo "✅ Sync process has COMPLETED or STOPPED"
        echo ""
        
        # Show final status
        echo "📊 Final Sync Status:"
        php artisan app:fetch-amazon-orders --status | head -20
        
        echo ""
        echo "Running quick diagnostic..."
        php check_amazon_sales.php | tail -20
        
        echo ""
        echo "=================================="
        echo "Sync monitoring complete!"
        echo "Press Ctrl+C to exit or wait for auto-exit in 5 seconds..."
        sleep 5
        break
    fi
    
    echo ""
    echo "Press Ctrl+C to stop monitoring"
    sleep 10
done
