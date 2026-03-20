<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\ProductMaster;

class FixAmazonSalesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amazon:fix-sales-data 
        {--days=34 : Number of days to check (default: 34)}
        {--fix : Actually fix the issues (without this flag, only shows issues)}
        {--fix-zero-prices : Fix items with zero/null prices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and fix Amazon sales data issues directly in database tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $shouldFix = $this->option('fix');
        $fixZeroPrices = $this->option('fix-zero-prices') || $shouldFix;

        $todayPacific = Carbon::now('America/Los_Angeles');
        $endToday = $todayPacific->copy()->endOfDay();
        $startDate = $todayPacific->copy()->subDays($days - 1)->startOfDay();

        $this->info("=" . str_repeat("=", 80));
        $this->info("Amazon Sales Data Diagnostic & Fix");
        $this->info("=" . str_repeat("=", 80));
        $this->line("Date Range: {$startDate->format('Y-m-d')} to {$endToday->format('Y-m-d')} ({$days} days)");
        $this->line("Mode: " . ($shouldFix ? "FIX MODE (will make changes)" : "CHECK MODE (read-only)"));
        $this->line("");

        $issues = [];
        $fixes = [];

        // ============================================================
        // 1. CHECK ORDERS WITHOUT ITEMS
        // ============================================================
        $this->info("1. Checking for orders without items...");
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
            ->select('o.id', 'o.amazon_order_id', 'o.order_date', 'o.status', 'o.total_amount')
            ->get();

        if ($ordersWithoutItems->count() > 0) {
            $totalMissing = $ordersWithoutItems->sum('total_amount');
            $this->error("   ✗ Found {$ordersWithoutItems->count()} orders without items");
            $this->line("     Potential missing sales: $" . number_format($totalMissing, 2));
            
            $issues[] = [
                'type' => 'orders_without_items',
                'count' => $ordersWithoutItems->count(),
                'missing_amount' => $totalMissing,
                'orders' => $ordersWithoutItems->take(10)->toArray(),
            ];

            if ($shouldFix) {
                $this->warn("     → These orders need items fetched. Run: php artisan app:fetch-amazon-orders --fetch-missing-items");
            }
        } else {
            $this->info("   ✓ All orders have items");
        }
        $this->line("");

        // ============================================================
        // 2. CHECK ITEMS WITH ZERO/NULL PRICE
        // ============================================================
        $this->info("2. Checking for items with zero/null price...");
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
            ->select(
                'i.id as item_id',
                'o.amazon_order_id',
                'o.order_date',
                'i.sku',
                'i.asin',
                'i.quantity',
                'i.price',
                'o.total_amount as order_total'
            )
            ->get();

        if ($zeroPriceItems->count() > 0) {
            $this->warn("   ⚠ Found {$zeroPriceItems->count()} items with zero/null price");
            
            // Group by order to see if we can use order total
            $ordersWithZeroPrice = $zeroPriceItems->groupBy('amazon_order_id');
            $fixableItems = [];
            $unfixableItems = [];

            foreach ($ordersWithZeroPrice as $orderId => $items) {
                $orderTotal = $items->first()->order_total ?? 0;
                $zeroPriceCount = $items->where('price', 0)->count() + $items->whereNull('price')->count();
                $totalItems = $items->count();
                
                // If all items have zero price and order has total, we can distribute
                if ($zeroPriceCount == $totalItems && $orderTotal > 0) {
                    $pricePerItem = $orderTotal / $totalItems;
                    foreach ($items as $item) {
                        $fixableItems[] = [
                            'item_id' => $item->item_id,
                            'order_id' => $orderId,
                            'sku' => $item->sku,
                            'current_price' => $item->price ?? 0,
                            'suggested_price' => round($pricePerItem, 2),
                            'method' => 'distribute_order_total',
                        ];
                    }
                } else {
                    // Try to get price from product_master
                    foreach ($items as $item) {
                        if ($item->sku) {
                            $pm = ProductMaster::where('sku', $item->sku)->first();
                            if ($pm && isset($pm->Values)) {
                                $values = is_array($pm->Values) ? $pm->Values : json_decode($pm->Values, true);
                                $suggestedPrice = $values['price'] ?? $values['lp'] ?? null;
                                if ($suggestedPrice && $suggestedPrice > 0) {
                                    $fixableItems[] = [
                                        'item_id' => $item->item_id,
                                        'order_id' => $orderId,
                                        'sku' => $item->sku,
                                        'current_price' => $item->price ?? 0,
                                        'suggested_price' => round($suggestedPrice, 2),
                                        'method' => 'product_master',
                                    ];
                                } else {
                                    $unfixableItems[] = $item;
                                }
                            } else {
                                $unfixableItems[] = $item;
                            }
                        } else {
                            $unfixableItems[] = $item;
                        }
                    }
                }
            }

            $this->line("     Fixable items: " . count($fixableItems));
            $this->line("     Unfixable items: " . count($unfixableItems));

            $issues[] = [
                'type' => 'zero_price_items',
                'total' => $zeroPriceItems->count(),
                'fixable' => count($fixableItems),
                'unfixable' => count($unfixableItems),
                'fixable_items' => array_slice($fixableItems, 0, 10),
            ];

            // Fix zero prices if requested
            if ($fixZeroPrices && count($fixableItems) > 0) {
                $this->line("");
                $this->info("   Fixing zero-price items...");
                $fixed = 0;
                $totalFixedAmount = 0;

                foreach ($fixableItems as $fixItem) {
                    try {
                        DB::table('amazon_order_items')
                            ->where('id', $fixItem['item_id'])
                            ->update(['price' => $fixItem['suggested_price']]);
                        
                        $fixed++;
                        $totalFixedAmount += $fixItem['suggested_price'];
                    } catch (\Exception $e) {
                        $this->error("     Failed to fix item {$fixItem['item_id']}: " . $e->getMessage());
                    }
                }

                $fixes[] = [
                    'type' => 'zero_price_items',
                    'fixed_count' => $fixed,
                    'total_amount_added' => $totalFixedAmount,
                ];

                $this->info("     ✓ Fixed {$fixed} items, added $" . number_format($totalFixedAmount, 2) . " to sales");
            }
        } else {
            $this->info("   ✓ No items with zero/null price");
        }
        $this->line("");

        // ============================================================
        // 3. CHECK FOR ORDERS WITH STATUS BUT SHOULD BE INCLUDED
        // ============================================================
        $this->info("3. Checking order statuses...");
        $statusBreakdown = DB::table('amazon_orders')
            ->where('order_date', '>=', $startDate)
            ->where('order_date', '<=', $endToday)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('status')
            ->get();

        $this->table(
            ['Status', 'Count', 'Total Amount'],
            $statusBreakdown->map(function($s) {
                return [
                    $s->status ?? 'NULL',
                    $s->count,
                    '$' . number_format($s->total ?? 0, 2),
                ];
            })->toArray()
        );
        $this->line("");

        // ============================================================
        // 4. CALCULATE CURRENT VS EXPECTED SALES
        // ============================================================
        $this->info("4. Calculating current sales...");
        $currentSales = (float) DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDate)
            ->where('o.order_date', '<=', $endToday)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->sum('i.price');

        $this->line("   Current Sales: $" . number_format($currentSales, 2));
        $this->line("");

        // ============================================================
        // 5. SUMMARY
        // ============================================================
        $this->info("5. Summary:");
        $this->line("");

        if (count($issues) > 0) {
            $this->warn("Issues Found:");
            foreach ($issues as $issue) {
                if ($issue['type'] === 'orders_without_items') {
                    $this->line("  • {$issue['count']} orders without items (missing $" . number_format($issue['missing_amount'], 2) . ")");
                } elseif ($issue['type'] === 'zero_price_items') {
                    $this->line("  • {$issue['total']} items with zero/null price");
                    $this->line("    - {$issue['fixable']} can be fixed");
                    $this->line("    - {$issue['unfixable']} need manual review");
                }
            }
        } else {
            $this->info("  ✓ No major issues found");
        }

        if (count($fixes) > 0) {
            $this->line("");
            $this->info("Fixes Applied:");
            foreach ($fixes as $fix) {
                if ($fix['type'] === 'zero_price_items') {
                    $this->line("  ✓ Fixed {$fix['fixed_count']} zero-price items");
                    $this->line("    Added $" . number_format($fix['total_amount_added'], 2) . " to sales");
                }
            }

            // Recalculate sales after fixes
            $newSales = (float) DB::table('amazon_orders as o')
                ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                ->where('o.order_date', '>=', $startDate)
                ->where('o.order_date', '<=', $endToday)
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
                })
                ->sum('i.price');

            $this->line("");
            $this->info("Updated Sales: $" . number_format($newSales, 2));
            $this->line("Increase: $" . number_format($newSales - $currentSales, 2));
        }

        $this->line("");
        $this->info("=" . str_repeat("=", 80));
        $this->info("Diagnostic Complete!");
        $this->info("=" . str_repeat("=", 80));

        if (!$shouldFix && count($issues) > 0) {
            $this->line("");
            $this->warn("To apply fixes, run with --fix flag:");
            $this->line("  php artisan amazon:fix-sales-data --fix");
        }

        return 0;
    }
}
