<?php

namespace App\Console\Commands;

use App\Models\WaifairProductSheet;
use Illuminate\Console\Command;
use App\Http\Controllers\ApiController;
use App\Models\WayfairDataView;
use App\Models\WayfairProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncWayfairSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:wayfair-sheet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Wayfair Product Sheet'; 

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = 'https://script.google.com/macros/s/AKfycbxkkmo4L0EbqNK6WaOqM73yUuvC4mwAJMDJcfebxNnzwZ_LuL_9SIOtP09moPFHjV27/exec';

        try {
            $response = Http::timeout(seconds: 120)->get($url);
            if ($response->successful()) {
                $data = $response->json();
                $this->info('Fetched data: ' . json_encode($data));
                $rows = collect($data['data'] ?? $data ?? []);
                $this->info('Rows count: ' . $rows->count());
            } else {
                $this->error('Failed to fetch data from Google Sheet. Status: ' . $response->status());
                return;
            }
        } catch (\Exception $e) {
            $this->error('Exception while fetching data: ' . $e->getMessage());
            return;
        }

        foreach ($rows as $row) {
            $sku = trim($row['sku'] ?? '');
            if (!$sku) continue;

            WaifairProductSheet::updateOrCreate(
                ['sku' => $sku],
                [
                    'price'     => $this->toDecimalOrNull($row['price'] ?? null),
                    'l30'       => $this->toIntOrNull($row['l30'] ?? null),
                    'l60'       => $this->toIntOrNull($row['l60'] ?? null),
                ]
            );
        }

        $this->info('Wayfair sheet data synced successfully!');

        // Fetch L30 and L60 from Shopify for Wayfair SKUs
        $this->fetchShopifyWayfairData();
    }

    private function fetchShopifyWayfairData()
    {
        $this->info('Fetching Shopify data for Wayfair L30/L60...');

        $now = Carbon::now();
        $sixtyDaysAgo = $now->copy()->subDays(60);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $baseUrl = "https://" . env('SHOPIFY_STORE_URL') . "/admin/api/2024-10/orders.json";
        $params = [
            'status' => 'any',
            'created_at_min' => $sixtyDaysAgo->toISOString(),
            'created_at_max' => $now->toISOString(),
            'limit' => 250 
        ];

        $allOrders = [];
        $page = 1;

        do {
            $url = $baseUrl . '?' . http_build_query($params);

            // Add rate limiting and retry logic
            $maxRetries = 3;
            $retryCount = 0;
            $response = null;

            while ($retryCount < $maxRetries) {
                // Sleep to respect rate limits (0.5 seconds = 2 calls per second max)
                if ($retryCount > 0) {
                    $this->info("Retrying in 2 seconds... (attempt {$retryCount})");
                    sleep(2);
                } else {
                    // Always sleep 0.5 seconds between requests to stay under rate limit
                    sleep(1);
                }

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN')
                ])->get($url);

                if ($response->successful()) {
                    break;
                } elseif ($response->status() === 429 || 
                         (str_contains($response->body(), 'Exceeded') && str_contains($response->body(), 'calls per second'))) {
                    $retryCount++;
                    $this->warn("Rate limit hit, retrying... (attempt {$retryCount}/{$maxRetries})");
                } else {
                    $this->error('Failed to fetch Shopify orders: ' . $response->body());
                    return;
                }
            }

            if (!$response->successful()) {
                $this->error('Failed to fetch Shopify orders after retries: ' . $response->body());
                return;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            $allOrders = array_merge($allOrders, $orders);

            $this->info("Fetched page {$page}: " . count($orders) . " orders");

            // Check for next page
            $linkHeader = $response->header('Link');
            $nextUrl = $this->getNextPageUrl($linkHeader);

            if ($nextUrl) {
                // Extract query params from next URL
                $parsedUrl = parse_url($nextUrl);
                parse_str($parsedUrl['query'], $params);
                $page++;
            } else {
                break;
            }
        } while (true);

        $this->info('Total orders fetched: ' . count($allOrders));

        $skuCountsL60 = [];
        $skuCountsL30 = [];
        $skuPrices = [];
        $totalItems = 0;
        $wayfairItems = 0;
        $wayfairOrders = 0;
        $totalOrders = count($allOrders);

        foreach ($allOrders as $order) {
            $createdAt = Carbon::parse($order['created_at']);
            
            // Check if it's a Wayfair order
            $isWayfairOrder = $this->isWayfairOrder($order);
            
            if ($isWayfairOrder) {
                $wayfairOrders++;
                
                foreach ($order['line_items'] as $item) {
                    $sku = $item['sku'] ?? null;
                    $totalItems++;
                    
                    if ($sku) {
                        $wayfairItems++;
                        $quantity = $item['current_quantity'] ?? 0;
                        $price = $item['price'] ?? 0;

                        if ($createdAt >= $sixtyDaysAgo) {
                            $skuCountsL60[$sku] = ($skuCountsL60[$sku] ?? 0) + $quantity;
                        }

                        if ($createdAt >= $thirtyDaysAgo) {
                            $skuCountsL30[$sku] = ($skuCountsL30[$sku] ?? 0) + $quantity;
                        }

                        // Track the latest price for each SKU
                        if (!isset($skuPrices[$sku]) || $createdAt > $skuPrices[$sku]['date']) {
                            $skuPrices[$sku] = [
                                'price' => $price,
                                'date' => $createdAt
                            ];
                        }
                    }
                }
            }
        }

        $this->info("Total orders processed: {$totalOrders}");
        $this->info("Wayfair orders found: {$wayfairOrders}");
        $this->info("Non-Wayfair orders filtered: " . ($totalOrders - $wayfairOrders));
        $this->info("Total line items in Wayfair orders: {$totalItems}");
        $this->info("Wayfair items with SKUs: {$wayfairItems}");

        // Update WaifairProductSheet with the counts and prices - for SKUs that have prices
        foreach ($skuPrices as $sku => $priceData) {
            $updateData = [
                'shopify_wayfair_price' => $priceData['price'],
            ];

            // Only add L30/L60 if they exist for this SKU
            if (isset($skuCountsL60[$sku])) {
                $updateData['shopify_wayfairl60'] = $skuCountsL60[$sku];
                $updateData['shopify_wayfairl30'] = $skuCountsL30[$sku] ?? 0;
            }

            WaifairProductSheet::where('sku', $sku)->update($updateData);
        }

        // Update L30/L60 for all SKUs that have sales data but no prices
        foreach ($skuCountsL60 as $sku => $l60) {
            if (!isset($skuPrices[$sku])) {
                $updateData = [
                    'shopify_wayfairl60' => $l60,
                    'shopify_wayfairl30' => $skuCountsL30[$sku] ?? 0,
                ];
                WaifairProductSheet::where('sku', $sku)->update($updateData);
            }
        }

        $this->info('Shopify Wayfair data updated successfully!');
    }

    private function isWayfairOrder($order)
    {
        // Check tags
        $tags = strtolower($order['tags'] ?? '');
        if (str_contains($tags, 'wayfair')) {
            return true;
        }

        // Check note attributes
        if (!empty($order['note_attributes'])) {
            foreach ($order['note_attributes'] as $attr) {
                if (
                    strtolower($attr['name'] ?? '') === 'channel' &&
                    strtolower($attr['value'] ?? '') === 'wayfair'
                ) {
                    return true;
                }
            }
        }

        // Check source name
        $source = strtolower($order['source_name'] ?? '');
        return str_contains($source, 'wayfair');
    }

    private function getNextPageUrl($linkHeader)
    {
        if (!$linkHeader) {
            return null;
        }

        // Link header format: <https://...>; rel="next", <https://...>; rel="previous"
        $links = explode(',', $linkHeader);
        foreach ($links as $link) {
            if (strpos($link, 'rel="next"') !== false) {
                preg_match('/<([^>]+)>/', $link, $matches);
                return $matches[1] ?? null;
            }
        }

        return null;
    }

    private function toDecimalOrNull($value)
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) return null;
        return (string)$value; // Keep as string to preserve exact decimal representation
    }

    private function toIntOrNull($value)
    {
        if ($value === null || $value === '') return null;
        $value = str_replace(',', '', $value);
        return is_numeric($value) ? (int)$value : null;
    }

}
