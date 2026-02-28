#!/bin/bash
echo "=== AMAZON BID JOB VERIFICATION ==="
echo "Current time: $(date)"
echo "======================================"

# Define expected run times
declare -A jobs=(
    ["amz-over-kw-bids-update.log"]="02:00"
    ["amz-under-kw-bids-update.log"]="02:30"
    ["amazon-pink-dil-kw-ads.log"]="03:00"
    ["amz-over-pt-bids-update.log"]="04:00"
    ["amz-under-pt-bids-update.log"]="04:30"
    ["amazon-pink-dil-pt-ads.log"]="05:00"
    ["amz-over-hl-bids-update.log"]="06:00"
    ["amz-under-hl-bids-update.log"]="06:30"
    ["amazon-pink-dil-hl-ads.log"]="07:00"
    ["amz-bgt-kw-update.log"]="08:00"
    ["amz-bgt-pt-update.log"]="08:30"
)

for logfile in "${!jobs[@]}"; do
    if [ -f "storage/logs/$logfile" ]; then
        last_run=$(stat -c %y "storage/logs/$logfile" | cut -d. -f1)
        today_entries=$(grep -c "$(date +%Y-%m-%d)" "storage/logs/$logfile")
        
        echo "$logfile (scheduled: ${jobs[$logfile]})"
        echo "  Last run: $last_run"
        echo "  Today's entries: $today_entries"
        
        # Check if it ran successfully
        if grep -q "completed successfully\|status.: 200" "storage/logs/$logfile" 2>/dev/null; then
            echo "  ✅ STATUS: SUCCESS"
        elif [ $today_entries -gt 0 ]; then
            echo "  ⚠️ STATUS: RAN (check for errors)"
        else
            echo "  ❌ STATUS: DID NOT RUN TODAY"
        fi
        echo ""
    else
        echo "$logfile: ❌ LOG FILE MISSING"
    fi
done
