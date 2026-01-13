<?php

namespace App\Console\Commands;

use App\Models\TikTokProduct;
use App\Models\TiktokSheet;
use App\Services\TikTokShopService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTikTokApiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:tiktok-api-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync TikTok product data (price, stock, views) from TikTok API';

    protected $tiktokService;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->tiktokService = new TikTokShopService();
        
        // Set callback to output signature retry attempts to console
        $this->tiktokService->setSignatureCallback(function($event, $data) {
            switch ($event) {
                case 'format1_failed':
                    $this->warn('⚠ Signature Format 1 (SHA256) failed. Trying alternative formats...');
                    break;
                case 'trying_format2':
                    $this->info('🔄 Trying Signature Format 2 (HMAC-SHA256 with path)...');
                    break;
                case 'format2_success':
                    $this->info('✅ Signature Format 2 (HMAC-SHA256) worked!');
                    break;
                case 'trying_format3':
                    $this->info('🔄 Trying Signature Format 3 (HMAC-SHA256 params only)...');
                    break;
                case 'format3_success':
                    $this->info('✅ Signature Format 3 (HMAC-SHA256 params only) worked!');
                    break;
                case 'all_formats_failed':
                    $this->error('❌ All signature formats failed. Response: ' . json_encode($data));
                    break;
            }
        });

        // Try to load tokens from env if not in cache
        if (!$this->tiktokService->isAuthenticated()) {
            $accessToken = env('TIKTOK_ACCESS_TOKEN');
            $refreshToken = env('TIKTOK_REFRESH_TOKEN');
            
            if ($accessToken) {
                $this->info('Loading tokens from environment variables...');
                $this->tiktokService->setTokens($accessToken, $refreshToken);
            }
        }

        // Check if authenticated
        if (!$this->tiktokService->isAuthenticated()) {
            $this->error('TikTok API: No access token found. Please authenticate first.');
            $this->info('');
            $this->info('To set tokens, use:');
            $this->info('  php artisan tiktok:set-tokens --access-token=YOUR_TOKEN --refresh-token=YOUR_REFRESH_TOKEN');
            $this->info('');
            $this->info('Or set in .env file:');
            $this->info('  TIKTOK_ACCESS_TOKEN=your_token');
            $this->info('  TIKTOK_REFRESH_TOKEN=your_refresh_token');
            $this->info('');
            $this->info('Or get authorization URL:');
            $this->info($this->tiktokService->getAuthorizationUrl());
            return 1;
        }

        $this->info('Starting TikTok API data sync...');

        try {
            // Fetch all product data
            $this->info('Fetching products, inventory, and analytics from TikTok API...');
            
            // First, test shop info to verify connection
            $shopInfo = $this->tiktokService->getShopInfo();
            if ($shopInfo && isset($shopInfo['data'])) {
                $this->info('✓ Connected to TikTok Shop API');
            } else {
                $this->warn('⚠ Could not fetch shop info. API response: ' . json_encode($shopInfo));
            }
            
            $data = $this->tiktokService->syncAllProductData();

            if (!empty($data['errors'])) {
                foreach ($data['errors'] as $error) {
                    $this->error('Error: ' . $error);
                }
            }

            // Process and store products
            $this->processProducts($data['products'] ?? []);
            
            // Process and store inventory
            $this->processInventory($data['inventory'] ?? []);
            
            // Process and store analytics/views
            $this->processAnalytics($data['analytics'] ?? []);

            $this->info('✅ TikTok API data sync completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to sync TikTok API data: ' . $e->getMessage());
            Log::error('TikTok API sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Process and store products data
     */
    protected function processProducts(array $products)
    {
        if (empty($products)) {
            $this->warn('No products found');
            return;
        }

        $this->info('Processing ' . count($products) . ' products...');
        $updated = 0;
        $created = 0;

        foreach ($products as $product) {
            try {
                $productId = $product['id'] ?? null;
                $sku = $this->extractSku($product);
                
                if (!$sku) {
                    $this->warn('Skipping product ' . $productId . ' - no SKU found');
                    continue;
                }

                // Extract price from product
                $price = $this->extractPrice($product);

                // Update or create product
                $tiktokProduct = TikTokProduct::updateOrCreate(
                    ['product_id' => $productId],
                    [
                        'sku' => strtoupper(trim($sku)),
                        'price' => $price,
                    ]
                );

                if ($tiktokProduct->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

            } catch (\Exception $e) {
                $this->error('Error processing product: ' . ($product['id'] ?? 'unknown') . ' - ' . $e->getMessage());
                Log::error('TikTok product processing error', [
                    'product' => $product,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Products: {$created} created, {$updated} updated");
    }

    /**
     * Process and store inventory data
     */
    protected function processInventory(array $inventory)
    {
        if (empty($inventory)) {
            $this->warn('No inventory data found');
            return;
        }

        $this->info('Processing ' . count($inventory) . ' inventory records...');
        $updated = 0;

        foreach ($inventory as $item) {
            try {
                $productId = $item['product_id'] ?? null;
                if (!$productId) {
                    continue;
                }

                // Find product by product_id
                $tiktokProduct = TikTokProduct::where('product_id', $productId)->first();
                if (!$tiktokProduct) {
                    continue;
                }

                // Extract stock quantity
                $stock = $this->extractStock($item);

                $tiktokProduct->stock = $stock;
                $tiktokProduct->save();
                $updated++;

            } catch (\Exception $e) {
                $this->error('Error processing inventory: ' . ($item['product_id'] ?? 'unknown') . ' - ' . $e->getMessage());
                Log::error('TikTok inventory processing error', [
                    'item' => $item,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Inventory: {$updated} records updated");
    }

    /**
     * Process and store analytics/views data
     */
    protected function processAnalytics(array $analytics)
    {
        if (empty($analytics)) {
            $this->warn('No analytics data found');
            return;
        }

        $this->info('Processing ' . count($analytics) . ' analytics records...');
        $updated = 0;

        foreach ($analytics as $analytic) {
            try {
                $productId = $analytic['product_id'] ?? null;
                $sku = $analytic['sku'] ?? null;
                
                if (!$productId && !$sku) {
                    continue;
                }

                // Extract views
                $views = $analytic['product_views'] ?? $analytic['views'] ?? 0;

                // Try to find by product_id first, then by SKU
                $tiktokProduct = null;
                if ($productId) {
                    $tiktokProduct = TikTokProduct::where('product_id', $productId)->first();
                }
                
                if (!$tiktokProduct && $sku) {
                    $tiktokProduct = TikTokProduct::where('sku', strtoupper(trim($sku)))->first();
                }

                if ($tiktokProduct) {
                    $tiktokProduct->views = $views;
                    $tiktokProduct->save();
                    $updated++;
                } else {
                    // Also update tiktok_sheet_data if product not found in tiktok_products
                    if ($sku) {
                        TiktokSheet::updateOrCreate(
                            ['sku' => strtoupper(trim($sku))],
                            ['views' => $views]
                        );
                    }
                }

            } catch (\Exception $e) {
                $this->error('Error processing analytics: ' . ($analytic['product_id'] ?? 'unknown') . ' - ' . $e->getMessage());
                Log::error('TikTok analytics processing error', [
                    'analytic' => $analytic,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Analytics/Views: {$updated} records updated");
    }

    /**
     * Extract SKU from product data
     */
    protected function extractSku(array $product): ?string
    {
        // Try different possible fields for SKU
        if (isset($product['seller_sku'])) {
            return $product['seller_sku'];
        }
        
        if (isset($product['sku'])) {
            return $product['sku'];
        }

        if (isset($product['skus']) && is_array($product['skus']) && !empty($product['skus'])) {
            return $product['skus'][0]['seller_sku'] ?? $product['skus'][0]['sku'] ?? null;
        }

        // Try to get from variants
        if (isset($product['variants']) && is_array($product['variants']) && !empty($product['variants'])) {
            return $product['variants'][0]['seller_sku'] ?? $product['variants'][0]['sku'] ?? null;
        }

        return null;
    }

    /**
     * Extract price from product data
     */
    protected function extractPrice(array $product): float
    {
        // Try different possible fields for price
        if (isset($product['price'])) {
            return (float) $product['price'];
        }

        if (isset($product['sale_price'])) {
            return (float) $product['sale_price'];
        }

        if (isset($product['price_info']['price'])) {
            return (float) $product['price_info']['price'];
        }

        // Try to get from variants
        if (isset($product['variants']) && is_array($product['variants']) && !empty($product['variants'])) {
            $variant = $product['variants'][0];
            if (isset($variant['price'])) {
                return (float) $variant['price'];
            }
            if (isset($variant['sale_price'])) {
                return (float) $variant['sale_price'];
            }
        }

        return 0;
    }

    /**
     * Extract stock from inventory data
     */
    protected function extractStock(array $item): int
    {
        // Try different possible fields for stock
        if (isset($item['available_stock'])) {
            return (int) $item['available_stock'];
        }

        if (isset($item['stock'])) {
            return (int) $item['stock'];
        }

        if (isset($item['quantity'])) {
            return (int) $item['quantity'];
        }

        if (isset($item['inventory'])) {
            return (int) $item['inventory'];
        }

        // Try nested structures
        if (isset($item['inventory_info']['available_stock'])) {
            return (int) $item['inventory_info']['available_stock'];
        }

        return 0;
    }
}
