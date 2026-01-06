<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\EbayMetric;
use App\Models\EbayTask;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class FetchEbayReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-ebay-reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch eBay reports and store metrics in DB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // Check database connection
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $token = $this->getToken();
            if (! $token) {
                $this->error('Token error');
                return 1;
            }

            $taskId = $this->getInventoryTaskId($token);
            if (! $taskId) {
                $this->error('Task error');
                return 1;
            }

            $listingData = $this->processTask($taskId, $token);
        
        // Delete the task after successful processing to prevent reuse
        $this->deleteTask($taskId);

        $this->info("📊 Total raw listings fetched: " . count($listingData));

        // Save price + SKU mapping
        // Map: sku => itemId (keep latest/active item_id per SKU)
        // Note: SKU is the unique identifier to prevent duplicates.
        // Same SKU may appear in multiple eBay listings, we keep the most recent item_id
        $skuToItemId = [];
        $skuPrices = [];
        $skipped = ['no_item_id' => 0, 'no_sku' => 0, 'duplicate_sku' => 0];
        $itemsWithoutSku = [];

        $duplicateDetails = [];
        
        foreach ($listingData as $row) {
            $itemId = $row['item_id'] ?? null;
            $sku = trim((string)($row['sku'] ?? ''));
            $price = $row['price'] ?? null;

            // skip rows with no itemId
            if (! $itemId) {
                $skipped['no_item_id']++;
                continue;
            }
            
            if ($sku === '') {
                $skipped['no_sku']++;
                // Save items without SKU using item_id as fallback
                $itemsWithoutSku[$itemId] = $price;
                continue;
            }

            // Track duplicates
            if (isset($skuToItemId[$sku])) {
                $skipped['duplicate_sku']++;
                if (!isset($duplicateDetails[$sku])) {
                    $duplicateDetails[$sku] = [$skuToItemId[$sku]];
                }
                $duplicateDetails[$sku][] = $itemId;
            }

            // Keep the latest item_id for each SKU (prevent duplicates)
            $skuToItemId[$sku] = $itemId;
            $skuPrices[$sku] = $price;
        }

        $this->info("📝 Unique SKUs to save: " . count($skuToItemId));
        if (count($itemsWithoutSku) > 0) {
            $this->warn("⚠️  Found {$skipped['no_sku']} listings without SKU (will use item_id as identifier)");
        }
        if ($skipped['no_item_id'] > 0) {
            $this->warn("⚠️  Skipped {$skipped['no_item_id']} listings with no item_id");
        }
        if ($skipped['duplicate_sku'] > 0) {
            $this->info("ℹ️  Found {$skipped['duplicate_sku']} duplicate SKUs (using latest item_id)");
            $this->newLine();
            $this->info("📋 Duplicate SKU Details (first 10):");
            $count = 0;
            foreach ($duplicateDetails as $sku => $itemIds) {
                if ($count >= 10) break;
                $this->info("   • SKU: " . str_pad($sku, 35) . " → Item IDs: " . implode(', ', $itemIds));
                $count++;
            }
            if (count($duplicateDetails) > 10) {
                $this->info("   ... and " . (count($duplicateDetails) - 10) . " more");
            }
        }

        // Track all active SKUs for cleanup
        $allActiveSkus = array_keys($skuToItemId);
        foreach ($itemsWithoutSku as $itemId => $price) {
            $allActiveSkus[] = 'NO-SKU-' . $itemId;
        }

            // Save metrics with SKU as unique identifier in chunks
            $saved = 0;
            $skuChunks = array_chunk($skuToItemId, 100, true);
            foreach ($skuChunks as $chunk) {
                foreach ($chunk as $sku => $itemId) {
                    // Check if this SKU exists with different item_id and clean up
                    $existingRecord = EbayMetric::where('sku', $sku)->first();
                    if ($existingRecord && $existingRecord->item_id !== $itemId) {
                        $this->info("🔄 SKU {$sku}: Item ID changed from {$existingRecord->item_id} to {$itemId}");
                    }
                    
                    EbayMetric::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'item_id' => $itemId,
                            'ebay_price' => $skuPrices[$sku],
                            'report_date' => now()->toDateString(),
                        ]
                    );
                    $saved++;
                }
                DB::disconnect();
            }

            // Save items without SKU (use item_id as SKU identifier) in chunks
            $itemsWithoutSkuChunks = array_chunk($itemsWithoutSku, 100, true);
            foreach ($itemsWithoutSkuChunks as $chunk) {
                foreach ($chunk as $itemId => $price) {
                    EbayMetric::updateOrCreate(
                        ['sku' => 'NO-SKU-' . $itemId],
                        [
                            'item_id' => $itemId,
                            'ebay_price' => $price,
                            'report_date' => now()->toDateString(),
                        ]
                    );
                    $saved++;
                }
                DB::disconnect();
            }

            // Clean up SKUs that are no longer active (not in current fetch)
            if (!empty($allActiveSkus)) {
                $deletedCount = EbayMetric::whereNotIn('sku', $allActiveSkus)->delete();
            } else {
                $deletedCount = 0;
            }
            if ($deletedCount > 0) {
                $this->info("🗑️  Cleaned up {$deletedCount} inactive SKU records");
            }
            DB::disconnect();

            $this->info("💾 Saved/Updated {$saved} records in database");

        // Build reverse mapping: itemId => [skus]
        $itemIdToSkus = [];
        foreach ($skuToItemId as $sku => $itemId) {
            $itemIdToSkus[$itemId][] = $sku;
        }
        
        // Add items without SKU
        foreach ($itemsWithoutSku as $itemId => $price) {
            $itemIdToSkus[$itemId][] = 'NO-SKU-' . $itemId;
        }

        $this->info("📋 Tracking " . count($itemIdToSkus) . " unique item IDs");

        // Final summary
        $uniqueItems = count($itemIdToSkus);
        $totalSkus = count($skuToItemId) + count($itemsWithoutSku);
        $this->newLine();
        $this->info("📈 Final Summary:");
        $this->info("   • Unique eBay Item IDs: {$uniqueItems}");
        $this->info("   • Total SKU Records: {$totalSkus}");
        $this->info("   • Multi-variation Items: " . count(array_filter($itemIdToSkus, fn($skus) => count($skus) > 1)));
        
        if ($uniqueItems < 753) {
            $missing = 753 - $uniqueItems;
            $this->warn("   ⚠️  {$missing} listings not in API response (may be inactive or filtered by eBay)");
        }

        // Update views per itemId
        $this->updateViews($token, $itemIdToSkus);

        // Update L7 views (last 7 days)
        $this->updateL7Views($token, $itemIdToSkus);

            // Update organic clicks for last 30 days
            $this->updateOrganicClicksFromSheet($itemIdToSkus);

            // L7 / L30 / L60
            $existingItemIds = array_keys($itemIdToSkus);
            $dateRanges = $this->dateRanges();

            $l7 = $this->orderQty($token, $dateRanges['l7'], $existingItemIds);
            $l30 = $this->orderQty($token, $dateRanges['l30'], $existingItemIds);
            $l60 = $this->orderQty($token, $dateRanges['l60'], $existingItemIds);

            // Save L7/L30/L60 for each SKU in chunks
            $chunks = array_chunk($itemIdToSkus, 100, true);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $itemId => $skus) {
                    foreach ($skus as $sku) {
                        EbayMetric::where('sku', $sku)
                            ->update([
                                'ebay_l7' => $l7[$itemId] ?? 0,
                                'ebay_l30' => $l30[$itemId] ?? 0,
                                'ebay_l60' => $l60[$itemId] ?? 0,
                            ]);
                    }
                }
                DB::disconnect();
            }

            $this->info('✅ eBay Metrics updated');
            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::disconnect();
        }
    }

    private function dateRanges()
    {
        $today = Carbon::today();

        return [
            'l7' => [
                'start' => $today->copy()->subDays(6),
                'end' => $today->copy()->subDay(),
            ],
            'l30' => [
                'start' => $today->copy()->subDays(29),
                'end' => $today->copy()->subDay(),
            ],
            'l60' => [
                'start' => $today->copy()->subDays(59),
                'end' => $today->copy()->subDays(30),
            ],
        ];
    }

    private function getToken()
    {
        $id = env('EBAY_APP_ID');
        $secret = env('EBAY_CERT_ID');
        $rtoken = env('EBAY_REFRESH_TOKEN');

        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $attempt++;
            
            try {
                $response = Http::asForm()
                    ->withBasicAuth($id, $secret)
                    ->timeout(30)
                    ->connectTimeout(15)
                    ->retry(2, 1000)
                    ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $rtoken,
                    ]);

                if (! $response->successful()) {
                    if ($attempt < $maxRetries) {
                        $this->warn("⚠️  Token request failed (attempt {$attempt}/{$maxRetries}), retrying...");
                        sleep(2);
                        continue;
                    }
                    $this->error('❌ TOKEN FAILED: '.json_encode($response->json()));
                    return null;
                }

                return $response->json()['access_token'] ?? null;

            } catch (\Throwable $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("⚠️  Token request exception (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                    $this->warn('EBAY TOKEN EXCEPTION (retrying) - Attempt: ' . $attempt . ', Message: ' . $e->getMessage());
                    sleep(2);
                    continue;
                }
                
                $this->error('EBAY TOKEN EXCEPTION: ' . $e->getMessage());

                return null;
            }
        }

        return null;
    }

    private function getInventoryTaskId($token)
    {
        $type = 'LMS_ACTIVE_INVENTORY_REPORT';

        $this->info('⏳ Creating new task...');

        $payload = [
            'feedType' => $type,
            'format' => 'TSV_GZIP',
            'schemaVersion' => '1.0',
        ];

        $maxRetries = 3;
        $attempt = 0;
        $response = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            
            try {
                $response = Http::withToken($token)
                    ->timeout(60)
                    ->connectTimeout(20)
                    ->retry(2, 1000)
                    ->post('https://api.ebay.com/sell/feed/v1/inventory_task', $payload);

                if ($response->successful()) {
                    break;
                }

                if ($attempt < $maxRetries) {
                    $this->warn("⚠️  Task API failed (attempt {$attempt}/{$maxRetries}), retrying...");
                    sleep(2);
                    continue;
                }

                $this->error('❌ Task API failed: '.$response->body());
                return null;
            } catch (\Throwable $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("⚠️  Task API exception (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                    sleep(2);
                    continue;
                }
                $this->error('❌ Task API exception: '.$e->getMessage());
                return null;
            }
        }

        if (! $response || ! $response->successful()) {
            $this->error('❌ Task API failed after retries');
            return null;
        }

        $location = $response->header('Location');
        if (! $location) {
            $this->error('❌ Location header not found!');
            return null;
        }

        $taskId = basename($location);

        EbayTask::create([
            'ebay_account' => 'Ebay',
            'task_id' => $taskId,
            'type' => $type,
        ]);

        $this->info("✅ New Task Created: $taskId");

        return $taskId;
    }

    private function deleteTask($taskId)
    {
        try {
            $deleted = EbayTask::where('task_id', $taskId)->delete();
            if ($deleted) {
                $this->info("🗑️  Task {$taskId} deleted from database (one-time use)");
            } else {
                $this->warn("⚠️  Task {$taskId} not found in database to delete");
            }
        } catch (\Throwable $e) {
            $this->warn("⚠️  Failed to delete task {$taskId}: " . $e->getMessage());
            $this->warn('Failed to delete eBay task - Task ID: ' . $taskId . ', Error: ' . $e->getMessage());
        }
    }

    private function processTask($taskId, $token)
    {
        while (true) {
            $maxRetries = 3;
            $attempt = 0;
            $check = null;

            while ($attempt < $maxRetries) {
                $attempt++;
                
                try {
                    $check = Http::withToken($token)
                        ->timeout(30)
                        ->connectTimeout(15)
                        ->retry(2, 1000)
                        ->get("https://api.ebay.com/sell/feed/v1/inventory_task/{$taskId}");

                    if ($check->successful()) {
                        break;
                    }

                    if ($attempt < $maxRetries) {
                        $this->warn("⚠️  Task status check failed (attempt {$attempt}/{$maxRetries}), retrying...");
                        sleep(2);
                        continue;
                    }
                } catch (\Throwable $e) {
                    if ($attempt < $maxRetries) {
                        $this->warn("⚠️  Task status check exception (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                        sleep(2);
                        continue;
                    }
                    $this->error('❌ Task status check exception: '.$e->getMessage());
                    sleep(10);
                    continue;
                }
            }

            if (! $check || ! $check->successful()) {
                $this->warn('⚠️  Failed to check task status, retrying in 10 seconds...');
                sleep(10);
                continue;
            }

            $status = $check['status'] ?? 'UNKNOWN';

            if (in_array($status, ['COMPLETED', 'COMPLETED_WITH_ERROR', 'FAILED'])) {
                break;
            }
            sleep(10);
        }

        return $this->downloadReport($taskId, $token);
    }

    private function downloadReport($taskId, $token)
    {
        $url = "https://api.ebay.com/sell/feed/v1/task/{$taskId}/download_result_file";

        $maxRetries = 3;
        $attempt = 0;
        $response = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            
            try {
                $response = Http::withToken($token)
                    ->timeout(120)
                    ->connectTimeout(20)
                    ->retry(2, 1000)
                    ->get($url);

                if ($response->successful()) {
                    break;
                }

                if ($attempt < $maxRetries) {
                    $this->warn("⚠️  Download failed (attempt {$attempt}/{$maxRetries}), retrying...");
                    sleep(2);
                    continue;
                }

                $this->error('❌ Download failed: '.$response->body());
                return [];
            } catch (\Throwable $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("⚠️  Download exception (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                    sleep(2);
                    continue;
                }
                $this->error('❌ Download exception: '.$e->getMessage());
                return [];
            }
        }

        if (! $response || ! $response->successful()) {
            $this->error('❌ Download failed after retries');
            return [];
        }

        $content = $response->body();
        $magic = substr($content, 0, 2);

        // ZIP
        if ($magic === 'PK') {
            $zipPath = storage_path("app/{$taskId}.zip");
            file_put_contents($zipPath, $content);

            $zip = new ZipArchive;
            $ok = $zip->open($zipPath);
            if ($ok === true) {
                $zip->extractTo(storage_path('app/'));
                $zip->close();
            }

            $xml = glob(storage_path('app/*.xml'));
            if (! $xml) {
                return [];
            }

            $xmlObj = simplexml_load_file($xml[0]);
            @unlink($zipPath);
            @unlink($xml[0]);

            return $this->parseXml($xmlObj);
        }

        // GZIP
        if (substr($content, 0, 2) === "\x1f\x8b") {
            return $this->parseGzip($taskId, $content);
        }

        return [];
    }

    private function parseXml($xml)
    {
        $out = [];
        $seen = []; // Track item_id + sku combinations to avoid duplicates

        foreach ($xml->ActiveInventoryReport->SKUDetails as $item) {
            $itemId = (string) $item->ItemID;
            if (! $itemId) {
                continue;
            }

            // capture primary SKU (if present) and variations
            $primarySku = trim((string) ($item->SKU ?? ''));
            $price = isset($item->Price) ? (float) $item->Price : null;

            // Process variations first to collect SKUs with prices
            $variationData = [];
            foreach ($item->Variations->Variation ?? [] as $v) {
                $varSku = (string) $v->SKU;
                $varPrice = isset($v->Price) ? (float) $v->Price : $price;
                
                $variationData[$varSku] = [
                    'item_id' => $itemId,
                    'sku' => $varSku,
                    'price' => $varPrice,
                ];
            }

            // Add primary SKU only if it's not in variations OR if it has a price
            if ($primarySku !== '') {
                $key = $itemId . '|' . $primarySku;
                // Skip primary SKU if it exists in variations (variation price takes priority)
                if (!isset($variationData[$primarySku]) && !isset($seen[$key])) {
                    $out[] = [
                        'item_id' => $itemId,
                        'sku' => $primarySku,
                        'price' => $price,
                    ];
                    $seen[$key] = true;
                }
            }

            // Add all variations
            foreach ($variationData as $varSku => $varData) {
                $key = $itemId . '|' . $varSku;
                if (!isset($seen[$key])) {
                    $out[] = $varData;
                    $seen[$key] = true;
                }
            }
        }

        return $out;
    }

    private function parseGzip($taskId, $content)
    {
        $gz = storage_path("app/{$taskId}.tsv.gz");
        $tsv = storage_path("app/{$taskId}.tsv");

        file_put_contents($gz, $content);

        $in = gzopen($gz, 'rb');
        $out = fopen($tsv, 'wb');
        while (! gzeof($in)) {
            fwrite($out, gzread($in, 4096));
        }
        gzclose($in);
        fclose($out);

        $lines = file($tsv, FILE_SKIP_EMPTY_LINES);
        @unlink($gz);
        @unlink($tsv);

        if (! $lines) {
            return [];
        }

        $rows = array_map(fn ($l) => str_getcsv($l, "\t"), $lines);
        $headers = array_shift($rows);

        $totalRows = count($rows);
        $data = [];
        $parseSkipped = ['column_mismatch' => 0, 'no_itemid' => 0, 'no_sku' => 0, 'duplicate_in_feed' => 0];
        $seen = []; // Track item_id + sku combinations
        
        foreach ($rows as $row) {
            if (count($headers) != count($row)) {
                $parseSkipped['column_mismatch']++;
                continue;
            }
            $d = array_combine($headers, $row);
            $itemId = $d['itemId'] ?? null;
            $sku = trim((string) ($d['sku'] ?? ''));
            
            if (! $itemId) {
                $parseSkipped['no_itemid']++;
                continue;
            }
            
            if ($sku === '') {
                $parseSkipped['no_sku']++;
                continue;
            }
            
            // Check for duplicates in the feed itself
            $key = $itemId . '|' . $sku;
            if (isset($seen[$key])) {
                $parseSkipped['duplicate_in_feed']++;
                continue;
            }
            $seen[$key] = true;
            
            $data[] = [
                'item_id' => $itemId,
                'sku' => $sku,
                'price' => $d['price'] ?? null,
            ];
        }

            // Log parsing stats
            if ($parseSkipped['column_mismatch'] > 0 || $parseSkipped['no_itemid'] > 0 || $parseSkipped['no_sku'] > 0 || $parseSkipped['duplicate_in_feed'] > 0) {
                $this->info('eBay Report Parsing - Total rows: ' . $totalRows . ', Parsed: ' . count($data) . ', Skipped (column_mismatch: ' . $parseSkipped['column_mismatch'] . ', no_itemid: ' . $parseSkipped['no_itemid'] . ', no_sku: ' . $parseSkipped['no_sku'] . ', duplicate_in_feed: ' . $parseSkipped['duplicate_in_feed'] . ')');
            }

        return $data;
    }

    private function updateViews($token, $map)
    {
        // $map = [ itemId => [sku,sku,...], ... ]
        $chunks = array_chunk(array_keys($map), 20);
        
        $this->info('🔄 Fetching views for ' . count($map) . ' items in ' . count($chunks) . ' chunks...');

        foreach ($chunks as $chunkIndex => $chunk) {
            $ids = implode('|', $chunk);
            $range = now()->subDays(30)->format('Ymd').'..'.now()->subDay()->format('Ymd');

            $url = "https://api.ebay.com/sell/analytics/v1/traffic_report?dimension=LISTING&filter=listing_ids:%7B{$ids}%7D,date_range:[{$range}]&metric=LISTING_VIEWS_TOTAL";
            
            $this->info("📊 Chunk {$chunkIndex}: " . implode(', ', array_slice($chunk, 0, 3)) . (count($chunk) > 3 ? '...' : ''));

            $maxRetries = 3;
            $attempt = 0;
            $r = null;

            while ($attempt < $maxRetries) {
                $attempt++;
                
                try {
                    $r = Http::withToken($token)
                        ->timeout(60)
                        ->connectTimeout(20)
                        ->retry(2, 1000)
                        ->get($url);

                    if ($r->successful()) {
                        break;
                    }

                    if ($attempt < $maxRetries) {
                        $this->warn("⚠️  Views API failed for chunk {$chunkIndex} (attempt {$attempt}/{$maxRetries}), retrying...");
                        sleep(2);
                        continue;
                    }

                    $this->error('❌ Views API failed for chunk ' . $chunkIndex . ': ' . $r->body());
                    continue 2; // Continue to next chunk
                } catch (\Throwable $e) {
                    if ($attempt < $maxRetries) {
                        $this->warn("⚠️  Views API exception for chunk {$chunkIndex} (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                        sleep(2);
                        continue;
                    }
                    $this->error('❌ Views API exception for chunk ' . $chunkIndex . ': ' . $e->getMessage());
                    continue 2; // Continue to next chunk
                }
            }

            if (! $r || ! $r->successful()) {
                $this->warn('⚠️  Views API failed for chunk ' . $chunkIndex . ' after retries, skipping...');
                continue;
            }

            $records = $r['records'] ?? [];
            $this->info("📈 Found " . count($records) . " view records");
            
            if (empty($records)) {
                $this->warn('⚠️  No view records found. Response: ' . json_encode($r->json()));
            }

            foreach ($records as $rec) {
                $id = $rec['dimensionValues'][0]['value'] ?? null;
                $v = $rec['metricValues'][0]['value'] ?? 0;
                if (! $id) {
                    $this->warn('⚠️  Record missing item ID: ' . json_encode($rec));
                    continue;
                }
                
                $this->info("📊 Item {$id}: {$v} views");

                $skus = $map[$id] ?? [];
                if (!empty($skus)) {
                    foreach ($skus as $sku) {
                        $updated = EbayMetric::where('sku', $sku)
                            ->update(['views' => $v]);
                        
                        if ($updated) {
                            $this->info("✅ Updated views for {$id}/{$sku}: {$v}");
                        } else {
                            $this->warn("⚠️  Failed to update views for {$id}/{$sku}");
                        }
                    }
                }
            }
            
            DB::disconnect();
            
            // Add small delay between API calls
            if ($chunkIndex < count($chunks) - 1) {
                sleep(1);
            }
        }
    }

    private function updateOrganicClicksFromSheet($map)
    {
        $url = "https://script.google.com/macros/s/AKfycbyaXB7cj9TIL_gVY94F3JR3tx-dy6k4704YiwMqpfKJQnWTqAcMWfA-KMhnA6yitHTT/exec";

        $maxRetries = 3;
        $attempt = 0;
        $res = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            
            try {
                $res = Http::timeout(30)
                    ->connectTimeout(15)
                    ->retry(2, 1000)
                    ->get($url);

                if ($res->successful()) {
                    break;
                }

                if ($attempt < $maxRetries) {
                    $this->warn("⚠️  Google Sheet fetch failed (attempt {$attempt}/{$maxRetries}), retrying...");
                    sleep(2);
                    continue;
                }

                $this->warn('⚠️ Failed to fetch Google Sheet data: ' . $res->body());
                return;
            } catch (\Throwable $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("⚠️  Google Sheet fetch exception (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                    sleep(2);
                    continue;
                }
                $this->warn('⚠️ Failed to fetch Google Sheet data: ' . $e->getMessage());
                return;
            }
        }

        if (! $res || ! $res->successful()) {
            $this->warn('⚠️ Failed to fetch Google Sheet data after retries');
            return;
        }

        $rows = $res->json();

        foreach ($rows as $row) {
            $itemId = $row['item_id'] ?? null;
            $organicClicks = (float)($row['organic_clicks'] ?? 0);

            if (! $itemId) continue;

            // Update for all matched SKUs in $map
            $skus = $map[$itemId] ?? [];
            if (!empty($skus)) {
                foreach ($skus as $sku) {
                    EbayMetric::where('sku', $sku)
                        ->update([
                            'organic_clicks' => $organicClicks
                        ]);
                }
            }
        }
        
        DB::disconnect();

        $this->info('✅ Organic clicks updated from Sheet');
    }

    private function updateL7Views($token, $map)
    {
        // $map = [ itemId => [sku,sku,...], ... ]
        $chunks = array_chunk(array_keys($map), 20);
        
        $this->info('🔄 Fetching L7 views for ' . count($map) . ' items in ' . count($chunks) . ' chunks...');

        foreach ($chunks as $chunkIndex => $chunk) {
            $ids = implode('|', $chunk);
            $range = now()->subDays(6)->format('Ymd').'..'.now()->subDay()->format('Ymd');

            $url = "https://api.ebay.com/sell/analytics/v1/traffic_report?dimension=LISTING&filter=listing_ids:%7B{$ids}%7D,date_range:[{$range}]&metric=LISTING_VIEWS_TOTAL";
            
            $maxRetries = 3;
            $attempt = 0;
            $r = null;

            while ($attempt < $maxRetries) {
                $attempt++;
                
                try {
                    $r = Http::withToken($token)
                        ->timeout(60)
                        ->connectTimeout(20)
                        ->retry(2, 1000)
                        ->get($url);

                    if ($r->successful()) {
                        break;
                    }

                    if ($attempt < $maxRetries) {
                        $this->warn("⚠️  L7 Views API failed for chunk {$chunkIndex} (attempt {$attempt}/{$maxRetries}), retrying...");
                        sleep(2);
                        continue;
                    }

                    $this->error('❌ L7 Views API failed for chunk ' . $chunkIndex . ': ' . $r->body());
                    continue 2; // Continue to next chunk
                } catch (\Throwable $e) {
                    if ($attempt < $maxRetries) {
                        $this->warn("⚠️  L7 Views API exception for chunk {$chunkIndex} (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                        sleep(2);
                        continue;
                    }
                    $this->error('❌ L7 Views API exception for chunk ' . $chunkIndex . ': ' . $e->getMessage());
                    continue 2; // Continue to next chunk
                }
            }

            if (! $r || ! $r->successful()) {
                $this->warn('⚠️  L7 Views API failed for chunk ' . $chunkIndex . ' after retries, skipping...');
                continue;
            }

            $records = $r['records'] ?? [];

            foreach ($records as $rec) {
                $id = $rec['dimensionValues'][0]['value'] ?? null;
                $v = $rec['metricValues'][0]['value'] ?? 0;
                if (! $id) continue;

                $skus = $map[$id] ?? [];
                if (!empty($skus)) {
                    foreach ($skus as $sku) {
                        EbayMetric::where('sku', $sku)
                            ->update(['l7_views' => $v]);
                    }
                }
            }
            
            DB::disconnect();
            
            // Add small delay between API calls
            if ($chunkIndex < count($chunks) - 1) {
                sleep(1);
            }
        }

        $this->info('✅ L7 views updated');
    }


    private function orderQty($token, $range, $validIds)
    {
        $qty = [];
        $from = $range['start']->format('Y-m-d\TH:i:s.000\Z');
        $to = $range['end']->format('Y-m-d\TH:i:s.000\Z');

        $url = "https://api.ebay.com/sell/fulfillment/v1/order?filter=creationdate:[{$from}..{$to}]&limit=200";

        do {
            $maxRetries = 3;
            $attempt = 0;
            $r = null;

            while ($attempt < $maxRetries) {
                $attempt++;
                
                try {
                    $r = Http::withToken($token)
                        ->timeout(60)
                        ->connectTimeout(20)
                        ->retry(2, 1000)
                        ->get($url);

                    if ($r->successful()) {
                        break;
                    }

                    if ($attempt < $maxRetries) {
                        $this->warn("⚠️  Order API failed (attempt {$attempt}/{$maxRetries}), retrying...");
                        sleep(2);
                        continue;
                    }

                    $this->error('❌ Order API failed: ' . ($r ? $r->body() : 'No response'));
                    break 2; // Break out of both loops
                } catch (\Throwable $e) {
                    if ($attempt < $maxRetries) {
                        $this->warn("⚠️  Order API exception (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                        sleep(2);
                        continue;
                    }
                    $this->error('❌ Order API exception: ' . $e->getMessage());
                    break 2; // Break out of both loops
                }
            }

            if (! $r || ! $r->successful()) {
                $this->error('❌ Order API failed after retries');
                break;
            }

            foreach ($r['orders'] ?? [] as $o) {
                foreach ($o['lineItems'] ?? [] as $li) {
                    $id = $li['legacyItemId'] ?? null;
                    $q = (int) $li['quantity'];
                    if (! $id || ! in_array($id, $validIds)) {
                        continue;
                    }
                    $qty[$id] = ($qty[$id] ?? 0) + $q;
                }
            }
            $url = $r['next'] ?? null;
        } while ($url);

        return $qty;
    }
}
