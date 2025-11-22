<?php

namespace App\Console\Commands;

use App\Models\PLSProduct;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchPlsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-pls-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle()
    {
        $shopUrl  = env('PROLIGHTSOUNDS_SHOPIFY_DOMAIN');
        $apiKey   = env('PROLIGHTSOUNDS_SHOPIFY_API_KEY');
        $password = env('PROLIGHTSOUNDS_SHOPIFY_PASSWORD');
        $version  = "2025-07";

        // --- Date ranges ---
        $now = Carbon::now('America/New_York');

        $startL30 = $now->copy()->subMonth()->startOfMonth();
        $endL30   = $now->copy()->subMonth()->endOfMonth();

        $startL60 = $now->copy()->subMonths(2)->startOfMonth();
        $endL60   = $now->copy()->subMonths(2)->endOfMonth();

        $skuSales = [];

        // Fetch orders only from last 90 days (to cover L30 + L60 safely)
        $createdAtMin = $now->copy()->subMonths(3)->toIso8601String();
        $url = "https://{$apiKey}:{$password}@{$shopUrl}/admin/api/{$version}/orders.json?status=any&limit=250&created_at_min={$createdAtMin}";

        do {
            $response = Http::get($url);

            if ($response->failed()) {
                $this->error("Shopify API Error: " . $response->body());
                Log::error("ProLightSounds Shopify API Error: " . $response->body());
                return;
            }

            $orders = $response->json()['orders'] ?? [];

            foreach ($orders as $order) {
                $createdAt = Carbon::parse($order['created_at'], 'America/New_York');

                foreach ($order['line_items'] as $item) {
                    $sku = $item['sku'] ?? null;
                    if (!$sku) continue;

                    if (!isset($skuSales[$sku])) {
                        $skuSales[$sku] = ['l30' => 0, 'l60' => 0, 'price' => $item['price'] ?? 0];
                    }

                    if ($createdAt->between($startL30, $endL30)) {
                        $skuSales[$sku]['l30'] += $item['quantity'] ?? 0;
                    }

                    if ($createdAt->between($startL60, $endL60)) {
                        $skuSales[$sku]['l60'] += $item['quantity'] ?? 0;
                    }

                    // Update latest price (always overwrite with last seen)
                    $skuSales[$sku]['price'] = $item['price'] ?? $skuSales[$sku]['price'];
                }
            }

            // Pagination
            $linkHeader = $response->header('Link');
            $nextPageUrl = null;
            if ($linkHeader) {
                preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches);
                if (!empty($matches[1])) {
                    $nextPageUrl = $matches[1];
                }
            }
            $url = $nextPageUrl;
        } while ($url);

        // Store into pls_products table
        foreach ($skuSales as $sku => $data) {
            PlsProduct::updateOrCreate(
                ['sku' => $sku],
                [
                    'price'   => $data['price'],
                    'p_l30' => $data['l30'],
                    'p_l60' => $data['l60'],
                ]
            );
        }

        $this->info("ProLightSounds products synced into pls_products table!");
    }
}
