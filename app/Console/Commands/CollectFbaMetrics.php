<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FbaTable;
use App\Models\FbaPrice;
use App\Models\FbaReportsMaster;
use App\Models\FbaManualData;
use App\Models\FbaMonthlySale;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaMetricsHistory;
use App\Models\FbaSkuDailyData;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CollectFbaMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fba:collect-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect daily FBA metrics (Price, Views, Gprft, Groi%, Tacos) for historical tracking';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting FBA metrics collection...');
        $today = Carbon::today();
        
        // Get all FBA SKUs
        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")->get();
        
        $fbaPriceData = FbaPrice::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->get()
            ->keyBy(function ($item) {
                $sku = $item->seller_sku;
                $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                return strtoupper(trim($base));
            });

        $fbaReportsData = FbaReportsMaster::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->get()
            ->keyBy(function ($item) {
                $sku = $item->seller_sku;
                $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                return strtoupper(trim($base));
            });

        $fbaMonthlySales = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->get()
            ->keyBy(function ($item) {
                $sku = $item->seller_sku;
                $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                return strtoupper(trim($base));
            });

        $fbaManualData = FbaManualData::all()->keyBy(function ($item) {
            return strtoupper(trim($item->sku));
        });

        $productData = ProductMaster::whereNull('deleted_at')
            ->get()
            ->keyBy(function ($p) {
                return strtoupper(trim($p->sku));
            });

        $collected = 0;
        $skipped = 0;

        foreach ($fbaData as $fba) {
            $sku = strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', $fba->seller_sku)));
            
            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $fbaPriceInfo = $fbaPriceData->get($sku);
            $fbaReportsInfo = $fbaReportsData->get($sku);
            $monthlySales = $fbaMonthlySales->get($sku);
            $manual = $fbaManualData->get($sku);
            $product = $productData->get($sku);

            // Calculate metrics
            $price = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
            $views = $fbaReportsInfo ? intval($fbaReportsInfo->current_month_views ?? 0) : 0;
            
            // Get LP value
            $LP = \App\Services\CustomLpMappingService::getLpValue($sku, $product);
            
            // Calculate FBA shipping
            $FBA_SHIP = 0;
            if ($manual) {
                $fbaFeeManual = floatval($manual->data['fba_fee_manual'] ?? 0);
                $sendCost = floatval($manual->data['send_cost'] ?? 0);
                $inCharges = floatval($manual->data['in_charges'] ?? 0);
                $totalQuantitySent = floatval($manual->data['total_quantity_sent'] ?? 0);
                
                if ($totalQuantitySent > 0) {
                    $FBA_SHIP = $fbaFeeManual + ($sendCost + $inCharges) / $totalQuantitySent;
                } else {
                    $FBA_SHIP = $fbaFeeManual;
                }
            }

            $commissionPercentage = $manual ? floatval($manual->data['commission_percentage'] ?? 0) : 0;

            // Calculate GPFT%
            $gpft = 0;
            if ($price > 0 && $LP > 0) {
                $gpft = (($price * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $price) * 100;
            }

            // Calculate GROI%
            $groi = 0;
            if ($LP > 0 && $price > 0) {
                $groi = (($price * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $LP) * 100;
            }

            // Calculate TACOS
            $l30Units = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
            $priceL30 = $price * $l30Units;

            // Get ads spend (KW + PT)
            $adsKW = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->where(function ($q) use ($sku) {
                    $q->where('campaignName', 'LIKE', '%' . $sku . '%');
                })
                ->where(function ($q) {
                    $q->where('campaignName', 'LIKE', '%FBA%')
                        ->orWhere('campaignName', 'LIKE', '%fba%');
                })
                ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->first();

            $adsPT = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->where(function ($q) use ($sku) {
                    $q->where('campaignName', 'LIKE', '%' . $sku . '%');
                })
                ->where(function ($q) {
                    $q->where('campaignName', 'LIKE', '%FBA PT%')
                        ->orWhere('campaignName', 'LIKE', '%fba pt%')
                        ->orWhere('campaignName', 'LIKE', '%FBA.PT%')
                        ->orWhere('campaignName', 'LIKE', '%fba.pt%');
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->first();

            $kwSpend = $adsKW ? floatval($adsKW->cost ?? 0) : 0;
            $ptSpend = $adsPT ? floatval($adsPT->cost ?? 0) : 0;
            $totalSpendSum = $kwSpend + $ptSpend;

            $tacos = 0;
            if ($totalSpendSum == 0) {
                $tacos = 0;
            } elseif ($totalSpendSum > 0 && $priceL30 == 0) {
                $tacos = 100;
            } else {
                $tacos = ($totalSpendSum / $priceL30) * 100;
            }

            // Store the metrics
            try {
                $record = FbaMetricsHistory::updateOrCreate(
                    [
                        'sku' => $sku,
                        'record_date' => $today,
                    ],
                    [
                        'price' => round($price, 2),
                        'views' => $views,
                        'gprft' => round($gpft, 2),
                        'groi_percent' => round($groi, 2),
                        'tacos' => round($tacos, 2),
                    ]
                );
                
                // Log for verification
                if ($record->wasRecentlyCreated) {
                    Log::info("Created new metrics record for SKU: $sku on {$today->toDateString()}");
                } else {
                    Log::info("Updated existing metrics record for SKU: $sku on {$today->toDateString()}");
                }
                
                // Calculate CVR: (l30_units / views) * 100
                $cvr = 0;
                if ($views > 0) {
                    $cvr = ($l30Units / $views) * 100;
                }
                
                // Store in new JSON format table
                $dailyData = [
                    'price' => round($price, 2),
                    'views' => $views,
                    'cvr_percent' => round($cvr, 2),
                    'tacos_percent' => round($tacos, 2),
                    'l30_units' => $l30Units,
                    'gpft' => round($gpft, 2),
                    'groi_percent' => round($groi, 2),
                ];
                
                FbaSkuDailyData::updateOrCreate(
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
                Log::error("Failed to collect metrics for SKU: $sku", ['error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->info("Metrics collection completed!");
        $this->info("Collected: $collected SKUs");
        $this->info("Skipped: $skipped SKUs");
        
        Log::info("FBA Metrics Collection", [
            'date' => $today->toDateString(),
            'collected' => $collected,
            'skipped' => $skipped
        ]);

        return Command::SUCCESS;
    }
}
