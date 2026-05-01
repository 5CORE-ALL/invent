<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ReverbSalesController extends Controller
{
    /**
     * Show Reverb sales tabulator view
     */
    public function reverbSalesTabulatorView()
    {
        return view('market-places.reverb_sales_tabulator_view');
    }

    /**
     * Get daily sales data from reverb_daily_data table for tabulator
     */
    public function getDailyData(Request $request)
    {
        try {
            // Check if table exists
            if (!Schema::hasTable('reverb_daily_data')) {
                return response()->json(['error' => 'Reverb daily data table not found'], 404);
            }

            // Calculate L30 date range using Pacific time (matching ChannelMasterController)
            $latestRaw = DB::table('reverb_daily_data')->whereNotNull('order_date')->max('order_date');
            if ($latestRaw) {
                $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
                $l30EndDate = $latestPacific->copy()->subDay()->toDateString();
                $l30StartDate = $latestPacific->copy()->subDay()->subDays(29)->toDateString();
            } else {
                // Fallback to simple 30 days if no data
                $l30EndDate = Carbon::now()->toDateString();
                $l30StartDate = Carbon::now()->subDays(30)->toDateString();
            }

            // Fetch L30 data only (last 30 days based on Pacific windows)
            $reverbData = DB::table('reverb_daily_data')
                ->whereNotNull('order_date')
                ->whereBetween('order_date', [$l30StartDate, $l30EndDate])
                ->orderBy('order_date', 'desc')
                ->get();

            Log::info('Reverb daily data fetched', [
                'total_records' => $reverbData->count()
            ]);

            // Get unique SKUs from the data (filter out null/empty values)
            $skus = $reverbData->pluck('sku')
                ->filter(function($sku) {
                    return !empty($sku);
                })
                ->unique()
                ->values()
                ->toArray();

            Log::info('Unique SKUs found', [
                'unique_skus_count' => count($skus)
            ]);

            // Fetch LP and Ship from ProductMaster for these SKUs
            $productMasters = [];
            if (!empty($skus)) {
                $productMasters = ProductMaster::whereIn('sku', $skus)
                    ->get()
                    ->keyBy('sku');
            }

            // Get Reverb marketplace percentage (net revenue after fees)
            $mpRow = MarketplacePercentage::where('marketplace', 'Reverb')->first();
            $percentage = $mpRow !== null ? (float) ($mpRow->percentage ?? 85) : 85.0;
            if ($percentage <= 0) {
                $percentage = 85.0;
            }
            $margin = $percentage / 100.0;

            $data = [];
            foreach ($reverbData as $item) {
                $sku = $item->sku;
                $lp = 0;
                $ship = 0;

                // Get LP and Ship from ProductMaster
                if (!empty($sku) && isset($productMasters[$sku])) {
                    $productMaster = $productMasters[$sku];
                    $values = is_array($productMaster->Values) 
                        ? $productMaster->Values 
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    
                    // Get LP
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($productMaster->lp)) {
                        $lp = floatval($productMaster->lp);
                    }
                    
                    // Get Ship
                    $ship = isset($values["ship"]) 
                        ? floatval($values["ship"]) 
                        : (isset($productMaster->ship) ? floatval($productMaster->ship) : 0);
                }

                // Calculate values
                $quantity = max(1, (int) $item->quantity);
                $productSubtotal = (float) ($item->product_subtotal ?? 0);
                $amount = (float) ($item->amount ?? 0);
                
                // Unit price: prefer product_subtotal, fallback to amount
                $lineTotal = $productSubtotal > 0 ? $productSubtotal : $amount;
                $unitPrice = $lineTotal > 0 ? $lineTotal / $quantity : 0;

                // Fees
                $sellingFee = (float) ($item->selling_fee ?? 0);
                $bumpFee = (float) ($item->bump_fee ?? 0);
                $directCheckoutFee = (float) ($item->direct_checkout_fee ?? 0);
                $totalFees = $sellingFee + $bumpFee + $directCheckoutFee;

                // Calculate PFT Each (per unit) = (unit_price * margin) - lp - ship
                $pftEach = ($unitPrice * $margin) - $lp - $ship;

                // Calculate PFT Each % = (pft_each / unit_price) * 100
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                // Calculate Total PFT = pft_each * quantity
                $tPft = $pftEach * $quantity;

                // COGS = LP * quantity
                $cogs = $lp * $quantity;

                // ROI = (Total PFT / COGS) * 100
                $roi = $cogs > 0 ? ($tPft / $cogs) * 100 : 0;

                $data[] = [
                    'id' => $item->id,
                    'order_number' => $item->order_number,
                    'order_date' => $item->order_date ? Carbon::parse($item->order_date)->format('Y-m-d') : null,
                    'period' => $item->period,
                    'status' => $item->status,
                    'sku' => $item->sku ?? '',
                    'display_sku' => $item->display_sku,
                    'title' => $item->title,
                    'quantity' => $quantity,
                    'unit_price' => round($unitPrice, 2),
                    'product_subtotal' => round($productSubtotal, 2),
                    'amount' => round($amount, 2),
                    'shipping_amount' => round((float)($item->shipping_amount ?? 0), 2),
                    'tax_amount' => round((float)($item->tax_amount ?? 0), 2),
                    'selling_fee' => round($sellingFee, 2),
                    'bump_fee' => round($bumpFee, 2),
                    'direct_checkout_fee' => round($directCheckoutFee, 2),
                    'total_fees' => round($totalFees, 2),
                    'payout_amount' => round((float)($item->payout_amount ?? 0), 2),
                    'buyer_name' => $item->buyer_name,
                    'buyer_email' => $item->buyer_email,
                    'shipping_city' => $item->shipping_city,
                    'shipping_state' => $item->shipping_state,
                    'shipping_country' => $item->shipping_country,
                    'payment_method' => $item->payment_method,
                    'order_type' => $item->order_type,
                    'shipment_status' => $item->shipment_status,
                    'paid_at' => $item->paid_at ? Carbon::parse($item->paid_at)->format('Y-m-d H:i') : null,
                    'shipped_at' => $item->shipped_at ? Carbon::parse($item->shipped_at)->format('Y-m-d H:i') : null,
                    // Calculated fields
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'cogs' => round($cogs, 2),
                    'pft_each' => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    'pft' => round($tPft, 2),
                    'roi' => round($roi, 2),
                    'margin' => (float)$margin,
                ];
            }

            Log::info('Reverb daily data processed', [
                'processed_records' => count($data)
            ]);
            
            return response()->json($data)->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Error fetching Reverb daily data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save column visibility preferences
     */
    public function saveColumnVisibility(Request $request)
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $visibility = $request->input('visibility', []);
            
            Cache::put("reverb_sales_column_visibility_{$userId}", $visibility, now()->addDays(30));
            
            return response()->json([
                'success' => true,
                'message' => 'Column visibility saved'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save preferences'
            ], 500);
        }
    }

    /**
     * Get column visibility preferences
     */
    public function getColumnVisibility()
    {
        $userId = auth()->id() ?? 'guest';
        $visibility = Cache::get("reverb_sales_column_visibility_{$userId}", []);
        
        return response()->json($visibility);
    }
}
