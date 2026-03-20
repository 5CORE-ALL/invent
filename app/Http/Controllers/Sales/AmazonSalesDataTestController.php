<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AmazonSalesDataTestController extends Controller
{
    /**
     * Test endpoint to check Amazon sales data and identify missing dates
     * Access via: /sales/amazon/test-data
     */
    public function testData(Request $request)
    {
        $todayPacific = Carbon::now('America/Los_Angeles');
        $endToday = $todayPacific->copy()->endOfDay();
        $start34 = $todayPacific->copy()->subDays(33)->startOfDay(); // Last 34 days
        
        $results = [
            'date_range' => [
                'start' => $start34->format('Y-m-d H:i:s'),
                'end' => $endToday->format('Y-m-d H:i:s'),
                'days' => 34,
            ],
            'summary' => [],
            'daily_breakdown' => [],
            'missing_dates' => [],
            'data_quality_issues' => [],
            'recommendations' => [],
        ];

        // ============================================================
        // 1. TOTAL SALES CALCULATION (Same as index method)
        // ============================================================
        $totalSales = (float) DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $start34)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->sum('i.price');

        $results['summary']['total_sales'] = round($totalSales, 2);
        $results['summary']['expected_sales'] = 120000; // User mentioned ~1.2 lakh
        $results['summary']['difference'] = round(120000 - $totalSales, 2);
        $results['summary']['missing_percentage'] = $totalSales > 0 
            ? round((120000 - $totalSales) / 120000 * 100, 2) 
            : 100;

        // ============================================================
        // 2. DAILY BREAKDOWN - Sales by date
        // ============================================================
        $dailySales = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $start34)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->selectRaw('
                DATE(o.order_date) as sale_date,
                COUNT(DISTINCT o.amazon_order_id) as order_count,
                COUNT(i.id) as item_count,
                SUM(i.quantity) as total_quantity,
                SUM(i.price) as daily_sales
            ')
            ->groupBy(DB::raw('DATE(o.order_date)'))
            ->orderBy('sale_date')
            ->get();

        // Build complete date range with all dates
        $allDates = [];
        $currentDate = $start34->copy();
        while ($currentDate->lte($endToday)) {
            $dateStr = $currentDate->format('Y-m-d');
            $allDates[$dateStr] = [
                'date' => $dateStr,
                'has_data' => false,
                'sales' => 0,
                'orders' => 0,
                'items' => 0,
                'quantity' => 0,
            ];
            $currentDate->addDay();
        }

        // Fill in actual data
        foreach ($dailySales as $day) {
            $dateStr = $day->sale_date;
            if (isset($allDates[$dateStr])) {
                $allDates[$dateStr]['has_data'] = true;
                $allDates[$dateStr]['sales'] = round((float)$day->daily_sales, 2);
                $allDates[$dateStr]['orders'] = (int)$day->order_count;
                $allDates[$dateStr]['items'] = (int)$day->item_count;
                $allDates[$dateStr]['quantity'] = (int)$day->total_quantity;
            }
        }

        $results['daily_breakdown'] = array_values($allDates);

        // ============================================================
        // 3. IDENTIFY MISSING DATES (dates with no sales or very low sales)
        // ============================================================
        $missingDates = [];
        $averageDailySales = $totalSales / 34; // Average per day
        
        foreach ($allDates as $date => $data) {
            if (!$data['has_data']) {
                $missingDates[] = [
                    'date' => $date,
                    'issue' => 'NO_DATA',
                    'sales' => 0,
                    'expected_avg' => round($averageDailySales, 2),
                ];
            } elseif ($data['sales'] < ($averageDailySales * 0.1)) {
                // Less than 10% of average - suspiciously low
                $missingDates[] = [
                    'date' => $date,
                    'issue' => 'VERY_LOW_SALES',
                    'sales' => $data['sales'],
                    'expected_avg' => round($averageDailySales, 2),
                    'percentage_of_avg' => round(($data['sales'] / $averageDailySales) * 100, 2),
                ];
            }
        }

        $results['missing_dates'] = $missingDates;

        // ============================================================
        // 4. DATA QUALITY ISSUES
        // ============================================================
        $dataQuality = [];

        // 4a. Orders without items (orphaned orders)
        $ordersWithoutItems = DB::table('amazon_orders as o')
            ->where('o.order_date', '>=', $start34)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('amazon_order_items as i')
                      ->whereRaw('i.amazon_order_id = o.id');
            })
            ->count();

        if ($ordersWithoutItems > 0) {
            $dataQuality[] = [
                'issue' => 'ORDERS_WITHOUT_ITEMS',
                'count' => $ordersWithoutItems,
                'description' => "Found {$ordersWithoutItems} orders that have no items in amazon_order_items table. These orders are not included in sales calculations.",
            ];

            // Get sample of orphaned orders
            $orphanedSample = DB::table('amazon_orders as o')
                ->where('o.order_date', '>=', $start34)
                ->where('o.order_date', '<=', $endToday)
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
                })
                ->whereNotExists(function($query) {
                    $query->select(DB::raw(1))
                          ->from('amazon_order_items as i')
                          ->whereRaw('i.amazon_order_id = o.id');
                })
                ->select('o.amazon_order_id', 'o.order_date', 'o.status', 'o.total_amount')
                ->limit(10)
                ->get();

            $dataQuality[count($dataQuality) - 1]['sample_orders'] = $orphanedSample;
        }

        // 4b. Items with zero or null price
        $zeroPriceItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $start34)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->where(function ($query) {
                $query->where('i.price', '=', 0)
                      ->orWhereNull('i.price');
            })
            ->count();

        if ($zeroPriceItems > 0) {
            $dataQuality[] = [
                'issue' => 'ITEMS_WITH_ZERO_PRICE',
                'count' => $zeroPriceItems,
                'description' => "Found {$zeroPriceItems} items with zero or null price. These items are included in sales but contribute $0.",
            ];
        }

        // 4c. Check for orders with status but should be included
        $canceledOrders = DB::table('amazon_orders')
            ->where('order_date', '>=', $start34)
            ->where('order_date', '<=', $endToday)
            ->where('status', '=', 'Canceled')
            ->count();

        $dataQuality[] = [
            'issue' => 'CANCELED_ORDERS_EXCLUDED',
            'count' => $canceledOrders,
            'description' => "Found {$canceledOrders} canceled orders (correctly excluded from sales calculation).",
        ];

        $results['data_quality_issues'] = $dataQuality;

        // ============================================================
        // 5. COMPARISON WITH ORDER TOTALS
        // ============================================================
        $totalOrders = DB::table('amazon_orders')
            ->where('order_date', '>=', $start34)
            ->where('order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'Canceled');
            })
            ->count();

        $totalOrdersWithItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $start34)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->distinct('o.amazon_order_id')
            ->count('o.amazon_order_id');

        $results['summary']['total_orders'] = $totalOrders;
        $results['summary']['orders_with_items'] = $totalOrdersWithItems;
        $results['summary']['orders_without_items'] = $totalOrders - $totalOrdersWithItems;

        // Calculate potential missing sales from orphaned orders
        $orphanedOrdersTotal = DB::table('amazon_orders as o')
            ->where('o.order_date', '>=', $start34)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('amazon_order_items as i')
                      ->whereRaw('i.amazon_order_id = o.id');
            })
            ->sum('o.total_amount');

        $results['summary']['potential_missing_from_orphaned'] = round((float)$orphanedOrdersTotal, 2);

        // ============================================================
        // 6. RECOMMENDATIONS
        // ============================================================
        $recommendations = [];

        if ($totalSales < 120000 * 0.9) {
            $recommendations[] = "CRITICAL: Sales are significantly lower than expected. Missing approximately $" . 
                number_format(120000 - $totalSales, 2) . " (" . 
                round((120000 - $totalSales) / 120000 * 100, 2) . "%)";
        }

        if (count($missingDates) > 0) {
            $recommendations[] = "Found " . count($missingDates) . " dates with missing or very low sales data. Check if data import failed for these dates.";
        }

        if ($ordersWithoutItems > 0) {
            $recommendations[] = "CRITICAL: {$ordersWithoutItems} orders have no items. Run the FetchAmazonOrders command to sync missing order items. This could account for $" . 
                number_format($orphanedOrdersTotal, 2) . " in missing sales.";
        }

        if ($zeroPriceItems > 0) {
            $recommendations[] = "WARNING: {$zeroPriceItems} items have zero or null price. Review data import process.";
        }

        // Check for date gaps
        $consecutiveMissingDays = 0;
        $maxConsecutiveMissing = 0;
        foreach ($allDates as $date => $data) {
            if (!$data['has_data'] || $data['sales'] < ($averageDailySales * 0.1)) {
                $consecutiveMissingDays++;
                $maxConsecutiveMissing = max($maxConsecutiveMissing, $consecutiveMissingDays);
            } else {
                $consecutiveMissingDays = 0;
            }
        }

        if ($maxConsecutiveMissing >= 3) {
            $recommendations[] = "WARNING: Found {$maxConsecutiveMissing} consecutive days with missing/low data. Possible data import failure period.";
        }

        $results['recommendations'] = $recommendations;

        // ============================================================
        // 7. DATE RANGE ANALYSIS
        // ============================================================
        $results['date_analysis'] = [
            'earliest_order_date' => DB::table('amazon_orders')->min('order_date'),
            'latest_order_date' => DB::table('amazon_orders')->max('order_date'),
            'total_orders_in_db' => DB::table('amazon_orders')->count(),
            'total_order_items_in_db' => DB::table('amazon_order_items')->count(),
        ];

        return response()->json($results, 200, [], JSON_PRETTY_PRINT);
    }

    /**
     * Get detailed breakdown for a specific date
     */
    public function getDateDetails(Request $request, $date)
    {
        try {
            $dateCarbon = Carbon::parse($date)->startOfDay();
            $endOfDay = $dateCarbon->copy()->endOfDay();

            $orders = DB::table('amazon_orders as o')
                ->leftJoin('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                ->where('o.order_date', '>=', $dateCarbon)
                ->where('o.order_date', '<=', $endOfDay)
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
                })
                ->select([
                    'o.amazon_order_id',
                    'o.order_date',
                    'o.status',
                    'o.total_amount as order_total',
                    'i.id as item_id',
                    'i.asin',
                    'i.sku',
                    'i.quantity',
                    'i.price as item_price',
                ])
                ->orderBy('o.order_date')
                ->orderBy('o.amazon_order_id')
                ->get();

            $grouped = [];
            foreach ($orders as $row) {
                $orderId = $row->amazon_order_id;
                if (!isset($grouped[$orderId])) {
                    $grouped[$orderId] = [
                        'order_id' => $orderId,
                        'order_date' => $row->order_date,
                        'status' => $row->status,
                        'order_total' => $row->order_total,
                        'items' => [],
                        'has_items' => false,
                    ];
                }
                
                if ($row->item_id) {
                    $grouped[$orderId]['has_items'] = true;
                    $grouped[$orderId]['items'][] = [
                        'asin' => $row->asin,
                        'sku' => $row->sku,
                        'quantity' => $row->quantity,
                        'price' => $row->item_price,
                    ];
                }
            }

            return response()->json([
                'date' => $date,
                'total_orders' => count($grouped),
                'orders_with_items' => count(array_filter($grouped, fn($o) => $o['has_items'])),
                'orders_without_items' => count(array_filter($grouped, fn($o) => !$o['has_items'])),
                'orders' => array_values($grouped),
            ], 200, [], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
