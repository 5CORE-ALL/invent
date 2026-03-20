<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestAmazonSalesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amazon:test-sales-data 
        {--expected=120000 : Expected total sales amount}
        {--days=34 : Number of days to check (default: 34)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Amazon sales data and identify missing dates or data discrepancies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expectedSales = (float) $this->option('expected');
        $days = (int) $this->option('days');
        
        $todayPacific = Carbon::now('America/Los_Angeles');
        $endToday = $todayPacific->copy()->endOfDay();
        $startDate = $todayPacific->copy()->subDays($days - 1)->startOfDay();

        $this->info("=" . str_repeat("=", 80));
        $this->info("Amazon Sales Data Test");
        $this->info("=" . str_repeat("=", 80));
        $this->line("Date Range: {$startDate->format('Y-m-d')} to {$endToday->format('Y-m-d')} ({$days} days)");
        $this->line("Expected Sales: $" . number_format($expectedSales, 2));
        $this->line("");

        // ============================================================
        // 1. TOTAL SALES CALCULATION
        // ============================================================
        $this->info("1. Calculating Total Sales...");
        $totalSales = (float) DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->sum('i.price');

        $this->line("   Total Sales: $" . number_format($totalSales, 2));
        $this->line("   Expected: $" . number_format($expectedSales, 2));
        $difference = $expectedSales - $totalSales;
        $this->line("   Difference: $" . number_format($difference, 2));
        $missingPercentage = $expectedSales > 0 ? round(($difference / $expectedSales) * 100, 2) : 0;
        $this->line("   Missing: {$missingPercentage}%");
        $this->line("");

        // ============================================================
        // 2. DAILY BREAKDOWN
        // ============================================================
        $this->info("2. Analyzing Daily Sales Breakdown...");
        $dailySales = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
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

        // Build complete date range
        $allDates = [];
        $currentDate = $startDate->copy();
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

        $averageDailySales = $totalSales / $days;
        // Define threshold for "very low" sales: 5% of average or $200, whichever is lower
        $lowThreshold = min($averageDailySales * 0.05, 200);
        $this->line("   Average Daily Sales: $" . number_format($averageDailySales, 2));
        $this->line("   Low Sales Threshold: $" . number_format($lowThreshold, 2) . " (5% of average or $200)");
        $this->line("");

        // ============================================================
        // 3. IDENTIFY MISSING DATES
        // ============================================================
        $this->info("3. Identifying Missing or Low Sales Dates...");
        $missingDates = [];
        foreach ($allDates as $date => $data) {
            if (!$data['has_data']) {
                $missingDates[] = [
                    'date' => $date,
                    'issue' => 'NO_DATA',
                    'sales' => 0,
                    'expected_avg' => $averageDailySales,
                ];
            } elseif ($data['sales'] < $lowThreshold) {
                $missingDates[] = [
                    'date' => $date,
                    'issue' => 'VERY_LOW_SALES',
                    'sales' => $data['sales'],
                    'expected_avg' => $averageDailySales,
                    'percentage_of_avg' => round(($data['sales'] / $averageDailySales) * 100, 2),
                    'threshold' => $lowThreshold,
                ];
            }
        }

        if (count($missingDates) > 0) {
            $this->warn("   Found " . count($missingDates) . " dates with issues:");
            $this->line("");
            foreach ($missingDates as $missing) {
                if ($missing['issue'] === 'NO_DATA') {
                    $this->error("   ✗ {$missing['date']}: NO DATA (Expected ~$" . number_format($missing['expected_avg'], 2) . ")");
                } else {
                    $this->warn("   ⚠ {$missing['date']}: Very Low Sales ($" . number_format($missing['sales'], 2) . " - " . $missing['percentage_of_avg'] . "% of average)");
                }
            }
        } else {
            $this->info("   ✓ All dates have data");
        }
        $this->line("");

        // ============================================================
        // 4. DATA QUALITY ISSUES
        // ============================================================
        $this->info("4. Checking Data Quality Issues...");
        
        // Orders without items
        $ordersWithoutItems = DB::table('amazon_orders as o')
            ->where('o.order_date', '>=', $startDate)
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
            $orphanedOrdersTotal = DB::table('amazon_orders as o')
                ->where('o.order_date', '>=', $startDate)
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

            $this->error("   ✗ Found {$ordersWithoutItems} orders WITHOUT items");
            $this->line("     Potential missing sales: $" . number_format((float)$orphanedOrdersTotal, 2));
            $this->line("     Action: Run 'php artisan app:fetch-amazon-orders --fetch-missing-items'");
        } else {
            $this->info("   ✓ All orders have items");
        }

        // Items with zero price
        $zeroPriceItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
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
            $this->warn("   ⚠ Found {$zeroPriceItems} items with zero or null price");
        } else {
            $this->info("   ✓ No items with zero price");
        }
        $this->line("");

        // ============================================================
        // 5. SUMMARY TABLE
        // ============================================================
        $this->info("5. Daily Sales Summary (Top 10 Lowest Days):");
        $this->line("");
        
        // Sort by sales ascending
        usort($allDates, function($a, $b) {
            return $a['sales'] <=> $b['sales'];
        });

        // Use same threshold for table status
        $this->table(
            ['Date', 'Sales', 'Orders', 'Items', 'Quantity', 'Status'],
            array_map(function($day) use ($lowThreshold) {
                $status = !$day['has_data'] ? '❌ NO DATA' : 
                          ($day['sales'] < $lowThreshold ? '⚠ VERY LOW' : '✓ OK');
                return [
                    $day['date'],
                    '$' . number_format($day['sales'], 2),
                    $day['orders'],
                    $day['items'],
                    $day['quantity'],
                    $status,
                ];
            }, array_slice($allDates, 0, 10))
        );
        $this->line("");

        // ============================================================
        // 6. RECOMMENDATIONS
        // ============================================================
        $this->info("6. Recommendations:");
        $this->line("");
        
        if ($totalSales < $expectedSales * 0.9) {
            $this->error("   CRITICAL: Sales are significantly lower than expected!");
            $this->line("   Missing approximately $" . number_format($difference, 2) . " ({$missingPercentage}%)");
        }

        if (count($missingDates) > 0) {
            $this->warn("   Found " . count($missingDates) . " dates with missing or very low sales data.");
            $this->line("   Check if data import failed for these dates.");
        }

        if ($ordersWithoutItems > 0) {
            $this->error("   CRITICAL: {$ordersWithoutItems} orders have no items!");
            $this->line("   Run: php artisan app:fetch-amazon-orders --fetch-missing-items");
        }

        // Check for consecutive missing days (only truly missing or very low - < 5% of average or < $200)
        $consecutiveMissingDays = 0;
        $maxConsecutiveMissing = 0;
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endToday)) {
            $dateStr = $currentDate->format('Y-m-d');
            $data = $allDates[$dateStr] ?? null;
            // Only flag if truly missing or very low (less than 5% of average or less than $200)
            if (!$data || !$data['has_data'] || ($data['has_data'] && $data['sales'] < $lowThreshold)) {
                $consecutiveMissingDays++;
                $maxConsecutiveMissing = max($maxConsecutiveMissing, $consecutiveMissingDays);
            } else {
                $consecutiveMissingDays = 0;
            }
            $currentDate->addDay();
        }

        if ($maxConsecutiveMissing >= 3) {
            $this->warn("   WARNING: Found {$maxConsecutiveMissing} consecutive days with missing/very low data (< $" . number_format($lowThreshold, 2) . ").");
            $this->line("   Possible data import failure period.");
        }

        // Analyze zero-price items impact
        if ($zeroPriceItems > 0) {
            $zeroPriceItemsTotal = DB::table('amazon_orders as o')
                ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                ->where('o.order_date', '>=', $startDate)
                ->where('o.order_date', '<=', $endToday)
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
                })
                ->where(function ($query) {
                    $query->where('i.price', '=', 0)
                          ->orWhereNull('i.price');
                })
                ->select('o.amazon_order_id', 'o.order_date', 'i.sku', 'i.quantity', 'i.price', 'o.total_amount')
                ->get();

            $this->line("");
            $this->info("7. Zero-Price Items Analysis:");
            $this->line("   Found {$zeroPriceItems} items with zero/null price");
            
            // Check if we can estimate missing value from order total
            $ordersWithZeroPriceItems = $zeroPriceItemsTotal->groupBy('amazon_order_id');
            $potentialMissingFromZeroPrice = 0;
            foreach ($ordersWithZeroPriceItems as $orderId => $items) {
                $orderTotal = $items->first()->total_amount ?? 0;
                $zeroPriceItemsInOrder = $items->where('price', 0)->count() + $items->whereNull('price')->count();
                $totalItemsInOrder = $items->count();
                // If all items in order have zero price, use order total
                if ($zeroPriceItemsInOrder == $totalItemsInOrder && $orderTotal > 0) {
                    $potentialMissingFromZeroPrice += $orderTotal;
                }
            }
            
            if ($potentialMissingFromZeroPrice > 0) {
                $this->warn("   Potential missing sales from zero-price items: $" . number_format($potentialMissingFromZeroPrice, 2));
                $this->line("   This could account for " . round(($potentialMissingFromZeroPrice / $difference) * 100, 1) . "% of the missing $" . number_format($difference, 2));
            }
        }

        // ============================================================
        // 8. FINAL SUMMARY
        // ============================================================
        $this->line("");
        $this->info("8. Summary & Next Steps:");
        $this->line("");
        
        $issuesFound = [];
        if ($totalSales < $expectedSales * 0.95) {
            $issuesFound[] = "Sales are " . round($missingPercentage, 1) . "% lower than expected ($" . number_format($difference, 2) . " missing)";
        }
        if (count($missingDates) > 0) {
            $issuesFound[] = count($missingDates) . " dates with missing/very low data";
        }
        if ($zeroPriceItems > 0) {
            $issuesFound[] = "{$zeroPriceItems} items with zero/null price";
        }
        if ($ordersWithoutItems > 0) {
            $issuesFound[] = "{$ordersWithoutItems} orders without items";
        }

        if (count($issuesFound) > 0) {
            $this->warn("   Issues Found:");
            foreach ($issuesFound as $issue) {
                $this->line("   • {$issue}");
            }
            $this->line("");
            $this->info("   Recommended Actions:");
            if ($ordersWithoutItems > 0) {
                $this->line("   1. Run: php artisan app:fetch-amazon-orders --fetch-missing-items");
            }
            if ($zeroPriceItems > 0) {
                $this->line("   2. Run: php artisan app:fetch-amazon-orders --fix-zero-prices");
            }
            if (count($missingDates) > 0) {
                $this->line("   3. Check data import logs for dates: " . implode(", ", array_slice(array_column($missingDates, 'date'), 0, 5)));
            }
            $this->line("   4. Verify Amazon Seller Central reports match your expected sales");
            $this->line("   5. Check if date range in Amazon matches your system (Pacific Time)");
        } else {
            $this->info("   ✓ No major issues found. Small discrepancy may be due to:");
            $this->line("     • Timing differences (Amazon reports may exclude today)");
            $this->line("     • Currency conversion differences");
            $this->line("     • Refunds/returns not yet reflected");
        }

        $this->line("");
        $this->info("=" . str_repeat("=", 80));
        $this->info("Test Complete!");
        $this->info("=" . str_repeat("=", 80));

        return 0;
    }
}
