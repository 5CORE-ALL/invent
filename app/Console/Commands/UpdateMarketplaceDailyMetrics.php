<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceDailyMetric;
use App\Models\AmazonOrder;
use App\Models\EbayOrder;
use App\Models\TemuDailyData;
use App\Models\SheinDailyData;
use App\Models\MercariDailyData;
use App\Models\AliexpressDailyData;
use App\Models\ShopifyB2CDailyData;
use App\Models\ShopifyB2BDailyData;
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
            'Temu' => fn() => $this->calculateTemuMetrics($date),
            'Shein' => fn() => $this->calculateSheinMetrics($date),
            'Mercari With Ship' => fn() => $this->calculateMercariWithShipMetrics($date),
            'Mercari Without Ship' => fn() => $this->calculateMercariWithoutShipMetrics($date),
            'AliExpress' => fn() => $this->calculateAliexpressMetrics($date),
            'Shopify B2C' => fn() => $this->calculateShopifyB2CMetrics($date),
            'Shopify B2B' => fn() => $this->calculateShopifyB2BMetrics($date),
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
        // Get L30 orders data
        $orders = AmazonOrder::with('items')
            ->where('period', 'l30')
            ->where('status', '!=', 'Canceled')
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

        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        $margin = ($percentage - $adUpdates) / 100;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (!$item->sku || $item->sku === '') continue;

                $totalOrders++;
                $quantity = (int) ($item->quantity ?? 1);
                $price = (float) ($item->price ?? 0);
                
                $totalQuantity += $quantity;
                $totalRevenue += $price;

                if ($quantity > 0 && $price > 0) {
                    $pricePerUnit = $price / $quantity;
                    $totalWeightedPrice += $pricePerUnit * $quantity;
                    $totalQuantityForPrice += $quantity;
                }

                // Get LP, Ship and wt_act from ProductMaster
                $sku = strtoupper($item->sku);
                $lp = 0;
                $ship = 0;
                $weightAct = 0;

                if (isset($productMasters[$sku])) {
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

                // COGS = LP * quantity (sirf LP, ship nahi)
                $cogs = $lp * $quantity;
                $totalCogs += $cogs;

                // PFT Each = (unit_price * 0.80) - lp - ship_cost
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;
                $pftEach = ($unitPrice * 0.80) - $lp - $shipCost;

                // T PFT = pft_each * quantity
                $pft = $pftEach * $quantity;
                $totalPft += $pft;
            }
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        // ROI = (PFT / COGS) * 100 - but COGS is LP only
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Calculate KW and PT Spent
        $thirtyDaysAgo = Carbon::now()->subDays(31)->format('Y-m-d');
        $yesterday = Carbon::now()->subDay()->format('Y-m-d');

        $kwSpent = DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereDate('report_date_range', '>=', $thirtyDaysAgo)
            ->whereDate('report_date_range', '<=', $yesterday)
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->where(function($query) {
                $query->whereRaw("campaignName NOT LIKE '%PT'")
                    ->whereRaw("campaignName NOT LIKE '%PT.'");
            })
            ->sum('spend') ?? 0;

        $ptSpent = DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereDate('report_date_range', '>=', $thirtyDaysAgo)
            ->whereDate('report_date_range', '<=', $yesterday)
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->where(function($query) {
                $query->whereRaw("campaignName LIKE '%PT'")
                    ->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->sum('spend') ?? 0;

        $tacosPercentage = $totalRevenue > 0 ? (($kwSpent + $ptSpent) / $totalRevenue) * 100 : 0;
        $nPft = $pftPercentage - $tacosPercentage;

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
            'kw_spent' => $kwSpent,
            'pmt_spent' => $ptSpent,
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

            // Calculate PFT: (FB Prc * 0.87 - LP - Temu Ship) * Quantity
            $pft = ($fbPrice * 0.87 - $lp - $temuShip) * $quantity;
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
            'total_commission' => $totalCommission,
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

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalOrders, // 1 per order
            'total_revenue' => $totalSales,
            'total_sales' => $totalSales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalSales,
            'total_fees' => $totalFees,
            'net_proceeds' => $totalNetProceeds,
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

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalOrders, // 1 per order
            'total_revenue' => $totalSales,
            'total_sales' => $totalSales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalSales,
            'total_fees' => $totalFees,
            'net_proceeds' => $totalNetProceeds,
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
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
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
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
        ];
    }
}
