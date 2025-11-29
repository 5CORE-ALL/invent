<?php

namespace App\Console\Commands;

use App\Models\Ebay2Metric;
use App\Models\EbayTask;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->info('🚀 Starting eBay2 Metrics fetch...');
        
        $token = $this->getToken();
        if (! $token) {
            $this->error('❌ Token error');
            return;
        }
        
        $this->info('✅ Token obtained successfully');

        $taskId = $this->getInventoryTaskId($token);
        if (! $taskId) {
            $this->error('Task error');
            return;
        }

        $listingData = $this->processTask($taskId, $token);

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
                    'report_range' => now()->toDateString(),
                ]
            );
        }

        // Clean up SKUs that are no longer active (not in current fetch)
        $uniqueActiveSkus = array_unique($activeSkus);
        $deletedCount = Ebay2Metric::whereNotIn('sku', $uniqueActiveSkus)->delete();
        if ($deletedCount > 0) {
            $this->info("🗑️  Cleaned up {$deletedCount} inactive SKU records");
        }

        // Update views per itemId -> sku list
        $this->updateViews($token, $itemIdToSku);

        // L30 / L60
        $existingItemIds = array_keys($itemIdToSku);
        $dateRanges = $this->dateRanges();

        $l30 = $this->orderQty($token, $dateRanges['l30'], $existingItemIds);
        $l60 = $this->orderQty($token, $dateRanges['l60'], $existingItemIds);

        // Save L30/L60 for each SKU under the item
        foreach ($existingItemIds as $itemId) {
            $skus = $itemIdToSku[$itemId] ?? [];
            foreach ($skus as $sku) {
                Ebay2Metric::where('item_id', $itemId)
                    ->where('sku', $sku)
                    ->update([
                        'ebay_l30' => $l30[$itemId] ?? 0,
                        'ebay_l60' => $l60[$itemId] ?? 0,
                    ]);
            }
        }

        $this->info('✅ eBay Metrics updated');
    }

    private function dateRanges()
    {
        $today = Carbon::today();

        return [
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
        $id = env('EBAY2_APP_ID');
        $secret = env('EBAY2_CERT_ID');
        $rtoken = env('EBAY2_REFRESH_TOKEN');

        try {
            $response = Http::asForm()
                ->withBasicAuth($id, $secret)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $rtoken,
                ]);

            if (! $response->successful()) {
                $this->error('❌ TOKEN FAILED: '.json_encode($response->json()));
                return null;
            }

            return $response->json()['access_token'] ?? null;

        } catch (\Throwable $e) {
            Log::channel('daily')->error('EBAY TOKEN EXCEPTION', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function getInventoryTaskId($token)
    {
        $type = 'LMS_ACTIVE_INVENTORY_REPORT';

        $task = EbayTask::where('type', $type)
            ->where('ebay_account', 'Ebay2')
            ->latest()
            ->first();

        // reuse last task if created less than 24h ago
        if ($task && now()->diffInHours($task->created_at) < 24) {
            $this->info('✅ Reusing existing Task: '.$task->task_id.' (created: '.$task->created_at.')');
            return $task->task_id;
        }

        $this->info('⏳ Creating new task...');

        $payload = [
            'feedType' => $type,
            'format' => 'TSV_GZIP',
            'schemaVersion' => '1.0',
        ];

        $response = Http::withToken($token)
            ->post('https://api.ebay.com/sell/feed/v1/inventory_task', $payload);

        if (! $response->successful()) {
            $this->error('❌ Task API failed: '.$response->body());
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

    private function processTask($taskId, $token)
    {
        while (true) {
            $check = Http::withToken($token)
                ->get("https://api.ebay.com/sell/feed/v1/inventory_task/{$taskId}");

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

        $response = Http::withToken($token)->get($url);
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

        foreach ($xml->ActiveInventoryReport->SKUDetails as $item) {
            $itemId = (string) $item->ItemID;
            if (! $itemId) {
                continue;
            }

            // capture primary SKU (if present) and variations
            $primarySku = trim((string) ($item->SKU ?? ''));
            $price = isset($item->Price) ? (float) $item->Price : null;

            if ($primarySku !== '') {
                $out[] = [
                    'item_id' => $itemId,
                    'sku' => $primarySku,
                    'price' => $price,
                ];
            }

            foreach ($item->Variations->Variation ?? [] as $v) {
                $out[] = [
                    'item_id' => $itemId,
                    'sku' => (string) $v->SKU,
                    'price' => isset($v->Price) ? (float) $v->Price : $price,
                ];
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

            $r = Http::withToken($token)->get($url);
            
            if (!$r->successful()) {
                $this->error('❌ Views API failed for chunk ' . $chunkIndex . ': ' . $r->body());
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
            
            // Add small delay between API calls
            if ($chunkIndex < count($chunks) - 1) {
                sleep(1);
            }
        }
    }

    private function orderQty($token, $range, $validIds)
    {
        $qty = [];
        $from = $range['start']->format('Y-m-d\TH:i:s.000\Z');
        $to = $range['end']->format('Y-m-d\TH:i:s.000\Z');

        $url = "https://api.ebay.com/sell/fulfillment/v1/order?filter=creationdate:[{$from}..{$to}]&limit=200";

        do {
            $r = Http::withToken($token)->get($url);
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
