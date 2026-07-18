<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncAmazonPrices extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'sync:amazon-prices
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'One-time sync of prices from repricer.lmpa_data to 5coreinventory.amazon_datsheets';

    protected string $monitorJobName = 'Sync Amazon Prices';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeSync($m),
            $this->monitorJobName
        );
    }

    protected function executeSync(CronExecutionContext $monitor): int
    {
        $chunkSize = $this->monitoredChunkSize();

        try {
            $skus = DB::table('5core_repricer.lmpa_data')
                ->select('sku')
                ->where('price', '>', 0)
                ->whereNotNull('sku')
                ->groupBy('sku')
                ->pluck('sku')
                ->filter()
                ->values()
                ->all();

            $monitor->setFetched(count($skus));
            $monitor->setExpected(count($skus));

            if ($skus === []) {
                $this->warn('⚠️ No rows updated, prices already in sync.');
                return self::SUCCESS;
            }

            $totalUpdated = 0;

            foreach (array_chunk($skus, $chunkSize) as $skuChunk) {
                $updated = DB::transaction(function () use ($skuChunk) {
                    $subQuery = DB::table('5core_repricer.lmpa_data')
                        ->select('sku', DB::raw('MIN(price) as price'))
                        ->where('price', '>', 0)
                        ->whereIn('sku', $skuChunk)
                        ->groupBy('sku');

                    return DB::table('5coreinventory.amazon_datsheets as a')
                        ->joinSub($subQuery, 'l', function ($join) {
                            $join->on('a.sku', '=', 'l.sku');
                        })
                        ->where(function ($q) {
                            $q->whereColumn('a.price_lmpa', '<>', 'l.price')
                              ->orWhere(function ($sub) {
                                  $sub->whereNull('a.price_lmpa')
                                      ->whereNotNull('l.price');
                              })
                              ->orWhere(function ($sub) {
                                  $sub->whereNotNull('a.price_lmpa')
                                      ->whereNull('l.price');
                              });
                        })
                        ->update([
                            'a.price_lmpa' => DB::raw('l.price'),
                            'a.updated_at' => now(),
                        ]);
                });

                $totalUpdated += (int) $updated;
                $monitor->incrementProcessed(count($skuChunk));
                if ($updated > 0) {
                    $monitor->incrementUpdated((int) $updated);
                }
            }

            if ($totalUpdated) {
                $this->info("✅ {$totalUpdated} rows updated successfully.");
            } else {
                $this->warn("⚠️ No rows updated, prices already in sync.");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Error syncing prices: " . $e->getMessage());
            $monitor->classifyAndRecord($e);
            return self::FAILURE;
        }
    }
}
