<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\ProductMaster;
use App\Models\Temu2Metric;
use App\Models\Temu2Pricing;
use App\Models\TemuMetric;
use App\Models\TemuAdData;
use App\Services\TemuShopifySalesService;
use App\Models\TemuBadgeDailyData;
use App\Support\TemuGoodsIdHelper;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CollectTemuMetrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'temu:collect-metrics
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Collect daily Temu metrics (Price, Views, CVR%, Sales, Spend) for historical tracking';

    protected string $monitorJobName = 'Temu Collect Metrics';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeCollect($m),
            $this->monitorJobName
        );
    }

    protected function executeCollect(CronExecutionContext $monitor): int
    {
        $this->info('Starting Temu metrics collection...');
        $monitor->startFresh()->markLocalOnly();

        $today = Carbon::today('America/Los_Angeles');
        $chunkSize = $this->monitoredChunkSize();

        $this->info('Collection date (California Time): ' . $today->toDateString());

        $temuPricing = collect();
        if (Schema::hasTable('temu_metrics')) {
            TemuMetric::select('id', 'sku', 'goods_id', 'base_price', 'quantity')
                ->whereNotNull('sku')
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (&$temuPricing) {
                    foreach ($rows as $item) {
                        $temuPricing[strtoupper(trim($item->sku))] = $item;
                    }
                });
        }

        $this->info('Found ' . $temuPricing->count() . ' SKUs in Temu Metrics');

        $temuViewsData = $this->viewsByGoodsId('temu_view_data');
        if ($temuViewsData->isEmpty()
            && Schema::hasTable('temu_metrics')
            && Schema::hasColumn('temu_metrics', 'product_clicks_l30')) {
            $temuViewsData = TemuMetric::select('goods_id', DB::raw('SUM(product_clicks_l30) as total_clicks'))
                ->whereNotNull('goods_id')
                ->groupBy('goods_id')
                ->get()
                ->keyBy(fn ($row) => TemuGoodsIdHelper::normalizeKey($row->goods_id) ?: (string) $row->goods_id);
        }

        $temu2ViewsData = $this->viewsByGoodsId('temu2_view_data');
        $temu2GidBySku = $this->temu2GoodsIdBySku();

        $temuSalesData = collect();
        try {
            [$temuL30Start, $temuL30End] = TemuShopifySalesService::channelMasterL30Window();
            $salesBySku = [];
            foreach (TemuShopifySalesService::getOrdersTableRows($temuL30Start, $temuL30End) as $orderRow) {
                $skuKey = strtoupper(trim((string) ($orderRow['contribution_sku'] ?? '')));
                if ($skuKey === '') {
                    continue;
                }
                $salesBySku[$skuKey] = ($salesBySku[$skuKey] ?? 0) + (int) ($orderRow['quantity_purchased'] ?? 0);
            }
            $temuSalesData = collect($salesBySku)->map(fn ($qty) => (object) ['temu_l30' => $qty]);
        } catch (\Throwable $e) {
            Log::warning('CollectTemuMetrics: temu_orders L30 skipped: ' . $e->getMessage());
        }

        $temuAdData = collect();
        TemuAdData::select('id', 'goods_id', 'spend')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$temuAdData) {
                foreach ($rows as $item) {
                    $temuAdData[$item->goods_id] = $item;
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

        $monitor->setFetched($temuPricing->count());
        $monitor->setExpected($temuPricing->count());

        $collected = 0;
        $skipped = 0;

        $pricingItems = $temuPricing->map(fn ($item, $sku) => ['sku' => $sku, 'pricing' => $item])->values()->all();

        $this->chunkProcessor()->process(
            $monitor,
            $pricingItems,
            function (array $chunk) use (
                $today,
                $temuViewsData,
                $temu2ViewsData,
                $temu2GidBySku,
                $temuSalesData,
                $temuAdData,
                &$collected,
                &$skipped
            ) {
                $chunkCollected = 0;
                $chunkSkipped = 0;

                foreach ($chunk as $entry) {
                    $sku = $entry['sku'];
                    $pricingData = $entry['pricing'];

                    if (stripos($sku, 'PARENT') !== false || empty($sku)) {
                        $chunkSkipped++;
                        continue;
                    }

                    try {
                        $goodsId = $pricingData->goods_id;

                        $basePrice = floatval($pricingData->base_price ?? 0);

                        $gidKey = TemuGoodsIdHelper::normalizeKey($goodsId) ?: (string) $goodsId;
                        $viewData = $gidKey !== '' ? $temuViewsData->get($gidKey) : null;
                        $productClicks = $viewData ? intval($viewData->total_clicks ?? 0) : 0;
                        $temu2Gid = $temu2GidBySku[$sku] ?? $gidKey;
                        $temu2View = $temu2Gid !== '' ? $temu2ViewsData->get($temu2Gid) : null;
                        $temu2Clicks = $temu2View ? intval($temu2View->total_clicks ?? 0) : 0;

                        $salesData = $temuSalesData->get($sku);
                        $temuL30 = $salesData ? intval($salesData->temu_l30 ?? 0) : 0;

                        $cvrPercent = 0;
                        if ($productClicks > 0 && $temuL30 > 0) {
                            $cvrPercent = ($temuL30 / $productClicks) * 100;
                        }

                        $adData = $goodsId ? $temuAdData->get($goodsId) : null;
                        $spend = $adData ? floatval($adData->spend ?? 0) : 0;

                        $dailyData = [
                            'price' => round($basePrice, 2),
                            'base_price' => round($basePrice, 2),
                            'views' => $productClicks,
                            'product_clicks' => $productClicks,
                            'temu2_views' => $temu2Clicks,
                            'temu2_product_clicks' => $temu2Clicks,
                            'temu_l30' => $temuL30,
                            'cvr_percent' => round($cvrPercent, 2),
                            'spend' => round($spend, 2),
                            'goods_id' => $goodsId ?: null,
                        ];

                        $payload = [
                            'base_price' => round($basePrice, 2),
                            'product_clicks' => $productClicks,
                            'temu_l30' => $temuL30,
                            'cvr_percent' => round($cvrPercent, 2),
                            'spend' => round($spend, 2),
                            'updated_at' => now(),
                        ];
                        if (Schema::hasColumn('temu_sku_daily_data', 'daily_data')) {
                            $payload['daily_data'] = json_encode($dailyData);
                        }

                        DB::table('temu_sku_daily_data')->updateOrInsert(
                            [
                                'sku' => $sku,
                                'record_date' => $today,
                            ],
                            $payload
                        );

                        $chunkCollected++;
                    } catch (\Exception $e) {
                        $this->error("Error processing SKU {$sku}: " . $e->getMessage());
                        Log::error("Error collecting Temu metrics for SKU {$sku}: " . $e->getMessage());
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
            ['transaction' => true, 'fresh' => true]
        );

        $this->info("✓ Collection complete!");
        $this->info("  - Collected: {$collected} SKUs");
        $this->info("  - Skipped: {$skipped} SKUs");
        $this->info("  - Date: " . $today->toDateString());

        $this->snapshotTemu2OnlySkuViews($today, $temu2ViewsData, $temu2GidBySku, $temuPricing);
        $this->snapshotBadgeDailyData($today, $productData);

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<string, object>
     */
    private function viewsByGoodsId(string $table)
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'product_clicks')) {
            return collect();
        }

        return DB::table($table)
            ->select('goods_id', DB::raw('SUM(product_clicks) as total_clicks'))
            ->whereNotNull('goods_id')
            ->groupBy('goods_id')
            ->get()
            ->keyBy(fn ($row) => TemuGoodsIdHelper::normalizeKey($row->goods_id) ?: (string) $row->goods_id);
    }

    /**
     * @return array<string, string>
     */
    private function temu2GoodsIdBySku(): array
    {
        $map = [];
        if (Schema::hasTable('temu2_pricing')) {
            foreach (Temu2Pricing::query()->select(['sku', 'goods_id'])->get() as $row) {
                $sku = strtoupper(trim((string) $row->sku));
                $gid = TemuGoodsIdHelper::normalizeKey($row->goods_id);
                if ($sku !== '' && $gid) {
                    $map[$sku] = $gid;
                }
            }
        }
        if (Schema::hasTable('temu2_metrics')) {
            foreach (Temu2Metric::query()->select(['sku', 'goods_id'])->get() as $row) {
                $sku = strtoupper(trim((string) $row->sku));
                if ($sku === '' || isset($map[$sku])) {
                    continue;
                }
                $gid = TemuGoodsIdHelper::normalizeKey($row->goods_id);
                if ($gid) {
                    $map[$sku] = $gid;
                }
            }
        }

        return $map;
    }

    /**
     * Temu 2-only SKUs are missing from temu_metrics; still write their sheet views.
     *
     * @param  \Illuminate\Support\Collection<string, object>  $temu2ViewsData
     * @param  array<string, string>  $temu2GidBySku
     * @param  \Illuminate\Support\Collection<string, mixed>  $temu1Pricing
     */
    private function snapshotTemu2OnlySkuViews(Carbon $today, $temu2ViewsData, array $temu2GidBySku, $temu1Pricing): void
    {
        if ($temu2GidBySku === [] || ! Schema::hasTable('temu_sku_daily_data')) {
            return;
        }

        $written = 0;
        foreach ($temu2GidBySku as $sku => $gid) {
            if (stripos($sku, 'PARENT') !== false || $temu1Pricing->has($sku)) {
                continue;
            }
            $clicks = (int) ($temu2ViewsData->get($gid)->total_clicks ?? 0);
            $existing = DB::table('temu_sku_daily_data')
                ->where('sku', $sku)
                ->whereDate('record_date', $today)
                ->first();
            $json = [];
            if ($existing && ! empty($existing->daily_data)) {
                $json = is_array($existing->daily_data)
                    ? $existing->daily_data
                    : (json_decode((string) $existing->daily_data, true) ?: []);
            }
            $json['temu2_views'] = $clicks;
            $json['temu2_product_clicks'] = $clicks;
            $payload = ['updated_at' => now()];
            if (Schema::hasColumn('temu_sku_daily_data', 'daily_data')) {
                $payload['daily_data'] = json_encode($json);
            }
            DB::table('temu_sku_daily_data')->updateOrInsert(
                ['sku' => $sku, 'record_date' => $today->toDateString()],
                $payload
            );
            $written++;
        }

        $this->info("  - Temu 2 view snapshots: {$written} SKUs");
    }

    /**
     * Build one row of badge summary for the given date and upsert into temu_badge_daily_data.
     *
     * Reads from the live decrease-page endpoint (TemuController::getTemuDecreaseData)
     * so the saved snapshot is BYTE-FOR-BYTE the same dataset the live badge sums
     * over — same SKU set, same sales source (Temu Orders API for Temu 1 / temu2 daily
     * for Temu 2), same view-data joins, same $2.99 ship-bumper applied to revenue,
     * etc. That keeps the badge value on the page and the chart point for "today"
     * perfectly in sync.
     */
    protected function snapshotBadgeDailyData(Carbon $recordDate, $productData): void
    {
        try {
            $controller = app(\App\Http\Controllers\MarketPlace\TemuController::class);
            $response = $controller->getTemuDecreaseData(new \Illuminate\Http\Request());
            $payload = json_decode($response->getContent(), true);
        } catch (\Throwable $e) {
            Log::error('Temu badge snapshot: getTemuDecreaseData call failed: ' . $e->getMessage());
            return;
        }

        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $salesSummary = is_array($payload['sales_summary'] ?? null) ? $payload['sales_summary'] : [];

        $totalOrders   = (int) ($salesSummary['total_orders'] ?? 0);
        $totalQuantity = (int) ($salesSummary['total_quantity'] ?? 0);
        $totalSales    = round((float) ($salesSummary['total_revenue'] ?? 0), 2);

        $totalViews = 0;
        $totalSpend = 0.0;
        $skuCount = 0;
        $skusWithViews = 0;
        foreach ($rows as $row) {
            $sku = (string) ($row['sku'] ?? '');
            if ($sku === '' || stripos($sku, 'PARENT') !== false) continue;
            $skuCount++;
            $clicks = (int) ($row['product_clicks'] ?? 0);
            if ($clicks > 0) {
                $totalViews += $clicks;
                $skusWithViews++;
            }
            $totalSpend += (float) ($row['spend'] ?? 0);
        }
        $totalSpend = round($totalSpend, 2);
        $avgViews   = $skusWithViews > 0 ? round($totalViews / $skusWithViews, 2) : 0.0;

        $avgCvrPct = $totalViews > 0 ? round(($totalQuantity / $totalViews) * 100, 2) : 0.0;

        TemuBadgeDailyData::updateOrCreate(
            ['record_date' => $recordDate->toDateString()],
            [
                'total_sales' => round($totalSales, 2),
                'total_orders' => $totalOrders,
                'total_quantity' => $totalQuantity,
                'sku_count' => $skuCount,
                'total_views' => $totalViews,
                'avg_views' => $avgViews,
                'total_spend' => $totalSpend,
                'avg_cvr_pct' => $avgCvrPct,
            ]
        );
        $this->info("  - Badge daily snapshot saved for " . $recordDate->toDateString());
    }
}
