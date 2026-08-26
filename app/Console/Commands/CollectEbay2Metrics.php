<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\Ebay2GeneralReport;
use App\Models\Ebay2Metric;
use App\Models\Ebay2PriorityReport;
use App\Models\Ebay2SkuDailyData;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectEbay2Metrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'ebay2:collect-metrics
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Collect daily eBay 2 metrics (Price, Views, CVR%) for historical tracking — same source as /ebay2-tabulator-view (ebay_2_metrics), California date';

    protected string $monitorJobName = 'eBay 2 Collect Metrics';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeCollect($m),
            $this->monitorJobName
        );
    }

    protected function executeCollect(CronExecutionContext $monitor): int
    {
        $this->info('Starting eBay 2 metrics collection...');
        $monitor->startFresh()->markLocalOnly();
        // California calendar day — matches /ebay2-tabulator-view SKU chart (PT).
        $today = Carbon::now('America/Los_Angeles')->toDateString();
        $yesterday = Carbon::now('America/Los_Angeles')->subDay()->toDateString();
        $chunkSize = $this->monitoredChunkSize();

        // First-run bootstrap: if yesterday has no snapshots, also write yesterday so
        // Price green/red/gray dots can compare as soon as live prices change.
        $seedYesterday = Ebay2SkuDailyData::where('record_date', $yesterday)->exists() === false;
        if ($seedYesterday) {
            $this->info("No yesterday snapshots found — seeding {$yesterday} as baseline.");
        }

        $totalMetrics = Ebay2Metric::query()->whereNotNull('sku')->count();
        $monitor->setFetched($totalMetrics);
        $monitor->setExpected($totalMetrics);

        $campaignBySku = Ebay2PriorityReport::where('report_range', 'L30')
            ->get(['campaign_name', 'cpc_ad_fees_payout_currency'])
            ->keyBy(function ($item) {
                return strtoupper(trim((string) $item->campaign_name));
            });
        $generalByListing = Ebay2GeneralReport::where('report_range', 'L30')
            ->get(['listing_id', 'ad_fees'])
            ->keyBy(function ($item) {
                return trim((string) $item->listing_id);
            });

        // Shopify INV + OV L30 for tabulator trend dots (same source as /ebay2-tabulator-view columns).
        $shopifyBySku = ShopifySku::query()
            ->select('sku', 'inv', 'quantity')
            ->whereNotNull('sku')
            ->get()
            ->keyBy(function ($row) {
                return ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
            });

        $collected = 0;
        $skipped = 0;

        $this->processQueryInChunks(
            $monitor,
            Ebay2Metric::query()
                ->select('id', 'sku', 'ebay_price', 'ebay_l30', 'views', 'l7_views', 'item_id')
                ->whereNotNull('sku')
                ->orderBy('id'),
            function ($rows) use (
                $today,
                $yesterday,
                $seedYesterday,
                $campaignBySku,
                $generalByListing,
                $shopifyBySku,
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

                        // Same formula as EbayTwoController CVR 30 (SCVR): (eBay L30 / views) × 100
                        $cvr = ($views > 0) ? (($ebayL30 / $views) * 100) : 0;

                        $matchedCampaign = $campaignBySku->get($sku);
                        $matchedGeneral = $itemId !== null ? $generalByListing->get(trim((string) $itemId)) : null;

                        $kw_spend_l30 = (float) str_replace('USD ', '', $matchedCampaign->cpc_ad_fees_payout_currency ?? 0);
                        $pmt_spend_l30 = (float) str_replace('USD ', '', $matchedGeneral->ad_fees ?? 0);
                        $adSpendL30 = $kw_spend_l30 + $pmt_spend_l30;

                        $totalRevenue = $price * $ebayL30;
                        $adPercent = $totalRevenue > 0 ? ($adSpendL30 / $totalRevenue) * 100 : 0;

                        $shopify = $shopifyBySku->get(ShopifySku::normalizeSkuForShopifyLookup($sku));
                        $dailyData = [
                            'price' => round($price, 2),
                            'views' => $views,
                            'l7_views' => $l7Views,
                            'cvr_percent' => round($cvr, 2),
                            'ad_percent' => round($adPercent, 2),
                            'ebay_l30' => $ebayL30,
                            'ad_spend_l30' => round($adSpendL30, 2),
                            'inv' => (int) ($shopify->inv ?? 0),
                            'ovl30' => (int) ($shopify->quantity ?? 0),
                        ];

                        Ebay2SkuDailyData::updateOrCreate(
                            [
                                'sku' => $sku,
                                'record_date' => $today,
                            ],
                            [
                                'daily_data' => $dailyData,
                            ]
                        );

                        if ($seedYesterday) {
                            Ebay2SkuDailyData::updateOrCreate(
                                [
                                    'sku' => $sku,
                                    'record_date' => $yesterday,
                                ],
                                [
                                    'daily_data' => $dailyData,
                                ]
                            );
                        } else {
                            // Backfill inv/ovl30 onto yesterday snapshot when older collects lacked those keys.
                            $yRow = Ebay2SkuDailyData::where('sku', $sku)->where('record_date', $yesterday)->first();
                            if ($yRow) {
                                $yData = is_array($yRow->daily_data) ? $yRow->daily_data : [];
                                if (! array_key_exists('inv', $yData) || ! array_key_exists('ovl30', $yData)) {
                                    $yData['inv'] = $dailyData['inv'];
                                    $yData['ovl30'] = $dailyData['ovl30'];
                                    $yRow->daily_data = $yData;
                                    $yRow->save();
                                }
                            }
                        }

                        $chunkCollected++;
                    } catch (\Exception $e) {
                        Log::error("Failed to collect eBay2 metrics for SKU: $sku", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
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

        $this->info('eBay 2 metrics collection completed!');
        $this->info("Collected: $collected SKUs");
        $this->info("Skipped: $skipped SKUs");
        $this->info("Record date (California): $today");

        Log::info('eBay 2 Metrics Collection', [
            'date' => $today,
            'timezone' => 'America/Los_Angeles',
            'source' => 'ebay_2_metrics',
            'collected' => $collected,
            'skipped' => $skipped,
        ]);

        return self::SUCCESS;
    }
}
