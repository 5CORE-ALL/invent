<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VerifyAmazonData extends Command
{
    protected $signature = 'verify:amazon-data {--start-date=2026-01-10} {--end-date=2026-02-09}';
    protected $description = 'Verify Amazon order data against Seller Central';

    public function handle()
    {
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        
        $this->info("Verifying Amazon data from {$startDate} to {$endDate}");
        $this->info(str_repeat('=', 70));
        
        // Get latest order date in database
        $latestDate = DB::table('amazon_orders')->max('order_date');
        $this->info("Latest order date in database: {$latestDate}");
        $this->newLine();
        
        // Calculate what the system should use (31 days from latest)
        if ($latestDate) {
            $latestCarbon = Carbon::parse($latestDate);
            $systemStart = $latestCarbon->copy()->subDays(30)->startOfDay();
            $systemEnd = $latestCarbon->endOfDay();
            $this->info("System calculated range (31 days):");
            $this->info("  Start: " . $systemStart->format('Y-m-d H:i:s'));
            $this->info("  End:   " . $systemEnd->format('Y-m-d H:i:s'));
            $this->newLine();
        }
        
        // Check order counts and totals for the specified date range
        $this->info("📊 Data for specified date range ({$startDate} to {$endDate}):");
        $this->info(str_repeat('-', 70));
        
        // Total orders (excluding Canceled)
        $totalOrders = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->where(function($query) {
                $query->where('o.status', '!=', 'Canceled')
                      ->orWhereNull('o.status');
            })
            ->count();
        
        $this->info("Total order items (excluding Canceled): {$totalOrders}");
        
        // Total units
        $totalUnits = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->where(function($query) {
                $query->where('o.status', '!=', 'Canceled')
                      ->orWhereNull('o.status');
            })
            ->sum('i.quantity');
        
        $this->info("Total units ordered: {$totalUnits}");
        
        // Total sales (sum of price field)
        $totalSales = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->where(function($query) {
                $query->where('o.status', '!=', 'Canceled')
                      ->orWhereNull('o.status');
            })
            ->sum('i.price');
        
        $this->info("Total sales (price field): $" . number_format($totalSales, 2));
        $this->newLine();
        
        // Check order status breakdown
        $this->info("📋 Order Status Breakdown:");
        $this->info(str_repeat('-', 70));
        
        $statusBreakdown = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->select('o.status', DB::raw('COUNT(*) as count'), DB::raw('SUM(i.price) as total_sales'))
            ->groupBy('o.status')
            ->orderByDesc('count')
            ->get();
        
        foreach ($statusBreakdown as $status) {
            $statusName = $status->status ?? 'NULL';
            $count = $status->count;
            $sales = number_format($status->total_sales, 2);
            $this->info("  {$statusName}: {$count} items, \${sales}");
        }
        $this->newLine();
        
        // Daily breakdown
        $this->info("📅 Daily Breakdown:");
        $this->info(str_repeat('-', 70));
        
        $dailyData = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"])
            ->where(function($query) {
                $query->where('o.status', '!=', 'Canceled')
                      ->orWhereNull('o.status');
            })
            ->select(
                DB::raw('DATE(o.order_date) as order_date'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(i.quantity) as units'),
                DB::raw('SUM(i.price) as daily_sales')
            )
            ->groupBy(DB::raw('DATE(o.order_date)'))
            ->orderBy('order_date')
            ->get();
        
        $this->table(
            ['Date', 'Orders', 'Units', 'Sales'],
            $dailyData->map(function($row) {
                return [
                    $row->order_date,
                    $row->order_count,
                    $row->units,
                    '$' . number_format($row->daily_sales, 2)
                ];
            })
        );
        
        $this->newLine();
        $this->info("✅ Verification complete!");
        
        // Compare with Amazon Seller Central
        $this->newLine();
        $this->info("🔍 Comparison with Amazon Seller Central:");
        $this->info(str_repeat('-', 70));
        $this->warn("Amazon Seller Central shows: \$122,748.17");
        $this->warn("Your database shows: \$" . number_format($totalSales, 2));
        $difference = 122748.17 - $totalSales;
        $percentDiff = ($difference / 122748.17) * 100;
        $this->warn("Difference: \$" . number_format($difference, 2) . " (" . number_format($percentDiff, 2) . "%)");
        
        if ($totalSales < 122748.17) {
            $this->newLine();
            $this->error("⚠️  Your database has LESS data than Amazon Seller Central!");
            $this->error("Possible reasons:");
            $this->error("  1. Missing orders - data sync incomplete");
            $this->error("  2. Price field doesn't include all fees/adjustments");
            $this->error("  3. Some orders haven't been imported yet");
        }
        
        return 0;
    }
}
