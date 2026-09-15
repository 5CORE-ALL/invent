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

    protected $description = 'Sync Amazon LMP from amazon_sku_competitors into amazon_datsheets.price_lmpa';

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
            if (! \Illuminate\Support\Facades\Schema::hasTable('amazon_sku_competitors')
                || ! \Illuminate\Support\Facades\Schema::hasTable('amazon_datsheets')) {
                $this->warn('⚠️ amazon_sku_competitors / amazon_datsheets missing. Nothing to sync.');

                return self::SUCCESS;
            }

            $skus = DB::table('amazon_sku_competitors')
                ->select('sku')
                ->whereRaw('CAST(price AS DECIMAL(10,2)) > 0')
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
                    $subQuery = DB::table('amazon_sku_competitors')
                        ->select('sku', DB::raw('MIN(CAST(price AS DECIMAL(10,2))) as price'))
                        ->whereRaw('CAST(price AS DECIMAL(10,2)) > 0')
                        ->whereIn('sku', $skuChunk)
                        ->groupBy('sku');

                    return DB::table('amazon_datsheets as a')
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
