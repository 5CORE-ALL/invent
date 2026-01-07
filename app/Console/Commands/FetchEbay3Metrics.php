<?php

namespace App\Console\Commands;

use App\Models\Ebay3Metric;
use App\Models\EbayTask;
use App\Services\EbayThreeApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class FetchEbay3Metrics extends Command
{
    protected $signature = 'app:fetch-ebay-three-metrics';
    protected $description = 'Fetch eBay price, L30, L60 and views for all SKUs (variations)';

    public function handle()
    {
        try {
            // Check database connection (without creating persistent connection)
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                // Immediately disconnect after check to prevent connection buildup
                DB::connection()->disconnect();
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

        // Save price + SKU mapping
        // We'll map itemId => [sku, sku, ...]
        $itemIdToSku = [];
        $activeSkus = []; // Track all active SKUs from current fetch

        foreach ($listingData as $row) {
            $itemId = $row['item_id'] ?? null;
            $sku = trim((string)($row['sku'] ?? ''));

            // skip rows with no itemId or no sku (we care about child SKUs)
            if (! $itemId || $sku === '') {
                continue;
            }

            // Track active SKUs
            $activeSkus[] = $sku;

            // keep mapping of itemId to array of SKUs
            if (! isset($itemIdToSku[$itemId])) {
                $itemIdToSku[$itemId] = [];
            }

            // avoid duplicates
            if (! in_array($sku, $itemIdToSku[$itemId])) {
                $itemIdToSku[$itemId][] = $sku;
            }

            // Check if this SKU exists with different item_id and delete old records
            $existingRecords = Ebay3Metric::where('sku', $sku)
                ->where('item_id', '!=', $itemId)
                ->get();
            
            if ($existingRecords->count() > 0) {
                $this->info("🔄 SKU {$sku}: Found old item_id(s), cleaning up...");
                foreach ($existingRecords as $oldRecord) {
                    $this->info("❌ Deleting old record: {$oldRecord->item_id}/{$sku}");
                    $oldRecord->delete();
                }
            }

            // Save per SKU (unique by item_id + sku)
            Ebay3Metric::updateOrCreate(
                ['item_id' => $itemId, 'sku' => $sku],
                [
                    'ebay_price' => $row['price'] ?? null,
                    'report_range' => now()->toDateString(),
                ]
            );
        }
        
        DB::connection()->disconnect();

        // Clean up SKUs that are no longer active (not in current fetch)
        $uniqueActiveSkus = array_unique($activeSkus);
        if (!empty($uniqueActiveSkus)) {
            $deletedCount = Ebay3Metric::whereNotIn('sku', $uniqueActiveSkus)->delete();
        } else {
            $deletedCount = 0;
        }
        if ($deletedCount > 0) {
            $this->info("🗑️  Cleaned up {$deletedCount} inactive SKU records");
        }
        DB::connection()->disconnect();

        // Update views per itemId -> sku list
        $this->updateViews($token, $itemIdToSku);

        // Update L7 views (last 7 days)
        $this->updateL7Views($token, $itemIdToSku);

        // L7 / L30 / L60
        $existingItemIds = array_keys($itemIdToSku);
        $dateRanges = $this->dateRanges();

        $l7 = $this->orderQty($token, $dateRanges['l7'], $existingItemIds);
        $l30 = $this->orderQty($token, $dateRanges['l30'], $existingItemIds);
        $l60 = $this->orderQty($token, $dateRanges['l60'], $existingItemIds);

            // Save L7/L30/L60 for each SKU under the item in chunks
            $chunks = array_chunk($existingItemIds, 100, true);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $itemId) {
                    $skus = $itemIdToSku[$itemId] ?? [];
                    if (!empty($skus)) {
                        foreach ($skus as $sku) {
                            Ebay3Metric::where('item_id', $itemId)
                                ->where('sku', $sku)
                                ->update([
                                    'ebay_l7' => $l7[$itemId] ?? 0,
                                    'ebay_l30' => $l30[$itemId] ?? 0,
                                    'ebay_l60' => $l60[$itemId] ?? 0,
                                ]);
                        }
                    }
                }
                DB::connection()->disconnect();
            }

            // Fetch competitor prices (LMP) using Browse API
            $this->updateCompetitorPrices();

            $this->info('✅ eBay Metrics updated');
            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    /**
     * Fetch competitor prices for all SKUs using Browse API
     */
    private function updateCompetitorPrices()
    {
        $this->info('🔄 Fetching competitor prices...');

        $ebayService = app(EbayThreeApiService::class);
        $allMetrics = Ebay3Metric::whereNotNull('sku')
            ->whereNotNull('item_id')
            ->where('sku', 'not like', '%PARENT%')
            ->get();

        $processedCount = 0;
        $totalCount = $allMetrics->count();
        $maxLmps = 5; // Store top 5 lowest competitor prices

        foreach ($allMetrics as $metric) {
            $sku = $metric->sku;
            $itemId = $metric->item_id;

            // First, get the item details (title, categoryId, epid) from eBay
            $itemDetails = $this->getItemDetails($itemId);
            
            if (empty($itemDetails) || empty($itemDetails['title'])) {
                $this->warn("⚠️  No details found for {$sku} (item_id: {$itemId}), skipping LMP");
                $processedCount++;
                continue;
            }
            
            $itemTitle = $itemDetails['title'];
            $categoryId = $itemDetails['categoryId'] ?? null;
            $epid = $itemDetails['epid'] ?? null;
            
            // Use EPID-based search if available, otherwise category + title search
            $this->info("🔍 {$sku}: Title: " . substr($itemTitle, 0, 40) . "... | Cat: {$categoryId} | EPID: " . ($epid ?? 'N/A'));
            
            $competitors = $ebayService->doRepricingWithCategory($itemTitle, $categoryId, $epid, $itemId);

            if (!empty($competitors)) {
                // Filter out our own listing and get top competitors
                $filteredCompetitors = [];
                foreach ($competitors as $comp) {
                    // Skip if it's our own item (compare as string)
                    $compItemId = str_replace(['v1|', '|0'], '', $comp['item_id']);
                    if ($compItemId == $metric->item_id) {
                        continue;
                    }
                    
                    if ($comp['total_price'] > 0) {
                        $filteredCompetitors[] = [
                            'price' => $comp['total_price'],
                            'link' => $comp['link'],
                            'title' => $comp['title'],
                            'seller' => $comp['seller'],
                        ];
                    }

                    // Limit to maxLmps
                    if (count($filteredCompetitors) >= $maxLmps) {
                        break;
                    }
                }

                if (!empty($filteredCompetitors)) {
                    // Lowest price for quick access
                    $lowestPrice = $filteredCompetitors[0]['price'] ?? null;
                    $lowestLink = $filteredCompetitors[0]['link'] ?? null;

                    $metric->update([
                        'price_lmpa' => $lowestPrice,
                        'lmp_link' => $lowestLink,
                        'lmp_data' => $filteredCompetitors,
                    ]);
                    
                    $lmpCount = count($filteredCompetitors);
                    $this->info("📊 {$sku}: {$lmpCount} LMPs found, Lowest = \${$lowestPrice}");
                }
            }

            $processedCount++;

            // Rate limiting - add small delay every 10 SKUs
            if ($processedCount % 10 === 0) {
                $this->info("⏳ Processed {$processedCount}/{$totalCount} SKUs...");
                sleep(1);
            }
        }

        $this->info("✅ Competitor prices updated for {$processedCount} SKUs");
    }

    /**
     * Get item details (title, categoryId, epid) from eBay using Browse API
     */
    private function getItemDetails($itemId)
    {
        $ebayService = app(EbayThreeApiService::class);
        $token = $ebayService->generateBrowseToken();
        
        if (!$token) {
            return null;
        }

        try {
            // Use get_items_by_item_group API - this works with legacy item IDs
            $response = Http::withToken($token)
                ->timeout(30)
                ->connectTimeout(15)
                ->get("https://api.ebay.com/buy/browse/v1/item/get_items_by_item_group?item_group_id={$itemId}");

            if ($response->successful()) {
                $data = $response->json();
                $items = $data['items'] ?? [];
                
                if (!empty($items)) {
                    $item = $items[0];
                    return [
                        'title' => $item['title'] ?? null,
                        'categoryId' => $item['categoryId'] ?? null,
                        'epid' => $item['epid'] ?? null,
                    ];
                }
            }

        } catch (\Exception $e) {
            $this->warn("Failed to get item details for {$itemId}: " . $e->getMessage());
        }

        return null;
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
        $id = env('EBAY_3_APP_ID');
        $secret = env('EBAY_3_CERT_ID');
        $rtoken = env('EBAY_3_REFRESH_TOKEN');

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
            'ebay_account' => 'Ebay3',
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

            // Add primary SKU only if it's not in variations
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

        $data = [];
        foreach ($rows as $row) {
            if (count($headers) != count($row)) {
                continue;
            }
            $d = array_combine($headers, $row);
            $itemId = $d['itemId'] ?? null;
            $sku = trim((string) ($d['sku'] ?? ''));
            if (! $itemId || $sku === '') {
                continue;
            }
            $data[] = [
                'item_id' => $itemId,
                'sku' => $sku,
                'price' => $d['price'] ?? null,
            ];
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
                        $updated = Ebay3Metric::where('item_id', $id)
                            ->where('sku', $sku)
                            ->update(['views' => $v]);
                        
                        if ($updated) {
                            $this->info("✅ Updated views for {$id}/{$sku}: {$v}");
                        } else {
                            $this->warn("⚠️  Failed to update views for {$id}/{$sku}");
                        }
                    }
                }
            }
            
            DB::connection()->disconnect();
            
            // Add small delay between API calls
            if ($chunkIndex < count($chunks) - 1) {
                sleep(1);
            }
        }
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
                        Ebay3Metric::where('item_id', $id)
                            ->where('sku', $sku)
                            ->update(['l7_views' => $v]);
                    }
                }
            }
            
            DB::connection()->disconnect();
            
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
