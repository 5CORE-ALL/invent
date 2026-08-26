<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\ReverbProduct;
use App\Models\ReverbSkuDailyData;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectReverbMetrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'reverb:collect-metrics
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Collect daily Reverb metrics (Price, Views/bump impressions, CVR%) for historical tracking — same source as /reverb-pricing (reverb_products), California date';

    protected string $monitorJobName = 'Reverb Collect Metrics';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeCollect($m),
            $this->monitorJobName
        );
    }

    protected function executeCollect(CronExecutionContext $monitor): int
    {
        $this->info('Starting Reverb metrics collection...');
        $monitor->startFresh()->markLocalOnly();
        $today = Carbon::now('America/Los_Angeles')->toDateString();
        $chunkSize = $this->monitoredChunkSize();

        $totalMetrics = ReverbProduct::query()->whereNotNull('sku')->count();
        $monitor->setFetched($totalMetrics);
        $monitor->setExpected($totalMetrics);

        $collected = 0;
        $skipped = 0;

        $this->processQueryInChunks(
            $monitor,
            ReverbProduct::query()
                ->select('id', 'sku', 'price', 'views', 'r_l30')
                ->whereNotNull('sku')
                ->orderBy('id'),
            function ($rows) use ($today, &$collected, &$skipped) {
                $chunkCollected = 0;
                $chunkSkipped = 0;

                foreach ($rows as $product) {
                    $sku = strtoupper(trim((string) $product->sku));

                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        $chunkSkipped++;
                        continue;
                    }

                    try {
                        $price = (float) ($product->price ?? 0);
                        $views = (int) ($product->views ?? 0);
                        $rvL30 = (int) ($product->r_l30 ?? 0);
                        $cvr = $views > 0 ? (($rvL30 / $views) * 100) : 0;

                        ReverbSkuDailyData::updateOrCreate(
                            [
                                'sku' => $sku,
                                'record_date' => $today,
                            ],
                            [
                                'daily_data' => [
                                    'price' => round($price, 2),
                                    'views' => $views,
                                    'cvr_percent' => round($cvr, 2),
                                    'rv_l30' => $rvL30,
                                ],
                            ]
                        );

                        $chunkCollected++;
                    } catch (\Exception $e) {
                        Log::error("Failed to collect Reverb metrics for SKU: $sku", [
                            'error' => $e->getMessage(),
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

        $this->info('Metrics collection completed!');
        $this->info("Collected: $collected SKUs");
        $this->info("Skipped: $skipped SKUs");
        $this->info("Record date (California): $today");

        Log::info('Reverb Metrics Collection', [
            'date' => $today,
            'timezone' => 'America/Los_Angeles',
            'source' => 'reverb_products',
            'collected' => $collected,
            'skipped' => $skipped,
        ]);

        return self::SUCCESS;
    }
}
