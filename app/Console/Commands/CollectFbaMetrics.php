<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\FbaTable;
use App\Models\FbaPrice;
use App\Models\FbaReportsMaster;
use App\Models\FbaManualData;
use App\Models\FbaMonthlySale;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaMetricsHistory;
use App\Models\FbaSkuDailyData;
use App\Models\ProductMaster;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectFbaMetrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'fba:collect-metrics
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Collect daily FBA metrics (Price, Views, Gprft, Groi%, Tacos) for historical tracking';

    protected string $monitorJobName = 'FBA Collect Metrics';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeCollect($m),
            $this->monitorJobName
        );
    }

    protected function executeCollect(CronExecutionContext $monitor): int
    {
        $this->info('Starting FBA metrics collection...');
        $today = Carbon::today();
        $chunkSize = $this->monitoredChunkSize();

        $fbaData = collect();
        FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$fbaData) {
                foreach ($rows as $row) {
                    $fbaData->push($row);
                }
            });

        $fbaPriceData = collect();
        FbaPrice::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$fbaPriceData) {
                foreach ($rows as $item) {
                    $sku = $item->seller_sku;
                    $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                    $fbaPriceData[strtoupper(trim($base))] = $item;
                }
            });

        $fbaReportsData = collect();
        FbaReportsMaster::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$fbaReportsData) {
                foreach ($rows as $item) {
                    $sku = $item->seller_sku;
                    $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                    $fbaReportsData[strtoupper(trim($base))] = $item;
                }
            });

        $fbaMonthlySales = collect();
        FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$fbaMonthlySales) {
                foreach ($rows as $item) {
                    $sku = $item->seller_sku;
                    $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                    $fbaMonthlySales[strtoupper(trim($base))] = $item;
                }
            });

        $fbaManualData = collect();
        FbaManualData::query()->orderBy('id')->chunkById($chunkSize, function ($rows) use (&$fbaManualData) {
            foreach ($rows as $item) {
                $fbaManualData[strtoupper(trim($item->sku))] = $item;
            }
        });

        $productData = collect();
        ProductMaster::whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$productData) {
                foreach ($rows as $p) {
                    $productData[strtoupper(trim($p->sku))] = $p;
                }
            });

        $monitor->setFetched($fbaData->count());
        $monitor->setExpected($fbaData->count());

        $collected = 0;
        $skipped = 0;

        $this->chunkProcessor()->process(
            $monitor,
            $fbaData->values()->all(),
            function (array $chunk) use (
                $today,
                $fbaPriceData,
                $fbaReportsData,
                $fbaMonthlySales,
                $fbaManualData,
                $productData,
                &$collected,
                &$skipped
            ) {
                $chunkCollected = 0;
                $chunkSkipped = 0;

                foreach ($chunk as $fba) {
                    $sku = strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', $fba->seller_sku)));

                    if (stripos($sku, 'PARENT') !== false) {
                        continue;
                    }

                    $fbaPriceInfo = $fbaPriceData->get($sku);
                    $fbaReportsInfo = $fbaReportsData->get($sku);
                    $monthlySales = $fbaMonthlySales->get($sku);
                    $manual = $fbaManualData->get($sku);
                    $product = $productData->get($sku);

                    $price = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
                    $views = $fbaReportsInfo ? intval($fbaReportsInfo->current_month_views ?? 0) : 0;

                    $LP = \App\Services\CustomLpMappingService::getLpValue($sku, $product);

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

                    $gpft = 0;
                    if ($price > 0 && $LP > 0) {
                        $gpft = (($price * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $price) * 100;
                    }

                    $groi = 0;
                    if ($LP > 0 && $price > 0) {
                        $groi = (($price * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $LP) * 100;
                    }

                    $l30Units = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
                    $priceL30 = $price * $l30Units;

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

                        if ($record->wasRecentlyCreated) {
                            Log::info("Created new metrics record for SKU: $sku on {$today->toDateString()}");
                        } else {
                            Log::info("Updated existing metrics record for SKU: $sku on {$today->toDateString()}");
                        }

                        $cvr = 0;
                        if ($views > 0) {
                            $cvr = ($l30Units / $views) * 100;
                        }

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

                        $chunkCollected++;
                    } catch (\Exception $e) {
                        Log::error("Failed to collect metrics for SKU: $sku", ['error' => $e->getMessage()]);
                        $chunkSkipped++;
                    }
                }

                $collected += $chunkCollected;
                $skipped += $chunkSkipped;

                return [
                    'updated' => $chunkCollected,
                    'skipped' => $chunkSkipped,
                    'failed' => 0,
                    'processed' => count($chunk),
                ];
            },
            $chunkSize,
            null,
            ['transaction' => true]
        );

        $this->info("Metrics collection completed!");
        $this->info("Collected: $collected SKUs");
        $this->info("Skipped: $skipped SKUs");

        Log::info("FBA Metrics Collection", [
            'date' => $today->toDateString(),
            'collected' => $collected,
            'skipped' => $skipped
        ]);

        return self::SUCCESS;
    }
}
