#!/bin/bash

echo "================================================="
echo "AMAZON SALES - QUICK STATUS CHECK"
echo "================================================="
echo ""

# Calculate expected values
EXPECTED_SALES="109549.17"
EXPECTED_UNITS="2507"
EXPECTED_ITEMS="2227"

echo "Running diagnostic..."
echo ""

# Run the diagnostic and capture output
OUTPUT=$(php check_amazon_sales.php)

# Extract actual values
ACTUAL_SALES=$(echo "$OUTPUT" | grep "Total Sales:" | head -1 | sed 's/.*\$//;s/,//g')
ACTUAL_UNITS=$(echo "$OUTPUT" | grep "Total Quantity:" | head -1 | sed 's/.*: //;s/,//g')
ACTUAL_ITEMS=$(echo "$OUTPUT" | grep "Total Order Items:" | head -1 | sed 's/.*: //;s/,//g')

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 COMPARISON WITH AMAZON SELLER CENTRAL"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
printf "%-25s %-20s %-20s\n" "Metric" "Amazon Shows" "Your System"
echo "─────────────────────────────────────────────────────────────────"
printf "%-25s %-20s %-20s\n" "Ordered Product Sales" "\$$EXPECTED_SALES" "\$$ACTUAL_SALES"
printf "%-25s %-20s %-20s\n" "Units Ordered" "$EXPECTED_UNITS" "$ACTUAL_UNITS"
printf "%-25s %-20s %-20s\n" "Total Order Items" "$EXPECTED_ITEMS" "$ACTUAL_ITEMS"
echo ""

# Calculate differences
if command -v bc &> /dev/null; then
    SALES_DIFF=$(echo "$EXPECTED_SALES - $ACTUAL_SALES" | bc)
    SALES_DIFF_PCT=$(echo "scale=1; ($SALES_DIFF / $EXPECTED_SALES) * 100" | bc)
    
    if (( $(echo "$SALES_DIFF < 100" | bc -l) )); then
        echo "✅ SALES MATCH! Discrepancy: \$$SALES_DIFF ($SALES_DIFF_PCT%)"
    elif (( $(echo "$SALES_DIFF < 1000" | bc -l) )); then
        echo "⚠️  Minor discrepancy: \$$SALES_DIFF ($SALES_DIFF_PCT%)"
    else
        echo "❌ SIGNIFICANT DISCREPANCY: \$$SALES_DIFF ($SALES_DIFF_PCT%)"
        echo ""
        echo "Action needed: Run ./fix_amazon_sync.sh"
    fi
else
    echo "Sales difference: Check diagnostic output above"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📅 RECENT SYNC STATUS (Last 7 Days)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

php artisan app:fetch-amazon-orders --status | grep -A 10 "Date.*Status"

echo ""
echo "================================================="
echo ""
echo "For full diagnostic report, run: php check_amazon_sales.php"
echo "To fix issues, run: ./fix_amazon_sync.sh"
echo ""
