<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AmazonSalesController extends Controller
{
    /** Inclusive calendar days ending yesterday (Pacific), same for badge + grid API + Channel Master */
    public const DAILY_SALES_WINDOW_DAYS = 30;

    /**
     * Per-line PFT / COGS — same math /amazon/daily-sales getData uses on each order row.
     *
     * @return array{unit_price: float, t_weight: float, ship_cost: float, cogs: float, pft_each: float, pft_each_pct: float, pft: float, roi: float, sale_amount: float}
     */
    public static function lineFinancials(float $qty, float $lineRevenue, float $lp, float $ship, float $weightAct): array
    {
        if ($qty <= 0) {
            return [
                'unit_price' => 0.0,
                't_weight' => 0.0,
                'ship_cost' => 0.0,
                'cogs' => 0.0,
                'pft_each' => 0.0,
                'pft_each_pct' => 0.0,
                'pft' => 0.0,
                'roi' => 0.0,
                'sale_amount' => 0.0,
            ];
        }

        $unitPrice = $lineRevenue / $qty;
        $tWeight = $weightAct * $qty;
        $shipCost = ($qty == 1.0 || $tWeight >= 20)
            ? $ship
            : ($ship / max($qty, 1.0));
        $cogs = $lp * $qty;
        $pftEach = ($unitPrice * 0.80) - $lp - $shipCost;
        $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0.0;
        $pft = $pftEach * $qty;
        $roi = $lp > 0 ? ($pftEach / $lp) * 100 : 0.0;

        return [
            'unit_price' => $unitPrice,
            't_weight' => $tWeight,
            'ship_cost' => $shipCost,
            'cogs' => $cogs,
            'pft_each' => $pftEach,
            'pft_each_pct' => $pftEachPct,
            'pft' => $pft,
            'roi' => $roi,
            'sale_amount' => $lineRevenue,
        ];
    }

    /**
     * L30 PFT / COGS / GPFT% / GROI% from the same order lines /amazon/daily-sales
     * sums in its summary badges (sold unit price, not today's list price).
     *
     * @return array{qty: int, line_sales: float, pft: float, cogs: float, gpft: float, groi: float}
     */
    public static function l30OrdersFinancials(): array
    {
        $empty = [
            'qty' => 0,
            'line_sales' => 0.0,
            'pft' => 0.0,
            'cogs' => 0.0,
            'gpft' => 0.0,
            'groi' => 0.0,
        ];

        try {
            if (! Schema::hasTable('amazon_orders') || ! Schema::hasTable('amazon_order_items')) {
                return $empty;
            }

            [$startWindow, $endDate] = AmazonOrder::dailySalesL30Window(self::DAILY_SALES_WINDOW_DAYS);
            $lineRevExpr = AmazonOrder::lineRevenueSelectSql('i');

            $orderRows = AmazonOrder::constrainOrderDate(
                DB::table('amazon_orders as o')
                    ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                    ->where(function ($q) {
                        $q->whereNull('o.status')
                            ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
                    }),
                $startWindow,
                $endDate
            )
                ->select([
                    'i.sku',
                    'i.quantity',
                    DB::raw("({$lineRevExpr}) as line_revenue"),
                ])
                ->get();

            if ($orderRows->isEmpty()) {
                return $empty;
            }

            $skus = $orderRows->pluck('sku')->filter()->unique()->values()->all();
            $productMasters = $skus !== []
                ? ProductMaster::whereIn('sku', $skus)->select(['sku', 'Values'])->get()->keyBy('sku')
                : collect();

            $qty = 0;
            $lineSales = 0.0;
            $pft = 0.0;
            $cogs = 0.0;

            foreach ($orderRows as $row) {
                $sku = trim((string) ($row->sku ?? ''));
                if ($sku === '') {
                    continue;
                }
                $lineQty = (float) ($row->quantity ?? 0);
                if ($lineQty == 0.0) {
                    continue;
                }

                $pm = $productMasters[$row->sku] ?? $productMasters[$sku] ?? null;
                $lp = 0.0;
                $ship = 0.0;
                $weightAct = 0.0;
                if ($pm) {
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : json_decode((string) $pm->Values, true);
                    $lp = floatval($values['lp'] ?? 0);
                    $ship = floatval($values['ship'] ?? 0);
                    $weightAct = floatval($values['wt_act'] ?? 0);
                }

                $fin = self::lineFinancials(
                    $lineQty,
                    (float) ($row->line_revenue ?? 0),
                    $lp,
                    $ship,
                    $weightAct
                );

                $qty += (int) $lineQty;
                // Round per line the same way getData() does before the sales-page JS sums.
                $lineSales += round($fin['sale_amount'], 2);
                $pft += round($fin['pft'], 2);
                $cogs += round($fin['cogs'], 2);
            }

            return [
                'qty' => $qty,
                'line_sales' => $lineSales,
                'pft' => $pft,
                'cogs' => $cogs,
                'gpft' => $lineSales > 0 ? round(($pft / $lineSales) * 100, 2) : 0.0,
                'groi' => $cogs > 0 ? round(($pft / $cogs) * 100, 2) : 0.0,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    public function index()
    {
        $windowDays = self::DAILY_SALES_WINDOW_DAYS;
        $yesterdayPacific = Carbon::yesterday('America/Los_Angeles');
        $endDate = $yesterdayPacific->copy()->endOfDay();
        $startWindow = $yesterdayPacific->copy()->subDays($windowDays - 1)->startOfDay();

        // Total Sales badge: AMAZON_SALES_TOTAL_MODE (default lines = Seller Central "Ordered Product Sales", tax excluded)
        $amazonSalesTotal = AmazonOrder::badgeTotalSalesByOrderDate($startWindow, $endDate);

        // 8 Feb to yesterday (separate badge, fixed start) — same formula as main badge
        $start8Feb = Carbon::createFromDate(now()->year, 2, 8)->startOfDay();
        $daysFrom8Feb = $start8Feb->isFuture() ? 0 : $start8Feb->diffInDays($endDate) + 1;
        $salesFrom8Feb = $start8Feb->isFuture() ? 0.0 : AmazonOrder::badgeTotalSalesByOrderDate($start8Feb, $endDate);

        // Yesterday only — Pacific calendar day (converted to UTC for the UTC-stored
        // order_date column, DST-safe) + product sales (tax excluded) to match Amazon's
        // Seller Central "Sales" tile as closely as possible.
        $yesterdayStartUtc = $yesterdayPacific->copy()->startOfDay()->utc();
        $yesterdayEndUtc = $yesterdayPacific->copy()->endOfDay()->utc();
        $salesYesterday = AmazonOrder::productSalesByOrderDate($yesterdayStartUtc, $yesterdayEndUtc);

        return view('sales.amazon_daily_sales_data', [
            'amazonSalesTotal'        => $amazonSalesTotal,
            'amazonSalesWindowDays'   => $windowDays,
            'amazonSalesTotalMode'    => AmazonOrder::salesTotalMode(),
            'salesFrom8Feb'           => $salesFrom8Feb,
            'daysFrom8Feb'            => $daysFrom8Feb,
            'salesYesterday'          => $salesYesterday,
            'amazonYesterdayLabel'    => $yesterdayPacific->format('M j, Y'),
            'amazonSalesWindowStart'  => $startWindow->copy()->timezone('America/Los_Angeles')->format('M j, Y'),
            'amazonSalesWindowEnd'    => $yesterdayPacific->format('M j, Y'),
        ]);
    }

    public function getData(Request $request)
    {
        // ============================================================
        // Rolling window ending yesterday (Pacific) — same as main badge
        // ============================================================

        $yesterdayPacific = Carbon::yesterday('America/Los_Angeles');
        $endDate = $yesterdayPacific->copy()->endOfDay();
        $startWindow = $yesterdayPacific->copy()->subDays(self::DAILY_SALES_WINDOW_DAYS - 1)->startOfDay();
        $startDateStr = $startWindow->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');

        $lineRevExpr = AmazonOrder::lineRevenueSelectSql('i');
        $orderLinesSum = AmazonOrder::perOrderTotalForBadgeSelectSql('o');

        $orderRows = AmazonOrder::constrainOrderDate(
            DB::table('amazon_orders as o')
                ->leftJoin('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                ->where(function ($q) {
                    $q->whereNull('o.status')
                        ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
                }),
            $startWindow,
            $endDate
        )
            ->select([
                'o.amazon_order_id as order_id',
                'o.order_date',
                'o.status',
                DB::raw("({$orderLinesSum}) as order_total_amount"),
                DB::raw("({$lineRevExpr}) as line_revenue"),
                'o.currency',
                'i.asin',
                'i.sku',
                'i.title',
                'i.quantity',
                'i.price as line_price',
            ])
            ->orderBy('o.order_date')
            ->orderBy('o.amazon_order_id')
            ->get();

        if ($orderRows->isEmpty()) {
            return response()->json([]);
        }

        // Build orderItems-like collection: one row per order line (same shape as before)
        $periodLabel = 'L'.(int) self::DAILY_SALES_WINDOW_DAYS;
        $orderItems = $orderRows->map(function ($row) use ($periodLabel) {
            $qty = (int) ($row->quantity ?? 0);
            $dbPrice = (float) ($row->line_price ?? 0);
            $lineRevenue = (float) ($row->line_revenue ?? ($qty * $dbPrice));

            return (object) [
                'order_id'           => $row->order_id,
                'order_date'         => $row->order_date,
                'status'             => $row->status,
                'order_total_amount' => (float) ($row->order_total_amount ?? 0),
                'total_amount'       => $lineRevenue,
                'currency'           => $row->currency ?? 'USD',
                'period'             => $periodLabel,
                'asin'               => $row->asin ?? '',
                'sku'                => $row->sku ?? '',
                'title'              => $row->title ?? '',
                'quantity'           => $qty,
                'price'              => $dbPrice,
                'line_revenue'       => $lineRevenue,
            ];
        });

        // ============================================================
        // PRODUCT MASTER
        // ============================================================

        $skus = $orderItems->pluck('sku')->filter()->unique()->values()->toArray();
    
        $productMasters = ProductMaster::whereIn('sku', $skus)
            ->select(['sku', 'Values'])
            ->get()
            ->keyBy('sku');

        // ============================================================
        // PROCESS DATA
        // ============================================================
    
        $data = [];
    
        foreach ($orderItems as $item) {
    
            $pm = $productMasters[$item->sku] ?? null;
    
            $lp = 0;
            $ship = 0;
            $weightAct = 0;
    
            if ($pm) {
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : json_decode($pm->Values, true);
    
                $lp = floatval($values['lp'] ?? 0);
                $ship = floatval($values['ship'] ?? 0);
                $weightAct = floatval($values['wt_act'] ?? 0);
            }
    
            $qty = floatval($item->quantity);
    
            $totalPrice = floatval($item->line_revenue ?? ($qty * floatval($item->price)));
            $fin = self::lineFinancials($qty, $totalPrice, $lp, $ship, $weightAct);
    
            $data[] = [
                'order_id' => $item->order_id,
                'asin' => $item->asin,
                'sku' => $item->sku,
                'title' => $item->title,
                'quantity' => $qty,
                'sale_amount' => round($fin['sale_amount'], 2),
                'price' => round($fin['unit_price'], 2),
                'total_amount' => $item->total_amount,
                'order_total_amount' => round((float) ($item->order_total_amount ?? 0), 2),
                'currency' => $item->currency,
                'order_date' => $item->order_date,
                'status' => $item->status,
                'period' => 'L'.(int) self::DAILY_SALES_WINDOW_DAYS,
                'lp' => round($lp, 2),
                'ship' => round($ship, 2),
                't_weight' => round($fin['t_weight'], 2),
                'ship_cost' => round($fin['ship_cost'], 2),
                'cogs' => round($fin['cogs'], 2),
                'pft_each' => round($fin['pft_each'], 2),
                'pft_each_pct' => round($fin['pft_each_pct'], 2),
                'pft' => round($fin['pft'], 2),
                'roi' => round($fin['roi'], 2),
            ];
        }
    
        return response()->json($data);
    }
    

    public function getColumnVisibility(Request $request)
    {
        // Similar to ebay, but for amazon
        $defaultVisibility = [
            'order_id' => true,
            'asin' => true,
            'sku' => true,
            'title' => true,
            'quantity' => true,
            'sale_amount' => true,
            'price' => true,
            'total_amount' => true,
            'order_total_amount' => false,
            'order_date' => true,
            'status' => true,
            'lp' => true,
            'ship' => true,
            't_weight' => true,
            'ship_cost' => true,
            'cogs' => true,
            'pft_each' => true,
            'pft_each_pct' => true,
            'pft' => true,
            'roi' => true,
        ];

        $saved = session('amazon_sales_column_visibility', $defaultVisibility);
        return response()->json($saved);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        session(['amazon_sales_column_visibility' => $visibility]);
        return response()->json(['success' => true]);
    }

    /**
     * Debug endpoint to verify data discrepancy
     */
    public function debugData(Request $request)
    {
        // Use the SAME date calculation as getData() method (Pacific, same window as badge)
        $yesterday = \Carbon\Carbon::yesterday('America/Los_Angeles');
        $endDateCarbon = $yesterday->endOfDay();
        $startDateCarbon = $yesterday->copy()->subDays(self::DAILY_SALES_WINDOW_DAYS - 1)->startOfDay();
        
        $debug = [];
        
        // 1. Date range info
        $latestOrderInDb = DB::table('amazon_orders')->max('order_date');
        $debug['date_ranges'] = [
            'latest_order_in_db' => $latestOrderInDb,
            'yesterday' => $yesterday->format('Y-m-d'),
            'calculated_start' => $startDateCarbon->format('Y-m-d H:i:s'),
            'calculated_end' => $endDateCarbon->format('Y-m-d H:i:s'),
            'amazon_shows' => 'Jan 15, 2026 to Feb 13, 2026 (from your screenshot)',
            'days_difference' => $startDateCarbon->diffInDays($endDateCarbon) + 1,
            'note' => 'Using yesterday as end date to match Amazon (Amazon excludes today)',
        ];
        
        // 2. Total orders in date range (all statuses)
        $totalOrdersAll = DB::table('amazon_orders')
            ->where('order_date', '>=', $startDateCarbon)
            ->where('order_date', '<=', $endDateCarbon)
            ->count();
        $debug['total_orders_all_statuses'] = $totalOrdersAll;
        
        // 3. Orders by status breakdown
        $ordersByStatus = DB::table('amazon_orders')
            ->where('order_date', '>=', $startDateCarbon)
            ->where('order_date', '<=', $endDateCarbon)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
        $debug['orders_by_status'] = $ordersByStatus;
        
        // 4. Non-cancelled orders (correct logic)
        $nonCancelledOrders = DB::table('amazon_orders')
            ->where('order_date', '>=', $startDateCarbon)
            ->where('order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'Canceled');
            })
            ->count();
        $debug['non_cancelled_orders'] = $nonCancelledOrders;
        
        // 5. Orders WITHOUT items (orphaned orders - potential data loss)
        $ordersWithoutItems = DB::table('amazon_orders as o')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
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
        $debug['orders_without_items'] = $ordersWithoutItems;
        
        // 6. Sample orphaned orders
        if ($ordersWithoutItems > 0) {
            $orphanedSample = DB::table('amazon_orders as o')
                ->where('o.order_date', '>=', $startDateCarbon)
                ->where('o.order_date', '<=', $endDateCarbon)
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
                ->limit(10)
                ->get();
            $debug['orphaned_orders_sample'] = $orphanedSample;
        }
        
        // 7. Count order items via join (what getData() returns)
        $itemCount = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('o.status')
                    ->orWhere('o.status', '!=', 'Canceled');
            })
            ->count();
        $debug['total_order_items_joined'] = $itemCount;
        
        // 8. Total quantity from joined items
        $totalQty = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('o.status')
                    ->orWhere('o.status', '!=', 'Canceled');
            })
            ->sum('i.quantity');
        $debug['total_quantity_from_items'] = $totalQty;
        
        // 9. Total sales (same as page badge — AMAZON_SALES_TOTAL_MODE)
        $totalSales = AmazonOrder::badgeTotalSalesByOrderDate($startDateCarbon, $endDateCarbon);
        $debug['total_sales_badge'] = round($totalSales, 2);
        $debug['amazon_sales_total_mode'] = AmazonOrder::salesTotalMode();
        
        // 10. Items with 0 or null price (data quality issue)
        $zeroPriceItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('o.status')
                    ->orWhere('o.status', '!=', 'Canceled');
            })
            ->where(function ($query) {
                $query->where('i.price', '=', 0)
                    ->orWhereNull('i.price');
            })
            ->count();
        $debug['items_with_zero_or_null_price'] = $zeroPriceItems;
        
        // 11. Sample of zero price items
        if ($zeroPriceItems > 0) {
            $sampleZeroPrice = DB::table('amazon_orders as o')
                ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                ->where('o.order_date', '>=', $startDateCarbon)
                ->where('o.order_date', '<=', $endDateCarbon)
                ->where(function ($query) {
                    $query->whereNull('o.status')
                        ->orWhere('o.status', '!=', 'Canceled');
                })
                ->where(function ($query) {
                    $query->where('i.price', '=', 0)
                        ->orWhereNull('i.price');
                })
                ->select('o.amazon_order_id', 'o.order_date', 'i.asin', 'i.sku', 'i.quantity', 'i.price')
                ->limit(10)
                ->get();
            $debug['zero_price_items_sample'] = $sampleZeroPrice;
        }
        
        // 12. Amazon's expected values (from your screenshots)
        $debug['amazon_expected_values'] = [
            'date_range' => 'Jan 15 to Feb 13, 2026',
            'ordered_product_sales' => 142925.00, // $142.9K
            'units_ordered' => 2609,
            'total_order_items' => 2327,
        ];
        
        // 13. What your system currently shows (live)
        $debug['your_system_shows'] = [
            'total_sales' => round($totalSales, 2),
            'total_quantity' => (int) $totalQty,
            'total_orders' => $nonCancelledOrders,
        ];
        
        // 14. Calculate discrepancies
        $debug['discrepancies'] = [
            'missing_sales_amount' => round(142925.00 - $totalSales, 2),
            'missing_sales_percentage' => $totalSales > 0 ? round((142925.00 - $totalSales) / 142925.00 * 100, 1) : 100,
            'missing_units' => 2609 - $totalQty,
            'missing_units_percentage' => $totalQty > 0 ? round((2609 - $totalQty) / 2609 * 100, 1) : 100,
            'missing_order_items' => 2327 - $itemCount,
        ];
        
        // 15. Recommendations
        $recommendations = [];
        if ($ordersWithoutItems > 0) {
            $recommendations[] = "CRITICAL: {$ordersWithoutItems} orders have NO items in amazon_order_items table. Run FetchAmazonOrders command to sync missing items.";
        }
        if ($zeroPriceItems > 0) {
            $recommendations[] = "WARNING: {$zeroPriceItems} items have zero or null price. Check data import process.";
        }
        if ($totalSales < 142925.00 * 0.9) {
            $recommendations[] = "CRITICAL: Sales are more than 10% lower than Amazon's report. Check date range and data completeness.";
        }
        $debug['recommendations'] = $recommendations;
        
        return response()->json($debug, 200, [], JSON_PRETTY_PRINT);
    }
}
