<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\AmazonSkuDailyData;
use App\Models\AmazonDataView;
use App\Models\ProductMaster;
use App\Models\AmazonDatasheet;
use App\Models\AmazonSpCampaignReport;
use App\Models\MarketplacePercentage;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectAmazonMetrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'amazon:collect-metrics
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Collect daily Amazon metrics (Price, Views, CVR%, AD%) for historical tracking';

    protected string $monitorJobName = 'Amazon Collect Metrics';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeCollect($m),
            $this->monitorJobName
        );
    }

    protected function executeCollect(CronExecutionContext $monitor): int
    {
        $this->info('Starting Amazon metrics collection...');
        $today = Carbon::today();
        $chunkSize = $this->monitoredChunkSize();

        $amazonDatasheets = collect();
        AmazonDatasheet::whereNotNull('sku')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$amazonDatasheets) {
                foreach ($rows as $row) {
                    $amazonDatasheets->push($row);
                }
            });

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.80;

        $allSkus = $amazonDatasheets->pluck('sku')->unique()->filter()->toArray();

        $amazonSpCampaignReportsL30 = collect();
        AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($allSkus) {
                foreach ($allSkus as $sku) {
                    $q->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% PT')
                      ->orWhere('campaignName', 'NOT LIKE', '%' . $sku . '% pt');
                }
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$amazonSpCampaignReportsL30) {
                foreach ($rows as $row) {
                    $amazonSpCampaignReportsL30->push($row);
                }
            });

        $amazonSpCampaignReportsPtL30 = collect();
        AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$amazonSpCampaignReportsPtL30) {
                foreach ($rows as $row) {
                    $amazonSpCampaignReportsPtL30->push($row);
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

        $monitor->setFetched($amazonDatasheets->count());
        $monitor->setExpected($amazonDatasheets->count());

        $collected = 0;
        $skipped = 0;

        $this->chunkProcessor()->process(
            $monitor,
            $amazonDatasheets->values()->all(),
            function (array $chunk) use (
                $today,
                $amazonSpCampaignReportsL30,
                $amazonSpCampaignReportsPtL30,
                &$collected,
                &$skipped
            ) {
                $chunkCollected = 0;
                $chunkSkipped = 0;

                $chunkSkus = [];
                foreach ($chunk as $amazonSheet) {
                    $sku = strtoupper(trim($amazonSheet->sku ?? ''));
                    if ($sku !== '' && stripos($sku, 'PARENT') === false) {
                        $chunkSkus[] = $sku;
                    }
                }
                $spriceBySku = [];
                if (! empty($chunkSkus)) {
                    AmazonDataView::whereIn('sku', $chunkSkus)
                        ->select('sku', 'value')
                        ->get()
                        ->each(function ($row) use (&$spriceBySku) {
                            $val = is_array($row->value)
                                ? $row->value
                                : (json_decode($row->value ?? '{}', true) ?: []);
                            $sp = isset($val['SPRICE']) ? floatval($val['SPRICE']) : 0;
                            if ($sp > 0) {
                                $spriceBySku[strtoupper(trim($row->sku))] = round($sp, 2);
                            }
                        });
                }

                foreach ($chunk as $amazonSheet) {
                    $sku = strtoupper(trim($amazonSheet->sku));

                    if (stripos($sku, 'PARENT') !== false || empty($sku)) {
                        continue;
                    }

                    try {
                        $price = floatval($amazonSheet->price ?? 0);
                        $views = intval($amazonSheet->sessions_l30 ?? 0);
                        $aL30 = intval($amazonSheet->units_ordered_l30 ?? 0);
                        $organicViews = intval($amazonSheet->organic_views ?? 0);

                        // Same formula as amazon-tabulator-view CVR L30: (A_L30 / Sess30) × 100
                        $cvr = $views > 0 ? (($aL30 / $views) * 100) : 0;

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
                        $sprice = $spriceBySku[$sku] ?? ($price > 0 ? round($price, 2) : 0);

                        $dailyData = [
                            'price' => round($price, 2),
                            'sprice' => $sprice,
                            'views' => $views,
                            'cvr_percent' => round($cvr, 2),
                            'ad_percent' => round($adPercent, 2),
                            'a_l30' => $aL30,
                            'ad_spend_l30' => round($adSpendL30, 2),
                            'organic_views' => $organicViews,
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

                        $chunkCollected++;
                    } catch (\Exception $e) {
                        Log::error("Failed to collect metrics for SKU: $sku", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
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

        Log::info("Amazon Metrics Collection", [
            'date' => $today->toDateString(),
            'collected' => $collected,
            'skipped' => $skipped
        ]);

        return self::SUCCESS;
    }
}
