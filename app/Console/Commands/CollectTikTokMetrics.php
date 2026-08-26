<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Models\TiktokOrder;
use App\Models\TiktokSkuDailyData;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectTikTokMetrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'tiktok:collect-metrics
        {--channel=both : tiktok | tiktok2 | both}
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Collect daily TikTok metrics (Price, Stock, Sold) for historical Price charts on /tiktok-pricing and /tiktok-2-pricing';

    protected string $monitorJobName = 'TikTok Collect Metrics';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeCollect($m),
            $this->monitorJobName
        );
    }

    protected function executeCollect(CronExecutionContext $monitor): int
    {
        $channelOpt = strtolower((string) $this->option('channel') ?: 'both');
        $channels = match ($channelOpt) {
            'tiktok', 'tiktok1', 'v1' => ['tiktok'],
            'tiktok2', 'v2' => ['tiktok2'],
            default => ['tiktok', 'tiktok2'],
        };

        $monitor->startFresh()->markLocalOnly();
        $today = Carbon::now('America/Los_Angeles')->toDateString();
        $chunkSize = $this->monitoredChunkSize();
        $collected = 0;
        $skipped = 0;

        // TikTok 1 sold = L30 from tiktok_orders (last 30 California calendar days)
        $ordersSoldBySku = TiktokOrder::soldQtyL30(null, 30);

        foreach ($channels as $channel) {
            $this->info("Collecting TikTok metrics for channel={$channel} date={$today}...");
            $model = $channel === 'tiktok2' ? TikTokProductTwo::class : TikTokProduct::class;
            $total = $model::query()->whereNotNull('sku')->count();
            $monitor->setFetched(($monitor->fetched ?? 0) + $total);
            $monitor->setExpected(($monitor->expected ?? 0) + $total);

            $this->processQueryInChunks(
                $monitor,
                $model::query()
                    ->select('id', 'sku', 'price', 'stock', 'sold')
                    ->whereNotNull('sku')
                    ->orderBy('id'),
                function ($rows) use ($today, $channel, &$collected, &$skipped, $ordersSoldBySku) {
                    $chunkCollected = 0;
                    $chunkSkipped = 0;

                    foreach ($rows as $row) {
                        $sku = strtoupper(trim((string) ($row->sku ?? '')));
                        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                            $chunkSkipped++;
                            continue;
                        }

                        try {
                            $sold = $channel === 'tiktok'
                                ? (int) ($ordersSoldBySku[$sku] ?? 0)
                                : (int) ($row->sold ?? 0);
                            $dailyData = [
                                'price' => round((float) ($row->price ?? 0), 2),
                                'stock' => (int) ($row->stock ?? 0),
                                'sold' => $sold,
                            ];

                            TiktokSkuDailyData::updateOrCreate(
                                [
                                    'sku' => $sku,
                                    'channel' => $channel,
                                    'record_date' => $today,
                                ],
                                [
                                    'daily_data' => $dailyData,
                                ]
                            );
                            $chunkCollected++;
                        } catch (\Throwable $e) {
                            Log::error("Failed to collect TikTok metrics for SKU: {$sku}", [
                                'channel' => $channel,
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
        }

        $this->info("TikTok metrics collection done. collected={$collected} skipped={$skipped}");

        return self::SUCCESS;
    }
}
