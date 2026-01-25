<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\ProductStockMapping;
use App\Services\WalmartRateLimiter;

class FetchWalmartInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walmart:fetch-inventory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch only Walmart inventory/stock from API';

    protected $baseUrl = 'https://marketplace.walmartapis.com';
    protected $token;
    protected $rateLimiter;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        
        // Initialize rate limiter
        $this->rateLimiter = new WalmartRateLimiter();
        
        $this->info("Fetching Walmart Inventory...");

        // Get access token
        $this->token = $this->getAccessToken();
        if (!$this->token) {
            $this->error('Failed to get access token');
            return 1;
        }

        $this->info('✓ Access token received');

        // Fetch and save inventory (processes in batches as it fetches)
        $totalProcessed = $this->fetchAndSaveInventory();

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("✓ Walmart inventory fetched and saved successfully in {$elapsed} seconds");

        return 0;
    }

    /**
     * Get access token from Walmart
     */
    protected function getAccessToken(): ?string
    {
        $clientId = env('WALMART_CLIENT_ID');
        $clientSecret = env('WALMART_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            $this->error('Walmart credentials missing in .env');
            return null;
        }

        $authorization = base64_encode("{$clientId}:{$clientSecret}");

        $response = Http::withoutVerifying()->asForm()->withHeaders([
            'Authorization' => "Basic {$authorization}",
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/v3/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'] ?? null;
        }

        Log::error('Failed to get Walmart access token: ' . $response->body());
        return null;
    }

    /**
     * Fetch and save inventory from Walmart API (processes in batches)
     */
    protected function fetchAndSaveInventory(): int
    {
        $totalProcessed = 0;
        $cursor = null;
        $pageCount = 0;
        $maxPages = 100; // Safety limit

        do {
            try {
                $url = $this->baseUrl . '/v3/inventories';
                
                // Add cursor if we have one
                if ($cursor) {
                    $url .= '?nextCursor=' . urlencode($cursor);
                }

                // Use rate limiter with retry logic
                $response = $this->rateLimiter->executeWithRetry(function() use ($url) {
                    return Http::withoutVerifying()->withHeaders([
                        'WM_QOS.CORRELATION_ID' => uniqid(),
                        'WM_SEC.ACCESS_TOKEN' => $this->token,
                        'WM_SVC.NAME' => 'Walmart Marketplace',
                        'Accept' => 'application/json',
                    ])->get($url);
                }, 'inventory', 3);

                if (!$response->successful()) {
                    $this->error("Inventory API failed: " . $response->body());
                    break;
                }

                $data = $response->json();
            
            // Correct path: inventories are inside 'elements'
            $inventories = $data['elements']['inventories'] ?? [];
            $cursor = $data['meta']['nextCursor'] ?? null;
            $totalCount = $data['meta']['totalCount'] ?? 0;
            
            if ($pageCount === 0) {
                $this->info("  Total inventory items in Walmart: {$totalCount}");
            }

            // Process and save this page immediately
            $pageSaved = 0;
            foreach ($inventories as $item) {
                $sku = $item['sku'] ?? null;
                $quantity = 0;

                // Get quantity from available to sell or input qty
                if (isset($item['nodes'][0]['availToSellQty']['amount'])) {
                    $quantity = (int) $item['nodes'][0]['availToSellQty']['amount'];
                } elseif (isset($item['nodes'][0]['inputQty']['amount'])) {
                    $quantity = (int) $item['nodes'][0]['inputQty']['amount'];
                }

                if ($sku) {
                    // Save to product_stock_mappings only (no apicentral sync)
                    ProductStockMapping::updateOrCreate(
                        ['sku' => $sku],
                        ['inventory_walmart' => $quantity]
                    );
                    
                    $pageSaved++;
                    $totalProcessed++;
                }
            }

            $pageCount++;
            $remaining = $this->rateLimiter->getRemainingRequests('inventory');
            $this->info("  Page {$pageCount}: Saved {$pageSaved} items (Total: {$totalProcessed}, Remaining: {$remaining})");

            } catch (\Exception $e) {
                $this->error("Failed to fetch inventory page {$pageCount}: " . $e->getMessage());
                Log::error('Walmart Inventory Fetch Error', [
                    'page' => $pageCount,
                    'error' => $e->getMessage()
                ]);
                break;
            }

        } while ($cursor && $pageCount < $maxPages);

        return $totalProcessed;
    }

    /**
     * Save inventory to database (ONLY to product_stock_mappings)
     */
    protected function saveInventory(array $inventory): void
    {
        $saved = 0;
        $updated = 0;

        foreach ($inventory as $sku => $quantity) {
            // Save to product_stock_mappings (main storage)
            $result = ProductStockMapping::updateOrCreate(
                ['sku' => $sku],
                ['inventory_walmart' => $quantity]
            );

            if ($result->wasRecentlyCreated) {
                $saved++;
            } else {
                $updated++;
            }

            // Note: Not syncing to apicentral - using local tables only
        }

        $this->info("✓ Saved {$saved} new SKUs, updated {$updated} existing SKUs in product_stock_mappings");
        $this->info("✓ Total inventory updated: " . count($inventory) . " SKUs");
    }
}
