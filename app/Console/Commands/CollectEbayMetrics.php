<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\EbaySkuDailyData;
use App\Models\ProductMaster;
use App\Models\EbayPriorityReport;
use App\Models\EbayGeneralReport;
use App\Models\MarketplacePercentage;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CollectEbayMetrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'ebay:collect-metrics
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Collect daily eBay metrics (Price, Views, CVR%, AD%) for historical tracking';

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
        $today = Carbon::today();
        $chunkSize = $this->monitoredChunkSize();

        $ebayMetrics = collect();
        DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->select('sku', 'ebay_price', 'ebay_l30', 'views', 'item_id')
            ->whereNotNull('sku')
            ->orderBy('sku')
            ->chunk($chunkSize, function ($rows) use (&$ebayMetrics) {
                foreach ($rows as $row) {
                    $ebayMetrics->push($row);
                }
            });

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;

        $allSkus = $ebayMetrics->pluck('sku')->unique()->filter()->toArray();

        $ebayCampaignReportsL30 = collect();
        EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($allSkus) {
                foreach ($allSkus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$ebayCampaignReportsL30) {
                foreach ($rows as $row) {
                    $ebayCampaignReportsL30->push($row);
                }
            });

        $itemIds = $ebayMetrics->pluck('item_id')->filter()->unique()->values()->all();
        $ebayGeneralReportsL30 = collect();
        if (! empty($itemIds)) {
            EbayGeneralReport::where('report_range', 'L30')
                ->whereIn('listing_id', $itemIds)
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (&$ebayGeneralReportsL30) {
                    foreach ($rows as $row) {
                        $ebayGeneralReportsL30->push($row);
                    }
                });
        }

        $productData = collect();
        ProductMaster::whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$productData) {
                foreach ($rows as $p) {
                    $productData[strtoupper(trim($p->sku))] = $p;
                }
            });

        $monitor->setFetched($ebayMetrics->count());
        $monitor->setExpected($ebayMetrics->count());

        $collected = 0;
        $skipped = 0;

        $this->chunkProcessor()->process(
            $monitor,
            $ebayMetrics->values()->all(),
            function (array $chunk) use (
                $today,
                $ebayCampaignReportsL30,
                $ebayGeneralReportsL30,
                &$collected,
                &$skipped
            ) {
                $chunkCollected = 0;
                $chunkSkipped = 0;

                foreach ($chunk as $ebayMetric) {
                    $sku = strtoupper(trim($ebayMetric->sku));

                    if (stripos($sku, 'PARENT') !== false || empty($sku)) {
                        continue;
                    }

                    try {
                        $price = floatval($ebayMetric->ebay_price ?? 0);
                        $views = intval($ebayMetric->views ?? 0);
                        $l7Views = intval($ebayMetric->l7_views ?? 0);
                        $ebayL30 = intval($ebayMetric->ebay_l30 ?? 0);
                        $itemId = $ebayMetric->item_id ?? null;

                        $cvr = 0;
                        if ($views > 0 && $ebayL30 > 0) {
                            $cvr = ($ebayL30 / $views) * 100;
                        }

                        $matchedCampaign = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                            return strtoupper(trim($item->campaign_name)) === $sku;
                        });

                        $matchedGeneral = $ebayGeneralReportsL30->first(function ($item) use ($itemId) {
                            return trim((string) $item->listing_id) == trim((string) $itemId);
                        });

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

        Log::info("eBay Metrics Collection", [
            'date' => $today->toDateString(),
            'collected' => $collected,
            'skipped' => $skipped
        ]);

        return self::SUCCESS;
    }
}
