<?php

/**
 * Amazon Sales Data Diagnostic Script
 * Run this from command line: php check_amazon_sales.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "=================================================\n";
echo "AMAZON SALES DATA DIAGNOSTIC\n";
echo "=================================================\n\n";

// Calculate date range (matching AmazonSalesController)
$yesterday = Carbon::yesterday('America/Los_Angeles');
$endDate = $yesterday->endOfDay();
$startDate = $yesterday->copy()->subDays(29)->startOfDay();

echo "1. DATE RANGE CALCULATION\n";
echo "   Today (Pacific Time): " . Carbon::now('America/Los_Angeles')->format('Y-m-d H:i:s T') . "\n";
echo "   Start Date: " . $startDate->format('Y-m-d H:i:s T') . "\n";
echo "   End Date:   " . $endDate->format('Y-m-d H:i:s T') . "\n";
echo "   Days: " . ($startDate->diffInDays($endDate) + 1) . "\n\n";

// Check total orders in date range
$totalOrders = DB::table('amazon_orders')
    ->where('order_date', '>=', $startDate)
    ->where('order_date', '<=', $endDate)
    ->count();

echo "2. ORDERS IN DATE RANGE\n";
echo "   Total Orders (all statuses): " . number_format($totalOrders) . "\n";

// Check orders by status
$ordersByStatus = DB::table('amazon_orders')
    ->where('order_date', '>=', $startDate)
    ->where('order_date', '<=', $endDate)
    ->select('status', DB::raw('COUNT(*) as count'))
    ->groupBy('status')
    ->get();

echo "   Orders by Status:\n";
foreach ($ordersByStatus as $status) {
    $statusName = $status->status ?: '(NULL)';
    echo "     - {$statusName}: " . number_format($status->count) . "\n";
}

// Non-cancelled orders
$nonCancelledOrders = DB::table('amazon_orders')
    ->where('order_date', '>=', $startDate)
    ->where('order_date', '<=', $endDate)
    ->where(function ($query) {
        $query->whereNull('status')
            ->orWhere('status', '!=', 'Canceled');
    })
    ->count();

echo "   Non-Cancelled Orders: " . number_format($nonCancelledOrders) . "\n\n";

// Check for orders without items
$ordersWithoutItems = DB::table('amazon_orders as o')
    ->where('o.order_date', '>=', $startDate)
    ->where('o.order_date', '<=', $endDate)
    ->where(function ($query) {
        $query->whereNull('o.status')
            ->orWhere('o.status', '!=', 'Canceled');
    })
    ->whereNotExists(function($q) {
        $q->select(DB::raw(1))
          ->from('amazon_order_items as i')
          ->whereRaw('i.amazon_order_id = o.id');
    })
    ->count();

echo "3. DATA COMPLETENESS CHECK\n";
echo "   ⚠️ Orders WITHOUT Items: " . number_format($ordersWithoutItems) . "\n";

if ($ordersWithoutItems > 0) {
    echo "   ❌ CRITICAL: {$ordersWithoutItems} orders are missing item data!\n";
    echo "   → Run: php artisan app:fetch-amazon-orders --fetch-missing-items\n\n";
    
    // Show sample of orders without items
    $orphanedSample = DB::table('amazon_orders as o')
        ->where('o.order_date', '>=', $startDate)
        ->where('o.order_date', '<=', $endDate)
        ->where(function ($query) {
            $query->whereNull('o.status')
                ->orWhere('o.status', '!=', 'Canceled');
        })
        ->whereNotExists(function($q) {
            $q->select(DB::raw(1))
              ->from('amazon_order_items as i')
              ->whereRaw('i.amazon_order_id = o.id');
        })
        ->select('o.amazon_order_id', 'o.order_date', 'o.status', 'o.total_amount')
        ->limit(5)
        ->get();
    
    echo "   Sample Orders Without Items:\n";
    foreach ($orphanedSample as $order) {
        echo "     - Order: {$order->amazon_order_id}, Date: {$order->order_date}, Total: \${$order->total_amount}\n";
    }
    echo "\n";
}

// Check order items
$orderItems = DB::table('amazon_orders as o')
    ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
    ->where('o.order_date', '>=', $startDate)
    ->where('o.order_date', '<=', $endDate)
    ->where(function ($query) {
        $query->whereNull('o.status')
            ->orWhere('o.status', '!=', 'Canceled');
    })
    ->where('i.quantity', '>', 0) // Exclude cancelled/returned items
    ->count();

$totalQuantity = DB::table('amazon_orders as o')
    ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
    ->where('o.order_date', '>=', $startDate)
    ->where('o.order_date', '<=', $endDate)
    ->where(function ($query) {
        $query->whereNull('o.status')
            ->orWhere('o.status', '!=', 'Canceled');
    })
    ->where('i.quantity', '>', 0) // Exclude cancelled/returned items
    ->sum('i.quantity');

$totalSales = DB::table('amazon_orders as o')
    ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
    ->where('o.order_date', '>=', $startDate)
    ->where('o.order_date', '<=', $endDate)
    ->where(function ($query) {
        $query->whereNull('o.status')
            ->orWhere('o.status', '!=', 'Canceled');
    })
    ->where('i.quantity', '>', 0) // Exclude cancelled/returned items
    ->sum('i.price');

echo "4. ORDER ITEMS & SALES\n";
echo "   Total Order Items: " . number_format($orderItems) . "\n";
echo "   Total Quantity: " . number_format($totalQuantity) . "\n";
echo "   Total Sales: $" . number_format($totalSales, 2) . "\n\n";

// Check for items with $0 or null price
$zeroPriceItems = DB::table('amazon_orders as o')
    ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
    ->where('o.order_date', '>=', $startDate)
    ->where('o.order_date', '<=', $endDate)
    ->where(function ($query) {
        $query->whereNull('o.status')
            ->orWhere('o.status', '!=', 'Canceled');
    })
    ->where(function ($query) {
        $query->where('i.price', '=', 0)
            ->orWhereNull('i.price');
    })
    ->count();

echo "5. DATA QUALITY CHECK\n";
echo "   Items with \$0 or NULL price: " . number_format($zeroPriceItems) . "\n";

if ($zeroPriceItems > 0) {
    echo "   ⚠️ WARNING: {$zeroPriceItems} items have missing price data!\n";
    echo "   → Run: php artisan app:fetch-amazon-orders --fix-zero-prices\n\n";
    
    // Show sample
    $zeroSample = DB::table('amazon_orders as o')
        ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
        ->where('o.order_date', '>=', $startDate)
        ->where('o.order_date', '<=', $endDate)
        ->where(function ($query) {
            $query->whereNull('o.status')
                ->orWhere('o.status', '!=', 'Canceled');
        })
        ->where(function ($query) {
            $query->where('i.price', '=', 0)
                ->orWhereNull('i.price');
        })
        ->select('o.amazon_order_id', 'i.sku', 'i.asin', 'i.quantity', 'i.price')
        ->limit(5)
        ->get();
    
    echo "   Sample Items with \$0 Price:\n";
    foreach ($zeroSample as $item) {
        echo "     - SKU: {$item->sku}, Qty: {$item->quantity}, Price: \${$item->price}\n";
    }
    echo "\n";
}

// Expected values from Amazon screenshot
echo "6. COMPARISON WITH AMAZON SELLER CENTRAL\n";
echo "   Amazon Shows (from screenshot):\n";
echo "     - Date Range: Jan 15 to Feb 13, 2026\n";
echo "     - Ordered Product Sales: \$109,549.17\n";
echo "     - Units Ordered: 2,507\n";
echo "     - Total Order Items: 2,227\n\n";

echo "   Your System Shows:\n";
echo "     - Date Range: {$startDate->format('M d')} to {$endDate->format('M d, Y')}\n";
echo "     - Total Sales: \$" . number_format($totalSales, 2) . "\n";
echo "     - Total Quantity: " . number_format($totalQuantity) . "\n";
echo "     - Total Order Items: " . number_format($orderItems) . "\n\n";

// Calculate discrepancy
$amazonExpectedSales = 109549.17;
$amazonExpectedUnits = 2507;
$amazonExpectedItems = 2227;

$salesDiff = $amazonExpectedSales - $totalSales;
$salesDiffPct = $totalSales > 0 ? ($salesDiff / $amazonExpectedSales) * 100 : 100;

$unitsDiff = $amazonExpectedUnits - $totalQuantity;
$unitsDiffPct = $totalQuantity > 0 ? ($unitsDiff / $amazonExpectedUnits) * 100 : 100;

$itemsDiff = $amazonExpectedItems - $orderItems;

echo "7. DISCREPANCY ANALYSIS\n";
echo "   Sales Discrepancy:\n";
echo "     - Missing Amount: \$" . number_format($salesDiff, 2) . "\n";
echo "     - Missing Percentage: " . number_format($salesDiffPct, 1) . "%\n\n";

echo "   Units Discrepancy:\n";
echo "     - Missing Units: " . number_format($unitsDiff) . "\n";
echo "     - Missing Percentage: " . number_format($unitsDiffPct, 1) . "%\n\n";

echo "   Order Items Discrepancy:\n";
echo "     - Missing Items: " . number_format($itemsDiff) . "\n\n";

// Recommendations
echo "8. RECOMMENDATIONS\n";
if ($ordersWithoutItems > 0) {
    echo "   ❌ CRITICAL: Run this command to fetch missing order items:\n";
    echo "      php artisan app:fetch-amazon-orders --fetch-missing-items\n\n";
}

if ($zeroPriceItems > 0) {
    echo "   ⚠️ WARNING: Run this command to fix zero-price items:\n";
    echo "      php artisan app:fetch-amazon-orders --fix-zero-prices\n\n";
}

if ($salesDiffPct > 10) {
    echo "   ❌ CRITICAL: Sales are more than 10% lower than Amazon's report!\n";
    echo "      Possible causes:\n";
    echo "      1. Missing order items (orders without line items)\n";
    echo "      2. Missing price components (shipping, gift wrap, discounts)\n";
    echo "      3. Date range mismatch (timezone issues)\n";
    echo "      4. Incomplete data sync\n\n";
    echo "      Run a full re-sync for the last 30 days:\n";
    echo "      php artisan app:fetch-amazon-orders --resync-last-days=30 --with-items\n\n";
}

echo "=================================================\n";
echo "DIAGNOSTIC COMPLETE\n";
echo "=================================================\n";
