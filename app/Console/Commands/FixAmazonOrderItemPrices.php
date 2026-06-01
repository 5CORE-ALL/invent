<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixAmazonOrderItemPrices extends Command
{
    protected $signature = 'amazon:fix-item-prices 
        {--days=34 : Number of days to check}
        {--fix : Actually apply fixes}
        {--threshold=0.01 : Minimum difference to fix (default: $0.01)}';

    protected $description = 'Fix Amazon order item prices to match order totals';

    public function handle()
    {
        $days = (int) $this->option('days');
        $shouldFix = $this->option('fix');
        $threshold = (float) $this->option('threshold');

        $todayPacific = Carbon::now('America/Los_Angeles');
        $endToday = $todayPacific->copy()->endOfDay();
        $startDate = $todayPacific->copy()->subDays($days - 1)->startOfDay();

        $this->info("=" . str_repeat("=", 80));
        $this->info("Fix Amazon Order Item Prices");
        $this->info("=" . str_repeat("=", 80));
        $this->line("Date Range: {$startDate->format('Y-m-d')} to {$endToday->format('Y-m-d')}");
        $this->line("Mode: " . ($shouldFix ? "FIX MODE" : "CHECK MODE"));
        $this->line("");

        // Find orders where item total doesn't match order total
        $ordersToFix = DB::table('amazon_orders as o')
            ->leftJoin('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->select(
                'o.id as order_id',
                'o.amazon_order_id',
                'o.total_amount as order_total',
                DB::raw('COALESCE(SUM(i.price), 0) as items_total'),
                DB::raw('COUNT(i.id) as item_count'),
                DB::raw('ABS(o.total_amount - COALESCE(SUM(i.price), 0)) as difference')
            )
            ->groupBy('o.id', 'o.amazon_order_id', 'o.total_amount')
            ->havingRaw("ABS(order_total - items_total) >= ?", [$threshold])
            ->orderByDesc('difference')
            ->get();

        $this->info("Found {$ordersToFix->count()} orders with price mismatches");
        $this->line("");

        if ($ordersToFix->isEmpty()) {
            $this->info("✓ No orders need fixing");
            return 0;
        }

        // Categorize orders
        $ordersWithoutItems = $ordersToFix->where('item_count', 0);
        $ordersWithItems = $ordersToFix->where('item_count', '>', 0);

        $this->info("Breakdown:");
        $this->line("  • Orders without items: {$ordersWithoutItems->count()}");
        $this->line("  • Orders with items but price mismatch: {$ordersWithItems->count()}");
        $this->line("");

        $totalDifference = $ordersToFix->sum('difference');
        $this->warn("Total missing amount: $" . number_format($totalDifference, 2));
        $this->line("");

        if (!$shouldFix) {
            $this->warn("Run with --fix to apply fixes");
            $this->line("");
            $this->line("Top 10 orders with biggest differences:");
            $this->table(
                ['Order ID', 'Order Total', 'Items Total', 'Difference', 'Item Count'],
                $ordersToFix->take(10)->map(function($order) {
                    return [
                        $order->amazon_order_id,
                        '$' . number_format($order->order_total, 2),
                        '$' . number_format($order->items_total, 2),
                        '$' . number_format($order->difference, 2),
                        $order->item_count,
                    ];
                })->toArray()
            );
            return 0;
        }

        // Apply fixes
        $this->info("Applying fixes...");
        $this->line("");

        $fixedCount = 0;
        $totalFixed = 0;

        foreach ($ordersToFix as $order) {
            $orderId = $order->order_id;
            $difference = $order->order_total - $order->items_total;

            if ($order->item_count == 0) {
                // Order has no items - can't fix automatically
                $this->warn("  ⚠ Order {$order->amazon_order_id} has no items - skipping");
                continue;
            }

            // Get all items for this order
            $items = DB::table('amazon_order_items')
                ->where('amazon_order_id', $orderId)
                ->get();

            if ($items->isEmpty()) {
                continue;
            }

            // Calculate current total
            $currentTotal = $items->sum('price');
            $targetTotal = $order->order_total;

            if ($currentTotal == 0) {
                // All items have zero price - distribute order total
                $pricePerItem = $targetTotal / $items->count();
                foreach ($items as $item) {
                    DB::table('amazon_order_items')
                        ->where('id', $item->id)
                        ->update(['price' => round($pricePerItem, 2)]);
                }
                $fixedCount++;
                $totalFixed += $targetTotal;
                $this->line("  ✓ Fixed order {$order->amazon_order_id}: Distributed $" . number_format($targetTotal, 2) . " across {$items->count()} items");
            } else {
                // Distribute difference proportionally
                $adjustmentFactor = $targetTotal / $currentTotal;
                foreach ($items as $item) {
                    $newPrice = round($item->price * $adjustmentFactor, 2);
                    DB::table('amazon_order_items')
                        ->where('id', $item->id)
                        ->update(['price' => $newPrice]);
                }
                $fixedCount++;
                $totalFixed += $difference;
                $this->line("  ✓ Fixed order {$order->amazon_order_id}: Adjusted by $" . number_format($difference, 2));
            }
        }

        $this->line("");
        $this->info("=" . str_repeat("=", 80));
        $this->info("Fix Complete!");
        $this->info("=" . str_repeat("=", 80));
        $this->line("Fixed {$fixedCount} orders");
        $this->line("Total amount fixed: $" . number_format($totalFixed, 2));

        // Recalculate sales
        $newSales = (float) DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->sum('i.price');

        $this->line("New sales total: $" . number_format($newSales, 2));

        return 0;
    }
}
