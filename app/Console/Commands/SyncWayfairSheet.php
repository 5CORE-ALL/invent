<?php

namespace App\Console\Commands;

use App\Models\WaifairProductSheet;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncWayfairSheet extends Command
{
    protected $signature = 'sync:wayfair-sheet';
    protected $description = 'Sync Wayfair Product Sheet';

    public function handle()
    {
        $url = 'https://script.google.com/macros/s/AKfycbxkkmo4L0EbqNK6WaOqM73yUuvC4mwAJMDJcfebxNnzwZ_LuL_9SIOtP09moPFHjV27/exec';

        try {
            $response = Http::timeout(120)->get($url);

            if ($response->failed()) {
                $this->error("Failed to fetch sheet: " . $response->status());
                return;
            }

            $data = $response->json();
            $rows = collect($data['data'] ?? []);

        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
            return;
        }

        foreach ($rows as $row) {
            $sku = trim($row['sku'] ?? '');
            if (!$sku) continue;

            WaifairProductSheet::updateOrCreate(
                ['sku' => $sku],
                [
                    'price' => $this->toDecimalOrNull($row['price'] ?? null),
                    'l30'   => $this->toIntOrNull($row['l30'] ?? null),
                    'l60'   => $this->toIntOrNull($row['l60'] ?? null),
                    'views'   => $this->toIntOrNull($row['views'] ?? null),
                ]
            );
        }

        $this->info("Sheet synced. Fetching Shopify Wayfair L30/L60...");
        $this->fetchShopifyWayfairData();
    }

    private function fetchShopifyWayfairData()
    {
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

        $page = 1;

        // Storage for aggregated data
        $skuCountsL60 = [];
        $skuCountsL30 = [];
        $skuPrices = [];

        do {
            $url = $baseUrl . '?' . http_build_query($params);

            // Retry handling
            $maxRetries = 3;
            $retryCount = 0;
            $response = null;

            while ($retryCount < $maxRetries) {

                if ($retryCount > 0) {
                    sleep(2);
                } else {
                    sleep(1);
                }

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN')
                ])->get($url);

                if ($response->successful()) {
                    break;
                }

                if ($response->status() == 429 || str_contains($response->body(), 'Exceeded')) {
                    $retryCount++;
                    $this->warn("Rate limit hit… retry {$retryCount}/{$maxRetries}");
                } else {
                    $this->error("Failed fetching Shopify orders: " . $response->body());
                    return;
                }
            }

            if (!$response->successful()) {
                $this->error("Failed after retry: " . $response->body());
                return;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];

            $this->info("Fetched page {$page}: " . count($orders) . " orders");

            // 🔥 Process each order immediately (NO memory accumulation)
            foreach ($orders as $order) {
                $createdAt = Carbon::parse($order['created_at']);

                if (!$this->isWayfairOrder($order)) {
                    continue;
                }

                foreach ($order['line_items'] as $item) {
                    $sku = $item['sku'] ?? '';
                    if (!$sku) continue;

                    $quantity = $item['current_quantity'] ?? 0;
                    $price = $item['price'] ?? 0;

                    // L60
                    if ($createdAt >= $sixtyDaysAgo) {
                        $skuCountsL60[$sku] = ($skuCountsL60[$sku] ?? 0) + $quantity;
                    }

                    // L30
                    if ($createdAt >= $thirtyDaysAgo) {
                        $skuCountsL30[$sku] = ($skuCountsL30[$sku] ?? 0) + $quantity;
                    }

                    // Latest price
                    if (!isset($skuPrices[$sku]) || $createdAt > $skuPrices[$sku]['date']) {
                        $skuPrices[$sku] = [
                            'price' => $price,
                            'date'  => $createdAt
                        ];
                    }
                }
            }

            // Next page check
            $next = $this->getNextPageUrl($response->header('Link'));

            if ($next) {
                $parsed = parse_url($next);
                parse_str($parsed['query'], $params);
                $page++;
            } else {
                break;
            }

        } while (true);

        // 🔥 Update DB
        foreach ($skuPrices as $sku => $priceData) {
            $update = [
                'price' => $priceData['price'],
                'l60'    => $skuCountsL60[$sku] ?? 0,
                'l30'    => $skuCountsL30[$sku] ?? 0,
            ];
            WaifairProductSheet::where('sku', $sku)->update($update);
        }

        // SKUs with L30/L60 but no price
        foreach ($skuCountsL60 as $sku => $l60) {
            if (!isset($skuPrices[$sku])) {
                WaifairProductSheet::where('sku', $sku)->update([
                    'shopify_wayfairl60' => $l60,
                    'shopify_wayfairl30' => $skuCountsL30[$sku] ?? 0,
                ]);
            }
        }

        $this->info("Shopify Wayfair sales updated!");
    }

    private function isWayfairOrder($order)
    {
        $tags = strtolower($order['tags'] ?? '');
        if (str_contains($tags, 'wayfair')) return true;

        if (!empty($order['note_attributes'])) {
            foreach ($order['note_attributes'] as $a) {
                if (
                    strtolower($a['name'] ?? '') === 'channel' &&
                    strtolower($a['value'] ?? '') === 'wayfair'
                ) {
                    return true;
                }
            }
        }

        return str_contains(strtolower($order['source_name'] ?? ''), 'wayfair');
    }

    private function getNextPageUrl($header)
    {
        if (!$header) return null;

        foreach (explode(',', $header) as $link) {
            if (str_contains($link, 'rel="next"')) {
                preg_match('/<([^>]+)>/', $link, $m);
                return $m[1] ?? null;
            }
        }

        return null;
    }

    private function toDecimalOrNull($v)
    {
        if ($v === null || $v === '') return null;
        return is_numeric($v) ? (string) $v : null;
    }

    private function toIntOrNull($v)
    {
        if ($v === null || $v === '') return null;
        $v = str_replace(',', '', $v);
        return is_numeric($v) ? (int) $v : null;
    }
}
