<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\AmazonListingRaw;
use App\Models\AmazonSyncHistory;
use App\Services\AmazonSpApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAmazonProducts extends Command
{
    use ProcessesUpdatesInChunks;

    protected $signature = 'amazon:sync-products
                            {--enrich : Enrich listings with Catalog and Listings API data}
                            {--enrich-limit=100 : Max SKUs to enrich per run (0 = no limit)}
                            {--skip-report : Skip report fetch, only enrich existing records}
                            {--batch-size=100 : Records per batch before delay}
                            {--chunk= : Override DB write chunk size (default from cron-monitor config)}
                            {--sku= : Enrich single SKU only (e.g. "3501 USB") for testing}';

    protected $description = 'Sync Amazon product listings from GET_MERCHANT_LISTINGS_ALL_DATA report, enrich with Catalog + Listings APIs (26 fields)';

    private const BATCH_DELAY_SEC = 5;
    private const CATALOG_DELAY_SEC = 1;
    private const LISTINGS_DELAY_MS = 200;

    public function handle(): int
    {
        $startedAt = now();
        $history = AmazonSyncHistory::create([
            'started_at' => $startedAt,
            'status' => AmazonSyncHistory::STATUS_RUNNING,
        ]);

        try {
            $service = new AmazonSpApiService();
            $singleSku = $this->option('sku');
            if ($singleSku !== null && trim($singleSku) !== '') {
                return $this->enrichSingleSku($service, trim($singleSku), $history) ? 0 : 1;
            }

            $skipReport = $this->option('skip-report');
            $doEnrich = $this->option('enrich');
            $enrichLimit = (int) $this->option('enrich-limit');
            $batchSize = (int) $this->option('batch-size');
            $batchSize = max(1, min($batchSize, 100));

            // Step 1: Fetch and store listings report (unless skipped)
            if (! $skipReport) {
                $this->info('Fetching listings report...');
                $result = $service->fetchAndStoreListingsReport();
                if (! ($result['success'] ?? false)) {
                    $history->update([
                        'status' => AmazonSyncHistory::STATUS_FAILED,
                        'finished_at' => now(),
                        'error_message' => $result['message'] ?? 'Report fetch failed',
                    ]);
                    $this->error('Report fetch failed: ' . ($result['message'] ?? 'Unknown error'));
                    return 1;
                }
                $count = $result['count'] ?? 0;
                $history->increment('records_fetched', $count);
                $this->info("Imported {$count} listings from report.");
                Log::info('SyncAmazonProducts: Report imported', ['count' => $count]);
            }

            $totalRecords = AmazonListingRaw::count();
            $this->info("Total records in amazon_listings_raw: {$totalRecords}");

            // Step 2: Enrich with Catalog and Listings API (optional)
            if ($doEnrich && $totalRecords > 0) {
                $this->info('Enriching listings with Catalog and Listings API (26 fields)...');
                $enriched = $this->enrichListings($service, $enrichLimit, $batchSize, $history);
                $this->info("Enriched {$enriched} listings.");
                $history->increment('records_updated', $enriched);
                Log::info('SyncAmazonProducts: Enrichment complete', [
                    'enriched' => $enriched,
                    'api_calls' => $history->api_calls_count,
                ]);
            }

            $history->update([
                'status' => AmazonSyncHistory::STATUS_SUCCESS,
                'finished_at' => now(),
            ]);

            $this->info('Amazon product sync completed successfully.');
            return 0;
        } catch (\Throwable $e) {
            Log::error('SyncAmazonProducts: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $history->update([
                'status' => AmazonSyncHistory::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            $this->error('Sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function enrichListings(AmazonSpApiService $service, int $limit, int $batchSize, AmazonSyncHistory $history): int
    {
        $baseQuery = AmazonListingRaw::query()
            ->whereNotNull('asin1')
            ->where('asin1', '!=', '')
            ->where(function ($q) {
                $q->whereNull('model_number')
                    ->orWhereNull('color')
                    ->orWhereNull('manufacturer');
            });

        $eligibleTotal = (clone $baseQuery)->count();
        if ($eligibleTotal === 0) {
            $this->info('No listings to enrich.');
            return 0;
        }

        $total = $limit > 0 ? min($limit, $eligibleTotal) : $eligibleTotal;
        $chunkSize = $this->monitoredChunkSize();
        $enriched = 0;
        $apiCalls = 0;
        $skipped = 0;
        $processed = 0;
        $stop = false;
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $baseQuery->orderBy('id')->chunkById($chunkSize, function ($listings) use (
            $service,
            $batchSize,
            $total,
            $limit,
            &$enriched,
            &$apiCalls,
            &$skipped,
            &$processed,
            &$stop,
            $bar
        ) {
            if ($stop) {
                return false;
            }

            $pendingUpdates = [];

            foreach ($listings as $listing) {
                if ($limit > 0 && $processed >= $limit) {
                    $stop = true;
                    break;
                }

                $processed++;
                $context = [];
                try {
                    $updates = $service->enrichListingData($listing->asin1, $listing->seller_sku, $context);
                    $apiCalls += 2; // Catalog + Listings API per SKU

                    if (! empty($updates)) {
                        $fillable = (new AmazonListingRaw)->getFillable();
                        $imageUrlsForRaw = $updates['_image_urls_for_raw_data'] ?? null;
                        unset($updates['_image_urls_for_raw_data']);
                        $filtered = array_intersect_key($updates, array_flip($fillable));
                        if ($imageUrlsForRaw !== null && is_array($imageUrlsForRaw)) {
                            $rawData = $listing->raw_data;
                            if (! is_array($rawData)) {
                                $rawData = is_string($rawData) ? (json_decode($rawData, true) ?? []) : [];
                            }
                            foreach ($imageUrlsForRaw as $k => $v) {
                                if (is_string($v) && $v !== '') {
                                    $rawData[$k] = $v;
                                }
                            }
                            $filtered['raw_data'] = $rawData;
                        }
                        $pendingUpdates[] = ['listing' => $listing, 'filtered' => $filtered, 'updates' => $updates, 'imageUrlsForRaw' => $imageUrlsForRaw];
                    } else {
                        $skipped++;
                    }

                    if (! empty($context['warnings'])) {
                        foreach ($context['warnings'] as $w) {
                            Log::warning('SyncAmazonProducts: '.$w);
                        }
                    }
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::warning('SyncAmazonProducts: Enrich failed for SKU', [
                        'sku' => $listing->seller_sku,
                        'asin' => $listing->asin1,
                        'error' => $e->getMessage(),
                    ]);
                }

                $pct = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                $bar->setMessage("{$enriched} enriched | {$pct}%");
                $bar->advance();

                if ($processed % $batchSize === 0 && $processed < $total) {
                    Log::info('SyncAmazonProducts: Batch pause', [
                        'processed' => $processed,
                        'enriched' => $enriched,
                        'api_calls' => $apiCalls,
                    ]);
                    sleep(self::BATCH_DELAY_SEC);
                }
            }

            if ($pendingUpdates !== []) {
                DB::transaction(function () use ($pendingUpdates, &$enriched) {
                    foreach ($pendingUpdates as $entry) {
                        $entry['listing']->update($entry['filtered']);
                        $enriched++;
                        Log::debug('SyncAmazonProducts: SKU enriched', [
                            'sku' => $entry['listing']->seller_sku,
                            'asin' => $entry['listing']->asin1,
                            'fields_populated' => array_keys($entry['updates']),
                            'count' => count($entry['updates']),
                            'images_merged' => $entry['imageUrlsForRaw'] !== null ? count($entry['imageUrlsForRaw']) : 0,
                            'your_price' => $entry['filtered']['your_price'] ?? null,
                            'thumbnail_set' => isset($entry['filtered']['thumbnail_image']),
                        ]);
                    }
                });
            }

            return $stop ? false : null;
        });

        $bar->finish();
        $this->newLine();
        $history->increment('api_calls_count', $apiCalls);
        $history->increment('records_skipped', $skipped);

        return $enriched;
    }

    private function enrichSingleSku(AmazonSpApiService $service, string $sku, AmazonSyncHistory $history): bool
    {
        $this->info("Enriching single SKU: {$sku}");
        $result = $service->enrichSingleSku($sku, true);
        if (isset($result['error'])) {
            $this->error($result['error']);
            $history->update([
                'status' => AmazonSyncHistory::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => $result['error'],
            ]);
            return false;
        }
        $this->info("Enriched SKU {$sku}: " . ($result['updates_count'] ?? 0) . ' fields saved.');
        if (! empty($result['warnings'])) {
            foreach ($result['warnings'] as $w) {
                $this->warn($w);
            }
        }
        $history->update([
            'status' => AmazonSyncHistory::STATUS_SUCCESS,
            'finished_at' => now(),
            'records_updated' => 1,
        ]);
        return true;
    }
}
