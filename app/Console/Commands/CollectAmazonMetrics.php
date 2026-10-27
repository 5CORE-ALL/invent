<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Models\AmazonOrder;
use App\Models\AmazonSkuDailyData;
use App\Models\AmazonDataView;
use App\Models\AmazonDatasheet;
use App\Models\AmazonSpCampaignReport;
use App\Models\MarketplacePercentage;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectAmazonMetrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'amazon:collect-metrics
        {--chunk= : Override chunk size (default from cron-monitor config)}
        {--backfill-days= : Rebuild A L30 history for the last N Pacific days from real Amazon orders (fills missing days), then exit}';

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
        $monitor->startFresh()->markLocalOnly();
        $today = Carbon::now('America/Los_Angeles')->toDateString();
        $chunkSize = $this->monitoredChunkSize();

        $backfillDays = (int) $this->option('backfill-days');
        if ($backfillDays > 0) {
            return $this->backfillAl30History($monitor, $backfillDays, $chunkSize);
        }

        // A L30 = real Amazon order units, same source / window as the tabulator
        // column and /amazon/daily-sales. The datasheet units_ordered_l30 is only a
        // fallback (it can go stale and is not what the table shows).
        $ordersUnitsBySku = null;
        try {
            [$ordersStart, $ordersEnd] = AmazonOrder::dailySalesL30Window(AmazonSalesController::DAILY_SALES_WINDOW_DAYS);
            $ordersUnitsBySku = AmazonOrder::unitsSoldBySkuForWindow($ordersStart, $ordersEnd);
        } catch (\Throwable $e) {
            Log::warning('Amazon metrics: could not load order-based A L30, falling back to datasheet units', [
                'error' => $e->getMessage(),
            ]);
        }

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

        $monitor->setFetched($amazonDatasheets->count());
        $monitor->setExpected($amazonDatasheets->count());

        $shopifyBySku = ShopifySku::query()
            ->select('sku', 'quantity')
            ->whereNotNull('sku')
            ->get()
            ->keyBy(function ($row) {
                return ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
            });

        $collected = 0;
        $skipped = 0;

        $this->chunkProcessor()->process(
            $monitor,
            $amazonDatasheets->values()->all(),
            function (array $chunk) use (
                $today,
                $amazonSpCampaignReportsL30,
                $amazonSpCampaignReportsPtL30,
                $shopifyBySku,
                $ordersUnitsBySku,
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
                        $aL30 = $ordersUnitsBySku !== null
                            ? AmazonOrder::unitsSoldForProductSku($sku, $ordersUnitsBySku, $amazonSheet->sku ?? null)
                            : intval($amazonSheet->units_ordered_l30 ?? 0);
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
                        $shopify = $shopifyBySku->get(ShopifySku::normalizeSkuForShopifyLookup($sku));

                        $daily = AmazonSkuDailyData::firstOrNew([
                            'sku' => $sku,
                            'record_date' => $today,
                        ]);
                        $existingPayload = is_array($daily->daily_data) ? $daily->daily_data : [];
                        unset($existingPayload['inv']);
                        $daily->daily_data = array_merge($existingPayload, [
                            'price' => round($price, 2),
                            'sprice' => $sprice,
                            'views' => $views,
                            'cvr_percent' => round($cvr, 2),
                            'ad_percent' => round($adPercent, 2),
                            'a_l30' => $aL30,
                            'a_l30_source' => $ordersUnitsBySku !== null ? 'orders' : 'datasheet',
                            'ad_spend_l30' => round($adSpendL30, 2),
                            'organic_views' => $organicViews,
                            'l30' => (int) ($shopify?->quantity ?? 0),
                        ]);
                        $daily->save();

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
            0,
            ['transaction' => true, 'fresh' => true]
        );

        $this->info("Metrics collection completed!");
        $this->info("Collected: $collected SKUs");
        $this->info("Skipped: $skipped SKUs");

        Log::info("Amazon Metrics Collection", [
            'date' => $today,
            'collected' => $collected,
            'skipped' => $skipped
        ]);

        return self::SUCCESS;
    }

    /**
     * Rebuild the rolling A L30 history from real Amazon orders.
     *
     * For each Pacific record date D the value is the units sold in the 30 days
     * ending D-1 (identical to what the tabulator showed on that day). Existing
     * rows only get a_l30 / cvr refreshed; days with no row are created, carrying
     * price / views from the SKU's previous snapshot so other charts stay continuous.
     */
    protected function backfillAl30History(CronExecutionContext $monitor, int $days, int $chunkSize): int
    {
        $days = min(max($days, 1), 400);
        $windowDays = AmazonSalesController::DAILY_SALES_WINDOW_DAYS;
        $todayPt = Carbon::now('America/Los_Angeles')->startOfDay();

        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[] = $todayPt->copy()->subDays($i)->toDateString();
        }
        $firstDate = $dates[0];

        $this->info("Backfilling A L30 for {$days} day(s): {$firstDate} → {$todayPt->toDateString()}");

        $unitsByDate = [];
        foreach ($dates as $date) {
            $end = Carbon::parse($date, 'America/Los_Angeles')->subDay()->endOfDay();
            $start = $end->copy()->subDays($windowDays - 1)->startOfDay();
            $unitsByDate[$date] = AmazonOrder::unitsSoldBySkuForWindow($start, $end);
        }

        $skus = AmazonDatasheet::whereNotNull('sku')
            ->pluck('sku')
            ->map(fn ($s) => strtoupper(trim((string) $s)))
            ->filter(fn ($s) => $s !== '' && stripos($s, 'PARENT') === false)
            ->unique()
            ->values();

        $monitor->setFetched($skus->count());
        $monitor->setExpected($skus->count());

        $carryFields = ['price', 'sprice', 'views', 'cvr_percent', 'ad_percent', 'ad_spend_l30', 'organic_views', 'l30'];
        $written = 0;
        $unchanged = 0;

        foreach ($skus->chunk(max(50, min($chunkSize, 300))) as $skuChunk) {
            $chunkSkus = $skuChunk->values()->all();

            $existing = [];
            AmazonSkuDailyData::whereIn('sku', $chunkSkus)
                ->where('record_date', '>=', $firstDate)
                ->get()
                ->each(function ($row) use (&$existing) {
                    $existing[strtoupper(trim((string) $row->sku))][Carbon::parse($row->record_date)->toDateString()] = $row;
                });

            // Last snapshot before the window — seed for carry-forward on missing days.
            $carry = [];
            AmazonSkuDailyData::whereIn('sku', $chunkSkus)
                ->where('record_date', '<', $firstDate)
                ->where('record_date', '>=', Carbon::parse($firstDate)->subDays(120)->toDateString())
                ->orderByDesc('record_date')
                ->get()
                ->each(function ($row) use (&$carry, $carryFields) {
                    $key = strtoupper(trim((string) $row->sku));
                    if (isset($carry[$key])) {
                        return;
                    }
                    $data = is_array($row->daily_data) ? $row->daily_data : [];
                    $carry[$key] = array_intersect_key($data, array_flip($carryFields));
                });

            foreach ($chunkSkus as $sku) {
                foreach ($dates as $date) {
                    $aL30 = AmazonOrder::unitsSoldForProductSku($sku, $unitsByDate[$date], $sku);
                    $row = $existing[$sku][$date] ?? null;

                    try {
                        if ($row) {
                            $data = is_array($row->daily_data) ? $row->daily_data : [];
                            $views = (int) ($data['views'] ?? 0);
                            if ((int) ($data['a_l30'] ?? -1) === $aL30 && ($data['a_l30_source'] ?? null) === 'orders') {
                                $unchanged++;
                            } else {
                                $data['a_l30'] = $aL30;
                                $data['a_l30_source'] = 'orders';
                                if ($views > 0) {
                                    $data['cvr_percent'] = round(($aL30 / $views) * 100, 2);
                                }
                                $row->daily_data = $data;
                                $row->save();
                                $written++;
                            }
                            $carry[$sku] = array_intersect_key($data, array_flip($carryFields));
                            continue;
                        }

                        $seed = $carry[$sku] ?? [];
                        if ($aL30 <= 0 && $seed === []) {
                            continue;
                        }

                        $views = (int) ($seed['views'] ?? 0);
                        $payload = array_merge($seed, [
                            'a_l30' => $aL30,
                            'a_l30_source' => 'orders',
                            'cvr_percent' => $views > 0 ? round(($aL30 / $views) * 100, 2) : (float) ($seed['cvr_percent'] ?? 0),
                            'backfilled' => true,
                        ]);
                        AmazonSkuDailyData::updateOrCreate(
                            ['sku' => $sku, 'record_date' => $date],
                            ['daily_data' => $payload]
                        );
                        $written++;
                    } catch (\Throwable $e) {
                        Log::error("Amazon A L30 backfill failed for SKU {$sku} on {$date}", ['error' => $e->getMessage()]);
                    }
                }
            }

            $this->line('… ' . count($chunkSkus) . ' SKU(s) processed');
        }

        $this->info("Backfill complete. Rows written/updated: {$written}, already correct: {$unchanged}");
        Log::info('Amazon A L30 backfill', ['days' => $days, 'written' => $written, 'unchanged' => $unchanged]);

        return self::SUCCESS;
    }
}
