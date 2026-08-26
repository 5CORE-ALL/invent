<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingDailyMetric;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StoreAmazonListingDailyMetrics extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'amazon:store-listing-daily-metrics
        {--date= : Specific date to store (YYYY-MM-DD)}
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Store daily count of Missing & INV>0 metrics for Amazon listings';

    protected string $monitorJobName = 'Amazon Store Listing Daily Metrics';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeStore($m),
            $this->monitorJobName
        );
    }

    protected function executeStore(CronExecutionContext $monitor): int
    {
        $monitor->startFresh()->markLocalOnly();
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $chunkSize = $this->monitoredChunkSize();

        $this->info("Storing Amazon listing daily metrics for: " . $date->format('Y-m-d'));

        try {
            $productMasters = collect();
            ProductMaster::whereNull('deleted_at')
                ->select('id', 'sku', 'parent', 'Values')
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (&$productMasters) {
                    foreach ($rows as $row) {
                        $productMasters->push($row);
                    }
                }, 'id');

            $skus = $productMasters->pluck('sku')->unique()->toArray();

            $shopifyData = ShopifySku::mapByProductSkus($skus);

            $statusData = collect();
            AmazonDataView::whereIn('sku', $skus)
                ->select('id', 'sku', 'value')
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (&$statusData) {
                    foreach ($rows as $row) {
                        $statusData[$row->sku] = $row;
                    }
                });

            $listingStatusData = collect();
            AmazonDatasheet::whereIn('sku', $skus)
                ->select('id', 'sku', 'listing_status')
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (&$listingStatusData) {
                    foreach ($rows as $row) {
                        $listingStatusData[$row->sku] = $row;
                    }
                });

            $nrListingStatuses = collect();
            AmazonListingStatus::whereIn('sku', $skus)
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (&$nrListingStatuses) {
                    foreach ($rows as $row) {
                        $nrListingStatuses[$row->sku] = $row;
                    }
                });

            $missingInvCombinedCount = 0;
            $monitor->setFetched($productMasters->count());
            $monitor->setExpected(1);

            foreach ($productMasters->chunk($chunkSize) as $pmChunk) {
                foreach ($pmChunk as $item) {
                    $childSku = $item->sku;

                    if (str_starts_with(strtoupper(trim($childSku)), 'PARENT')) {
                        continue;
                    }

                    $inv = isset($shopifyData[$childSku]) ? ($shopifyData[$childSku]->inv ?? 0) : 0;

                    $listingStatus = isset($listingStatusData[$childSku]) ? $listingStatusData[$childSku]->listing_status : null;

                    $nr = null;

                    if (isset($statusData[$childSku])) {
                        $status = $statusData[$childSku]->value;
                        if (!is_array($status)) {
                            $status = json_decode($status, true) ?? [];
                        }

                        $nrlValue = $status['NRL'] ?? null;
                        if ($nrlValue === 'NRL') {
                            $nr = 'NR';
                        } else {
                            $nr = 'REQ';
                        }
                    }

                    if ($nr === null) {
                        $listingStatusRecord = $nrListingStatuses->get($childSku);
                        if ($listingStatusRecord && $listingStatusRecord->value) {
                            $listingValue = is_array($listingStatusRecord->value)
                                ? $listingStatusRecord->value
                                : json_decode($listingStatusRecord->value, true) ?? [];
                            $nr = $listingValue['nr_req'] ?? 'REQ';
                        }
                    }

                    if ($nr === null) {
                        $nr = 'REQ';
                    }

                    if (floatval($inv) > 0 && !$listingStatus && $nr !== 'NR') {
                        $missingInvCombinedCount++;
                    }
                }
                $monitor->incrementProcessed($pmChunk->count());
            }

            AmazonListingDailyMetric::updateOrCreate(
                ['date' => $date->format('Y-m-d')],
                ['missing_status_inv_count' => $missingInvCombinedCount]
            );
            $monitor->incrementUpdated(1);

            $this->info("Successfully stored Missing & INV>0 count: {$missingInvCombinedCount} for date: " . $date->format('Y-m-d'));
            Log::info("Amazon Listing Daily Metrics stored", [
                'date' => $date->format('Y-m-d'),
                'missing_status_inv_count' => $missingInvCombinedCount
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error storing Amazon listing daily metrics: " . $e->getMessage());
            Log::error("Error storing Amazon listing daily metrics", [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $monitor->classifyAndRecord($e);
            return self::FAILURE;
        }
    }
}
