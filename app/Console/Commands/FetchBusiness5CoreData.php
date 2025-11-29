<?php

namespace App\Console\Commands;

use App\Models\Business5CoreProduct;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchBusiness5CoreData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-business-5core-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Business 5 Core Shopify data and sync to database table';

    public function handle()
    {
        $shopUrl  = env('BUSINESS_5CORE_SHOPIFY_DOMAIN');
        $accessToken = env('BUSINESS_5CORE_SHOPIFY_ACCESS_TOKEN');
        $version  = "2025-07";

        // Validate environment variables
        if (!$shopUrl || !$accessToken) {
            $this->error("Missing Business 5 Core Shopify credentials in .env file");
            Log::error("Business 5 Core: Missing Shopify credentials");
            return;
        }

        $this->info("Fetching orders from Business 5 Core Shopify...");
        $this->info("Shop: {$shopUrl}");
        
        // --- Date ranges ---
        $now = Carbon::now('America/New_York');

        $startL30 = $now->copy()->subMonth()->startOfMonth();
        $endL30   = $now->copy()->subMonth()->endOfMonth();

        $startL60 = $now->copy()->subMonths(2)->startOfMonth();
        $endL60   = $now->copy()->subMonths(2)->endOfMonth();

        $skuSales = [];

        // Fetch orders only from last 90 days (to cover L30 + L60 safely)
        $createdAtMin = $now->copy()->subMonths(3)->toIso8601String();
        $url = "https://{$shopUrl}/admin/api/{$version}/orders.json?status=any&limit=250&created_at_min={$createdAtMin}";

        $orderCount = 0;

        do {
            try {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                ])->timeout(30)->get($url);

                if ($response->failed()) {
                    $this->error("Shopify API Error (Status {$response->status()}): " . $response->body());
                    Log::error("Business 5 Core Shopify API Error: " . $response->body());
                    return;
                }

                $orders = $response->json()['orders'] ?? [];
                $orderCount += count($orders);
                $this->info("Processing " . count($orders) . " orders...");

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
            } catch (\Exception $e) {
                $this->error("Error fetching orders: " . $e->getMessage());
                Log::error("Business 5 Core: Error fetching orders - " . $e->getMessage());
                return;
            }
        } while ($url);

        // Store into business_5core_products table
        $syncCount = 0;
        foreach ($skuSales as $sku => $data) {
            Business5CoreProduct::updateOrCreate(
                ['sku' => $sku],
                [
                    'price'   => $data['price'],
                    'b5c_l30' => $data['l30'],
                    'b5c_l60' => $data['l60'],
                ]
            );
            $syncCount++;
        }

        $this->info("✓ Business 5 Core products synced successfully!");
        $this->info("  Total Orders Processed: $orderCount");
        $this->info("  Total Products Synced: $syncCount");
        $this->info("  L30 Date Range: {$startL30->format('Y-m-d')} to {$endL30->format('Y-m-d')}");
        $this->info("  L60 Date Range: {$startL60->format('Y-m-d')} to {$endL60->format('Y-m-d')}");

        Log::info("Business 5 Core products synced into business_5core_products table. Total: $syncCount products from $orderCount orders");
    }
}
