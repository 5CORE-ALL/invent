<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonSkuDailyData;
use App\Models\ProductMaster;
use App\Models\AmazonDatasheet;
use App\Models\AmazonSpCampaignReport;
use App\Models\MarketplacePercentage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CollectAmazonMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amazon:collect-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect daily Amazon metrics (Price, Views, CVR%, AD%) for historical tracking';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Amazon metrics collection...');
        $today = Carbon::today();
        
        // Get all Amazon SKUs from amazon_datasheets
        $amazonDatasheets = AmazonDatasheet::whereNotNull('sku')->get();
        
        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.80;
        
        // Get all SKUs for campaign report lookup
        $allSkus = $amazonDatasheets->pluck('sku')->unique()->filter()->toArray();
        
        // Get campaign reports for AD% calculation (KW campaigns - NOT PT)
        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($allSkus) {
                foreach ($allSkus as $sku) {
                    $q->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% PT')
                      ->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% pt');
                }
            })
            ->get();
        
        // Get PT campaigns separately
        $amazonSpCampaignReportsPtL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->get();
        
        $productData = ProductMaster::whereNull('deleted_at')
            ->get()
            ->keyBy(function ($p) {
                return strtoupper(trim($p->sku));
            });
        
        $collected = 0;
        $skipped = 0;
        
        foreach ($amazonDatasheets as $amazonSheet) {
            $sku = strtoupper(trim($amazonSheet->sku));
            
            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false || empty($sku)) {
                continue;
            }
            
            try {
                // Get metrics
                $price = floatval($amazonSheet->price ?? 0);
                $views = intval($amazonSheet->sessions_l30 ?? 0);
                $aL30 = intval($amazonSheet->units_ordered_l30 ?? 0);
                
                // Calculate CVR: (A_L30 / sessions_l30) * 100
                $cvr = 0;
                if ($views > 0 && $aL30 > 0) {
                    $cvr = ($aL30 / $views) * 100;
                }
                
                // Calculate AD%
                $matchedCampaignKwL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });
                
                $matchedCampaignPtL30 = $amazonSpCampaignReportsPtL30->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    return (str_ends_with($cleanName, $sku . ' PT') || str_ends_with($cleanName, $sku . ' PT.'));
                });
                
                $kw_spend_l30 = floatval($matchedCampaignKwL30->cost ?? 0);
                $pmt_spend_l30 = floatval($matchedCampaignPtL30->cost ?? 0);
                $adSpendL30 = $kw_spend_l30 + $pmt_spend_l30;
                
                $totalRevenue = $price * $aL30;
                $adPercent = $totalRevenue > 0 ? ($adSpendL30 / $totalRevenue) * 100 : 0;
                
                // Store in JSON format table
                $dailyData = [
                    'price' => round($price, 2),
                    'views' => $views,
                    'cvr_percent' => round($cvr, 2),
                    'ad_percent' => round($adPercent, 2),
                    'a_l30' => $aL30,
                    'ad_spend_l30' => round($adSpendL30, 2),
                ];
                
                AmazonSkuDailyData::updateOrCreate(
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
        
        Log::info("Amazon Metrics Collection", [
            'date' => $today->toDateString(),
            'collected' => $collected,
            'skipped' => $skipped
        ]);
        
        return Command::SUCCESS;
    }
}

