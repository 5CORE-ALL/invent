<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceDailyMetric;
use App\Models\EbayOrder;
use App\Models\Ebay2Order;
use App\Models\TemuAdData;
use App\Models\TemuCampaignReport;
use App\Models\Temu2CampaignReport;
use App\Models\Temu2DailyData;
use App\Models\TopDawgOrderMetric;
use App\Models\SheinDailyData;
use App\Models\MercariDailyData;
use App\Models\AliexpressDailyData;
use App\Models\ShopifyB2CDailyData;
use App\Models\ShopifyB2BDailyData;
use App\Models\TikTokDailyData;
use App\Models\TiktokSalesTwo;
use App\Models\DepopSheetData;
use App\Http\Controllers\MarketPlace\VintedController;
use App\Models\DepopSalesData;
use App\Models\VintedSalesData;
use App\Models\MiraklDailyData;
use App\Models\DobaDailyData;
use App\Models\WayfairDailyData;
use App\Models\FaireDailyData;
use App\Models\PurchasingPowerSale;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use App\Models\ChannelMaster;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Services\EbayChannelMetricsService;
use App\Services\SheinShopifySalesService;
use App\Services\TemuShopifySalesService;
use App\Models\AmazonOrder;
use App\Models\AmazonSpCampaignReport;
use App\Models\EbayPromotedListingReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateMarketplaceDailyMetrics extends Command
{
    protected $signature = 'app:update-marketplace-daily-metrics {--date= : Specific date to update (YYYY-MM-DD)}';
    protected $description = 'Update daily metrics for all marketplace channels';


    /**
     * Load ProductMaster in chunks of 50 (never Model::all()).
     */
    protected function productMastersChunked(): \Illuminate\Support\Collection
    {
        $all = collect();
        \App\Models\ProductMaster::query()
            ->orderBy('id')
            ->chunkById(50, function ($rows) use (&$all) {
                foreach ($rows as $row) {
                    $all->push($row);
                }
            });

        return $all;
    }

    /**
     * Load any Eloquent model in chunks of 50 (never Model::all()).
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    protected function modelsChunked(string $model): \Illuminate\Support\Collection
    {
        $all = collect();
        $model::query()
            ->orderBy('id')
            ->chunkById(50, function ($rows) use (&$all) {
                foreach ($rows as $row) {
                    $all->push($row);
                }
            });

        return $all;
    }




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
            'Temu 2' => fn() => $this->calculateTemu2Metrics($date),
            'Shein' => fn() => $this->calculateSheinMetrics($date),
            'Mercari With Ship' => fn() => $this->calculateMercariWithShipMetrics($date),
            'Mercari Without Ship' => fn() => $this->calculateMercariWithoutShipMetrics($date),
            'Purchasing Power' => fn() => $this->calculatePurchasingPowerMetrics($date),
            'AliExpress' => fn() => $this->calculateAliexpressMetrics($date),
            'Shopify B2C' => fn() => $this->calculateShopifyB2CMetrics($date),
            'Shopify B2B' => fn() => $this->calculateShopifyB2BMetrics($date),
            'TikTok' => fn() => $this->calculateTikTokMetrics($date),
            'TikTok 2' => fn() => $this->calculateTikTokTwoMetrics($date),
            'Best Buy USA' => fn() => $this->calculateBestBuyMetrics($date),
            'Macys' => fn() => $this->calculateMacysMetrics($date),
            'Doba' => fn() => $this->calculateDobaMetrics($date),
            'Walmart' => fn() => $this->calculateWalmartMetrics($date),
            'Wayfair' => fn() => $this->calculateWayfairMetrics($date),
            'TopDawg' => fn() => $this->calculateTopDawgMetrics($date),
            'Depop' => fn() => $this->calculateDepopMetrics($date),
            'Vinted' => fn() => $this->calculateVintedMetrics($date),
            'Faire' => fn() => $this->calculateFaireMetrics($date),
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
        $windowDays = AmazonSalesController::DAILY_SALES_WINDOW_DAYS;
        // Rolling window ending on *yesterday* Pacific (today is a partial day and is
        // excluded), IDENTICAL to AmazonSalesController::getData()/getAmazonChannelData().
        // Anchoring here (instead of $date/today) keeps /all-marketplace-master's Amazon
        // Gprofit% and PFT byte-identical with the /amazon/daily-sales page.
        $yesterdayPacific = Carbon::yesterday('America/Los_Angeles');
        $endOfDay = $yesterdayPacific->copy()->endOfDay();
        $startOfDay = $yesterdayPacific->copy()->subDays($windowDays - 1)->startOfDay();

        $totalRevenue = AmazonOrder::badgeTotalSalesByOrderDate($startOfDay, $endOfDay);

        // Get order rows for item-level metrics (COGS, profit, etc.)
        $orderRows = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startOfDay)
            ->where('o.order_date', '<=', $endOfDay)
            ->where(function ($q) {
                $q->whereNull('o.status')
                    ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
            })
            ->select([
                'o.amazon_order_id',
                'i.sku',
                'i.quantity',
                'i.price as line_price',
            ])
            ->get();

        if ($orderRows->isEmpty()) {
            return null;
        }

        // Key by exact SKU (case-sensitive), EXACTLY like AmazonSalesController::getData()
        // (ProductMaster::whereIn('sku', …)->keyBy('sku')). Uppercasing here matched extra
        // rows the daily-sales page does not, skewing PFT/COGS away from that page.
        $productMasters = $this->productMastersChunked()->keyBy('sku');

        $totalOrders = $orderRows->pluck('amazon_order_id')->unique()->count();
        $totalQuantity = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;
        // Sum of SKU-line sales — this is the GPFT% denominator on the /amazon/daily-sales
        // page (Σ sale_amount, i.e. line price), NOT the order-greatest badge total.
        // Keep this so /all-marketplace-master's Amazon Gprofit% matches that page exactly.
        $totalSkuLineSales = 0;

        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        $margin = ($percentage - $adUpdates) / 100;

        // Process each order line for item-level metrics (COGS, profit calculations)
        // Note: totalRevenue is already calculated from order totals above
        foreach ($orderRows as $row) {
            $quantity = (int) $row->quantity;
            $linePrice = (float) $row->line_price;
            $lineRevenue = AmazonOrder::salesTotalMode() === AmazonOrder::SALES_TOTAL_MODE_QTY_TIMES_PRICE
                ? $quantity * $linePrice
                : $linePrice;
            $unitPrice = $quantity > 0 ? $lineRevenue / $quantity : 0;

            $totalQuantity += $quantity;

            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Exact SKU (case-sensitive) — matches getData()'s $productMasters[$item->sku].
            $sku = $row->sku ?? '';

            // Accumulate SKU-line sales exactly like the /amazon/daily-sales badge:
            // only lines with a SKU and qty > 0 count toward the GPFT% denominator.
            if ($sku !== '' && $quantity > 0) {
                $totalSkuLineSales += round($lineRevenue, 2);
            }
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if ($sku !== '' && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (isset($values['lp'])) $lp = (float) $values['lp'];
                if (isset($values['ship'])) $ship = (float) $values['ship'];
                if (isset($values['wt_act'])) $weightAct = (float) $values['wt_act'];
            }

            $tWeight = $weightAct * $quantity;
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // Round per line before summing — matches getData()'s per-row round(…, 2)
            // whose rounded values the daily-sales badge then sums.
            $cogs = round($lp * $quantity, 2);
            $totalCogs += $cogs;
            $pftEach = ($unitPrice * 0.80) - $lp - $shipCost;
            $pft = round($pftEach * $quantity, 2);
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        // GPFT% denominator = Σ SKU-line sales (matches /amazon/daily-sales "GPFT %"),
        // NOT $totalRevenue (order-greatest badge total shown in the "Total Sales" badge).
        // This keeps /all-marketplace-master's Amazon Gprofit% identical to the daily-sales page.
        $pftPercentage = $totalSkuLineSales > 0 ? ($totalPft / $totalSkuLineSales) * 100 : 0;
        // ROI = (PFT / COGS) * 100 - but COGS is LP only
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Calculate KW Spent - use LATEST L30 row per campaign (MAX(id)) approach
        // Matches ChannelMasterController::fetchAdMetricsFromTables() exactly
        $kwLatestIds = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_date_range', 'L30')
            ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'")
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->groupBy('campaignName')
            ->pluck('id');
        // Fetch full data for KW campaigns (spend, clicks, sales, sold)
        $kwData = $kwLatestIds->isNotEmpty()
            ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $kwLatestIds)->get()
            : collect();
        $kwSpent = (float) $kwData->sum('spend');
        $kwClicks = (int) $kwData->sum('clicks');
        $kwSales = (float) $kwData->sum('sales7d');
        $kwSold = (int) $kwData->sum('purchases7d');

        // Calculate PT Spent - use LATEST L30 row per campaign (MAX(id)) approach
        $ptLatestIds = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_date_range', 'L30')
            ->where(function($query) {
                $query->whereRaw("campaignName LIKE '%PT'")
                    ->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->groupBy('campaignName')
            ->pluck('id');
        $ptData = $ptLatestIds->isNotEmpty()
            ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $ptLatestIds)->get()
            : collect();
        $ptSpent = (float) $ptData->sum('spend');
        $ptClicks = (int) $ptData->sum('clicks');
        $ptSales = (float) $ptData->sum('sales7d');
        $ptSold = (int) $ptData->sum('purchases7d');

        // Calculate HL Spent - use LATEST L30 row per campaign (MAX(id)) approach
        $hlLatestIds = DB::table('amazon_sb_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_date_range', 'L30')
            ->groupBy('campaignName')
            ->pluck('id');
        $hlData = $hlLatestIds->isNotEmpty()
            ? DB::table('amazon_sb_campaign_reports')->whereIn('id', $hlLatestIds)->get()
            : collect();
        $hlSpent = (float) $hlData->sum('cost');
        $hlClicks = (int) $hlData->sum('clicks');
        $hlSales = (float) $hlData->sum('sales');
        $hlSold = (int) $hlData->sum('purchases');

        // TACOS% denominator = Σ SKU-line sales, same as GPFT% above, so N PFT
        // (GPFT% − TACOS%) stays byte-identical with the /amazon/daily-sales page.
        $tacosPercentage = $totalSkuLineSales > 0 ? (($kwSpent + $ptSpent + $hlSpent) / $totalSkuLineSales) * 100 : 0;
        $nPft = $pftPercentage - $tacosPercentage;
        
        // N ROI = (Net Profit / COGS) * 100 where Net Profit = Gross Profit - Ad Spend
        $totalAdSpend = $kwSpent + $ptSpent + $hlSpent;
        $netProfit = $totalPft - $totalAdSpend;
        $nRoi = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

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
            'ads_percentage' => $tacosPercentage, // Add ads_percentage for Amazon (same as TACOS)
            'n_pft' => $nPft,
            'n_roi' => $nRoi,
            'kw_spent' => $kwSpent,
            'pmt_spent' => $ptSpent,
            'hl_spent' => $hlSpent,
            'extra_data' => [
                'kw_clicks' => $kwClicks, 'pt_clicks' => $ptClicks, 'hl_clicks' => $hlClicks,
                'kw_sales' => round($kwSales, 2), 'pt_sales' => round($ptSales, 2), 'hl_sales' => round($hlSales, 2),
                'kw_sold' => $kwSold, 'pt_sold' => $ptSold, 'hl_sold' => $hlSold,
            ],
        ];
    }

    /**
     * L30 aggregate for an eBay channel from the SAME rows its daily-sales page
     * builds (SalesController::getData). Guarantees marketplace_daily_metrics — and thus
     * the all-marketplace-master "active channel" row — matches the order page:
     *   - sales = Σ per-order total (tax-incl), once per order, excl. CANCELED/FULLY_REFUNDED
     *   - qty   = Σ item quantity, merch = Σ (qty × unit price) [GPFT denominator]
     *   - pft / cogs from ProductMaster LP/ship per order item (same as the page)
     */
    private function ebaySalesAggregate(string $salesControllerClass): array
    {
        $out = ['orders' => 0, 'sales' => 0.0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0, 'merch' => 0.0];
        try {
            $rows = app($salesControllerClass)->getData(request())->getData(true);
            if (!is_array($rows)) return $out;
            $seen = [];
            foreach ($rows as $r) {
                $sku = $r['sku'] ?? '';
                $orderId = $r['order_id'] ?? '';
                if ($sku === '' || $orderId === '') continue;
                if (!isset($seen[$orderId])) {
                    $seen[$orderId] = true;
                    $out['sales'] += (float) ($r['total_amount'] ?? 0);
                    $out['orders']++;
                }
                $q = (int) ($r['quantity'] ?? 0);
                $out['qty'] += $q;
                $out['pft'] += (float) ($r['pft'] ?? 0);
                $out['cogs'] += (float) ($r['cogs'] ?? 0);
                $out['merch'] += $q * (float) ($r['price'] ?? 0);
            }
        } catch (\Throwable $e) {
            \Log::warning("ebaySalesAggregate({$salesControllerClass}) failed: " . $e->getMessage());
        }
        return $out;
    }

    private function calculateEbayMetrics($date)
    {
        // Sales / Qty / PFT / COGS from the same real orders /ebay/daily-sales uses,
        // so the all-marketplace-master eBay row matches that page (tax-incl order totals,
        // excl. CANCELED + FULLY_REFUNDED). GPFT% uses merchandise sales as denominator
        // (same as the daily-sales page); TACOS uses the tax-inclusive order sales.
        $agg = $this->ebaySalesAggregate(\App\Http\Controllers\Sales\EbaySalesController::class);
        if ($agg['orders'] === 0) {
            return null;
        }

        $totalOrders = $agg['orders'];
        $totalQuantity = $agg['qty'];
        $totalRevenue = round($agg['merch'], 2);   // merchandise (GPFT% denominator)
        $orderSales = round($agg['sales'], 2);     // tax-incl order total (the "Sales" figure)
        $totalCogs = round($agg['cogs'], 2);
        $totalPft = round($agg['pft'], 2);

        $avgPrice = $totalQuantity > 0 ? $totalRevenue / $totalQuantity : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Calculate KW and PMT Spent for eBay
        // Sum daily reports (individual date report_ranges) instead of L30 aggregate
        // Daily data is closer to eBay Seller Hub dashboard values
        $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        // KW from ebay_priority_reports (CPC ads) - fetch all metrics using daily sum
        $kwRow = DB::table('ebay_priority_reports')
            ->where('report_range', '>=', $startDate)
            ->where('report_range', '<=', $endDate)
            ->where('report_range', 'NOT LIKE', 'L%')
            ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as spend,
                         COALESCE(SUM(cpc_clicks), 0) as clicks,
                         COALESCE(SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")), 0) as sales,
                         COALESCE(SUM(cpc_attributed_sales), 0) as sold')
            ->first();
        $kwSpent = (float) ($kwRow->spend ?? 0);
        $kwClicks = (int) ($kwRow->clicks ?? 0);
        $kwSales = (float) ($kwRow->sales ?? 0);
        $kwSold = (int) ($kwRow->sold ?? 0);

        // PMT from ebay_general_reports (Promoted Listing ads) - fetch all metrics using daily sum
        $pmtRow = DB::table('ebay_general_reports')
            ->where('report_range', '>=', $startDate)
            ->where('report_range', '<=', $endDate)
            ->where('report_range', 'NOT LIKE', 'L%')
            ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")), 0) as spend,
                         COALESCE(SUM(clicks), 0) as clicks,
                         COALESCE(SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")), 0) as sales,
                         COALESCE(SUM(sales), 0) as sold')
            ->first();
        $pmtSpent = (float) ($pmtRow->spend ?? 0);
        $pmtClicks = (int) ($pmtRow->clicks ?? 0);
        $pmtSales = (float) ($pmtRow->sales ?? 0);
        $pmtSold = (int) ($pmtRow->sold ?? 0);

        $tacosPercentage = $orderSales > 0 ? (($kwSpent + $pmtSpent) / $orderSales) * 100 : 0;
        $nPft = $pftPercentage - $tacosPercentage;
        
        // N ROI = (Net Profit / COGS) * 100 where Net Profit = Gross Profit - Ad Spend
        $totalAdSpend = $kwSpent + $pmtSpent;
        $netProfit = $totalPft - $totalAdSpend;
        $nRoi = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $orderSales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $orderSales,
            'tacos_percentage' => $tacosPercentage,
            'ads_percentage' => $tacosPercentage, // Add ads_percentage for eBay (same as TACOS)
            'n_pft' => $nPft,
            'n_roi' => $nRoi,
            'kw_spent' => $kwSpent,
            'pmt_spent' => $pmtSpent,
            'extra_data' => [
                'kw_clicks' => $kwClicks, 'pmt_clicks' => $pmtClicks,
                'kw_sales' => round($kwSales, 2), 'pmt_sales' => round($pmtSales, 2),
                'kw_sold' => $kwSold, 'pmt_sold' => $pmtSold,
            ],
        ];
    }

    private function calculateEbay2Metrics($date)
    {
        // Sales / Qty / PFT / COGS from the same real orders /ebay2/daily-sales uses,
        // so the all-marketplace-master eBay 2 row matches that page (tax-incl order totals,
        // excl. CANCELED + FULLY_REFUNDED). GPFT% uses merchandise sales as denominator.
        $agg = $this->ebaySalesAggregate(\App\Http\Controllers\Sales\Ebay2SalesController::class);
        if ($agg['orders'] === 0) {
            return null;
        }

        $totalOrders = $agg['orders'];
        $totalQuantity = $agg['qty'];
        $totalRevenue = round($agg['merch'], 2);   // merchandise (GPFT% denominator)
        $orderSales = round($agg['sales'], 2);     // tax-incl order total (the "Sales" figure)
        $totalCogs = round($agg['cogs'], 2);
        $totalPft = round($agg['pft'], 2);

        $avgPrice = $totalQuantity > 0 ? $totalRevenue / $totalQuantity : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Calculate ad spend for eBay 2
        // Sum daily reports instead of L30 aggregate — closer to eBay dashboard values
        $startDate2 = Carbon::now()->subDays(31)->format('Y-m-d');
        $endDate2 = Carbon::now()->format('Y-m-d');

        // KW from ebay_2_priority_reports (CPC ads) - fetch all metrics using daily sum
        $kwRow = DB::table('ebay_2_priority_reports')
            ->where('report_range', '>=', $startDate2)
            ->where('report_range', '<=', $endDate2)
            ->where('report_range', 'NOT LIKE', 'L%')
            ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as spend,
                         COALESCE(SUM(cpc_clicks), 0) as clicks,
                         COALESCE(SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")), 0) as sales,
                         COALESCE(SUM(cpc_attributed_sales), 0) as sold')
            ->first();
        $kwSpent = (float) ($kwRow->spend ?? 0);
        $kwClicks = (int) ($kwRow->clicks ?? 0);
        $kwSales = (float) ($kwRow->sales ?? 0);
        $kwSold = (int) ($kwRow->sold ?? 0);

        // PMT from ebay_2_general_reports (Promoted Listings) - fetch all metrics using daily sum
        $pmtRow = DB::table('ebay_2_general_reports')
            ->where('report_range', '>=', $startDate2)
            ->where('report_range', '<=', $endDate2)
            ->where('report_range', 'NOT LIKE', 'L%')
            ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")), 0) as spend,
                         COALESCE(SUM(clicks), 0) as clicks,
                         COALESCE(SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")), 0) as sales,
                         COALESCE(SUM(sales), 0) as sold')
            ->first();
        $pmtSpent = (float) ($pmtRow->spend ?? 0);
        $pmtClicks = (int) ($pmtRow->clicks ?? 0);
        $pmtSales = (float) ($pmtRow->sales ?? 0);
        $pmtSold = (int) ($pmtRow->sold ?? 0);

        $tacosPercentage = $orderSales > 0 ? (($kwSpent + $pmtSpent) / $orderSales) * 100 : 0;
        $nPft = $pftPercentage - $tacosPercentage;
        
        // N ROI = (Net Profit / COGS) * 100 where Net Profit = Gross Profit - Ad Spend
        $totalAdSpend = $kwSpent + $pmtSpent;
        $netProfit = $totalPft - $totalAdSpend;
        $nRoi = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $orderSales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $orderSales,
            'tacos_percentage' => $tacosPercentage,
            'ads_percentage' => $tacosPercentage, // Add ads_percentage for eBay (same as TACOS)
            'n_pft' => $nPft,
            'n_roi' => $nRoi,
            'kw_spent' => $kwSpent,
            'pmt_spent' => $pmtSpent,
            'extra_data' => [
                'kw_clicks' => $kwClicks, 'pmt_clicks' => $pmtClicks,
                'kw_sales' => round($kwSales, 2), 'pmt_sales' => round($pmtSales, 2),
                'kw_sold' => $kwSold, 'pmt_sold' => $pmtSold,
            ],
        ];
    }

    private function calculateEbay3Metrics($date)
    {
        // Sales / Qty / PFT / COGS from the same real orders /ebay3/daily-sales uses,
        // so the all-marketplace-master eBay 3 row matches that page (tax-incl order totals,
        // excl. CANCELED + FULLY_REFUNDED). GPFT% uses merchandise sales as denominator.
        $agg = $this->ebaySalesAggregate(\App\Http\Controllers\Sales\Ebay3SalesController::class);
        if ($agg['orders'] === 0) {
            return null;
        }

        $totalOrders = $agg['orders'];
        $totalQuantity = $agg['qty'];
        $totalRevenue = round($agg['merch'], 2);   // merchandise (GPFT% denominator)
        $orderSales = round($agg['sales'], 2);     // tax-incl order total (the "Sales" figure)
        $totalCogs = round($agg['cogs'], 2);
        $totalPft = round($agg['pft'], 2);

        $avgPrice = $totalQuantity > 0 ? $totalRevenue / $totalQuantity : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Calculate KW and PMT Spent for eBay 3
        // Sum daily reports instead of L30 aggregate — closer to eBay dashboard values
        $startDate3 = Carbon::now()->subDays(31)->format('Y-m-d');
        $endDate3 = Carbon::now()->format('Y-m-d');
        
        // KW from ebay_3_priority_reports (CPC ads) - fetch all metrics using daily sum
        $kwRow = DB::table('ebay_3_priority_reports')
            ->where('report_range', '>=', $startDate3)
            ->where('report_range', '<=', $endDate3)
            ->where('report_range', 'NOT LIKE', 'L%')
            ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as spend,
                         COALESCE(SUM(cpc_clicks), 0) as clicks,
                         COALESCE(SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")), 0) as sales,
                         COALESCE(SUM(cpc_attributed_sales), 0) as sold')
            ->first();
        $kwSpent = (float) ($kwRow->spend ?? 0);
        $kwClicks = (int) ($kwRow->clicks ?? 0);
        $kwSales = (float) ($kwRow->sales ?? 0);
        $kwSold = (int) ($kwRow->sold ?? 0);

        // PMT from ebay_3_general_reports (Promoted Listings) - fetch all metrics using daily sum
        $pmtRow = DB::table('ebay_3_general_reports')
            ->where('report_range', '>=', $startDate3)
            ->where('report_range', '<=', $endDate3)
            ->where('report_range', 'NOT LIKE', 'L%')
            ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")), 0) as spend,
                         COALESCE(SUM(clicks), 0) as clicks,
                         COALESCE(SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")), 0) as sales,
                         COALESCE(SUM(sales), 0) as sold')
            ->first();
        $pmtSpent = (float) ($pmtRow->spend ?? 0);
        $pmtClicks = (int) ($pmtRow->clicks ?? 0);
        $pmtSales = (float) ($pmtRow->sales ?? 0);
        $pmtSold = (int) ($pmtRow->sold ?? 0);

        $tacosPercentage = $orderSales > 0 ? (($kwSpent + $pmtSpent) / $orderSales) * 100 : 0;
        $nPft = $pftPercentage - $tacosPercentage;
        
        // N ROI = (Net Profit / COGS) * 100 where Net Profit = Gross Profit - Ad Spend
        $totalAdSpend = $kwSpent + $pmtSpent;
        $netProfit = $totalPft - $totalAdSpend;
        $nRoi = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $orderSales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'l30_sales' => $orderSales,
            'tacos_percentage' => $tacosPercentage,
            'ads_percentage' => $tacosPercentage, // Add ads_percentage for eBay (same as TACOS)
            'n_pft' => $nPft,
            'n_roi' => $nRoi,
            'kw_spent' => $kwSpent,
            'pmt_spent' => $pmtSpent,
            'extra_data' => [
                'kw_clicks' => $kwClicks, 'pmt_clicks' => $pmtClicks,
                'kw_sales' => round($kwSales, 2), 'pmt_sales' => round($pmtSales, 2),
                'kw_sold' => $kwSold, 'pmt_sold' => $pmtSold,
            ],
        ];
    }

    private function calculateTemuMetrics($date)
    {
        // Source: temu_orders table (Temu API order-wise data), last 30 days.
        $start = Carbon::now()->subDays(30)->startOfDay();
        $end = Carbon::now()->endOfDay();
        $m = TemuShopifySalesService::computeMetricsFromOrders($start, $end);

        if ($m['sales'] <= 0 && $m['qty'] <= 0) {
            return null;
        }

        $totalL30Sales = $m['sales'];
        $totalPft = $m['pft'];
        $totalCogs = $m['cogs'];
        $pftPercentage = $totalL30Sales > 0 ? ($totalPft / $totalL30Sales) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        $temuSpent = TemuCampaignReport::where('report_range', 'L30')
            ->selectRaw('SUM(spend) as total_spend')
            ->value('total_spend') ?? 0;

        $tacosPercentage = $totalL30Sales > 0 ? ($temuSpent / $totalL30Sales) * 100 : 0;
        $nPftPercentage = $pftPercentage - $tacosPercentage;
        $netProfit = $totalPft - $temuSpent;
        $nRoiPercentage = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

        return [
            'total_orders' => $m['orders'],
            'total_quantity' => $m['qty'],
            'total_revenue' => $totalL30Sales,
            'total_sales' => $totalL30Sales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $m['qty'] > 0 ? round($totalL30Sales / $m['qty'], 2) : 0,
            'l30_sales' => $totalL30Sales,
            'kw_spent' => round($temuSpent, 2),
            'pmt_spent' => 0,
            'tacos_percentage' => round($tacosPercentage, 1),
            'ads_percentage' => round($tacosPercentage, 1),
            'n_pft' => round($nPftPercentage, 1),
            'n_roi' => round($nRoiPercentage, 1),
        ];
    }

    /**
     * Temu 2: mirrors temu2_tabulator_view badges and getTemu2DailyData filtering.
     * Filters to ProductMaster SKUs, applies fbPrice (+$2.99 if order total < $27),
     * and reads margin from marketplace_percentages (Temu 2).
     * Ad spend from temu2_campaign_reports (L30 upload on /temu2/ads).
     */
    private function calculateTemu2Metrics($date)
    {
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim((string) $sku));
            $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
            $sku = preg_replace('/\s+/', ' ', $sku);
            return $sku;
        };

        // ProductMaster SKU universe (same as temu2_tabulator/getTemu2DailyData)
        $productMasterSkus = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->pluck('sku')
            ->filter(function ($sku) {
                return stripos($sku, 'PARENT') === false;
            })
            ->unique()
            ->values()
            ->all();

        // Mirror temu2-tabulator's salesSummary loop exactly: normalized match first,
        // then no-space fallback. Same divergence story as Temu — without the fallback
        // and without dropping the redundant "PARENT" check, the L30 stored here drifts
        // from the badge on /temu2-tabulator.
        $normalizedPmSet = collect($productMasterSkus)->mapWithKeys(function ($s) use ($normalizeSku) {
            return [$normalizeSku($s) => true];
        })->all();
        $noSpaceToNormalized = [];
        foreach (array_keys($normalizedPmSet) as $nk) {
            $noSpace = str_replace(' ', '', $nk);
            if ($noSpace !== '') {
                $noSpaceToNormalized[$noSpace] = $nk;
            }
        }

        $data = $this->modelsChunked(Temu2DailyData::class);

        if ($data->isEmpty()) {
            return null;
        }

        $allPms = $this->productMastersChunked();
        $productMastersBySku = $allPms->keyBy('sku');
        $productMastersByNormalized = $allPms->keyBy(function ($pm) use ($normalizeSku) {
            return $normalizeSku($pm->sku ?? '');
        });
        $productMastersByNoSpace = $allPms->keyBy(function ($pm) use ($normalizeSku) {
            return str_replace(' ', '', $normalizeSku($pm->sku ?? ''));
        });

        // Read Temu 2 margin from marketplace_percentages (no hardcoded fallback)
        $percentage = \App\Services\TemuShopifySalesService::temu2MarginDecimal();

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalL30Sales = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($data as $row) {
            $rawSku  = trim((string) ($row->contribution_sku ?? ''));
            $orderId = trim((string) ($row->order_id ?? ''));
            if ($rawSku === '' || $orderId === '') {
                continue;
            }

            $normalizedRowSku        = $normalizeSku($rawSku);
            $normalizedRowSkuNoSpace = str_replace(' ', '', $normalizedRowSku);
            if (!isset($normalizedPmSet[$normalizedRowSku])
                && !isset($noSpaceToNormalized[$normalizedRowSkuNoSpace])) {
                continue;
            }

            $pm = $productMastersBySku[$rawSku]
                ?? $productMastersByNormalized[$normalizedRowSku]
                ?? $productMastersByNoSpace[$normalizedRowSkuNoSpace]
                ?? null;

            $totalOrders++;
            $quantity  = (int)   ($row->quantity_purchased ?? 0);
            $basePrice = (float) ($row->base_price_total ?? 0);
            $totalQuantity += $quantity;

            // FB Prc: +$2.99 when per-unit base price ≤ $26.99 (matches /temu-decrease and /temu-tabulator).
            $fbPrice = $basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice;
            $totalRevenue  += $fbPrice * $quantity; // match tabulator: revenue uses fbPrice
            $totalL30Sales += $fbPrice * $quantity;

            if ($quantity > 0 && $basePrice > 0) {
                $totalWeightedPrice += $basePrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $lp = 0;
            $temuShip = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                if (isset($values['temu_ship'])) {
                    $temuShip = floatval($values['temu_ship']);
                } elseif (isset($pm->temu_ship)) {
                    $temuShip = floatval($pm->temu_ship);
                }
            }

            if ($quantity > 0 && $basePrice > 0) {
                $pftDecimal = $fbPrice > 0 ? ($fbPrice * $percentage - $lp - $temuShip) / $fbPrice : 0;
                $totalPft  += $pftDecimal * $fbPrice * $quantity;
                $totalCogs += $lp * $quantity;
            }
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalL30Sales > 0 ? ($totalPft / $totalL30Sales) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Temu 2: L30 spend from temu2_campaign_reports (/temu2/ads upload)
        $temu2Spent = (float) (Temu2CampaignReport::where('report_range', 'L30')->sum('spend') ?? 0);
        $tacosPercentage = $totalL30Sales > 0 ? ($temu2Spent / $totalL30Sales) * 100 : 0;
        $nPftPercentage = $pftPercentage - $tacosPercentage;
        $netProfit = $totalPft - $temu2Spent;
        $nRoiPercentage = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

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
            'kw_spent' => round($temu2Spent, 2),
            'pmt_spent' => 0,
            'tacos_percentage' => round($tacosPercentage, 1),
            'ads_percentage' => round($tacosPercentage, 1),
            'n_pft' => round($nPftPercentage, 1),
            'n_roi' => round($nRoiPercentage, 1),
        ];
    }

    /**
     * TopDawg: from topdawg_order_metrics.
     * PFT = (price * margin - lp) * quantity; margin from marketplace_percentages, no ship. No ad spend.
     */
    private function calculateTopDawgMetrics($date)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('topdawg_order_metrics')) {
            return null;
        }

        $data = $this->modelsChunked(TopDawgOrderMetric::class);
        if ($data->isEmpty()) {
            return null;
        }

        $pct = MarketplacePercentage::where('marketplace', 'TopDawg')->value('percentage');
        $percentage = $pct !== null ? (float) $pct : 95.0;
        if ($percentage <= 0) {
            $percentage = 95.0;
        }
        $margin = $percentage / 100.0;

        $productMastersBySku = $this->productMastersChunked()->keyBy('sku');
        $productMastersByNormalized = $this->productMastersChunked()->keyBy(function ($pm) {
            $sku = strtoupper(trim((string) ($pm->sku ?? '')));
            $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
            $sku = preg_replace('/\s+/', ' ', $sku);
            return $sku;
        });

        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim((string) $sku));
            $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
            $sku = preg_replace('/\s+/', ' ', $sku);
            return $sku;
        };

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalL30Sales = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($data as $row) {
            $sku = $row->sku ?? '';
            if (trim($sku) === '') {
                continue;
            }

            $pm = $productMastersBySku[$sku] ?? $productMastersByNormalized[$normalizeSku($sku)] ?? null;
            $lp = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values ?? null) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
                    foreach ($values as $k => $v) {
                        if (strtolower((string) $k) === 'lp') {
                            $lp = (float) $v;
                            break;
                        }
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }
            }

            $amount = (float) ($row->amount ?? 0);
            $quantity = (int) ($row->quantity ?? 1);
            $quantity = $quantity >= 1 ? $quantity : 1;
            $unitPrice = $quantity > 0 ? $amount / $quantity : 0;

            $totalOrders++;
            $totalQuantity += $quantity;
            $totalRevenue += $amount;
            if ($quantity > 0 && $amount > 0) {
                $totalWeightedPrice += $amount;
                $totalQuantityForPrice += $quantity;
            }

            $cogs = $lp * $quantity;
            $pft = ($unitPrice * $margin - $lp) * $quantity;
            $totalPft += $pft;
            $totalL30Sales += $amount;
            $totalCogs += $cogs;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalL30Sales > 0 ? ($totalPft / $totalL30Sales) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;
        $topdawgSpent = 0;
        $tacosPercentage = $totalL30Sales > 0 ? ($topdawgSpent / $totalL30Sales) * 100 : 0;
        $nPftPercentage = $pftPercentage - $tacosPercentage;
        // Calculate N ROI: (Net Profit / COGS) * 100 where Net Profit = Gross Profit - Ad Spend
        $netProfit = $totalPft - $topdawgSpent;
        $nRoiPercentage = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

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
            'kw_spent' => round($topdawgSpent, 2),
            'pmt_spent' => 0,
            'tacos_percentage' => round($tacosPercentage, 1),
            'ads_percentage' => round($tacosPercentage, 1), // Add ads_percentage for TopDawg (same as TACOS)
            'n_pft' => round($nPftPercentage, 1),
            'n_roi' => round($nRoiPercentage, 1),
        ];
    }

    private function calculateSheinMetrics($date)
    {
        // L30 from Shopify Sen Shp (same source as /shein-tabulator).
        // Previous logic read app/uploads-only shein_daily_data which no longer reflects live sales.
        [$start, $end] = SheinShopifySalesService::tabulatorL30Window();
        $summary = SheinShopifySalesService::computeChannelSummary($start, $end);

        if ($summary['total_orders'] === 0 && $summary['total_sales'] <= 0.00001) {
            return null;
        }

        // Shein has no ads, so N ROI = G ROI and N PFT = G PFT
        return [
            'total_orders' => $summary['total_orders'],
            'total_quantity' => $summary['total_quantity'],
            'total_revenue' => $summary['total_sales'],
            'total_sales' => $summary['total_sales'],
            'total_cogs' => $summary['total_cogs'],
            'total_pft' => $summary['total_pft'],
            'pft_percentage' => round($summary['pft_percentage'], 1),
            'roi_percentage' => round($summary['roi_percentage'], 1),
            'avg_price' => $summary['avg_price'],
            'l30_sales' => $summary['total_sales'],
            'total_commission' => $summary['total_commission'],
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $summary['total_pft'],
            'n_roi' => round($summary['roi_percentage'], 1),
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
        $productMastersBySku = $this->productMastersChunked()->mapWithKeys(function($pm) {
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
        $productMastersBySku = $this->productMastersChunked()->mapWithKeys(function($pm) {
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
        $data = $this->modelsChunked(AliexpressDailyData::class);

        if ($data->isEmpty()) {
            return null;
        }

        $productMasters = $this->productMastersChunked()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Margin source MUST match AliexpressController::getDailyData() and
        // aggregateAliexpressOrderRows() so the snapshot value stays in sync
        // with the live /aliexpress-tabulator and /all-marketplace-master pages.
        // Previously this read ChannelMaster.channel_percentage which could
        // silently drift from MarketplacePercentage.percentage.
        $mpRow = MarketplacePercentage::where('marketplace', 'Aliexpress')->first();
        $percentage = $mpRow !== null ? (float) ($mpRow->percentage ?? 100) : 100.0;
        if ($percentage <= 0) {
            $percentage = 89.0;
        }
        $margin = $percentage / 100; // Convert % to fraction (matches Aliexpress daily tabulator)

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
            $quantity = max(1, (int) ($row->quantity ?? 1));
            // Line revenue: same order as AliExpress tabulator summary (product_total → supply_price → order_amount)
            $lineRevenue = (float) ($row->product_total ?? 0);
            if ($lineRevenue <= 0) {
                $lineRevenue = (float) ($row->supply_price ?? 0);
            }
            if ($lineRevenue <= 0) {
                $lineRevenue = (float) ($row->order_amount ?? 0);
            }

            $totalQuantity += $quantity;
            $totalRevenue += $lineRevenue;

            $unitPrice = $quantity > 0 ? $lineRevenue / $quantity : 0.0;
            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
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

            // Calculate PFT: (Unit Price × Margin - LP - Ship) × Quantity (same as /aliexpress/daily-data)
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

        $productMasters = $this->productMastersChunked()->keyBy(function ($item) {
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

        // Calculate Google Ads Spent for Shopify B2C (L30)
        $yesterday = Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(29); // 30 days total
        
        // G-SHOP (Shopping campaigns) - fetch all metrics
        $shoppingRow = DB::table('google_ads_campaigns')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $yesterday)
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereIn('campaign_status', ['ENABLED'])
            ->selectRaw('COALESCE(SUM(metrics_cost_micros), 0) as spend_micros,
                         COALESCE(SUM(metrics_clicks), 0) as clicks,
                         COALESCE(SUM(ga4_ad_sales), 0) as sales,
                         COALESCE(SUM(ga4_sold_units), 0) as sold')
            ->first();
        $shoppingSpent = (float) ($shoppingRow->spend_micros ?? 0) / 1000000;
        $shoppingClicks = (int) ($shoppingRow->clicks ?? 0);
        $shoppingSales = (float) ($shoppingRow->sales ?? 0);
        $shoppingSold = (int) ($shoppingRow->sold ?? 0);

        // G-SERP (Search campaigns) - fetch all metrics
        $serpRow = DB::table('google_ads_campaigns')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $yesterday)
            ->where('advertising_channel_type', 'SEARCH')
            ->whereIn('campaign_status', ['ENABLED', 'PAUSED'])
            ->selectRaw('COALESCE(SUM(metrics_cost_micros), 0) as spend_micros,
                         COALESCE(SUM(metrics_clicks), 0) as clicks,
                         COALESCE(SUM(ga4_ad_sales), 0) as sales,
                         COALESCE(SUM(ga4_sold_units), 0) as sold')
            ->first();
        $serpSpent = (float) ($serpRow->spend_micros ?? 0) / 1000000;
        $serpClicks = (int) ($serpRow->clicks ?? 0);
        $serpSales = (float) ($serpRow->sales ?? 0);
        $serpSold = (int) ($serpRow->sold ?? 0);

        $totalGoogleSpent = $shoppingSpent + $serpSpent;

        // Calculate TACOS %: (Total Google Spent / Total Sales) * 100
        $tacosPercentage = $totalRevenue > 0 ? ($totalGoogleSpent / $totalRevenue) * 100 : 0;

        // Calculate N PFT: GPFT % - TACOS %
        $nPftPercentage = $pftPercentage - $tacosPercentage;

        // Calculate N ROI: (Net Profit / COGS) * 100 where Net Profit = Gross Profit - Ad Spend
        $netProfit = $totalPft - $totalGoogleSpent;
        $nRoiPercentage = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

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
            'kw_spent' => round($shoppingSpent, 2), // G-SHOP spend
            'pmt_spent' => round($serpSpent, 2),     // G-SERP spend
            'tacos_percentage' => round($tacosPercentage, 1),
            'n_pft' => round($nPftPercentage, 1),
            'n_roi' => round($nRoiPercentage, 1),
            'extra_data' => [
                'shopping_clicks' => $shoppingClicks, 'serp_clicks' => $serpClicks,
                'shopping_sales' => round($shoppingSales, 2), 'serp_sales' => round($serpSales, 2),
                'shopping_sold' => $shoppingSold, 'serp_sold' => $serpSold,
            ],
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

        $productMasters = $this->productMastersChunked()->keyBy(function ($item) {
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

            // PFT Each = (price * 0.95) - lp  (B2B excludes Ship; same as Business Analytics)
            $pftEach = ($price * $margin) - $lp;

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
        // L30 from tiktok_orders — last 30 California calendar days
        [$startDate, $endDate] = \App\Models\TiktokOrder::californiaDaysWindow(30);
        $orderItems = \App\Models\TiktokOrder::linesInWindow($startDate, $endDate);

        if ($orderItems->isEmpty()) {
            return null;
        }

        $productMasters = $this->productMastersChunked()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;
        $margin = 0.80;
        $seenOrders = [];

        foreach ($orderItems as $item) {
            $seenOrders[$item->order_id] = true;
            $totalOrders++;

            $quantity = (int) ($item->quantity ?? 1);
            if ($quantity <= 0) {
                continue;
            }

            $unitPrice = (float) ($item->sale_price ?? 0);
            $totalPrice = $unitPrice * $quantity;

            $totalQuantity += $quantity;
            $totalRevenue += $totalPrice;

            if ($unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $sku = strtoupper(trim((string) ($item->seller_sku ?? '')));
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if ($sku && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                if (isset($values['ship'])) {
                    $ship = floatval($values['ship']);
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
                if (isset($values['wt_act'])) {
                    $weightAct = floatval($values['wt_act']);
                }
            }

            $tWeight = $weightAct * $quantity;
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            $cogs = $lp * $quantity;
            $totalCogs += $cogs;
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
            $totalPft += $pftEach * $quantity;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        return [
            'total_orders' => count($seenOrders),
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

    private function calculateTikTokTwoMetrics($date)
    {
        // L30 from tiktok_sales_two: order_date in last 30 days ending on $date
        $endDate = Carbon::parse($date)->endOfDay();
        $startDate = Carbon::parse($date)->subDays(29)->startOfDay();

        $rows = TiktokSalesTwo::whereBetween('order_date', [$startDate, $endDate])->get();
        if ($rows->isEmpty()) {
            return null;
        }

        $productMasters = $this->productMastersChunked()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $margin = 0.80; // 80% margin (same as TikTok)
        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalPft = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($rows as $row) {
            $quantity = (int) ($row->quantity ?: 1);
            $unitPrice = (float) $row->unit_price;
            $saleAmount = $unitPrice * $quantity;

            $totalOrders++;
            $totalQuantity += $quantity;
            $totalRevenue += $saleAmount;

            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $sku = strtoupper($row->seller_sku ?? '');
            $lp = 0;
            $ship = 0;
            $weightAct = 0;

            if ($sku && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = floatval($v);
                        break;
                    }


                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                if (isset($values['ship'])) {
                    $ship = floatval($values['ship']);
                } elseif (isset($pm->ship)) {
                    $ship = floatval($pm->ship);
                }
                if (isset($values['wt_act'])) {
                    $weightAct = floatval($values['wt_act']);
                }
            }

            $tWeight = $weightAct * $quantity;
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            $cogs = $lp * $quantity;
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
            $pft = $pftEach * $quantity;

            $totalCogs += $cogs;
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
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => $roiPercentage,
        ];
    }

    private function calculateDepopMetrics($date)
    {
        $endDate = Carbon::parse($date)->endOfDay();
        $startDate = Carbon::parse($date)->subDays(29)->startOfDay();

        $rows = DepopSalesData::whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->get();
        if ($rows->isEmpty()) {
            return null;
        }

        $margin = 0.87; // 87% margin for Depop — no SKU / Product Master, sales only
        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($rows as $row) {
            $quantity = (int) ($row->quantity ?: 1);
            $unitPrice = (float) $row->item_price;
            $saleAmount = $unitPrice * $quantity;

            $totalOrders++;
            $totalQuantity += $quantity;
            $totalRevenue += $saleAmount;

            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }
        }

        $totalPft = $totalRevenue * $margin;
        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => 0,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => 0,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => 0,
        ];
    }

    private function calculateVintedMetrics($date)
    {
        $endDate = Carbon::parse($date)->endOfDay();
        $startDate = Carbon::parse($date)->subDays(29)->startOfDay();

        $rows = VintedSalesData::whereBetween('sale_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->get();
        if ($rows->isEmpty()) {
            return null;
        }

        // Margin from marketplace_percentages where marketplace = Vinted
        $margin = VintedController::marginFactor();
        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($rows as $row) {
            $quantity = (int) ($row->quantity ?: 1);
            $unitPrice = (float) $row->item_price;
            $saleAmount = $unitPrice * $quantity;

            $totalOrders++;
            $totalQuantity += $quantity;
            $totalRevenue += $saleAmount;

            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }
        }

        $totalPft = $totalRevenue * $margin;
        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => 0,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => 0,
            'avg_price' => $avgPrice,
            'l30_sales' => $totalRevenue,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'n_pft' => $totalPft,
            'n_roi' => 0,
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

        $productMasters = $this->productMastersChunked()->keyBy(function ($item) {
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
            // Skip rules MUST match the live /bestbuy/daily-sales JS aggregation
            // (resources/views/sales/bestbuy_daily_sales_data.blade.php updateSummary),
            // otherwise the snapshot's GPFT% / ROI% will drift from the page badges.
            if (!$order->sku || $order->sku === '') continue;
            if (!$order->order_id || $order->order_id === '') continue;

            $quantity = (int) ($order->quantity ?? 1);
            $unitPrice = (float) ($order->unit_price ?? 0);

            if ($quantity === 0) continue;

            $totalOrders++;
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
                
                // Get Ship — BestBuy uses channel-specific Ship BB (Values['ship_bb']),
                // matching /bestbuy-pricing. Falls back to pm->ship_bb column, then 0.
                if (isset($values['ship_bb'])) {
                    $ship = (float) $values['ship_bb'];
                } elseif (isset($pm->ship_bb)) {
                    $ship = floatval($pm->ship_bb);
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

        $productMasters = $this->productMastersChunked()->keyBy(function ($item) {
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

    private function calculateDobaMetrics($date)
    {
        // L30 from doba_daily_data (period stored lowercase by doba:daily), cancelled excluded
        $orders = DobaDailyData::whereRaw('LOWER(period) = ?', ['l30'])
            ->whereNotIn('order_status', ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'])
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
        // 31 days: Get latest Walmart order date from walmart_daily_data (same as Sales page)
        // Uses latest date in DB and goes back 31 days
        $latestDate = \App\Models\WalmartDailyData::max('order_date');

        if (!$latestDate) {
            return null;
        }

        $latestDateCarbon = Carbon::parse($latestDate);
        // Get 31 days from latest order date
        $endDate = $latestDateCarbon->endOfDay(); // Latest date in DB
        $startDate = $latestDateCarbon->copy()->subDays(30)->startOfDay(); // 31 days total

        // Get Walmart orders from walmart_daily_data (same as Sales page)
        $orders = \App\Models\WalmartDailyData::where('period', 'l30')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('fulfillment_option', 'DELIVERY')
            ->where('status', '!=', 'Cancelled')
            ->select([
                DB::raw("COALESCE(customer_order_id, purchase_order_id, CONCAT('WM-', id)) as order_id"),
                'order_date',
                'sku',
                'quantity',
                'unit_price as price',
            ])
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $productMasters = $this->productMastersChunked()->keyBy(function ($item) {
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

        // Process order items from walmart_daily_data (same as Sales page)
        foreach ($orders as $order) {
            $sku = strtoupper(trim($order->sku ?? ''));
            $quantity = (int) ($order->quantity ?? 1);
            $unitPrice = (float) ($order->price ?? 0);
            
            // Calculate sale amount from unit price and quantity
            $saleAmount = $unitPrice * $quantity;
            
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

        // Get Walmart ad spend (L30) - use MAX per campaign to avoid duplicates
        // Filter by recently updated records (within last 2 hours) to match current Google Sheet data
        $walmartSpentData = DB::table('walmart_campaign_reports')
            ->selectRaw('campaignName, MAX(spend) as max_spend')
            ->where('report_range', 'L30')
            ->where('updated_at', '>=', Carbon::now()->subHours(2))
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->groupBy('campaignName')
            ->get();
        
        // If no recent records found, try without the updated_at filter (fallback)
        if ($walmartSpentData->isEmpty()) {
            $walmartSpentData = DB::table('walmart_campaign_reports')
                ->selectRaw('campaignName, MAX(spend) as max_spend')
                ->where('report_range', 'L30')
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->groupBy('campaignName')
                ->get();
        }
        
        $walmartSpent = $walmartSpentData->sum('max_spend') ?? 0;
        
        // Calculate TACOS %: (Walmart Spent / Total Sales) * 100
        $tacosPercentage = $totalRevenue > 0 ? ($walmartSpent / $totalRevenue) * 100 : 0;
        
        // Calculate N PFT: GPFT % - TACOS %
        $nPftPercentage = $pftPercentage - $tacosPercentage;
        
        // Calculate N ROI: (Net Profit / COGS) * 100 where Net Profit = Gross Profit - Ad Spend
        $netProfit = $totalPft - $walmartSpent;
        $nRoiPercentage = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

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
            'kw_spent' => round($walmartSpent, 2),
            'pmt_spent' => 0, // Walmart doesn't have separate PMT (all in kw_spent)
            'tacos_percentage' => round($tacosPercentage, 1),
            'n_pft' => round($nPftPercentage, 1),
            'n_roi' => round($nRoiPercentage, 1),
        ];
    }

    private function calculateWayfairMetrics($date)
    {
        // Get Wayfair data from wayfair_daily_data table (period = 'l30')
        $wayfairData = WayfairDailyData::where('period', 'l30')->get();

        if ($wayfairData->isEmpty()) {
            return null;
        }

        // Calculate metrics
        $totalOrders = $wayfairData->count();
        $totalQuantity = $wayfairData->sum('quantity');

        // Calculate revenue and other metrics
        $totalRevenue = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;
        $totalPft = 0;
        $totalCogs = 0;

        // Get marketplace percentage for Wayfair
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $percentageFraction = $percentage / 100;

        // Get product masters for LP
        $skus = $wayfairData->pluck('sku')->unique()->toArray();
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        foreach ($wayfairData as $order) {
            $quantity = (int) $order->quantity;
            $unitPrice = (float) $order->unit_price;

            if ($quantity <= 0) {
                continue;
            }

            // Calculate revenue
            $revenue = $unitPrice * $quantity;
            $totalRevenue += $revenue;

            // Calculate weighted price for average
            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            // Get LP from product master
            $lp = 0;
            $pm = $productMasters[$order->sku] ?? null;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
            }

            // Wayfair Profit Formula: (unit_price * percentage) - lp (NO ship cost)
            $profitPerUnit = ($unitPrice * $percentageFraction) - $lp;
            $profitTotal = $profitPerUnit * $quantity;

            $totalPft += $profitTotal;
            $totalCogs += ($quantity * $lp);
        }

        // Calculate averages and percentages
        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        // Wayfair doesn't have ads data
        $kwSpent = 0;
        $pmtSpent = 0;
        $tacosPercentage = 0;
        $nPftPercentage = $pftPercentage; // Same as PFT% since no ads
        $nRoiPercentage = $roiPercentage; // Same as ROI% since no ads

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => round($avgPrice, 2),
            'l30_sales' => $totalRevenue,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'tacos_percentage' => 0,
            'n_pft' => round($nPftPercentage, 1),
            'n_roi' => round($nRoiPercentage, 1),
        ];
    }

    /**
     * Faire — same source and per-line economics as FaireController::getDailyData (faire-tabulator).
     * Wholesale price preferred over retail; PFT per unit = (price × 0.75) − LP.
     */
    private function calculateFaireMetrics($date)
    {
        $data = FaireDailyData::query()->orderBy('id')->get();

        if ($data->isEmpty()) {
            return null;
        }

        $skus = $data->pluck('sku')->filter()->unique()->values()->all();
        $productMasters = $skus !== []
            ? ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku')
            : collect();

        $totalOrders = $data->count();
        $totalQuantity = 0;
        $totalRevenue = 0.0;
        $totalWeightedPrice = 0.0;
        $totalQuantityForPrice = 0;
        $totalPft = 0.0;
        $totalCogs = 0.0;

        foreach ($data as $item) {
            $sku = $item->sku;
            $lp = 0.0;

            if (! empty($sku) && isset($productMasters[$sku])) {
                $productMaster = $productMasters[$sku];
                $values = is_array($productMaster->Values)
                    ? $productMaster->Values
                    : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower((string) $k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }

                if ($lp === 0.0 && isset($productMaster->lp)) {
                    $lp = (float) $productMaster->lp;
                }
            }

            $wholesale = (float) ($item->wholesale_price ?? 0) ?: 0.0;
            $retail = (float) ($item->retail_price ?? 0) ?: 0.0;
            $price = $wholesale > 0 ? $wholesale : $retail;
            $quantity = (int) ($item->quantity ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $lineRevenue = $price * $quantity;
            $totalRevenue += $lineRevenue;
            $totalQuantity += $quantity;

            if ($price > 0) {
                $totalWeightedPrice += $price * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $pftEach = ($price * 0.75) - $lp;
            $totalPft += $pftEach * $quantity;
            $totalCogs += $lp * $quantity;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0.0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0.0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0.0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => round($avgPrice, 2),
            'l30_sales' => $totalRevenue,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'tacos_percentage' => 0,
            'n_pft' => round($pftPercentage, 1),
            'n_roi' => round($roiPercentage, 1),
        ];
    }

    /**
     * Purchasing Power — purchasing_power_sales (same upload as /purchasing-power-sales).
     * Excludes canceled rows; margin from marketplace_percentages.marketplace = Purchase (default 65%).
     * PFT per line matches Purchasing Power pricing: (unit_price × margin) − LP, × quantity.
     * Note: Ship is intentionally excluded from PP profit (matches /purchasing-power-pricing).
     */
    private function calculatePurchasingPowerMetrics($date)
    {
        $sales = PurchasingPowerSale::query()
            ->whereRaw('LOWER(TRIM(COALESCE(status, ?))) NOT IN (?, ?)', ['', 'canceled', 'cancelled'])
            ->orderBy('id')
            ->get();

        if ($sales->isEmpty()) {
            return null;
        }

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
        $pct = (($marketplaceData ? (float) ($marketplaceData->percentage ?? 65) : 65) / 100);

        $productMasters = $this->productMastersChunked()->keyBy(fn ($pm) => strtoupper(trim((string) ($pm->sku ?? ''))));

        $yesterday = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();
        $l7Start = now()->subDays(7)->startOfDay();
        $l7End = now()->endOfDay();

        $totalOrders = $sales->count();
        $totalQuantity = 0;
        $totalRevenue = 0.0;
        $totalWeightedPrice = 0.0;
        $totalQuantityForPrice = 0;
        $totalPft = 0.0;
        $totalCogs = 0.0;
        $yesterdaySales = 0.0;
        $l7Sales = 0.0;

        foreach ($sales as $row) {
            $qty = (int) ($row->quantity ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $unit = (float) ($row->unit_price ?? 0);
            if ($unit <= 0) {
                $amt = (float) ($row->amount ?? 0);
                $unit = $qty > 0 ? $amt / $qty : 0.0;
            }

            $lineRevenue = $unit * $qty;
            $totalRevenue += $lineRevenue;
            $totalQuantity += $qty;

            if ($unit > 0) {
                $totalWeightedPrice += $unit * $qty;
                $totalQuantityForPrice += $qty;
            }

            $orderDate = $row->date_created;
            if ($orderDate) {
                if ($orderDate >= $yesterday && $orderDate <= $yesterdayEnd) {
                    $yesterdaySales += $lineRevenue;
                }
                if ($orderDate >= $l7Start && $orderDate <= $l7End) {
                    $l7Sales += $lineRevenue;
                }
            }

            $sku = strtoupper(trim((string) ($row->offer_sku ?? '')));
            $lp = 0.0;
            $pm = $sku !== '' ? ($productMasters[$sku] ?? null) : null;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower((string) $k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }
            }

            // Ship intentionally excluded to match /purchasing-power-pricing.
            $profitPerUnit = ($unit * $pct) - $lp;
            $totalPft += $profitPerUnit * $qty;
            $totalCogs += $lp * $qty;
        }

        if ($totalQuantity <= 0 || $totalRevenue <= 0) {
            return null;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0.0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0.0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0.0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => round($pftPercentage, 1),
            'roi_percentage' => round($roiPercentage, 1),
            'avg_price' => round($avgPrice, 2),
            'l30_sales' => $totalRevenue,
            'yesterday_sales' => $yesterdaySales,
            'l7_sales' => $l7Sales,
            'kw_spent' => 0,
            'pmt_spent' => 0,
            'tacos_percentage' => 0,
            'n_pft' => round($pftPercentage, 1),
            'n_roi' => round($roiPercentage, 1),
        ];
    }
}
