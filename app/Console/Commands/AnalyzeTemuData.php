<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AnalyzeTemuData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:analyze-temu-data {--fresh-api : Fetch fresh data from API instead of using cached database data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze Temu API data against database or compare fresh API data with database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== TEMU DATA ANALYSIS ===');
        $this->line('');

        if ($this->option('fresh-api')) {
            $this->info('Fetching fresh data from Temu API...');
            $apiData = $this->fetchApiData();
        } else {
            $this->info('Using existing database data for analysis...');
            $apiData = $this->getDatabaseData();
        }

        $this->analyzeData($apiData);
    }

    private function fetchApiData()
    {
        $apiData = [];

        try {
            // Fetch SKUs from API
            $this->info('Fetching SKUs from API...');
            $skusData = $this->fetchSkusFromApi();

            // Fetch quantities from API
            $this->info('Fetching quantities from API...');
            $quantitiesData = $this->fetchQuantitiesFromApi();

            // Combine SKU and quantity data
            foreach ($skusData as $sku => $skuInfo) {
                $apiData[$sku] = [
                    'sku_id' => $skuInfo['sku_id'],
                    'quantity' => $quantitiesData[$skuInfo['sku_id']] ?? 0,
                ];
            }

            $this->info('Fetched ' . count($apiData) . ' SKUs from API');

        } catch (\Exception $e) {
            $this->error('Error fetching API data: ' . $e->getMessage());
            Log::error('Error in fetchApiData: ' . $e->getMessage());
            return [];
        }

        return $apiData;
    }

    private function fetchSkusFromApi()
    {
        $skus = [];

        try {
            $pageToken = null;

            do {
                $requestBody = [
                    "type" => "temu.local.sku.list.retrieve",
                    "skuSearchType" => "ACTIVE",
                    "pageSize" => 50,
                ];

                if ($pageToken) {
                    $requestBody["pageToken"] = $pageToken;
                }

                $signedRequest = $this->generateSignValue($requestBody);

                $response = Http::timeout(40)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

                if ($response->failed()) {
                    $this->error("SKU API request failed: " . $response->body());
                    break;
                }

                $data = $response->json();

                if (!($data['success'] ?? false)) {
                    $this->error("Temu SKU API error: " . ($data['errorMsg'] ?? 'Unknown'));
                    break;
                }

                $skuList = $data['result']['skuList'] ?? [];

                foreach ($skuList as $sku) {
                    $outSkuSn = $sku['outSkuSn'] ?? null;
                    $skuId = $sku['skuId'] ?? null;

                    if ($outSkuSn && $skuId) {
                        // Clean SKU name
                        $outSkuSn = trim(preg_replace('/\s+/', ' ', $outSkuSn));
                        $skus[$outSkuSn] = [
                            'sku_id' => $skuId,
                        ];
                    }
                }

                $pageToken = $data['result']['pagination']['nextToken'] ?? null;

                // Small delay to avoid API rate limits
                usleep(500000);

            } while ($pageToken);

        } catch (\Exception $e) {
            Log::error('Error in fetchSkusFromApi: ' . $e->getMessage());
            throw $e;
        }

        return $skus;
    }

    private function fetchQuantitiesFromApi()
    {
        $quantities = [];

        try {
            // Define L30 date range
            $today = Carbon::today();
            $toDate = $today->copy()->subDay();
            $fromDate = $toDate->copy()->subDays(29);

            $pageNumber = 1;

            do {
                $requestBody = [
                    "type" => "bg.order.list.v2.get",
                    "pageSize" => 100,
                    "pageNumber" => $pageNumber,
                    "createAfter" => $fromDate->timestamp,
                    "createBefore" => $toDate->copy()->endOfDay()->timestamp,
                ];

                $signedRequest = $this->generateSignValue($requestBody);

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json'
                ])->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

                if ($response->failed()) {
                    $this->error("Quantity API request failed: " . $response->body());
                    break;
                }

                $data = $response->json();

                if (!($data['success'] ?? false)) {
                    $this->error("Temu Quantity API error: " . ($data['errorMsg'] ?? 'Unknown'));
                    break;
                }

                $orders = $data['result']['pageItems'] ?? [];
                if (empty($orders)) break;

                foreach ($orders as $order) {
                    foreach ($order['orderList'] ?? [] as $item) {
                        $skuId = trim($item['skuId']);
                        $qty = (int)($item['quantity'] ?? 0);

                        if (!empty($skuId) && $qty > 0) {
                            $quantities[$skuId] = ($quantities[$skuId] ?? 0) + $qty;
                        }
                    }
                }

                $pageNumber++;
            } while (true);

        } catch (\Exception $e) {
            Log::error('Error in fetchQuantitiesFromApi: ' . $e->getMessage());
            throw $e;
        }

        return $quantities;
    }

    private function getDatabaseData()
    {
        $dbData = [];

        $records = DB::table('temu_metrics')
            ->select('sku', 'sku_id', 'quantity_purchased_l30')
            ->get();

        foreach ($records as $record) {
            $dbData[$record->sku] = [
                'sku_id' => $record->sku_id,
                'quantity' => $record->quantity_purchased_l30 ?? 0,
            ];
        }

        return $dbData;
    }

    private function analyzeData($apiData)
    {
        $this->line('');
        $this->info('=== ANALYSIS RESULTS ===');
        $this->line('');

        $totalApiSkus = count($apiData);
        $totalApiQuantity = array_sum(array_column($apiData, 'quantity'));

        $this->info('API Data Summary:');
        $this->info('Total SKUs: ' . $totalApiSkus);
        $this->info('Total Quantity: ' . $totalApiQuantity);
        $this->line('');

        // Get database data for comparison
        $dbData = DB::table('temu_metrics')
            ->select('sku', 'quantity_purchased_l30')
            ->get()
            ->keyBy('sku')
            ->toArray();

        $totalDbSkus = count($dbData);
        $totalDbQuantity = 0;
        foreach ($dbData as $item) {
            $totalDbQuantity += $item->quantity_purchased_l30 ?? 0;
        }

        $this->info('Database Data Summary:');
        $this->info('Total SKUs: ' . $totalDbSkus);
        $this->info('Total Quantity: ' . $totalDbQuantity);
        $this->line('');

        // Find differences
        $missingInDB = [];
        $extraInDB = [];
        $quantityDifferences = [];

        foreach ($apiData as $sku => $apiInfo) {
            if (!isset($dbData[$sku])) {
                $missingInDB[$sku] = $apiInfo['quantity'];
            } else {
                $dbQty = $dbData[$sku]->quantity_purchased_l30 ?? 0;
                $apiQty = $apiInfo['quantity'];

                if ($dbQty != $apiQty) {
                    $quantityDifferences[$sku] = [
                        'api' => $apiQty,
                        'database' => $dbQty,
                        'difference' => $apiQty - $dbQty
                    ];
                }
            }
        }

        // Find extra SKUs in database
        foreach ($dbData as $sku => $item) {
            if (!isset($apiData[$sku])) {
                $extraInDB[$sku] = $item->quantity_purchased_l30 ?? 0;
            }
        }

        $this->info('=== MISSING SKUs IN DATABASE ===');
        if (empty($missingInDB)) {
            $this->info('None');
        } else {
            foreach ($missingInDB as $sku => $qty) {
                $this->line("$sku: $qty");
            }
        }
        $this->line('');

        $this->info('=== EXTRA SKUs IN DATABASE ===');
        if (empty($extraInDB)) {
            $this->info('None');
        } else {
            foreach ($extraInDB as $sku => $qty) {
                $this->line("$sku: $qty");
            }
        }
        $this->line('');

        $this->info('=== QUANTITY DIFFERENCES ===');
        if (empty($quantityDifferences)) {
            $this->info('None');
        } else {
            foreach ($quantityDifferences as $sku => $diff) {
                $this->line("$sku: API={$diff['api']}, DB={$diff['database']}, Diff={$diff['difference']}");
            }
        }
        $this->line('');

        $this->info('=== SUMMARY ===');
        $this->info('API SKUs: ' . $totalApiSkus);
        $this->info('Database SKUs: ' . $totalDbSkus);
        $this->info('Missing in DB: ' . count($missingInDB));
        $this->info('Extra in DB: ' . count($extraInDB));
        $this->info('Quantity differences: ' . count($quantityDifferences));
        $this->info('API Total Quantity: ' . $totalApiQuantity);
        $this->info('DB Total Quantity: ' . $totalDbQuantity);
        $this->info('Quantity Difference: ' . ($totalApiQuantity - $totalDbQuantity));
    }

    private function generateSignValue($requestBody)
    {
        // Environment/config variables
        $appKey = env('TEMU_APP_KEY');
        $appSecret = env('TEMU_SECRET_KEY');
        $accessToken = env('TEMU_ACCESS_TOKEN');
        $timestamp = time();

        // Top-level params
        $params = [
            'access_token' => $accessToken,
            'app_key' => $appKey,
            'timestamp' => $timestamp,
            'data_type' => 'JSON',
        ];

        // Flatten and sort for signing
        $signParams = array_merge($params, $requestBody);
        ksort($signParams);

        $temp = '';
        foreach ($signParams as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $temp .= $key . $value;
        }

        $signStr = $appSecret . $temp . $appSecret;
        $sign = strtoupper(md5($signStr));
        $params['sign'] = $sign;

        return array_merge($params, $requestBody);
    }
}