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
use App\Services\CustomLpMappingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        @ini_set('memory_limit', '1024M');
        $this->info('Starting FBA metrics collection...');
        $monitor->startFresh()->markLocalOnly();
        $today = Carbon::today();
        $chunkSize = $this->monitoredChunkSize();

        $fbaSkuFilter = function ($q) {
            $q->where('seller_sku', 'LIKE', '%FBA%')
                ->orWhere('seller_sku', 'LIKE', '%fba%');
        };

        $priceBySku = [];
        FbaPrice::query()->where($fbaSkuFilter)->select('id', 'seller_sku', 'price')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$priceBySku) {
                foreach ($rows as $item) {
                    $priceBySku[$this->fbaBaseSku($item->seller_sku)] = floatval($item->price ?? 0);
                }
            });

        $viewsBySku = [];
        FbaReportsMaster::query()->where($fbaSkuFilter)->select('id', 'seller_sku', 'current_month_views')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$viewsBySku) {
                foreach ($rows as $item) {
                    $viewsBySku[$this->fbaBaseSku($item->seller_sku)] = intval($item->current_month_views ?? 0);
                }
            });

        $l30BySku = [];
        FbaMonthlySale::query()->where($fbaSkuFilter)->select('id', 'seller_sku', 'l30_units')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$l30BySku) {
                foreach ($rows as $item) {
                    $l30BySku[$this->fbaBaseSku($item->seller_sku)] = intval($item->l30_units ?? 0);
                }
            });

        $manualBySku = [];
        FbaManualData::query()->select('id', 'sku', 'data')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$manualBySku) {
                foreach ($rows as $item) {
                    $manualBySku[strtoupper(trim((string) $item->sku))] = is_array($item->data)
                        ? $item->data
                        : (json_decode($item->data ?? '{}', true) ?: []);
                }
            });

        $lpBySku = $this->loadSlimLpMap($chunkSize);
        $fbaAds = $this->loadFbaAdSpendRows($chunkSize);

        $total = FbaTable::query()->where($fbaSkuFilter)->count();
        $monitor->setFetched($total);
        $monitor->setExpected($total);

        $collected = 0;
        $skipped = 0;

        $this->processQueryInChunks(
            $monitor,
            FbaTable::query()
                ->select('id', 'seller_sku')
                ->where($fbaSkuFilter)
                ->orderBy('id'),
            function ($rows) use (
                $today,
                $priceBySku,
                $viewsBySku,
                $l30BySku,
                $manualBySku,
                $lpBySku,
                $fbaAds,
                &$collected,
                &$skipped
            ) {
                $chunkCollected = 0;
                $chunkSkipped = 0;

                foreach ($rows as $fba) {
                    $sku = $this->fbaBaseSku($fba->seller_sku);
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }

                    $price = floatval($priceBySku[$sku] ?? 0);
                    $views = intval($viewsBySku[$sku] ?? 0);
                    $LP = CustomLpMappingService::getCustomLpMapping()[$sku]
                        ?? floatval($lpBySku[$sku] ?? 0);
                    $manual = $manualBySku[$sku] ?? [];

                    $fbaFeeManual = floatval($manual['fba_fee_manual'] ?? 0);
                    $sendCost = floatval($manual['send_cost'] ?? 0);
                    $inCharges = floatval($manual['in_charges'] ?? 0);
                    $totalQuantitySent = floatval($manual['total_quantity_sent'] ?? 0);
                    $FBA_SHIP = $totalQuantitySent > 0
                        ? $fbaFeeManual + ($sendCost + $inCharges) / $totalQuantitySent
                        : $fbaFeeManual;
                    $commissionPercentage = floatval($manual['commission_percentage'] ?? 0);

                    $gpft = 0;
                    if ($price > 0 && $LP > 0) {
                        $gpft = (($price * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $price) * 100;
                    }

                    $groi = 0;
                    if ($LP > 0 && $price > 0) {
                        $groi = (($price * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $LP) * 100;
                    }

                    $l30Units = intval($l30BySku[$sku] ?? 0);
                    $priceL30 = $price * $l30Units;
                    [$kwSpend, $ptSpend] = $this->fbaAdSpendForSku($sku, $fbaAds);
                    $totalSpendSum = $kwSpend + $ptSpend;

                    $tacos = 0;
                    if ($totalSpendSum == 0) {
                        $tacos = 0;
                    } elseif ($priceL30 == 0) {
                        $tacos = 100;
                    } else {
                        $tacos = ($totalSpendSum / $priceL30) * 100;
                    }

                    try {
                        FbaMetricsHistory::updateOrCreate(
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

                        $cvr = $views > 0 ? (($l30Units / $views) * 100) : 0;
                        FbaSkuDailyData::updateOrCreate(
                            [
                                'sku' => $sku,
                                'record_date' => $today,
                            ],
                            [
                                'daily_data' => [
                                    'price' => round($price, 2),
                                    'views' => $views,
                                    'cvr_percent' => round($cvr, 2),
                                    'tacos_percent' => round($tacos, 2),
                                    'l30_units' => $l30Units,
                                    'gpft' => round($gpft, 2),
                                    'groi_percent' => round($groi, 2),
                                ],
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
                    'processed' => $rows->count(),
                ];
            },
            $chunkSize,
            'id',
            null,
            ['fresh' => true]
        );

        $this->info("Metrics collection completed!");
        $this->info("Collected: $collected SKUs");
        $this->info("Skipped: $skipped SKUs");

        Log::info("FBA Metrics Collection", [
            'date' => $today->toDateString(),
            'collected' => $collected,
            'skipped' => $skipped,
        ]);

        return self::SUCCESS;
    }

    protected function fbaBaseSku(?string $sellerSku): string
    {
        return strtoupper(trim((string) preg_replace('/\s*FBA\s*/i', '', (string) $sellerSku)));
    }

    /**
     * @return array<string, float>
     */
    protected function loadSlimLpMap(int $chunkSize): array
    {
        $lpBySku = [];
        $columns = ['id', 'sku'];
        if (Schema::hasColumn('product_master', 'lp')) {
            $columns[] = 'lp';
        } else {
            $columns[] = 'Values';
        }

        ProductMaster::whereNull('deleted_at')
            ->select($columns)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$lpBySku) {
                foreach ($rows as $p) {
                    $sku = strtoupper(trim((string) $p->sku));
                    if ($sku === '') {
                        continue;
                    }
                    if (isset($p->lp) && $p->lp !== null && $p->lp !== '') {
                        $lpBySku[$sku] = floatval($p->lp);
                        continue;
                    }
                    $values = is_array($p->Values)
                        ? $p->Values
                        : (is_string($p->Values) ? (json_decode($p->Values, true) ?: []) : []);
                    $lpBySku[$sku] = floatval($values['lp'] ?? $values['LP'] ?? 0);
                }
            });

        return $lpBySku;
    }

    /**
     * @return list<array{name: string, cost: float, pt: bool}>
     */
    protected function loadFbaAdSpendRows(int $chunkSize): array
    {
        $ads = [];
        AmazonSpCampaignReport::query()
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA%')
                    ->orWhere('campaignName', 'LIKE', '%fba%');
            })
            ->select('id', 'campaignName', 'cost')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$ads) {
                foreach ($rows as $row) {
                    $name = (string) ($row->campaignName ?? '');
                    $trimmed = strtolower(rtrim(trim($name), '.'));
                    $ads[] = [
                        'name' => strtoupper($name),
                        'cost' => floatval($row->cost ?? 0),
                        'pt' => str_ends_with($trimmed, ' pt') || (bool) preg_match('/FBA[\s.]*PT/i', $name),
                    ];
                }
            });

        return $ads;
    }

    /**
     * @param  list<array{name: string, cost: float, pt: bool}>  $ads
     * @return array{0: float, 1: float}
     */
    protected function fbaAdSpendForSku(string $sku, array $ads): array
    {
        $kw = 0.0;
        $pt = 0.0;
        foreach ($ads as $ad) {
            if (! str_contains($ad['name'], $sku)) {
                continue;
            }
            if ($ad['pt']) {
                $pt += $ad['cost'];
            } else {
                $kw += $ad['cost'];
            }
        }

        return [$kw, $pt];
    }
}
