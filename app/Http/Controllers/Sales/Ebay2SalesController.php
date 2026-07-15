<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Models\Ebay2Order;
use App\Models\ProductMaster;
use App\Models\ChannelMasterCalculatedData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Ebay2SalesController extends Controller
{
    public function index()
    {
        // Yesterday's sales (Pacific) from real orders — same per-order total and
        // exclusions the Total Sales badge uses (pricingSummary.total + collect-and-remit
        // tax; skip CANCELED and FULLY_REFUNDED). Mirrors Amazon's "Y Sales" badge.
        $tz = 'America/Los_Angeles';
        $yesterday = \Carbon\Carbon::yesterday($tz)->toDateString();
        $ySales = 0.0;
        try {
            Ebay2Order::where('period', 'l30')->get()->each(function ($order) use (&$ySales, $tz, $yesterday) {
                $raw = is_array($order->raw_data) ? $order->raw_data : json_decode((string) $order->raw_data, true);
                if (!is_array($raw)) return;
                $cs = $raw['cancelStatus']['cancelState'] ?? '';
                $ps = $raw['orderPaymentStatus'] ?? '';
                if ($cs === 'CANCELED' || $ps === 'FULLY_REFUNDED') return;
                $created = $raw['creationDate'] ?? $order->order_date;
                if (!$created) return;
                if (\Carbon\Carbon::parse($created)->setTimezone($tz)->toDateString() !== $yesterday) return;
                $base = (float) ($raw['pricingSummary']['total']['value'] ?? 0);
                $carTax = 0.0;
                foreach (($raw['lineItems'] ?? []) as $li) {
                    foreach (($li['ebayCollectAndRemitTaxes'] ?? []) as $t) {
                        $carTax += (float) ($t['amount']['value'] ?? 0);
                    }
                }
                $ySales += round($base + $carTax, 2);
            });
        } catch (\Throwable $e) {
            Log::warning('Ebay2 Y Sales failed: ' . $e->getMessage());
        }

        // Ads% / TACOS + Total Ad Spend — same ChannelMasterCalculatedData values the
        // EbayTwo Ads% column on /all-marketplace-master uses (channel key is "EbayTwo").
        $ebay2Row = ChannelMasterCalculatedData::where('channel', 'EbayTwo')->first()
            ?? ChannelMasterCalculatedData::where('channel', 'eBay 2')->first()
            ?? ChannelMasterCalculatedData::where('channel', 'like', 'EbayTwo%')->first()
            ?? ChannelMasterCalculatedData::where('channel', 'like', 'eBay 2%')->first();
        $ebay2AdsPercent = (float) ($ebay2Row->ads_percentage ?? 0);
        $ebay2TotalAdSpend = (float) ($ebay2Row->total_ad_spend ?? 0);

        // KW / PMT badges — same live campaign-ads breakdown the master EbayTwo row uses.
        $kwSpent = 0.0;
        $pmtSpent = 0.0;
        $liveTotalAdSpend = 0.0;
        try {
            $breakdown = app(ChannelMasterController::class)->getEbaytwoMasterAdBreakdown();
            $kwSpent = (float) ($breakdown['kw_spent'] ?? 0);
            $pmtSpent = (float) ($breakdown['pmt_spent'] ?? 0);
            $liveTotalAdSpend = (float) ($breakdown['total_ad_spend'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning('Ebay2 daily-sales ad spend lookup failed: '.$e->getMessage());
        }

        if ($ebay2TotalAdSpend <= 0 && $liveTotalAdSpend > 0) {
            $ebay2TotalAdSpend = $liveTotalAdSpend;
        }
        if ($ebay2AdsPercent <= 0 && $ebay2TotalAdSpend > 0) {
            $masterL30 = (float) ($ebay2Row->l30_sales ?? 0);
            if ($masterL30 <= 0) {
                // Same sales basis getEbaytwoMasterAdsPercent / getEbaytwoChannelData use.
                $ebay2AdsPercent = (float) app(ChannelMasterController::class)->getEbaytwoMasterAdsPercent();
            } else {
                $ebay2AdsPercent = ($ebay2TotalAdSpend / $masterL30) * 100;
            }
        }

        return view('sales.ebay2_daily_sales_data', [
            'salesYesterday' => round($ySales, 2),
            'yesterdayLabel' => \Carbon\Carbon::yesterday($tz)->format('M j, Y'),
            'kwSpent' => round($kwSpent, 2),
            'pmtSpent' => round($pmtSpent, 2),
            'ebay2AdsPercent' => round($ebay2AdsPercent, 2),
            'ebay2TotalAdSpend' => round($ebay2TotalAdSpend, 2),
        ]);
    }

    public function getData(Request $request)
    {
        \Log::info('Ebay2SalesController getData called');

        $orders = Ebay2Order::with('items')
            ->where('period', 'l30')
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' eBay 2 orders');

        // Get unique SKUs
        $skus = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                // For OPEN BOX or USED items, extract the base SKU
                $baseSku = $item->sku;
                if (stripos($item->sku, 'OPEN BOX') !== false) {
                    $baseSku = trim(str_ireplace('OPEN BOX', '', $item->sku));
                } elseif (stripos($item->sku, 'USED') !== false) {
                    $baseSku = trim(str_ireplace('USED', '', $item->sku));
                }
                $skus[] = $baseSku;
            }
        }
        $skus = array_unique($skus);

        // Fetch ProductMaster data for LP and Ship (case-insensitive matching)
        // Build case-insensitive query for better performance
        $skuLowerMap = [];
        foreach ($skus as $sku) {
            $skuLowerMap[strtolower($sku)] = $sku;
        }
        
        $productMastersRaw = ProductMaster::whereRaw('LOWER(sku) IN (' . implode(',', array_fill(0, count($skuLowerMap), '?')) . ')', array_keys($skuLowerMap))->get();
        
        // Key by original order SKU (preserving order SKU case)
        $productMasters = collect();
        foreach ($productMastersRaw as $pm) {
            $pmSkuLower = strtolower($pm->sku);
            if (isset($skuLowerMap[$pmSkuLower])) {
                $productMasters[$skuLowerMap[$pmSkuLower]] = $pm;
            }
        }

        $data = [];
        foreach ($orders as $order) {
            $raw = is_array($order->raw_data) ? $order->raw_data : json_decode((string) $order->raw_data, true);

            // eBay's "Total sales" only counts orders that resulted in a completed sale.
            // It excludes:
            //   - CANCELED orders (buyer/seller cancelled — the order never happened)
            //   - FULLY_REFUNDED orders (payment fully reversed — no net sale)
            // Partially refunded orders are kept (goods partly retained = still a sale).
            if (is_array($raw)) {
                $cancelState = $raw['cancelStatus']['cancelState'] ?? '';
                $paymentStatus = $raw['orderPaymentStatus'] ?? '';
                if ($cancelState === 'CANCELED' || $paymentStatus === 'FULLY_REFUNDED') {
                    continue;
                }
            }

            // eBay "Total sales (includes taxes)" per order = pricingSummary.total (buyer-paid,
            // after discounts) + eBay collect-and-remit tax (reported per line item, not in
            // pricingSummary). The stored total_amount column is stale (0), so compute from raw_data.
            $orderTotal = (float) ($order->total_amount ?? 0);
            if (is_array($raw)) {
                $base = (float) ($raw['pricingSummary']['total']['value'] ?? 0);
                $carTax = 0.0;
                foreach (($raw['lineItems'] ?? []) as $li) {
                    foreach (($li['ebayCollectAndRemitTaxes'] ?? []) as $t) {
                        $carTax += (float) ($t['amount']['value'] ?? 0);
                    }
                }
                $computed = $base + $carTax;
                if ($computed > 0) {
                    $orderTotal = $computed;
                }
            }
            $orderTotal = round($orderTotal, 2);

            foreach ($order->items as $item) {
                // For OPEN BOX or USED items, use the base SKU to get ProductMaster data
                $lookupSku = $item->sku;
                if (stripos($item->sku, 'OPEN BOX') !== false) {
                    $lookupSku = trim(str_ireplace('OPEN BOX', '', $item->sku));
                } elseif (stripos($item->sku, 'USED') !== false) {
                    $lookupSku = trim(str_ireplace('USED', '', $item->sku));
                }
                
                $pm = $productMasters[$lookupSku] ?? null;

                // Extract LP, Ship, and Weight Act
                $lp = 0;
                $ship = 0;
                $weightAct = 0;
                if ($pm) {
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    $lp = 0;
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    // Same normal ship as eBay 1 (Values['ship']), not ebay2_ship
                    $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
                    $weightAct = isset($values["wt_act"]) ? floatval($values["wt_act"]) : 0;
                }

                $quantity = floatval($item->quantity);
                $price = floatval($item->price);

                // T Weight = Weight Act * Quantity
                $tWeight = $weightAct * $quantity;

                // Ship Cost — same rules as eBay 1
                if ($quantity == 1) {
                    $shipCost = $ship;
                } elseif ($quantity > 1 && $tWeight < 20) {
                    $shipCost = $ship / $quantity;
                } else {
                    $shipCost = $ship;
                }

                // COGS = LP * quantity
                $cogs = $lp * $quantity;

                // PFT Each = (unit_price * 0.85) - lp - ship_cost (eBay 2 uses 85% margin)
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;
                $pftEach = ($unitPrice * 0.85) - $lp - $shipCost;

                // PFT Each % = (pft_each / unit_price) * 100
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                // T PFT = pft_each * quantity
                $pft = $pftEach * $quantity;

                // ROI = (PFT / LP) * 100 (same as eBay 1)
                $roi = $lp > 0 ? ($pft / $lp) * 100 : 0;

                $data[] = [
                    'order_id' => $order->ebay_order_id,
                    'item_id' => $item->item_id,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'sale_amount' => round($price, 2),
                    'price' => round($unitPrice, 2),
                    'total_amount' => $orderTotal,
                    'currency' => $order->currency,
                    'order_date' => \Carbon\Carbon::parse($order->order_date)->setTimezone('America/Los_Angeles')->toIso8601String(),
                    'status' => $order->status,
                    'period' => $order->period,
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    't_weight' => round($tWeight, 2),
                    'ship_cost' => round($shipCost, 2),
                    'cogs' => round($cogs, 2),
                    'pft_each' => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    'pft' => round($pft, 2),
                    'roi' => round($roi, 2),
                ];
            }
        }

        \Log::info('Returning ' . count($data) . ' eBay 2 data items');

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/ebay2_column_visibility.json');
            
            $defaultVisibility = [
                'order_id' => true,
                'item_id' => true,
                'sku' => true,
                'quantity' => true,
                'sale_amount' => true,
                'price' => true,
                'total_amount' => true,
                'order_date' => true,
                'status' => true,
                'period' => true,
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

            if (file_exists($filePath)) {
                $json = file_get_contents($filePath);
                $saved = json_decode($json, true);
                if (is_array($saved)) {
                    return response()->json($saved);
                }
            }
            
            return response()->json($defaultVisibility);
        } catch (\Exception $e) {
            Log::error('Error getting eBay 2 column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/ebay2_column_visibility.json');
            $visibility = $request->input('visibility', []);
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay 2 column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
