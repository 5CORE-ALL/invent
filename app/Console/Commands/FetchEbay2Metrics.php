<?php

namespace App\Console\Commands;

use App\Models\Ebay2Metric;
use App\Models\EbayTask;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class FetchEbay2Metrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-ebay-two-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
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

            $this->info('🚀 Starting eBay2 Metrics fetch...');
            
            $token = $this->getToken();
            if (! $token) {
                $this->error('❌ Token error');
                return 1;
            }
            
            $this->info('✅ Token obtained successfully');

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
            $existingRecords = Ebay2Metric::where('sku', $sku)
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
            Ebay2Metric::updateOrCreate(
                ['item_id' => $itemId, 'sku' => $sku],
                [
                    'ebay_price' => $row['price'] ?? null,
                    'ebay_stock' => $row['quantity'] ?? null,
                    'ebay_title' => $row['title'] ?? null,
                    'report_range' => now()->toDateString(),
                ]
            );
        }
        
        DB::connection()->disconnect();

        // Clean up SKUs that are no longer active (not in current fetch)
        $uniqueActiveSkus = array_unique($activeSkus);
        if (!empty($uniqueActiveSkus)) {
            $deletedCount = Ebay2Metric::whereNotIn('sku', $uniqueActiveSkus)->delete();
        } else {
            $deletedCount = 0;
        }
        if ($deletedCount > 0) {
            $this->info("🗑️  Cleaned up {$deletedCount} inactive SKU records");
        }
        DB::connection()->disconnect();

        // Fetch and update titles for all items
        $this->updateTitles($token, $itemIdToSku);

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
                            Ebay2Metric::where('item_id', $itemId)
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

    private function dateRanges()
    {
        // Use California timezone to match FetchEbay2Orders
        $today = Carbon::now('America/Los_Angeles')->startOfDay();

        return [
            'l7' => [
                'start' => $today->copy()->subDays(6),
                'end' => $today->copy()->subDay()->endOfDay(),
            ],
            'l30' => [
                'start' => $today->copy()->subDays(30),
                'end' => $today->copy()->subDay()->endOfDay(),
            ],
            'l60' => [
                'start' => $today->copy()->subDays(60),
                'end' => $today->copy()->subDays(31)->endOfDay(),
            ],
        ];
    }

    private function getToken()
    {
        $id = config('services.ebay2.app_id');
        $secret = config('services.ebay2.cert_id');
        $rtoken = config('services.ebay2.refresh_token');

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
            'ebay_account' => 'Ebay2',
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
        $firstItem = true; // Flag to log first item structure

        foreach ($xml->ActiveInventoryReport->SKUDetails as $item) {
            $itemId = (string) $item->ItemID;
            if (! $itemId) {
                continue;
            }

            // Debug: Log first item structure to see available fields
            if ($firstItem) {
                $this->info('XML Item fields: ' . implode(', ', array_keys((array)$item)));
                $firstItem = false;
            }

            // capture primary SKU (if present) and variations
            $primarySku = trim((string) ($item->SKU ?? ''));
            $price = isset($item->Price) ? (float) $item->Price : null;
            $quantity = isset($item->Quantity) ? (int) $item->Quantity : null;
            $title = isset($item->Title) ? preg_replace('/[^\x20-\x7E]/', '', trim((string) $item->Title)) : null;

            // Process variations first to collect SKUs with prices and quantities
            $variationData = [];
            foreach ($item->Variations->Variation ?? [] as $v) {
                $varSku = (string) $v->SKU;
                $varPrice = isset($v->Price) ? (float) $v->Price : $price;
                $varQuantity = isset($v->Quantity) ? (int) $v->Quantity : $quantity;
                
                $variationData[$varSku] = [
                    'item_id' => $itemId,
                    'sku' => $varSku,
                    'price' => $varPrice,
                    'quantity' => $varQuantity,
                    'title' => $title,
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
                        'quantity' => $quantity,
                        'title' => $title,
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

        // Debug: Log available headers to identify the correct title field name
        $this->info('Available TSV headers: ' . implode(', ', $headers));

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
            
            // Extract and sanitize title
            $title = isset($d['title']) ? preg_replace('/[^\x20-\x7E]/', '', trim($d['title'])) : null;
            
            $data[] = [
                'item_id' => $itemId,
                'sku' => $sku,
                'price' => $d['price'] ?? null,
                'quantity' => isset($d['availableQuantity']) ? (int) $d['availableQuantity'] : null,
                'title' => $title,
            ];
        }

        return $data;
    }

    private function updateTitles($token, $map)
    {
        $this->info('🔄 Fetching titles for active listings...');
        
        $itemTitles = [];
        $pageNumber = 1;
        $totalPages = 1;
        
        do {
            try {
                $xmlBody = '<?xml version="1.0" encoding="utf-8"?>
                    <GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <ActiveList>
                            <Include>true</Include>
                            <Pagination>
                                <EntriesPerPage>200</EntriesPerPage>
                                <PageNumber>' . $pageNumber . '</PageNumber>
                            </Pagination>
                        </ActiveList>
                        <OutputSelector>ActiveList.ItemArray.Item.ItemID</OutputSelector>
                        <OutputSelector>ActiveList.ItemArray.Item.Title</OutputSelector>
                        <OutputSelector>ActiveList.PaginationResult</OutputSelector>
                    </GetMyeBaySellingRequest>';

                $response = Http::timeout(60)
                    ->withHeaders([
                        'X-EBAY-API-SITEID' => '0',
                        'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                        'X-EBAY-API-CALL-NAME' => 'GetMyeBaySelling',
                        'X-EBAY-API-IAF-TOKEN' => $token,
                    ])
                    ->withBody($xmlBody, 'text/xml')
                    ->post('https://api.ebay.com/ws/api.dll');

                if (!$response->successful()) {
                    $this->error("Failed to fetch titles page {$pageNumber}");
                    break;
                }

                $xml = simplexml_load_string($response->body());
                
                if (isset($xml->Errors)) {
                    $this->error('eBay API error: ' . (string)$xml->Errors->LongMessage);
                    break;
                }

                $listNode = $xml->ActiveList ?? null;

                // Get total pages from first response
                if ($pageNumber == 1 && $listNode && isset($listNode->PaginationResult->TotalNumberOfPages)) {
                    $totalPages = (int) $listNode->PaginationResult->TotalNumberOfPages;
                    $totalEntries = (int) $listNode->PaginationResult->TotalNumberOfEntries;
                    $this->info("Found {$totalEntries} active listings across {$totalPages} pages");
                }

                // Process items and extract titles
                if ($listNode && isset($listNode->ItemArray->Item)) {
                    foreach ($listNode->ItemArray->Item as $item) {
                        $itemId = (string) ($item->ItemID ?? '');
                        $title = (string) ($item->Title ?? '');
                        
                        // Clean the title to remove non-printable characters
                        $title = preg_replace('/[^\x20-\x7E]/', '', trim($title));
                        
                        if ($itemId && $title) {
                            $itemTitles[$itemId] = $title;
                        }
                    }
                }

                $pageNumber++;
                sleep(1); // Small delay between pages
                
            } catch (\Exception $e) {
                $this->error("Error fetching titles: " . $e->getMessage());
                break;
            }
            
        } while ($pageNumber <= $totalPages);

        // Update database with titles and links
        $this->info("📝 Updating " . count($itemTitles) . " titles and links in database...");
        
        foreach ($map as $itemId => $skus) {
            $title = $itemTitles[$itemId] ?? null;
            
            // Create eBay product link
            $ebayLink = $itemId ? "https://www.ebay.com/itm/{$itemId}" : null;
            
            if (!empty($skus)) {
                foreach ($skus as $sku) {
                    Ebay2Metric::where('item_id', $itemId)
                        ->where('sku', $sku)
                        ->update([
                            'ebay_title' => $title,
                            'ebay_link' => $ebayLink
                        ]);
                }
            }
        }
        
        DB::connection()->disconnect();
        $this->info('✅ Titles and links updated');
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
                        $updated = Ebay2Metric::where('item_id', $id)
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
                        Ebay2Metric::where('item_id', $id)
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
