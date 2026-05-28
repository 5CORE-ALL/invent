<?php

namespace App\Console\Commands;

use App\Jobs\ImportReverbOrderToShopify;
use App\Models\ReverbOrderMetric;
use App\Services\ReverbApiService;
use App\Models\ReverbProduct;
use App\Models\ReverbSyncSettings;
use App\Models\ReverbSyncState;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class FetchReverbData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:fetch {--force : Force full orders fetch (ignore last_sync)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Reverb listings/orders: replaces reverb_products (truncate + insert), upserts order metrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        
        $this->info('Fetching Reverb Orders...');
        $this->fetchAllOrders();

        $this->info('Fetching Reverb Listings...');
        $listings = $this->fetchAllListings();

        // Use California timezone for date calculations
        $today = Carbon::now('America/Los_Angeles')->startOfDay();
        
        // Calculate L30 range (last 30 days from today)
        $l30End = $today->copy();
        $l30Start = $today->copy()->subDays(30);

        // Calculate L60 range (31-60 days from today) - should be exactly 30 days
        $l60End = $l30Start->copy()->subDay();
        $l60Start = $l60End->copy()->subDays(29); // 30 days total for L60 period

        $this->info("Date ranges - L30: {$l30Start->toDateString()} to {$l30End->toDateString()}, L60: {$l60Start->toDateString()} to {$l60End->toDateString()}");

        // Create map of SKU to listing data - THIS IS THE SOURCE OF ALL SKUs
        $listingMap = [];
        $duplicateSkuCount = 0;
        foreach ($listings as $item) {
            $sku = $this->normalizeSku($item['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            if (isset($listingMap[$sku])) {
                $duplicateSkuCount++;
                if (! $this->shouldPreferListing($item, $listingMap[$sku])) {
                    continue;
                }
            }
            $listingMap[$sku] = $item;
        }
        $this->info('Found ' . count($listingMap) . ' total listings with SKUs.');
        if ($duplicateSkuCount > 0) {
            $this->warn("Merged {$duplicateSkuCount} duplicate SKU listing(s) (e.g. NBSP vs space); kept live/newest per SKU.");
        }

        // Calculate quantities for each SKU (optimized single query)
        $rL30 = $this->calculateQuantitiesFromMetrics($l30Start, $l30End);
        $rL60 = $this->calculateQuantitiesFromMetrics($l60Start, $l60End);

        // Fetch bump bid % for each listing (only bump bid from Reverb API)
        $this->info('Fetching bump bid % for each listing...');
        $bumpBidBySku = $this->fetchBumpBidForListings($listingMap);

        // Prepare bulk update data - Process ALL listed SKUs (not just those with orders)
        $bulkData = [];
        foreach ($listingMap as $sku => $listing) {
            $r30 = $rL30[$sku] ?? 0;
            $r60 = $rL60[$sku] ?? 0;

            $price = $listing['price']['amount'] ?? null;
            $views = $listing['stats']['views'] ?? null;
            $rawInventory = (int) ($listing['inventory'] ?? 0);
            $bumpBid = $bumpBidBySku[$sku] ?? null;
            $listingId = $listing['id'] ?? null;
            $listingState = $this->resolveListingStateFromApi($listing) ?? 'live';
            $remainingInventory = ReverbApiService::effectiveInventoryQuantity($rawInventory, $listingState);

            $bulkData[] = [
                'sku' => $sku,
                'reverb_listing_id' => $listingId,
                'listing_state' => $listingState,
                'r_l30' => $r30,
                'r_l60' => $r60,
                'price' => $price,
                'views' => $views,
                'remaining_inventory' => $remainingInventory,
                'bump_bid' => $bumpBid,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        $this->info('Replacing reverb_products (' . count($bulkData) . ' listings from API)...');
        $this->bulkReplaceProducts($bulkData);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $this->info("Reverb data stored successfully in {$duration} seconds.");
    }

    protected function fetchAllListings(): array
    {
        $listings = [];
        $url = 'https://api.reverb.com/api/my/listings?state=all';
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            $this->error('Reverb API token not configured (REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).');

            return [];
        }

        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/hal+json',
                'Accept-Version' => '3.0',
            ])->get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch listings.');
                break;
            }

            $data = $response->json();
            $listings = array_merge($listings, $data['listings'] ?? []);
            $url = $data['_links']['next']['href'] ?? null;

        } while ($url);

        $this->info('Fetched total listings: ' . count($listings));
        return $listings;
    }

    /**
     * Fetch bump bid % only from Reverb API for each listing.
     * GET https://api.reverb.com/api/listings/{id}/bump returns current_bid (display e.g. "2%").
     */
    protected function fetchBumpBidForListings(array $listingMap): array
    {
        $result = [];
        $total = count($listingMap);
        $index = 0;
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            $this->error('Reverb API token not configured (REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).');

            return [];
        }
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
        ];

        foreach ($listingMap as $sku => $listing) {
            $listingId = $listing['id'] ?? null;
            if (!$listingId) {
                continue;
            }
            $index++;
            if ($index % 50 === 0) {
                $this->info("  Bump bid: {$index}/{$total}...");
            }

            try {
                $response = Http::withHeaders($headers)
                    ->timeout(30)
                    ->retry(3, 2000, throw: false)
                    ->get("https://api.reverb.com/api/listings/{$listingId}/bump");
            } catch (ConnectionException $e) {
                $this->warn("  Connection reset at {$index}/{$total} (listing {$listingId}), skipping bump bid for this listing.");
                usleep(500000); // 0.5s pause after connection error
                continue;
            } catch (\Throwable $e) {
                $this->warn("  Bump bid request failed for listing {$listingId}: " . $e->getMessage());
                continue;
            }

            if ($response->failed()) {
                continue;
            }
            $data = $response->json();
            $currentBid = $data['current_bid'] ?? $data['bump_v2_stats']['current_bid'] ?? null;
            $display = is_array($currentBid) ? ($currentBid['display'] ?? null) : $currentBid;

            // Clean bump bid to prevent "Data too long for column" (e.g. "5.000000074505806%" -> "5%")
            if ($display !== null) {
                if (is_string($display)) {
                    if (preg_match('/^(\d+(?:\.\d+)?)%/', $display, $matches)) {
                        $display = $matches[1] . '%';
                    } else {
                        $display = substr($display, 0, 10);
                    }
                }
                $result[$sku] = $display;
            }
            usleep(150000); // 0.15s between calls to avoid rate limit
        }

        $this->info('Fetched bump bid for ' . count($result) . ' listings.');
        return $result;
    }

    protected function fetchAllOrders(): void
    {
        $baseUrl = 'https://api.reverb.com/api/my/orders/selling/all';

        // Fetch URL: when not --force, only fetch orders updated since last sync
        $lastSync = null;
        if (! $this->option('force') && Schema::hasTable('reverb_sync_states')) {
            $lastSync = ReverbSyncState::getLastSync(ReverbSyncState::KEY_ORDERS_LAST_SYNC);
        }

        // Push cutoff: always use stored value; never change it based on --force
        $lastSyncForPush = ReverbSyncState::getLastSync(ReverbSyncState::KEY_ORDERS_LAST_SYNC_FOR_PUSH);
        if ($lastSyncForPush === null) {
            $lastSyncForPush = Carbon::now();
            if (Schema::hasTable('reverb_sync_states')) {
                ReverbSyncState::setLastSync(ReverbSyncState::KEY_ORDERS_LAST_SYNC_FOR_PUSH, $lastSyncForPush);
                $this->info('First run: set push cutoff to now (no old orders will be pushed): ' . $lastSyncForPush->toIso8601String());
            }
        } else {
            $this->info('Using stored push cutoff (orders after this will be dispatched): ' . $lastSyncForPush->toIso8601String());
        }
        $settings = ReverbSyncSettings::getForReverb();
        $autoImportOrders = (bool) ($settings['order']['import_orders_to_main_store'] ?? false);
        $skipShipped = (bool) ($settings['order']['skip_shipped_orders'] ?? false);
        $jobsDispatched = 0;

        if ($lastSync) {
            $buffer = $lastSync->copy()->subMinutes(5);
            $url = $baseUrl . '?updated_start_date=' . $buffer->toIso8601String();
            $this->info('Fetching orders updated since last sync (with 5-min buffer): ' . $buffer->toIso8601String());
        } else {
            $url = $baseUrl;
            $this->info('Fetching all orders (first run or no last_sync).');
        }

        $pageCount = 0;
        $totalOrders = 0;
        $bulkOrders = [];
        $maxRetries = 5;
        $timeoutSeconds = 60;

        do {
            $pageCount++;
            $response = $this->fetchWithRetry($url, $maxRetries, $timeoutSeconds, $pageCount);

            if ($response === null) {
                $this->warn("Skipping page {$pageCount} after {$maxRetries} attempts (connection reset or timeout). Saving progress and stopping orders fetch.");
                break;
            }

            if ($response->failed()) {
                $this->error('Failed to fetch orders on page ' . $pageCount . ': ' . $response->body());
                break;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            $totalOrders += count($orders);

            // Prepare bulk insert data
            foreach ($orders as $order) {
                $paidAt = $order['paid_at'] ?? $order['created_at'] ?? null;
                if (!$paidAt) continue;

                $paidAtCarbon = Carbon::parse($paidAt, 'America/Los_Angeles');
                $bulkOrders[] = [
                    'order_number' => $order['order_number'],
                    'order_date' => $paidAtCarbon->toDateString(),
                    'order_paid_at' => $paidAtCarbon->toDateTimeString(),
                    'status' => $order['status'] ?? null,
                    'amount' => ($order['total']['amount_cents'] ?? 0) / 100,
                    'display_sku' => $order['title'] ?? null,
                    'sku' => $order['sku'] ?? null,
                    'quantity' => $order['quantity'] ?? 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }

            // Bulk insert in chunks of 100, then dispatch import jobs for new orders in this chunk
            if (count($bulkOrders) >= 100) {
                $chunkToInsert = $bulkOrders;
                $this->bulkUpsertOrders($chunkToInsert);
                $chunkNumbers = array_column($chunkToInsert, 'order_number');
                if ($autoImportOrders && !empty($chunkNumbers)) {
                    $dispatched = $this->dispatchImportJobsForOrderNumbers($chunkNumbers, $lastSyncForPush, $skipShipped);
                    $jobsDispatched += $dispatched;
                }
                $bulkOrders = [];
            }

            $url = isset($data['_links']['next']['href']) ? trim($data['_links']['next']['href']) : null;
            $this->info("  Processed page {$pageCount} ({$totalOrders} orders so far)...");

            if ($url) {
                usleep(300000); // 0.3s between pages
            }
        } while ($url);

        // Insert remaining orders and dispatch import jobs for them
        if (!empty($bulkOrders)) {
            $this->bulkUpsertOrders($bulkOrders);
            $chunkNumbers = array_column($bulkOrders, 'order_number');
            if ($autoImportOrders && !empty($chunkNumbers)) {
                $dispatched = $this->dispatchImportJobsForOrderNumbers($chunkNumbers, $lastSyncForPush, $skipShipped);
                $jobsDispatched += $dispatched;
            }
        }

        if ($jobsDispatched > 0) {
            $this->info("  Total import jobs dispatched: {$jobsDispatched}.");
        }

        if (Schema::hasTable('reverb_sync_states')) {
            ReverbSyncState::setLastSync(ReverbSyncState::KEY_ORDERS_LAST_SYNC);
            $this->info("Fetched and stored {$totalOrders} orders from {$pageCount} pages. Last sync time saved.");
        } else {
            $this->info("Fetched and stored {$totalOrders} orders from {$pageCount} pages.");
        }
    }

    /**
     * Dispatch ImportReverbOrderToShopify jobs for orders that are not yet pushed and meet cutoff/status filters.
     */
    protected function dispatchImportJobsForOrderNumbers(array $orderNumbers, Carbon $lastSyncForPush, bool $skipShipped): int
    {
        $toImport = ReverbOrderMetric::query()
            ->whereNull('shopify_order_id')
            ->whereIn('order_number', $orderNumbers)
            ->whereNotNull('order_paid_at')
            ->where('order_paid_at', '>', $lastSyncForPush);

        if ($skipShipped) {
            $toImport->whereNotIn('status', ['shipped', 'delivered']);
        }

        $ordersToImport = $toImport->orderBy('order_date')->orderBy('id')->get();
        $count = 0;
        foreach ($ordersToImport as $order) {
            ImportReverbOrderToShopify::dispatch($order->id)->onQueue('reverb');
            $count++;
            $this->info("  Dispatched import job for Reverb order #{$order->order_number}");
        }
        return $count;
    }

    /**
     * Fetch URL with retries and exponential backoff. Returns response or null on failure.
     */
    protected function fetchWithRetry(string $url, int $maxAttempts, int $timeoutSeconds, int $pageNum = 0): ?\Illuminate\Http\Client\Response
    {
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            return null;
        }
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
        ];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders($headers)
                    ->timeout($timeoutSeconds)
                    ->connectTimeout(15)
                    ->get($url);

                return $response;
            } catch (ConnectionException $e) {
                $this->warn("  Orders page {$pageNum} attempt {$attempt}/{$maxAttempts}: connection reset - " . $e->getMessage());
                if ($attempt < $maxAttempts) {
                    $delayMs = (int) pow(2, $attempt) * 1000; // 2s, 4s, 8s, 16s, 32s
                    $this->info("  Waiting {$delayMs}ms before retry...");
                    usleep($delayMs * 1000);
                } else {
                    return null;
                }
            } catch (\Throwable $e) {
                $this->warn("  Orders page {$pageNum} attempt {$attempt}/{$maxAttempts}: " . $e->getMessage());
                if ($attempt < $maxAttempts) {
                    $delayMs = (int) pow(2, $attempt) * 1000;
                    usleep($delayMs * 1000);
                } else {
                    return null;
                }
            }
        }

        return null;
    }

    protected function calculateQuantitiesFromMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $this->info("Calculating quantities from metrics table for {$startDate->toDateString()} to {$endDate->toDateString()}...");

        $rawQuantities = ReverbOrderMetric::whereBetween('order_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotIn('status', ['returned', 'refunded'])
            ->whereNotNull('sku')
            ->selectRaw('sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();

        $quantities = [];
        foreach ($rawQuantities as $sku => $total) {
            $normalized = $this->normalizeSku($sku);
            if ($normalized === '') {
                continue;
            }
            $quantities[$normalized] = ($quantities[$normalized] ?? 0) + (int) $total;
        }

        $this->info('Found ' . count($quantities) . ' SKUs with orders in this period.');

        return $quantities;
    }

    /**
     * Bulk upsert orders using raw SQL for better performance
     */
    protected function bulkUpsertOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        try {
            DB::transaction(function () use ($orders) {
                foreach ($orders as $order) {
                    DB::table('reverb_order_metrics')
                        ->updateOrInsert(
                            ['order_number' => $order['order_number']],
                            $order
                        );
                }
            });
        } catch (\Exception $e) {
            $this->error('Error bulk upserting orders: ' . $e->getMessage());
            // Fallback to individual inserts if bulk fails
            foreach ($orders as $order) {
                try {
                    ReverbOrderMetric::updateOrCreate(
                        ['order_number' => $order['order_number']],
                        $order
                    );
                } catch (\Exception $e) {
                    $this->warn('Failed to insert order ' . ($order['order_number'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Replace all reverb_products rows with the latest API snapshot (delete + insert).
     * Uses DELETE not TRUNCATE — MySQL TRUNCATE implicitly commits and breaks DB::transaction().
     * Skips replace when there is nothing to insert so a failed fetch does not wipe the table.
     */
    protected function bulkReplaceProducts(array $data): void
    {
        if (empty($data)) {
            $this->warn('No Reverb listings to insert — reverb_products was not changed.');

            return;
        }

        $data = $this->dedupeProductRowsForInsert($data);

        if ($data === []) {
            $this->warn('No valid SKUs in listing data — reverb_products was not changed.');

            return;
        }

        $previousCount = ReverbProduct::count();

        try {
            DB::transaction(function () use ($data) {
                Schema::disableForeignKeyConstraints();
                DB::table('reverb_products')->delete();
                Schema::enableForeignKeyConstraints();

                $chunks = array_chunk($data, 500);
                foreach ($chunks as $index => $chunk) {
                    // Safety net: never send duplicate SKUs to MySQL (case/unicode variants).
                    $chunk = $this->dedupeProductRowsForInsert($chunk);
                    DB::table('reverb_products')->insert($chunk);
                    if (count($chunks) > 1) {
                        $this->info('  Inserted chunk '.($index + 1).' of '.count($chunks).'...');
                    }
                }
            });

            $this->info('reverb_products: replaced '.$previousCount.' row(s) with '.count($data).' listing(s).');
        } catch (\Exception $e) {
            $this->error('Error replacing reverb_products: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Collapse rows that MySQL treats as the same SKU (case / spacing / PCS variants).
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function dedupeProductRowsForInsert(array $data): array
    {
        $bySku = [];
        foreach ($data as $item) {
            $key = ReverbProduct::normalizeSkuForLookup($item['sku'] ?? '');
            if ($key === '') {
                continue;
            }
            if (isset($bySku[$key]) && ! $this->shouldPreferProductRow($item, $bySku[$key])) {
                continue;
            }
            $item['sku'] = $key;
            $bySku[$key] = $item;
        }

        return array_values($bySku);
    }

    /**
     * Normalize SKU for storage and dedup (same rules as reverb-pricing lookup).
     */
    protected function normalizeSku(mixed $sku): string
    {
        return ReverbProduct::normalizeSkuForLookup((string) $sku);
    }

    /**
     * When duplicate normalized SKUs reach bulk insert, keep live/newest listing row.
     */
    protected function shouldPreferProductRow(array $candidate, array $current): bool
    {
        $candidatePriority = $this->listingStatePriority($candidate['listing_state'] ?? null);
        $currentPriority = $this->listingStatePriority($current['listing_state'] ?? null);
        if ($candidatePriority !== $currentPriority) {
            return $candidatePriority > $currentPriority;
        }

        return (int) ($candidate['reverb_listing_id'] ?? 0) > (int) ($current['reverb_listing_id'] ?? 0);
    }

    /**
     * When Reverb returns multiple listings with the same normalized SKU, keep the best row.
     */
    protected function shouldPreferListing(array $candidate, array $current): bool
    {
        $candidatePriority = $this->listingStatePriority($this->resolveListingStateFromApi($candidate));
        $currentPriority = $this->listingStatePriority($this->resolveListingStateFromApi($current));
        if ($candidatePriority !== $currentPriority) {
            return $candidatePriority > $currentPriority;
        }

        return (int) ($candidate['id'] ?? 0) > (int) ($current['id'] ?? 0);
    }

    protected function listingStatePriority(?string $state): int
    {
        return match (strtolower((string) $state)) {
            'live', 'published' => 100,
            'sold' => 50,
            default => 10,
        };
    }

    protected function resolveListingStateFromApi(array $listing): ?string
    {
        $state = $listing['state'] ?? $listing['status'] ?? null;
        if (is_array($state)) {
            $state = $state['slug'] ?? $state['name'] ?? $state['title'] ?? null;
        }
        if ($state === null && isset($listing['_embedded']['state'])) {
            $emb = $listing['_embedded']['state'];
            $state = is_array($emb) ? ($emb['slug'] ?? $emb['name'] ?? null) : $emb;
        }

        return $state !== null ? strtolower((string) $state) : null;
    }
}
