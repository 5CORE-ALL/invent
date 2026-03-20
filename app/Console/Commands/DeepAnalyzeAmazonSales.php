<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ProductMaster;

class DeepAnalyzeAmazonSales extends Command
{
    protected $signature = 'amazon:deep-analyze-sales {--days=34}';
    protected $description = 'Deep analysis of Amazon sales data to find all discrepancies';

    public function handle()
    {
        $days = (int) $this->option('days');
        $todayPacific = Carbon::now('America/Los_Angeles');
        $endToday = $todayPacific->copy()->endOfDay();
        $startDate = $todayPacific->copy()->subDays($days - 1)->startOfDay();

        $this->info("=" . str_repeat("=", 80));
        $this->info("Deep Amazon Sales Analysis");
        $this->info("=" . str_repeat("=", 80));
        $this->line("Date Range: {$startDate->format('Y-m-d')} to {$endToday->format('Y-m-d')}");
        $this->line("");

        // ============================================================
        // 1. CHECK PENDING AND UNSHIPPED ORDERS
        // ============================================================
        $this->info("1. Analyzing Pending and Unshipped Orders...");
        
        $pendingOrders = DB::table('amazon_orders as o')
            ->leftJoin('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
            ->where('o.order_date', '<=', $endToday)
            ->whereIn('o.status', ['Pending', 'Unshipped'])
            ->select(
                'o.id',
                'o.amazon_order_id',
                'o.order_date',
                'o.status',
                'o.total_amount',
                DB::raw('COUNT(i.id) as item_count'),
                DB::raw('SUM(i.price) as items_total')
            )
            ->groupBy('o.id', 'o.amazon_order_id', 'o.order_date', 'o.status', 'o.total_amount')
            ->get();

        $this->line("   Pending/Unshipped Orders: " . $pendingOrders->count());
        
        $ordersWithoutItems = $pendingOrders->where('item_count', 0);
        $ordersWithItems = $pendingOrders->where('item_count', '>', 0);
        
        if ($ordersWithoutItems->count() > 0) {
            $this->warn("   ⚠ {$ordersWithoutItems->count()} orders have NO items");
            $totalMissing = $ordersWithoutItems->sum('total_amount');
            $this->line("     Missing amount: $" . number_format($totalMissing, 2));
            
            // Show sample
            $this->line("     Sample orders without items:");
            foreach ($ordersWithoutItems->take(5) as $order) {
                $this->line("       - {$order->amazon_order_id} ({$order->status}): $" . number_format($order->total_amount, 2));
            }
        }

        $ordersWithLowItems = $ordersWithItems->filter(function($order) {
            return abs($order->items_total - $order->total_amount) > 0.01;
        });

        if ($ordersWithLowItems->count() > 0) {
            $this->warn("   ⚠ {$ordersWithLowItems->count()} orders have items total different from order total");
            $totalDiff = $ordersWithLowItems->sum(function($order) {
                return $order->total_amount - $order->items_total;
            });
            $this->line("     Total difference: $" . number_format($totalDiff, 2));
        }
        $this->line("");

        // ============================================================
        // 2. CHECK UNFIXABLE ZERO-PRICE ITEMS
        // ============================================================
        $this->info("2. Analyzing Unfixable Zero-Price Items...");
        
        $unfixableItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->where(function ($query) {
                $query->where('i.price', '=', 0)->orWhereNull('i.price');
            })
            ->select(
                'i.id',
                'o.amazon_order_id',
                'o.order_date',
                'o.status',
                'o.total_amount as order_total',
                'i.sku',
                'i.asin',
                'i.quantity',
                'i.price',
                DB::raw('(SELECT COUNT(*) FROM amazon_order_items WHERE amazon_order_id = o.id) as total_items_in_order')
            )
            ->get();

        $this->line("   Total zero-price items: " . $unfixableItems->count());

        // Group by order
        $byOrder = $unfixableItems->groupBy('amazon_order_id');
        $potentialFixes = [];

        foreach ($byOrder as $orderId => $items) {
            $orderTotal = $items->first()->order_total ?? 0;
            $zeroPriceCount = $items->count();
            $totalItems = $items->first()->total_items_in_order ?? 0;
            $otherItemsTotal = DB::table('amazon_order_items')
                ->where('amazon_order_id', $items->first()->id ? DB::table('amazon_orders')->where('amazon_order_id', $orderId)->value('id') : null)
                ->whereNotIn('id', $items->pluck('id')->toArray())
                ->sum('price');

            // If we can calculate remaining amount
            if ($orderTotal > 0 && $otherItemsTotal > 0) {
                $remainingForZeroItems = $orderTotal - $otherItemsTotal;
                if ($remainingForZeroItems > 0) {
                    $pricePerItem = $remainingForZeroItems / $zeroPriceCount;
                    foreach ($items as $item) {
                        $potentialFixes[] = [
                            'item_id' => $item->id,
                            'order_id' => $orderId,
                            'sku' => $item->sku,
                            'suggested_price' => round($pricePerItem, 2),
                            'method' => 'remaining_from_order',
                        ];
                    }
                }
            }
        }

        if (count($potentialFixes) > 0) {
            $this->info("   ✓ Found " . count($potentialFixes) . " additional items that can be fixed");
            $totalFixable = array_sum(array_column($potentialFixes, 'suggested_price'));
            $this->line("     Potential recovery: $" . number_format($totalFixable, 2));
        } else {
            $this->warn("   ⚠ No additional fixes found for unfixable items");
            $this->line("     Showing sample unfixable items:");
            foreach ($unfixableItems->take(5) as $item) {
                $this->line("       Order: {$item->amazon_order_id}, SKU: {$item->sku}, Price: " . ($item->price ?? 'NULL'));
            }
        }
        $this->line("");

        // ============================================================
        // 3. COMPARE ORDER TOTALS VS ITEM TOTALS
        // ============================================================
        $this->info("3. Comparing Order Totals vs Item Totals...");
        
        $orderComparison = DB::table('amazon_orders as o')
            ->leftJoin('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->select(
                'o.id',
                'o.amazon_order_id',
                'o.total_amount as order_total',
                DB::raw('COALESCE(SUM(i.price), 0) as items_total'),
                DB::raw('COUNT(i.id) as item_count')
            )
            ->groupBy('o.id', 'o.amazon_order_id', 'o.total_amount')
            ->havingRaw('ABS(order_total - items_total) > 0.01')
            ->get();

        if ($orderComparison->count() > 0) {
            $this->warn("   ⚠ Found {$orderComparison->count()} orders where item total doesn't match order total");
            $totalDifference = $orderComparison->sum(function($order) {
                return $order->order_total - $order->items_total;
            });
            $this->line("     Total difference: $" . number_format($totalDifference, 2));
            
            // Show orders with biggest differences
            $this->line("     Top 5 orders with biggest differences:");
            $topDifferences = $orderComparison->sortByDesc(function($order) {
                return abs($order->order_total - $order->items_total);
            })->take(5);
            
            foreach ($topDifferences as $order) {
                $diff = $order->order_total - $order->items_total;
                $this->line("       {$order->amazon_order_id}: Order Total $" . number_format($order->order_total, 2) . 
                           " vs Items Total $" . number_format($order->items_total, 2) . 
                           " (Diff: $" . number_format($diff, 2) . ")");
            }
        } else {
            $this->info("   ✓ All order totals match item totals");
        }
        $this->line("");

        // ============================================================
        // 4. FINAL SALES CALCULATION
        // ============================================================
        $this->info("4. Final Sales Calculation...");
        
        $currentSales = (float) DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->sum('i.price');

        $orderTotalsSum = (float) DB::table('amazon_orders')
            ->where('order_date', '>=', $startDate)
            ->where('order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'Canceled');
            })
            ->sum('total_amount');

        $this->line("   Sales from item prices: $" . number_format($currentSales, 2));
        $this->line("   Sum of order totals: $" . number_format($orderTotalsSum, 2));
        $this->line("   Difference: $" . number_format($orderTotalsSum - $currentSales, 2));
        $this->line("");

        // ============================================================
        // 5. SUMMARY
        // ============================================================
        $this->info("5. Summary & Recommendations:");
        $this->line("");
        
        $missing = $orderTotalsSum - $currentSales;
        
        if ($missing > 100) {
            $this->error("   CRITICAL: Missing $" . number_format($missing, 2) . " in sales!");
            $this->line("   This could be due to:");
            $this->line("     1. Orders without items (need to fetch items)");
            $this->line("     2. Items with zero/null prices");
            $this->line("     3. Items not properly linked to orders");
            $this->line("");
            $this->line("   Recommended actions:");
            if ($ordersWithoutItems->count() > 0) {
                $this->line("     • Run: php artisan app:fetch-amazon-orders --fetch-missing-items");
            }
            if (count($potentialFixes) > 0) {
                $this->line("     • Fix remaining zero-price items manually");
            }
        } else {
            $this->info("   ✓ Sales calculation looks correct");
        }

        $this->line("");
        $this->info("=" . str_repeat("=", 80));

        return 0;
    }
}
