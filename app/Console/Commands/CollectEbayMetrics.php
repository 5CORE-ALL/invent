<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\EbaySkuDailyData;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\EbayGeneralReport;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectEbayMetrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'ebay:collect-metrics
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Collect daily eBay metrics (Price, Views, CVR%, AD%) for historical tracking — same source as /ebay-tabulator (ebay_metrics), California date';

    protected string $monitorJobName = 'eBay Collect Metrics';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeCollect($m),
            $this->monitorJobName
        );
    }

    protected function executeCollect(CronExecutionContext $monitor): int
    {
        $this->info('Starting eBay metrics collection...');
        // California calendar day — matches /all-marketplace-master and the SKU chart (PT).
        $today = Carbon::now('America/Los_Angeles')->toDateString();
        $chunkSize = $this->monitoredChunkSize();

        // Same live table the eBay tabulator CVR 30 column uses (NOT stale apicentral.ebay_one_metrics).
        $totalMetrics = EbayMetric::query()->whereNotNull('sku')->count();
        $monitor->setFetched($totalMetrics);
        $monitor->setExpected($totalMetrics);

        // Preload L30 ad spend reports (keyed for O(1) lookup).
        $campaignBySku = EbayPriorityReport::where('report_range', 'L30')
            ->get(['campaign_name', 'cpc_ad_fees_payout_currency'])
            ->keyBy(function ($item) {
                return strtoupper(trim((string) $item->campaign_name));
            });
        $generalByListing = EbayGeneralReport::where('report_range', 'L30')
            ->get(['listing_id', 'ad_fees'])
            ->keyBy(function ($item) {
                return trim((string) $item->listing_id);
            });

        $collected = 0;
        $skipped = 0;

        // Stream by id (avoids stale array-offset resume that skipped all rows).
        $this->processQueryInChunks(
            $monitor,
            EbayMetric::query()
                ->select('id', 'sku', 'ebay_price', 'ebay_l30', 'views', 'l7_views', 'item_id')
                ->whereNotNull('sku')
                ->orderBy('id'),
            function ($rows) use (
                $today,
                $campaignBySku,
                $generalByListing,
                &$collected,
                &$skipped
            ) {
                $chunkCollected = 0;
                $chunkSkipped = 0;

                foreach ($rows as $ebayMetric) {
                    $sku = strtoupper(trim((string) $ebayMetric->sku));

                    if (stripos($sku, 'PARENT') !== false || $sku === '') {
                        $chunkSkipped++;
                        continue;
                    }

                    try {
                        $price = floatval($ebayMetric->ebay_price ?? 0);
                        $views = intval($ebayMetric->views ?? 0);
                        $l7Views = intval($ebayMetric->l7_views ?? 0);
                        $ebayL30 = intval($ebayMetric->ebay_l30 ?? 0);
                        $itemId = $ebayMetric->item_id ?? null;

                        // Same formula as EbayController CVR 30 (SCVR): (eBay L30 / views) × 100
                        $cvr = ($views > 0) ? (($ebayL30 / $views) * 100) : 0;

                        $matchedCampaign = $campaignBySku->get($sku);
                        $matchedGeneral = $itemId !== null ? $generalByListing->get(trim((string) $itemId)) : null;

                        $kw_spend_l30 = (float) str_replace('USD ', '', $matchedCampaign->cpc_ad_fees_payout_currency ?? 0);
                        $pmt_spend_l30 = (float) str_replace('USD ', '', $matchedGeneral->ad_fees ?? 0);
                        $adSpendL30 = $kw_spend_l30 + $pmt_spend_l30;

                        $totalRevenue = $price * $ebayL30;
                        $adPercent = $totalRevenue > 0 ? ($adSpendL30 / $totalRevenue) * 100 : 0;

                        $dailyData = [
                            'price' => round($price, 2),
                            'views' => $views,
                            'l7_views' => $l7Views,
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
                    'processed' => $rows->count(),
                ];
            },
            $chunkSize
        );

        $this->info("Metrics collection completed!");
        $this->info("Collected: $collected SKUs");
        $this->info("Skipped: $skipped SKUs");
        $this->info("Record date (California): $today");

        Log::info("eBay Metrics Collection", [
            'date' => $today,
            'timezone' => 'America/Los_Angeles',
            'source' => 'ebay_metrics',
            'collected' => $collected,
            'skipped' => $skipped
        ]);

        return self::SUCCESS;
    }
}
