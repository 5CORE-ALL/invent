<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WalmartSkuDailyData;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CollectWalmartMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walmart:collect-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect daily Walmart metrics (Price, Views, CVR%, AD%, Orders) for historical tracking';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Walmart metrics collection...');
        
        // Use California timezone (Pacific Time) for date
        $today = Carbon::today('America/Los_Angeles');
        
        $this->info('Collection date (California Time): ' . $today->toDateString());
        
        // Get all Walmart SKUs from walmart_price_data with their current pricing
        $walmartPricing = DB::table('walmart_price_data')
            ->select('sku', 'item_id', 'price', 'buy_box_price')
            ->whereNotNull('sku')
            ->get()
            ->keyBy(function ($item) {
                return strtoupper(trim($item->sku));
            });
        
        // Get listing views data
        $walmartViews = DB::table('walmart_listing_views_data')
            ->select('sku', 'page_views', 'conversion_rate', 'gmv')
            ->whereNotNull('sku')
            ->get()
            ->keyBy(function ($item) {
                return strtoupper(trim($item->sku));
            });
        
        // Get order data (table already contains only L30 data after truncate/insert)
        $recentOrders = DB::table('walmart_order_data')
            ->select('sku', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(qty) as total_qty'))
            ->where('status', '!=', 'Canceled') // Exclude canceled orders only
            ->whereNotNull('sku')
            ->groupBy('sku')
            ->get()
            ->keyBy(function ($item) {
                return strtoupper(trim($item->sku));
            });
        
        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        
        // Get all unique SKUs
        $allSkus = collect($walmartPricing->keys())
            ->merge($walmartViews->keys())
            ->merge($recentOrders->keys())
            ->unique()
            ->filter()
            ->toArray();
        
        // Get campaign reports for AD% calculation
        $walmartCampaignReports = DB::table('walmart_campaign_reports')
            ->select('campaignName', 'spend', 'sales', 'clicks', 'impression', 'sold')
            ->where('report_range', 'L30')
            ->get();
        
        $productData = ProductMaster::whereNull('deleted_at')
            ->get()
            ->keyBy(function ($p) {
                return strtoupper(trim($p->sku));
            });
        
        $collected = 0;
        $skipped = 0;
        
        foreach ($allSkus as $sku) {
            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false || empty($sku)) {
                continue;
            }
            
            try {
                // Get pricing data
                $pricingData = $walmartPricing->get($sku);
                $price = $pricingData ? floatval($pricingData->price ?? 0) : 0;
                $buyBoxPrice = $pricingData ? floatval($pricingData->buy_box_price ?? 0) : 0;
                
                // Get views data
                $viewsData = $walmartViews->get($sku);
                $views = $viewsData ? intval($viewsData->page_views ?? 0) : 0;
                $conversionRate = $viewsData ? floatval($viewsData->conversion_rate ?? 0) : 0;
                $gmv = $viewsData ? floatval($viewsData->gmv ?? 0) : 0;
                
                // Get order data
                $orderData = $recentOrders->get($sku);
                $orders = $orderData ? intval($orderData->order_count ?? 0) : 0;
                $totalQty = $orderData ? intval($orderData->total_qty ?? 0) : 0;
                
                // Calculate CVR using same formula as controller and blade: (total_qty / views) * 100
                // This matches the W L30 / Views calculation displayed in the table
                $cvr = 0;
                if ($views > 0 && $totalQty > 0) {
                    $cvr = ($totalQty / $views) * 100;
                }
                
                // Calculate AD% - match campaign by SKU in campaign name
                $matchedCampaign = $walmartCampaignReports->first(function ($item) use ($sku) {
                    return stripos($item->campaignName ?? '', $sku) !== false;
                });
                
                $adSpend = $matchedCampaign ? floatval($matchedCampaign->spend ?? 0) : 0;
                $campaignSales = $matchedCampaign ? floatval($matchedCampaign->sales ?? 0) : 0;
                
                // Use GMV or calculated revenue for AD%
                $totalRevenue = $gmv > 0 ? $gmv : ($price * $totalQty);
                $adPercent = $totalRevenue > 0 ? ($adSpend / $totalRevenue) * 100 : 0;
                
                // Get LP and ship from product_master for GPFT/GROI calculations
                $product = $productData->get($sku);
                $lp = $product ? floatval($product->lp ?? 0) : 0;
                $ship = $product ? floatval($product->wm_ship ?? 0) : 0;
                
                // Calculate GPFT% and GROI%
                $gpft = 0;
                $groi = 0;
                if ($price > 0) {
                    // GPFT% = ((Price × Percentage - LP - Ship) / Price) × 100
                    $gpft = (($price * $percentage - $lp - $ship) / $price) * 100;
                    
                    // GROI% = ((Price × Percentage - LP - Ship) / LP) × 100
                    if ($lp > 0) {
                        $groi = (($price * $percentage - $lp - $ship) / $lp) * 100;
                    }
                }
                
                // Store in JSON format
                $dailyData = [
                    'price' => round($price, 2),
                    'buy_box_price' => round($buyBoxPrice, 2),
                    'views' => $views,
                    'cvr_percent' => round($cvr, 2),
                    'ad_percent' => round($adPercent, 2),
                    'orders' => $orders,
                    'total_qty' => $totalQty,
                    'gmv' => round($gmv, 2),
                    'ad_spend' => round($adSpend, 2),
                    'campaign_sales' => round($campaignSales, 2),
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'gpft_percent' => round($gpft, 2),
                    'groi_percent' => round($groi, 2),
                ];
                
                WalmartSkuDailyData::updateOrCreate(
                    [
                        'sku' => $sku,
                        'record_date' => $today,
                    ],
                    [
                        'daily_data' => $dailyData,
                    ]
                );
                
                $collected++;
                
                if ($collected % 50 == 0) {
                    $this->info("Processed $collected SKUs...");
                }
                
            } catch (\Exception $e) {
                Log::error("Failed to collect Walmart metrics for SKU: $sku", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $skipped++;
            }
        }
        
        $this->info("Walmart metrics collection completed!");
        $this->info("Collected: $collected SKUs");
        $this->info("Skipped: $skipped SKUs");
        
        Log::info("Walmart Metrics Collection", [
            'date' => $today->toDateString(),
            'collected' => $collected,
            'skipped' => $skipped
        ]);
        
        return Command::SUCCESS;
    }
}