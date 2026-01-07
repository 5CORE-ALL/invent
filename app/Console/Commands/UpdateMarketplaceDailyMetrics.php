<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceDailyMetric;
use App\Models\AmazonOrder;
use App\Models\EbayOrder;
use App\Models\Ebay2Order;
use App\Models\TemuDailyData;
use App\Models\SheinDailyData;
use App\Models\MercariDailyData;
use App\Models\AliexpressDailyData;
use App\Models\ShopifyB2CDailyData;
use App\Models\ShopifyB2BDailyData;
use App\Models\TikTokDailyData;
use App\Models\MiraklDailyData;
use App\Models\DobaDailyData;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use App\Models\ChannelMaster;
use App\Models\AmazonSpCampaignReport;
use App\Models\EbayPromotedListingReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateMarketplaceDailyMetrics extends Command
{
    protected $signature = 'app:update-marketplace-daily-metrics {--date= : Specific date to update (YYYY-MM-DD)}';
    protected $description = 'Update daily metrics for all marketplace channels';

    public function handle()
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        
        $this->info("Updating marketplace daily metrics for: " . $date->format('Y-m-d'));

        $channels = [
            'Amazon' => fn() => $this->calculateAmazonMetrics($date),
            'eBay' => fn() => $this->calculateEbayMetrics($date),
            'eBay 2' => fn() => $this->calculateEbay2Metrics($date),
            'eBay 3' => fn() => $this->calculateEbay3Metrics($date),
            'Temu' => fn() => $this->calculateTemuMetrics($date),
            'Shein' => fn() => $this->calculateSheinMetrics($date),
            'Mercari With Ship' => fn() => $this->calculateMercariWithShipMetrics($date),
            'Mercari Without Ship' => fn() => $this->calculateMercariWithoutShipMetrics($date),
            'AliExpress' => fn() => $this->calculateAliexpressMetrics($date),
            'Shopify B2C' => fn() => $this->calculateShopifyB2CMetrics($date),
            'Shopify B2B' => fn() => $this->calculateShopifyB2BMetrics($date),
            'TikTok' => fn() => $this->calculateTikTokMetrics($date),
            'Best Buy USA' => fn() => $this->calculateBestBuyMetrics($date),
            'Macys' => fn() => $this->calculateMacysMetrics($date),
            'Tiendamia' => fn() => $this->calculateTiendamiaMetrics($date),
            'Doba' => fn() => $this->calculateDobaMetrics($date),
            'Walmart' => fn() => $this->calculateWalmartMetrics($date),
        ];

        foreach ($channels as $channel => $calculator) {
            try {
                $metrics = $calculator();
                
                if ($metrics) {
                    MarketplaceDailyMetric::updateOrCreate(
                        [
                            'channel' => $channel,
                            'date' => $date->format('Y-m-d'),
                        ],
                        $metrics
                    );
                    $this->info("✅ {$channel}: Updated successfully");
                } else {
                    $this->warn("⚠️ {$channel}: No data found");
                }
            } catch (\Exception $e) {
                $this->error("❌ {$channel}: Error - " . $e->getMessage());
                Log::error("MarketplaceDailyMetrics Error for {$channel}", [
                    'error' => $e->getMessage(),
                    'date' => $date->format('Y-m-d')
                ]);
            }
        }

        $this->info("✅ Marketplace daily metrics update complete!");
    }

    private function calculateAmazonMetrics($date)
    {
        // 33 days: Get latest Amazon order date from ShipHub and calculate 33-day range
        $latestDate = DB::connection('shiphub')
            ->table('orders')
            ->where('marketplace', '=', 'amazon')
            ->max('order_date');

        if (!$latestDate) {
            return null;
        }

        $latestDateCarbon = Carbon::parse($latestDate);
        $startDate = $latestDateCarbon->copy()->subDays(32); // 33 days total (matches AmazonSalesController)

        // Get order items from ShipHub (matching AmazonSalesController exactly)
        $orderItems = DB::connection('shiphub')
            ->table('orders as o')
            ->join('order_items as i', 'o.id', '=', 'i.order_id')
            ->whereBetween('o.order_date', [$startDate, $latestDateCarbon->endOfDay()])
            ->where('o.marketplace', '=', 'amazon')
            ->where(function($query) {
                $query->where('o.order_status', '!=', 'Canceled')
                      ->where('o.order_status', '!=', 'Cancelled')
                      ->orWhereNull('o.order_status');
            })
            ->select([
                'o.marketplace_order_id as order_id',
                'o.order_number',
                'o.order_date',
                'o.order_status as status',
                'o.order_total as total_amount',
                'i.sku',
                'i.product_name as title',
                'i.quantity_ordered as quantity',
                'i.unit_price as price', // This is TOTAL price for item line in ShipHub
                'i.asin',
                'i.currency',
            ])
            ->get();

        if ($orderItems->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        $margin = ($percentage - $adUpdates) / 100;

        // Process order items from ShipHub (matching AmazonSalesController)
        foreach ($orderItems as $item) {
            $totalOrders++;
            
            $quantity = (int) ($item->quantity ?? 1);
            // IMPORTANT: unit_price in ShipHub is TOTAL price for the line (not per unit)
            $totalPrice = (float) ($item->price ?? 0);
            $unitPrice = $quantity > 0 ? $totalPrice / $quantity : 0;
            
            $totalQuantity += $quantity;
            $totalRevenue += $totalPrice; // Use total price directly

            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP, Ship and wt_act from ProductMaster
            $sku = strtoupper($item->sku ?? '');
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if ($sku && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Keys are lowercase: lp, ship, wt_act
                if (isset($values['lp'])) $lp = (float) $values['lp'];
                if (isset($values['ship'])) $ship = (float) $values['ship'];
                if (isset($values['wt_act'])) $weightAct = (float) $values['wt_act'];
            }

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation (same as AmazonSalesController):
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity (only LP, not Ship)
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // PFT Each = (unit_price * 0.80) - lp - ship_cost
            $pftEach = ($unitPrice * 0.80) - $lp - $shipCost;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        // ROI = (PFT / COGS) * 100 - but COGS is LP only
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Calculate KW Spent - same logic as amazonKwAdsView
        // Uses L30 report_date_range, group by campaignName to get MAX(spend), then sum
        $kwSpentData = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('campaignName, MAX(spend) as max_spend')
            ->where('report_date_range', 'L30')
            ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'") // Exclude PT and FBA campaigns
            ->groupBy('campaignName')
            ->get();
        
        $kwSpent = $kwSpentData->sum('max_spend') ?? 0;

        // Calculate PT Spent - same logic as amazonPtAdsView
        // Uses L30 report_date_range, group by campaignName to get MAX(spend), then sum
        $ptSpentData = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('campaignName, MAX(spend) as max_spend')
            ->where('report_date_range', 'L30')
            ->where(function($query) {
                $query->whereRaw("campaignName LIKE '%PT'")
                    ->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->whereRaw("campaignName NOT LIKE '%FBA PT%'") // Exclude FBA PT campaigns
            ->whereRaw("campaignName NOT LIKE '%FBA PT.%'") // Exclude FBA PT. campaigns
            ->groupBy('campaignName')
            ->get();
        
        $ptSpent = $ptSpentData->sum('max_spend') ?? 0;

        // Calculate HL Spent - same logic as amazonHlAdsView
        // Uses L30 report_date_range, group by campaignName to get MAX(cost), then sum
        $hlSpentData = DB::table('amazon_sb_campaign_reports')
            ->selectRaw('campaignName, MAX(cost) as max_cost')
            ->where('report_date_range', 'L30')
            ->groupBy('campaignName')
            ->get();
        
        $hlSpent = $hlSpentData->sum('max_cost') ?? 0;

        $tacosPercentage = $totalRevenue > 0 ? (($kwSpent + $ptSpent + $hlSpent) / $totalRevenue) * 100 : 0;
        $nPft = $pftPercentage - $tacosPercentage;
        
        // N ROI = ROI % - TACOS % (same as N PFT formula)
        $nRoi = $roiPercentage - $tacosPercentage;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'tacos_percentage' => $tacosPercentage,
            'n_pft' => $nPft,
            'n_roi' => $nRoi,
            'kw_spent' => $kwSpent,
            'pmt_spent' => $ptSpent,
            'hl_spent' => $hlSpent,
        ];
    }

    private function calculateEbayMetrics($date)
    {
        // Get L30 orders data
        $orders = EbayOrder::with('items')
            ->where('period', 'l30')
            ->where('status', '!=', 'CANCELLED')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (!$item->sku || $item->sku === '') continue;

                $totalOrders++;
                $quantity = (int) ($item->quantity ?? 1);
                $price = (float) ($item->price ?? 0); // This is TOTAL price for all quantity
                
                $totalQuantity += $quantity;
                
                // price is already total (sale_amount), not per unit
                $totalRevenue += $price;

                // Unit price for calculations
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;

                if ($quantity > 0 && $unitPrice > 0) {
                    $totalWeightedPrice += $unitPrice * $quantity;
                    $totalQuantityForPrice += $quantity;
                }

                // Get LP, Ship, and Weight Act from ProductMaster
                $sku = strtoupper($item->sku);
                $lp = 0;
                $ship = 0;
                $weightAct = 0;

                if (isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values) ? $pm->Values :
                            (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    // Get LP (check both cases)
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    
                    // Get Ship
                    if (isset($values['ship'])) {
                        $ship = (float) $values['ship'];
                    } elseif (isset($pm->ship)) {
                        $ship = floatval($pm->ship);
                    }
                    
                    // Get Weight Act
                    if (isset($values['wt_act'])) {
                        $weightAct = floatval($values['wt_act']);
                    }
                }

                // T Weight = Weight Act * Quantity
                $tWeight = $weightAct * $quantity;

                // Ship Cost calculation (matching EbaySalesController exactly):
                // If quantity is 1: ship_cost = ship
                // If quantity > 1 and t_weight < 20: ship_cost = ship / quantity
                // Otherwise: ship_cost = ship
                if ($quantity == 1) {
                    $shipCost = $ship;
                } elseif ($quantity > 1 && $tWeight < 20) {
                    $shipCost = $ship / $quantity;
                } else {
                    $shipCost = $ship;
                }

                // COGS = LP * quantity (same as Amazon, NOT LP+Ship)
                $cogs = $lp * $quantity;
                $totalCogs += $cogs;

                // PFT Each = (unitPrice * 0.85) - lp - ship_cost
                $pftEach = ($unitPrice * 0.85) - $lp - $shipCost;

                // T PFT = pft_each * quantity
                $pft = $pftEach * $quantity;
                $totalPft += $pft;
            }
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Calculate KW and PMT Spent for eBay
        // Using ebay_priority_reports for KW Spent and ebay_general_reports for PMT Spent
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // KW Spent from ebay_priority_reports (CPC ads)
        $kwSpent = DB::table('ebay_priority_reports')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->selectRaw('SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as total_spend')
            ->value('total_spend') ?? 0;

        // PMT Spent from ebay_general_reports (Promoted Listing ads)
        $pmtSpent = DB::table('ebay_general_reports')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->selectRaw('SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")) as total_spend')
            ->value('total_spend') ?? 0;

        $tacosPercentage = $totalRevenue > 0 ? (($kwSpent + $pmtSpent) / $totalRevenue) * 100 : 0;
        $nPft = $pftPercentage - $tacosPercentage;
        
        // N ROI = ROI % - TACOS % (same as N PFT formula)
        $nRoi = $roiPercentage - $tacosPercentage;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'tacos_percentage' => $tacosPercentage,
            'n_pft' => $nPft,
            'n_roi' => $nRoi,
            'kw_spent' => $kwSpent,
            'pmt_spent' => $pmtSpent,
        ];
    }

    private function calculateEbay2Metrics($date)
    {
        // Get L30 orders data from ebay2_orders (same as Ebay2SalesController)
        $orders = Ebay2Order::with('items')
            ->where('period', 'l30')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        // Get unique SKUs from orders (same as Ebay2SalesController)
        // For OPEN BOX or USED items, extract the base SKU
        $skus = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
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

        // Fetch ProductMaster data using case-insensitive SKU match (same as Ebay2SalesController)
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

        // Get marketplace percentage for eBay 2
        // NOTE: Ebay2SalesController uses hardcoded 0.85, so we use the same for consistency
        $percentageDecimal = 0.85;

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (!$item->sku || $item->sku === '') continue;

                $totalOrders++;
                $quantity = (int) ($item->quantity ?? 1);
                $price = (float) ($item->price ?? 0); // This is TOTAL price for all quantity
                
                $totalQuantity += $quantity;
                $totalRevenue += $price;

                // Unit price for calculations
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;

                if ($quantity > 0 && $unitPrice > 0) {
                    $totalWeightedPrice += $unitPrice * $quantity;
                    $totalQuantityForPrice += $quantity;
                }

                // For OPEN BOX or USED items, use the base SKU to get ProductMaster data
                $lookupSku = $item->sku;
                if (stripos($item->sku, 'OPEN BOX') !== false) {
                    $lookupSku = trim(str_ireplace('OPEN BOX', '', $item->sku));
                } elseif (stripos($item->sku, 'USED') !== false) {
                    $lookupSku = trim(str_ireplace('USED', '', $item->sku));
                }

                // Get LP, Ship, and Weight Act from ProductMaster (using lookup SKU)
                $pm = $productMasters[$lookupSku] ?? null;
                $lp = 0;
                $ship = 0;

                if ($pm) {
                    $values = is_array($pm->Values) ? $pm->Values :
                            (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    // Get LP
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    
                    // Get Ship (ebay2_ship or ship)
                    $ship = isset($values["ebay2_ship"]) && $values["ebay2_ship"] !== null 
                        ? floatval($values["ebay2_ship"]) 
                        : (isset($values["ship"]) ? floatval($values["ship"]) : 0);
                }

                // Ship Cost = ship (NOT divided by quantity, as per Excel formula)
                $shipCost = $ship;

                // COGS = LP * quantity
                $cogs = $lp * $quantity;
                $totalCogs += $cogs;

                // PFT Each = (unitPrice * percentageDecimal) - lp - ship_cost
                $pftEach = ($unitPrice * $percentageDecimal) - $lp - $shipCost;

                // T PFT = pft_each * quantity
                $pft = $pftEach * $quantity;
                $totalPft += $pft;
            }
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Calculate PMT Spent for eBay 2 (from ebay_2_general_reports)
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // eBay 2 may not have priority reports (KW Spent = 0)
        $kwSpent = 0;

        // PMT Spent from ebay_2_general_reports
        $pmtSpent = DB::table('ebay_2_general_reports')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->selectRaw('SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")) as total_spend')
            ->value('total_spend') ?? 0;

        $tacosPercentage = $totalRevenue > 0 ? (($kwSpent + $pmtSpent) / $totalRevenue) * 100 : 0;
        $nPft = $pftPercentage - $tacosPercentage;
        
        // N ROI = ROI % - TACOS % (same as N PFT formula)
        $nRoi = $roiPercentage - $tacosPercentage;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'tacos_percentage' => $tacosPercentage,
            'n_pft' => $nPft,
            'n_roi' => $nRoi,
            'kw_spent' => $kwSpent,
            'pmt_spent' => $pmtSpent,
        ];
    }

    private function calculateEbay3Metrics($date)
    {
        // Get L30 orders data from ebay3_daily_data
        $orders = DB::table('ebay3_daily_data')->where('period', 'l30')->get();

        if ($orders->isEmpty()) {
            return null;
        }

        // Get unique SKUs from orders
        $skus = $orders->pluck('sku')->filter()->unique()->toArray();

        // Fetch ProductMaster data
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Get marketplace percentage for eBay 3 (85%)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'EbayThree')->first();
        $percentageDecimal = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.85;

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($orders as $order) {
            $sku = strtoupper($order->sku ?? '');
            if (empty($sku)) continue;

            $totalOrders++;
            $quantity = (int) ($order->quantity ?? 1);
            // IMPORTANT: unit_price in ebay3_daily_data is the TOTAL line item cost (not per unit)
            $lineItemTotal = (float) ($order->unit_price ?? 0);
            $perUnitPrice = $quantity > 0 ? $lineItemTotal / $quantity : 0;
            
            $totalQuantity += $quantity;
            $totalRevenue += $lineItemTotal; // Already the total, don't multiply by quantity

            if ($quantity > 0 && $perUnitPrice > 0) {
                $totalWeightedPrice += $perUnitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP, Ship and Weight Act from ProductMaster
            $lp = 0;
            $ship = 0;
            $weightAct = 0;
            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? floatval($pm->ship) : 0);
                
                // Get Weight Act
                if (isset($values['wt_act'])) {
                    $weightAct = floatval($values['wt_act']);
                }
            }

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation (same as Ebay3SalesController)
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // PFT Each = (per_unit_price * 0.85) - lp - ship_cost
            $pftEach = ($perUnitPrice * $percentageDecimal) - $lp - $shipCost;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Calculate KW and PMT Spent for eBay 3
        // Use the latest report data (from last 30 days of updated_at) to avoid accumulating old reports
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        // KW Spent from ebay_3_priority_reports
        $kwSpent = DB::table('ebay_3_priority_reports')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->get()
            ->sum(function ($r) {
                return (float) preg_replace('/[^\d.]/', '', $r->cpc_ad_fees_payout_currency ?? '0');
            });

        // PMT Spent from ebay_3_general_reports
        $pmtSpent = DB::table('ebay_3_general_reports')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->get()
            ->sum(function ($r) {
                return (float) preg_replace('/[^\d.]/', '', $r->ad_fees ?? '0');
            });

        $tacosPercentage = $totalRevenue > 0 ? (($kwSpent + $pmtSpent) / $totalRevenue) * 100 : 0;
        $nPft = $pftPercentage - $tacosPercentage;
        
        // N ROI = ROI % - TACOS % (same as N PFT formula)
        $nRoi = $roiPercentage - $tacosPercentage;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'tacos_percentage' => $tacosPercentage,
            'n_pft' => $nPft,
            'n_roi' => $nRoi,
            'kw_spent' => $kwSpent,
            'pmt_spent' => $pmtSpent,
        ];
    }

    private function calculateTemuMetrics($date)
    {
        // Get Temu daily data
        $data = TemuDailyData::all();

        if ($data->isEmpty()) {
            return null;
        }

        // Get ProductMaster with parent info (keyed by SKU, not uppercase)
        $productMasters = ProductMaster::all()->keyBy('sku');

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalL30Sales = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($data as $row) {
            if (!$row->contribution_sku || $row->contribution_sku === '') continue;

            // Skip parent rows (like badge calculation does)
            $pm = $productMasters[$row->contribution_sku] ?? null;
            $parent = $pm ? $pm->parent : '';
            if ($parent && str_starts_with($parent, 'PARENT')) {
                continue;
            }

            $totalOrders++;
            $quantity = (int) ($row->quantity_purchased ?? 0);
            $basePrice = (float) ($row->base_price_total ?? 0);
            
            $totalQuantity += $quantity;
            
            // Revenue = basePrice * quantity (without FB price adjustment)
            $totalRevenue += $basePrice * $quantity;
            
            // Calculate FB Price
            $total = $basePrice * $quantity;
            $fbPrice = $total < 27 ? $basePrice + 2.99 : $basePrice;
            
            // L30 Sales = fbPrice * quantity
            $totalL30Sales += $fbPrice * $quantity;

            if ($quantity > 0 && $basePrice > 0) {
                $totalWeightedPrice += $basePrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP and temu_ship from ProductMaster (already fetched above as $pm)
            $lp = 0;
            $temuShip = 0;

            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP (matching controller logic)
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Temu Ship
                if (isset($values['temu_ship'])) {
                    $temuShip = floatval($values['temu_ship']);
                } elseif (isset($pm->temu_ship)) {
                    $temuShip = floatval($pm->temu_ship);
                }
            }

            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // Calculate PFT: (FB Prc * 0.91 - LP - Temu Ship) * Quantity (matching blade view)
            $pft = ($fbPrice * 0.91 - $lp - $temuShip) * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalL30Sales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalL30Sales,
        ];
    }

    private function calculateSheinMetrics($date)
    {
        // Get Shein daily data
        $data = SheinDailyData::all();

        if ($data->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy('sku');

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalCommission = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($data as $row) {
            // Skip rows without order_number
            if (!$row->order_number || $row->order_number === '') continue;
            
            // Skip refunded/returned/cancelled orders (like badge does)
            $orderStatus = strtolower($row->order_status ?? '');
            if (str_contains($orderStatus, 'refund') || str_contains($orderStatus, 'returned') || str_contains($orderStatus, 'cancelled')) {
                continue;
            }

            $totalOrders++;
            $quantity = (int) ($row->quantity ?? 1);
            $productPrice = (float) ($row->product_price ?? 0);
            $commission = (float) ($row->commission ?? 0);
            
            $totalQuantity += $quantity;
            $totalRevenue += $productPrice * $quantity;
            $totalCommission += $commission;

            if ($quantity > 0 && $productPrice > 0) {
                $totalWeightedPrice += $productPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP and Ship from ProductMaster
            $sku = $row->seller_sku;
            $lp = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                if (isset($values['ship'])) {
                    $ship = floatval($values['ship']);
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
            }

            // COGS = Quantity * LP only (not Ship)
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // Calculate PFT: (Product Price * 0.89 - LP - Ship) * Quantity
            $pft = ($productPrice * 0.89 - $lp - $ship) * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Shein has no ads, so N ROI = G ROI and N PFT = G PFT
        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'total_commission' => $totalCommission,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => round($roiPercentage, 1),
        ];
    }

    private function calculateMercariWithShipMetrics($date)
    {
        // Get Mercari daily data - With Ship: buyer_shipping_fee = 0 or null (seller pays shipping)
        $data = MercariDailyData::where(function ($query) {
            $query->whereNull('buyer_shipping_fee')
                  ->orWhere('buyer_shipping_fee', '=', 0)
                  ->orWhere('buyer_shipping_fee', '=', '');
        })->get();

        if ($data->isEmpty()) {
            return null;
        }

        // Fetch all ProductMaster records and create lookup maps
        $productMastersBySku = ProductMaster::all()->mapWithKeys(function($pm) {
            $sku = strtoupper(trim($pm->sku));
            $skuNoSpaces = str_replace([' ', '-', '_'], '', $sku);
            return [
                $sku => $pm,
                $skuNoSpaces => $pm, // Also index by SKU without spaces/dashes
            ];
        });

        $totalOrders = 0;
        $totalSales = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalFees = 0;
        $totalNetProceeds = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($data as $row) {
            // Skip rows without item_id
            if (!$row->item_id || $row->item_id === '') {
                continue;
            }
            
            // Skip cancelled orders (like badge does)
            $orderStatus = strtolower($row->order_status ?? '');
            $isCancelled = ($row->canceled_date !== null && $row->canceled_date !== '') ||
                           str_contains($orderStatus, 'cancelled') ||
                           str_contains($orderStatus, 'canceled');
            if ($isCancelled) {
                continue;
            }

            $totalOrders++;
            $itemPrice = (float) ($row->item_price ?? 0);
            $netProceeds = (float) ($row->net_seller_proceeds ?? 0);
            $mercariFee = (float) ($row->mercari_selling_fee ?? 0);
            $paymentFee = (float) ($row->payment_processing_fee_charged_to_seller ?? 0);
            $shippingAdj = (float) ($row->shipping_adjustment_fee ?? 0);
            $penalty = (float) ($row->penalty_fee ?? 0);
            
            $totalSales += $itemPrice;
            $totalNetProceeds += $netProceeds;
            $totalFees += $mercariFee + $paymentFee + $shippingAdj + $penalty;

            if ($itemPrice > 0) {
                $totalWeightedPrice += $itemPrice;
                $totalQuantityForPrice++;
            }

            // Extract and match SKU from item_title
            $lp = 0;
            $ship = 0;
            $matchedSku = $this->extractSkuFromTitle($row->item_title, $productMastersBySku);
            
            if ($matchedSku) {
                // Find the ProductMaster record by the matched SKU
                $pm = null;
                foreach ($productMastersBySku as $pmSku => $pmRecord) {
                    if (strtoupper(trim($pmRecord->sku)) === strtoupper(trim($matchedSku))) {
                        $pm = $pmRecord;
                        break;
                    }
                }
                
                if ($pm) {
                    $values = is_array($pm->Values) 
                        ? $pm->Values 
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    // Get LP
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    
                    // Get Ship
                    $ship = isset($values["ship"]) 
                        ? floatval($values["ship"]) 
                        : (isset($pm->ship) ? floatval($pm->ship) : 0);
                }
            }
            
            // COGS = LP only
            $totalCogs += $lp;

            // Calculate PFT: (Item Price × 0.88) - LP - Ship
            $pft = ($itemPrice * 0.88) - $lp - $ship;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalSales > 0 ? ($totalPft / $totalSales) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Mercari has no ads, so N ROI = G ROI and N PFT = G PFT
        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalOrders, // 1 per order
            'total_revenue' => $totalSales,
            'total_sales' => $totalSales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => $avgPrice,
            'l30_sales' => $totalSales,
            'total_fees' => $totalFees,
            'net_proceeds' => $totalNetProceeds,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => round($roiPercentage, 1),
        ];
    }

    private function calculateMercariWithoutShipMetrics($date)
    {
        // Get Mercari daily data - Without Ship: buyer_shipping_fee > 0 (buyer pays shipping)
        $data = MercariDailyData::where('buyer_shipping_fee', '>', 0)->get();

        if ($data->isEmpty()) {
            return null;
        }

        // Fetch all ProductMaster records and create lookup maps
        $productMastersBySku = ProductMaster::all()->mapWithKeys(function($pm) {
            $sku = strtoupper(trim($pm->sku));
            $skuNoSpaces = str_replace([' ', '-', '_'], '', $sku);
            return [
                $sku => $pm,
                $skuNoSpaces => $pm, // Also index by SKU without spaces/dashes
            ];
        });

        $totalOrders = 0;
        $totalSales = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalFees = 0;
        $totalNetProceeds = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($data as $row) {
            // Skip rows without item_id
            if (!$row->item_id || $row->item_id === '') {
                continue;
            }
            
            // Skip cancelled orders (like badge does)
            $orderStatus = strtolower($row->order_status ?? '');
            $isCancelled = ($row->canceled_date !== null && $row->canceled_date !== '') ||
                           str_contains($orderStatus, 'cancelled') ||
                           str_contains($orderStatus, 'canceled');
            if ($isCancelled) {
                continue;
            }

            $totalOrders++;
            $itemPrice = (float) ($row->item_price ?? 0);
            $netProceeds = (float) ($row->net_seller_proceeds ?? 0);
            $mercariFee = (float) ($row->mercari_selling_fee ?? 0);
            $paymentFee = (float) ($row->payment_processing_fee_charged_to_seller ?? 0);
            $shippingAdj = (float) ($row->shipping_adjustment_fee ?? 0);
            $penalty = (float) ($row->penalty_fee ?? 0);
            
            $totalSales += $itemPrice;
            $totalNetProceeds += $netProceeds;
            $totalFees += $mercariFee + $paymentFee + $shippingAdj + $penalty;

            if ($itemPrice > 0) {
                $totalWeightedPrice += $itemPrice;
                $totalQuantityForPrice++;
            }

            // Extract and match SKU from item_title
            $lp = 0;
            $ship = 0;
            $matchedSku = $this->extractSkuFromTitle($row->item_title, $productMastersBySku);
            
            if ($matchedSku) {
                // Find the ProductMaster record by the matched SKU
                $pm = null;
                foreach ($productMastersBySku as $pmSku => $pmRecord) {
                    if (strtoupper(trim($pmRecord->sku)) === strtoupper(trim($matchedSku))) {
                        $pm = $pmRecord;
                        break;
                    }
                }
                
                if ($pm) {
                    $values = is_array($pm->Values) 
                        ? $pm->Values 
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    // Get LP
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    
                    // Get Ship
                    $ship = isset($values["ship"]) 
                        ? floatval($values["ship"]) 
                        : (isset($pm->ship) ? floatval($pm->ship) : 0);
                }
            }
            
            // COGS = LP only (quantity is 1 per order)
            $totalCogs += $lp;

            // Calculate PFT: (Item Price × 0.88) - LP (no ship for Without Ship)
            $pft = ($itemPrice * 0.88) - $lp;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalSales > 0 ? ($totalPft / $totalSales) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Mercari has no ads, so N ROI = G ROI and N PFT = G PFT
        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalOrders, // 1 per order
            'total_revenue' => $totalSales,
            'total_sales' => $totalSales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => $avgPrice,
            'l30_sales' => $totalSales,
            'total_fees' => $totalFees,
            'net_proceeds' => $totalNetProceeds,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => round($roiPercentage, 1),
        ];
    }
    
    /**
     * Extract potential SKUs from item title and match with ProductMaster
     * Returns the matched ProductMaster SKU or null
     * (Same logic as MercariController::extractAndMatchSkuFromTitle)
     */
    private function extractSkuFromTitle($itemTitle, $productMastersBySku)
    {
        if (empty($itemTitle)) {
            return null;
        }

        $variations = [];
        
        // Pattern 1: Extract last sequence (highest priority)
        if (preg_match('/\b([A-Za-z0-9\s\-]{3,})\s*$/', $itemTitle, $matches)) {
            $lastPart = trim($matches[1]);
            
            $variations[] = $lastPart;
            $variations[] = strtoupper($lastPart);
            $variations[] = str_replace(' ', '', $lastPart);
            $variations[] = str_replace(' ', '', strtoupper($lastPart));
            $variations[] = str_replace([' ', '-'], '', strtoupper($lastPart));
            
            $words = explode(' ', $lastPart);
            if (count($words) > 1 && strlen($words[0]) <= 3) {
                $withoutPrefix = trim(implode(' ', array_slice($words, 1)));
                if (strlen($withoutPrefix) >= 3) {
                    $variations[] = $withoutPrefix;
                    $variations[] = strtoupper($withoutPrefix);
                    $variations[] = str_replace(' ', '', $withoutPrefix);
                    $variations[] = str_replace(' ', '', strtoupper($withoutPrefix));
                    $variations[] = str_replace([' ', '-'], '', strtoupper($withoutPrefix));
                }
            }
        }

        // Pattern 2: Extract mixed case patterns (e.g., "GRack 3N1", "20R WoB")
        if (preg_match_all('/\b([A-Za-z]{1,}[a-z]*\s*[A-Z0-9]{1,}(?:\s+[A-Za-z0-9]+){0,3})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 3) {
                    $variations[] = $trimmed;
                    $variations[] = strtoupper($trimmed);
                    $variations[] = str_replace(' ', '', $trimmed);
                    $variations[] = str_replace(' ', '', strtoupper($trimmed));
                }
            }
        }

        // Pattern 3: Extract patterns starting with numbers (e.g., "20R WoB")
        if (preg_match_all('/\b(\d+[A-Za-z]+\s+[A-Za-z0-9]+(?:\s+[A-Za-z0-9]+){0,2})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 3) {
                    $variations[] = $trimmed;
                    $variations[] = strtoupper($trimmed);
                    $variations[] = str_replace(' ', '', $trimmed);
                    $variations[] = str_replace(' ', '', strtoupper($trimmed));
                }
            }
        }

        // Pattern 4: Extract product code patterns (e.g., "SS ECO 2PK BLK", "HW 405 WH")
        if (preg_match_all('/\b([A-Z]{2,}\s+[A-Z0-9]{1,}(?:\s+[A-Z0-9]+){0,4})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 4) {
                    $variations[] = $trimmed;
                    $variations[] = str_replace(' ', '', $trimmed);
                }
            }
        }

        // Pattern 5: Extract all alphanumeric sequences (potential SKUs)
        if (preg_match_all('/\b([A-Za-z0-9\-]{4,})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $variations[] = trim($match);
                $variations[] = strtoupper(trim($match));
            }
        }

        // Remove duplicates and empty values
        $variations = array_values(array_unique(array_filter($variations)));

        // Try to match each variation with ProductMaster SKUs
        foreach ($variations as $variation) {
            $normalized = strtoupper(trim($variation));
            $normalizedNoSpaces = str_replace([' ', '-', '_'], '', $normalized);

            // Try exact match first
            if (isset($productMastersBySku[$normalized])) {
                return $productMastersBySku[$normalized]->sku;
            }
            if (isset($productMastersBySku[$normalizedNoSpaces])) {
                return $productMastersBySku[$normalizedNoSpaces]->sku;
            }

            // Try partial match with ProductMaster SKUs
            foreach ($productMastersBySku as $pmSku => $pm) {
                $pmSkuUpper = strtoupper(trim($pmSku));
                $pmSkuNoSpaces = str_replace([' ', '-', '_'], '', $pmSkuUpper);
                
                // Exact match
                if ($normalized === $pmSkuUpper || $normalizedNoSpaces === $pmSkuNoSpaces) {
                    return $pm->sku;
                }
                
                // Partial match (if variation contains or is contained in SKU)
                if (strlen($normalized) >= 3) {
                    if (stripos($pmSkuUpper, $normalized) !== false || 
                        stripos($normalized, $pmSkuUpper) !== false ||
                        stripos($pmSkuNoSpaces, $normalizedNoSpaces) !== false ||
                        stripos($normalizedNoSpaces, $pmSkuNoSpaces) !== false) {
                        return $pm->sku;
                    }
                }
            }
        }

        return null;
    }

    private function calculateAliexpressMetrics($date)
    {
        // Get AliExpress daily data
        $data = AliexpressDailyData::all();

        if ($data->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Get marketplace percentage from ChannelMaster (like other channels)
        $marketplaceData = ChannelMaster::where('channel', 'Aliexpress')->first();
        $percentage = $marketplaceData ? ($marketplaceData->channel_percentage ?? 100) : 100;
        $margin = $percentage / 100; // Convert % to fraction

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($data as $row) {
            // Skip refunded, returned, cancelled orders
            $status = strtolower($row->order_status ?? '');
            if (strpos($status, 'refund') !== false || strpos($status, 'return') !== false || 
                strpos($status, 'cancel') !== false || strpos($status, 'closed') !== false) {
                continue;
            }

            // Skip rows with empty SKU or order_id
            if (empty($row->sku_code) || empty($row->order_id)) {
                continue;
            }

            $totalOrders++;
            $quantity = (int) ($row->quantity ?? 1);
            $productPrice = (float) ($row->product_total ?? 0);
            $orderAmount = (float) ($row->order_amount ?? 0);
            
            $totalQuantity += $quantity;
            $totalRevenue += $orderAmount;

            // For average price calculation, use product price per unit
            // product_total is TOTAL (not per-unit), so divide by quantity
            if ($quantity > 0 && $productPrice > 0) {
                $unitPriceForAvg = $productPrice / $quantity;
                $totalWeightedPrice += $unitPriceForAvg * $quantity; // Sum of (unit_price × quantity)
                $totalQuantityForPrice += $quantity;
            }

            // Get LP and Ship from ProductMaster (same extraction logic as sales page)
            $sku = strtoupper($row->sku_code ?? '');
            $lp = 0;
            $ship = 0;

            if ($sku && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) 
                    ? $pm->Values 
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP (similar to Temu extraction)
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                $ship = isset($values["ship"]) 
                    ? floatval($values["ship"]) 
                    : (isset($pm->ship) ? floatval($pm->ship) : 0);
            }

            // COGS = LP × Quantity (same as other channels, not LP + Ship)
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // Calculate unit price (product_total is TOTAL, not per-unit)
            $unitPrice = $quantity > 0 ? $productPrice / $quantity : 0;
            
            // Calculate PFT: (Unit Price × Margin - LP - Ship) × Quantity
            // Same formula as sales page and eBay
            $pft = (($unitPrice * $margin) - $lp - $ship) * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'n_roi' => $roiPercentage,
            'n_pft' => $totalPft,
            'kw_spent' => 0,
            'pmt_spent' => 0,
        ];
    }

    private function calculateShopifyB2CMetrics($date)
    {
        // Get L30 orders data (period = 'l30' and not refunded)
        $orders = ShopifyB2CDailyData::where('period', 'l30')
            ->where('financial_status', '!=', 'refunded')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        // Shopify B2C uses 0.95 margin (95%)
        $margin = 0.95;

        foreach ($orders as $order) {
            if (!$order->sku || $order->sku === '') continue;

            $totalOrders++;
            $quantity = (int) ($order->quantity ?? 1);
            $price = (float) ($order->price ?? 0); // This is final price per unit after discount
            $totalAmount = (float) ($order->total_amount ?? 0);
            
            $totalQuantity += $quantity;
            $totalRevenue += $totalAmount;

            if ($quantity > 0 && $price > 0) {
                $totalWeightedPrice += $price * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP, Ship and Weight Act from ProductMaster
            $sku = strtoupper($order->sku);
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                if (isset($values['ship'])) {
                    $ship = (float) $values['ship'];
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
                
                // Get Weight Act
                if (isset($values['wt_act'])) {
                    $weightAct = floatval($values['wt_act']);
                }
            }

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation (same as ShopifyB2CSalesController):
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity (only LP, not Ship)
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // PFT Each = (price * 0.95) - lp - ship_cost
            $pftEach = ($price * $margin) - $lp - $shipCost;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => round($roiPercentage, 1),
        ];
    }

    private function calculateShopifyB2BMetrics($date)
    {
        // Get L30 orders data (period = 'l30' and not refunded)
        $orders = ShopifyB2BDailyData::where('period', 'l30')
            ->where('financial_status', '!=', 'refunded')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        // Shopify B2B (Wholesale) uses 0.95 margin (95%)
        $margin = 0.95;

        foreach ($orders as $order) {
            if (!$order->sku || $order->sku === '') continue;

            $totalOrders++;
            $quantity = (int) ($order->quantity ?? 1);
            $price = (float) ($order->price ?? 0); // This is final price per unit after discount
            $totalAmount = (float) ($order->total_amount ?? 0);
            
            $totalQuantity += $quantity;
            $totalRevenue += $totalAmount;

            if ($quantity > 0 && $price > 0) {
                $totalWeightedPrice += $price * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP, Ship and Weight Act from ProductMaster
            $sku = strtoupper($order->sku);
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                if (isset($values['ship'])) {
                    $ship = (float) $values['ship'];
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
                
                // Get Weight Act
                if (isset($values['wt_act'])) {
                    $weightAct = floatval($values['wt_act']);
                }
            }

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation (same as ShopifyB2BSalesController):
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity (only LP, not Ship)
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // PFT Each = (price * 0.95) - lp - ship_cost
            $pftEach = ($price * $margin) - $lp - $shipCost;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => round($roiPercentage, 1),
        ];
    }

    private function calculateTikTokMetrics($date)
    {
        // 33 days: Get latest TikTok order date from ShipHub and calculate 33-day range
        $latestDate = DB::connection('shiphub')
            ->table('orders')
            ->where('marketplace', '=', 'tiktok')
            ->max('order_date');

        if (!$latestDate) {
            return null;
        }

        $latestDateCarbon = Carbon::parse($latestDate);
        $startDate = $latestDateCarbon->copy()->subDays(32); // 33 days total (matches Amazon)

        // Get order items from ShipHub (matching TikTokSalesController exactly)
        $orderItems = DB::connection('shiphub')
            ->table('orders as o')
            ->join('order_items as i', 'o.id', '=', 'i.order_id')
            ->whereBetween('o.order_date', [$startDate, $latestDateCarbon->endOfDay()])
            ->where('o.marketplace', '=', 'tiktok')
            ->where(function($query) {
                $query->where('o.order_status', '!=', 'Canceled')
                      ->where('o.order_status', '!=', 'Cancelled')
                      ->orWhereNull('o.order_status');
            })
            ->select([
                'o.id as internal_order_id', // For grouping multi-item orders
                'o.marketplace_order_id as order_id',
                'o.order_number',
                'o.order_date',
                'o.order_status as status',
                'o.order_total as total_amount',
                'i.sku',
                'i.product_name as title',
                'i.quantity_ordered as quantity',
                'i.unit_price as price', // This is TOTAL price for item line in ShipHub
                'i.asin',
                'i.currency',
            ])
            ->get();

        if ($orderItems->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        // TikTok margin fixed at 80%
        $margin = 0.80; // 80% margin (20% TikTok fees)

        // Group items by order to handle multi-item orders correctly
        $orderGroups = [];
        foreach ($orderItems as $item) {
            $orderId = $item->internal_order_id ?? 'unknown';
            if (!isset($orderGroups[$orderId])) {
                $orderGroups[$orderId] = [
                    'order_total' => (float) ($item->total_amount ?? 0),
                    'items' => []
                ];
            }
            $orderGroups[$orderId]['items'][] = $item;
        }

        // Process order items from ShipHub (matching TikTokSalesController)
        foreach ($orderGroups as $orderId => $orderData) {
            $orderTotal = $orderData['order_total'];
            $items = $orderData['items'];
            $itemCount = count($items);
            
            // Distribute order_total across all items in the order
            $pricePerItem = $itemCount > 0 ? $orderTotal / $itemCount : $orderTotal;
            
            foreach ($items as $item) {
                $totalOrders++;
                
                $quantity = (int) ($item->quantity ?? 1);
                
                // TikTok FIX: Use distributed price per item
                $totalPrice = $pricePerItem;
                $unitPrice = $quantity > 0 ? $totalPrice / $quantity : 0;
                
                $totalQuantity += $quantity;
                $totalRevenue += $totalPrice;

                if ($quantity > 0 && $unitPrice > 0) {
                    $totalWeightedPrice += $unitPrice * $quantity;
                    $totalQuantityForPrice += $quantity;
                }

            // Get LP, Ship and wt_act from ProductMaster
            $sku = strtoupper($item->sku ?? '');
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if ($sku && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                if (isset($values['ship'])) {
                    $ship = floatval($values['ship']);
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
                
                // Get Weight Act
                if (isset($values['wt_act'])) {
                    $weightAct = floatval($values['wt_act']);
                }
            }

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation (same as Amazon):
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity (only LP, not Ship)
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // PFT Each = (unit_price * margin) - lp - ship_cost
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
            } // End foreach items
        } // End foreach orderGroups

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // TikTok has no ads currently, so N ROI = G ROI and N PFT = G PFT
        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => $roiPercentage,
        ];
    }

    private function calculateBestBuyMetrics($date)
    {
        // Get Best Buy USA L30 orders from mirakl_daily_data (exclude CLOSED status)
        $orders = MiraklDailyData::where('channel_name', 'Best Buy USA')
            ->where('period', 'l30')
            ->where('status', '!=', 'CLOSED')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 80;
        $margin = $percentage / 100;

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($orders as $order) {
            if (!$order->sku || $order->sku === '') continue;

            $totalOrders++;
            $quantity = (int) ($order->quantity ?? 1);
            $unitPrice = (float) ($order->unit_price ?? 0);
            $saleAmount = $unitPrice * $quantity;
            
            $totalQuantity += $quantity;
            $totalRevenue += $saleAmount;

            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP, Ship and Weight Act from ProductMaster
            $sku = strtoupper($order->sku);
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                if (isset($values['ship'])) {
                    $ship = (float) $values['ship'];
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
                
                // Get Weight Act
                if (isset($values['wt_act'])) {
                    $weightAct = floatval($values['wt_act']);
                }
            }

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // PFT Each = (unitPrice * margin) - lp - ship_cost
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'n_roi' => $roiPercentage,
            'n_pft' => $totalPft,
            'kw_spent' => 0,
            'pmt_spent' => 0,
        ];
    }

    private function calculateMacysMetrics($date)
    {
        // Get Macy's L30 orders from mirakl_daily_data (exclude CLOSED status)
        $orders = MiraklDailyData::where('channel_name', "Macy's, Inc.")
            ->where('period', 'l30')
            ->where('status', '!=', 'CLOSED')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Get marketplace percentage for Macy's
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 76;
        $margin = $percentage / 100;

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($orders as $order) {
            if (!$order->sku || $order->sku === '') continue;

            $totalOrders++;
            $quantity = (int) ($order->quantity ?? 1);
            $unitPrice = (float) ($order->unit_price ?? 0);
            $saleAmount = $unitPrice * $quantity;
            
            $totalQuantity += $quantity;
            $totalRevenue += $saleAmount;

            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP, Ship and Weight Act from ProductMaster
            $sku = strtoupper($order->sku);
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                if (isset($values['ship'])) {
                    $ship = (float) $values['ship'];
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
                
                // Get Weight Act
                if (isset($values['wt_act'])) {
                    $weightAct = floatval($values['wt_act']);
                }
            }

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // PFT Each = (unitPrice * margin) - lp - ship_cost
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'n_roi' => $roiPercentage,
            'n_pft' => $totalPft,
            'kw_spent' => 0,
            'pmt_spent' => 0,
        ];
    }

    private function calculateTiendamiaMetrics($date)
    {
        // Get Tiendamia L30 orders from mirakl_daily_data (exclude CLOSED status)
        $orders = MiraklDailyData::where('channel_name', 'Tiendamia')
            ->where('period', 'l30')
            ->where('status', '!=', 'CLOSED')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Get marketplace percentage for Tiendamia
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Tiendamia')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 83;
        $margin = $percentage / 100;

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($orders as $order) {
            if (!$order->sku || $order->sku === '') continue;

            $totalOrders++;
            $quantity = (int) ($order->quantity ?? 1);
            $unitPrice = (float) ($order->unit_price ?? 0);
            $saleAmount = $unitPrice * $quantity;
            
            $totalQuantity += $quantity;
            $totalRevenue += $saleAmount;

            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP, Ship and Weight Act from ProductMaster
            $sku = strtoupper($order->sku);
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                if (isset($values['ship'])) {
                    $ship = (float) $values['ship'];
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
                
                // Get Weight Act
                if (isset($values['wt_act'])) {
                    $weightAct = floatval($values['wt_act']);
                }
            }

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // PFT Each = (unitPrice * margin) - lp - ship_cost
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'n_roi' => $roiPercentage,
            'n_pft' => $totalPft,
            'kw_spent' => 0,
            'pmt_spent' => 0,
        ];
    }

    private function calculateDobaMetrics($date)
    {
        // Get Doba L30 orders from DobaDailyData (matching DobaSalesController)
        $orders = DobaDailyData::where('period', 'L30')
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        // Get unique SKUs
        $skus = $orders->pluck('sku')->filter()->unique()->values()->toArray();

        // Get ProductMaster data keyed by SKU
        $productMasters = ProductMaster::whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        // Doba uses 0.95 margin (matching DobaSalesController)
        $margin = 0.95;

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($orders as $order) {
            if (!$order->sku || $order->sku === '') continue;

            $totalOrders++;
            $quantity = (int) ($order->quantity ?? 1);
            $itemPrice = (float) ($order->item_price ?? 0);
            $totalPrice = (float) ($order->total_price ?? 0);
            
            $totalQuantity += $quantity;
            $totalRevenue += $totalPrice;

            if ($quantity > 0 && $itemPrice > 0) {
                $totalWeightedPrice += $itemPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP and Ship from ProductMaster
            $lp = 0;
            $ship = 0;

            if (isset($productMasters[$order->sku])) {
                $pm = $productMasters[$order->sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                if (isset($values['lp'])) {
                    $lp = floatval($values['lp']);
                }
                
                // Get Ship
                if (isset($values['ship'])) {
                    $ship = floatval($values['ship']);
                }
            }

            // COGS = LP * quantity
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // Ship Cost calculation (matching DobaSalesController)
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // PFT Each = (itemPrice * 0.95) - ship - lp
            // If order type is "Pickup with a prepaid label", don't reduce shipping cost
            if (strtolower($order->order_type ?? '') === 'pickup with a prepaid label') {
                $pftEach = ($itemPrice * $margin) - $lp;
            } else {
                $pftEach = ($itemPrice * $margin) - $ship - $lp;
            }

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Doba has no ads, so N ROI = G ROI and N PFT = G PFT
        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => round($roiPercentage, 1),
        ];
    }

    private function calculateWalmartMetrics($date)
    {
        // 33 days: Get latest Walmart order date from ShipHub and calculate 33-day range
        $latestDate = DB::connection('shiphub')
            ->table('orders')
            ->where('marketplace', '=', 'walmart')
            ->max('order_date');

        if (!$latestDate) {
            return null;
        }

        $latestDateCarbon = Carbon::parse($latestDate);
        $startDate = $latestDateCarbon->copy()->subDays(32); // 33 days total (matches Amazon)

        // Get Walmart orders from ShipHub (orders table contains item details directly)
        $orders = DB::connection('shiphub')
            ->table('orders')
            ->whereBetween('order_date', [$startDate, $latestDateCarbon->endOfDay()])
            ->where('marketplace', '=', 'walmart')
            ->where(function($query) {
                $query->where('order_status', '!=', 'Canceled')
                      ->where('order_status', '!=', 'Cancelled')
                      ->orWhereNull('order_status');
            })
            ->select([
                'marketplace_order_id as order_id',
                'order_number',
                'order_date',
                'item_sku as sku',
                'quantity',
                'order_total',
            ])
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;
        $uniqueOrders = [];

        // Get Walmart percentage from database (default 80%)
        $marketplaceData = \App\Models\MarketplacePercentage::where('marketplace', 'Walmart')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 80;
        $margin = $percentage / 100; // Convert to decimal

        // Process order items from ShipHub
        foreach ($orders as $order) {
            $sku = strtoupper(trim($order->sku ?? ''));
            $quantity = (int) ($order->quantity ?? 1);
            $orderTotal = (float) ($order->order_total ?? 0);
            
            // For Walmart, order_total is the price for this item line
            $saleAmount = $orderTotal;
            $unitPrice = $quantity > 0 ? $saleAmount / $quantity : 0;
            
            $uniqueOrders[$order->order_id] = true;
            $totalQuantity += $quantity;
            $totalRevenue += $saleAmount;

            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP, Ship and wt_act from ProductMaster
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if ($sku && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                
                // Get LP
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                
                // Get Ship
                if (isset($values['ship'])) {
                    $ship = floatval($values['ship']);
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
                
                // Get Weight Act
                if (isset($values['wt_act']) || isset($values['weight_act'])) {
                    $weightAct = floatval($values['wt_act'] ?? $values['weight_act']);
                }
            }

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation (same as Amazon/TikTok):
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity (only LP, not Ship)
            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // PFT Each = (unit_price * margin) - lp - ship_cost
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;
            $totalPft += $pft;
        }

        $totalOrders = count($uniqueOrders);
        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Walmart may have ads in the future, but for now set to 0
        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => round($roiPercentage, 1),
        ];
    }
}
