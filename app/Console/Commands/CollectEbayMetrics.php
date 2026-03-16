<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EbaySkuDailyData;
use App\Models\ProductMaster;
use App\Models\EbayPriorityReport;
use App\Models\EbayGeneralReport;
use App\Models\MarketplacePercentage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CollectEbayMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay:collect-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect daily eBay metrics (Price, Views, CVR%, AD%) for historical tracking';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting eBay metrics collection...');
        $today = Carbon::today();
        
        // Get all eBay SKUs from ebay_one_metrics
        $ebayMetrics = DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->select('sku', 'ebay_price', 'ebay_l30', 'views', 'item_id')
            ->whereNotNull('sku')
            ->get();
        
        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        
        // Get all SKUs for campaign report lookup
        $allSkus = $ebayMetrics->pluck('sku')->unique()->filter()->toArray();
        
        // Get campaign reports for AD% calculation
        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($allSkus) {
                foreach ($allSkus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();
        
        $ebayGeneralReportsL30 = EbayGeneralReport::where('report_range', 'L30')
            ->whereIn('listing_id', $ebayMetrics->pluck('item_id')->toArray())
            ->get();
        
        $productData = ProductMaster::whereNull('deleted_at')
            ->get()
            ->keyBy(function ($p) {
                return strtoupper(trim($p->sku));
            });
        
        $collected = 0;
        $skipped = 0;
        
        foreach ($ebayMetrics as $ebayMetric) {
            $sku = strtoupper(trim($ebayMetric->sku));
            
            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false || empty($sku)) {
                continue;
            }
            
            try {
                // Get metrics
                $price = floatval($ebayMetric->ebay_price ?? 0);
                $views = intval($ebayMetric->views ?? 0);
                $ebayL30 = intval($ebayMetric->ebay_l30 ?? 0);
                $itemId = $ebayMetric->item_id ?? null;
                
                // Calculate CVR: (ebay_l30 / views) * 100
                $cvr = 0;
                if ($views > 0 && $ebayL30 > 0) {
                    $cvr = ($ebayL30 / $views) * 100;
                }
                
                // Calculate AD%
                $matchedCampaign = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                    return strtoupper(trim($item->campaign_name)) === $sku;
                });
                
                $matchedGeneral = $ebayGeneralReportsL30->first(function ($item) use ($itemId) {
                    return trim((string)$item->listing_id) == trim((string)$itemId);
                });
                
                $kw_spend_l30 = (float) str_replace('USD ', '', $matchedCampaign->cpc_ad_fees_payout_currency ?? 0);
                $pmt_spend_l30 = (float) str_replace('USD ', '', $matchedGeneral->ad_fees ?? 0);
                $adSpendL30 = $kw_spend_l30 + $pmt_spend_l30;
                
                $totalRevenue = $price * $ebayL30;
                $adPercent = $totalRevenue > 0 ? ($adSpendL30 / $totalRevenue) * 100 : 0;
                
                // Store in JSON format table
                $dailyData = [
                    'price' => round($price, 2),
                    'views' => $views,
                    'cvr_percent' => round($cvr, 2),
                    'ad_percent' => round($adPercent, 2),
                    'ebay_l30' => $ebayL30,
                    'ad_spend_l30' => round($adSpendL30, 2),
                ];
                
                EbaySkuDailyData::updateOrCreate(
                    [
                        'sku' => $sku,
                        'record_date' => $today,
                    ],
                    [
                        'daily_data' => $dailyData,
                    ]
                );
                
                $collected++;
            } catch (\Exception $e) {
                Log::error("Failed to collect metrics for SKU: $sku", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $skipped++;
            }
        }
        
        $this->info("Metrics collection completed!");
        $this->info("Collected: $collected SKUs");
        $this->info("Skipped: $skipped SKUs");
        
        Log::info("eBay Metrics Collection", [
            'date' => $today->toDateString(),
            'collected' => $collected,
            'skipped' => $skipped
        ]);
        
        return Command::SUCCESS;
    }
}

