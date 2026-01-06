<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use App\Models\WalmartCampaignReport;
use App\Models\WalmartDailyData;

class WalmartSalesController extends Controller
{
    public function index()
    {
        return view('sales.walmart_daily_sales_data');
    }

    public function getData(Request $request)
    {
        try {
            // ============================================================
            // DATA SOURCE: WalmartDailyData Table (Walmart Orders)
            // ============================================================
            // This fetches last 30 days data from walmart_daily_data table
            // Similar to Amazon implementation but using Walmart data source
            // ============================================================

            // Get latest order date from WalmartDailyData
            $latestDate = WalmartDailyData::max('order_date');

            if (!$latestDate) {
                return response()->json([
                    'data' => [],
                    'message' => 'No Walmart data found in walmart_daily_data table'
                ]);
            }

            $latestDateCarbon = Carbon::parse($latestDate);
            $startDate = $latestDateCarbon->copy()->subDays(32); // 33 days total for better accuracy
            $endDate = $latestDateCarbon->endOfDay();

            // Get Walmart percentage from database (default 80%)
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
            $percentage = $marketplaceData ? $marketplaceData->percentage : 80;
            $margin = $percentage / 100; // Convert to decimal

            // Fetch Walmart orders from WalmartDailyData with L30 period filter (similar to Amazon approach)
            $orderItems = WalmartDailyData::where('period', 'l30')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->where('fulfillment_option', 'DELIVERY')
                ->where('status', '!=', 'Cancelled')  // Only exclude Cancelled orders
                ->select([
                    DB::raw("COALESCE(customer_order_id, purchase_order_id, CONCAT('WM-', id)) as order_id"),
                    'order_date',
                    'status',
                    'sku',
                    'product_name as title',
                    'quantity',
                    'unit_price as price',
                    'currency'
                ])
                ->orderBy('order_date', 'desc')
                ->get();

            if ($orderItems->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No Walmart orders found in date range'
                ]);
            }

            // Get all unique SKUs
            $skus = $orderItems->pluck('sku')->filter()->unique()->values()->toArray();

            // Fetch ProductMaster data with UPPERCASE keys (similar to Amazon approach)
            $productMasters = ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });

            // Fetch ad spend data from WalmartCampaignReport (L30)
            $normalizeSku = fn($sku) => strtoupper(trim($sku));
            $walmartCampaignReportsL30 = WalmartCampaignReport::where('report_range', 'L30')
                ->where('status', 'Live')
                ->whereIn('campaignName', $skus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->campaignName));

            // Process data (similar to Amazon logic)
            $data = [];
            foreach ($orderItems as $item) {
                $sku = trim($item->sku ?? '');
                $quantity = floatval($item->quantity ?? 1);
                $unitPrice = floatval($item->price ?? 0);
                $saleAmount = $unitPrice * $quantity; // Total sale amount

                // Get ProductMaster data using UPPERCASE SKU
                $pm = $productMasters->get(strtoupper($sku));
                
                // Extract LP, Ship, and Weight from Values JSON
                $lp = 0;
                $ship = 0;
                $weightAct = 0;
                
                if ($pm) {
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    if (is_array($values)) {
                        foreach ($values as $key => $value) {
                            if (strtolower($key) === 'lp') {
                                $lp = floatval($value);
                            } elseif (strtolower($key) === 'ship') {
                                $ship = floatval($value);
                            } elseif (strtolower($key) === 'wt_act' || strtolower($key) === 'weight_act') {
                                $weightAct = floatval($value);
                            }
                        }
                    }
                    
                    // Fallback to direct properties if needed
                    if ($lp === 0 && isset($pm->lp)) $lp = floatval($pm->lp);
                    if ($ship === 0 && isset($pm->ship)) $ship = floatval($pm->ship);
                }

                // Calculate Ship Cost (Amazon-like logic)
                $tWeight = $weightAct * $quantity;
                $shipCost = ($quantity == 1) ? $ship : (($quantity > 1 && $tWeight < 20) ? ($ship / $quantity) : $ship);

                // COGS = LP × Quantity
                $cogs = $lp * $quantity;

                // PFT Each = (Unit Price × Margin) - LP - Ship Cost
                $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
                
                // PFT Each % = (PFT Each / Unit Price) × 100
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                // T PFT = PFT Each × Quantity
                $pft = $pftEach * $quantity;

                // ROI = (PFT Each / LP) × 100
                $roi = $lp > 0 ? ($pftEach / $lp) * 100 : 0;

                // Get ad spend for this SKU
                $normalizedSku = $normalizeSku($sku);
                $kwSpend = 0;
                $pmtSpend = 0; // Walmart doesn't have PMT spend
                
                if (isset($walmartCampaignReportsL30[$normalizedSku])) {
                    $campaign = $walmartCampaignReportsL30[$normalizedSku];
                    $kwSpend = floatval($campaign->spend ?? 0);
                }

                $data[] = [
                    'order_id' => $item->order_id,
                    'order_date' => $item->order_date,
                    'sku' => $sku,
                    'title' => $item->title ?? '',
                    'quantity' => $quantity,
                    'sale_amount' => round($saleAmount, 2),
                    'price' => round($unitPrice, 2),
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'ship_cost' => round($shipCost, 2),
                    'cogs' => round($cogs, 2),
                    'pft_each' => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    't_pft' => round($pft, 2),
                    'roi' => round($roi, 2),
                    'margin' => round($margin * 100, 2),
                    'kw_spend' => round($kwSpend, 2),
                    'pmt_spend' => round($pmtSpend, 2),
                    'hl_spend' => 0, // No HL spend for Walmart
                    'order_status' => $item->status ?? 'Unknown',
                    'currency' => $item->currency ?? 'USD',
                    'period' => 'L30'
                ];
            }

            return response()->json(['data' => $data]);

        } catch (\Exception $e) {
            \Log::error('Walmart Sales Data Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error fetching data: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    public function getColumnVisibility()
    {
        $visibility = Cache::get('walmart_sales_column_visibility', '{}');
        return response()->json(['visibility' => $visibility]);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility');
        Cache::put('walmart_sales_column_visibility', $visibility, now()->addYears(1));
        return response()->json(['success' => true]);
    }
}

