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
        
        // Set output callback to print debug info to console
        $this->tiktokService->setOutputCallback(function($type, $message) {
            switch ($type) {
                case 'info':
                    $this->line($message);
                    break;
                case 'error':
                    $this->error($message);
                    break;
                case 'warn':
                    $this->warn($message);
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
        
        // Verify credentials are loaded
        $this->info('Verifying credentials...');
        $clientKey = config('services.tiktok.client_key');
        $clientSecret = config('services.tiktok.client_secret');
        $shopId = config('services.tiktok.shop_id');
        
        if (empty($clientKey) || empty($clientSecret)) {
            $this->error('❌ Missing TikTok credentials in config!');
            $this->info('Please check your .env file has:');
            $this->info('  TIKTOK_CLIENT_KEY');
            $this->info('  TIKTOK_CLIENT_SECRET');
            return 1;
        }
        
        $this->info('✓ Client Key: ' . substr($clientKey, 0, 10) . '...');
        $this->info('✓ Client Secret: ' . (strlen($clientSecret) > 0 ? substr($clientSecret, 0, 10) . '...' : 'MISSING'));
        $this->info('✓ Shop ID: ' . ($shopId ?? 'NOT SET'));
        $this->info('✓ Access Token: ' . (strlen(env('TIKTOK_ACCESS_TOKEN') ?? '') > 0 ? substr(env('TIKTOK_ACCESS_TOKEN'), 0, 20) . '...' : 'MISSING'));

        $this->info('Starting TikTok API data sync...');

        try {
            // Fetch all product data
            $this->info('Fetching products, inventory, analytics, and reviews from TikTok API...');
            
            // First, test shop info to verify connection (non-blocking)
            $this->info('');
            $this->info('Testing shop info endpoint...');
            $command = $this;
            $shopInfo = $this->tiktokService->getShopInfo(function($type, $message) use ($command) {
                if ($type === 'info') {
                    $command->line($message);
                } elseif ($type === 'error') {
                    $command->error($message);
                }
            });
            
            // Check for success - library returns shops array directly or in data
            if ($shopInfo && (isset($shopInfo['shops']) || isset($shopInfo['data']['shops']))) {
                $shops = $shopInfo['shops'] ?? $shopInfo['data']['shops'] ?? [];
                if (!empty($shops)) {
                    $shop = $shops[0];
                    $this->info('✓ Connected to TikTok Shop API');
                    $this->info('  Shop: ' . ($shop['name'] ?? 'N/A') . ' (ID: ' . ($shop['id'] ?? 'N/A') . ')');
                } else {
                    $this->warn('⚠ Shop info returned but no shops found.');
                    $this->info('Continuing with product data sync...');
                }
            } elseif ($shopInfo && isset($shopInfo['code']) && $shopInfo['code'] != 0) {
                $this->warn('⚠ Could not fetch shop info.');
                $this->error('Error Code: ' . ($shopInfo['code'] ?? 'unknown'));
                $this->error('Error Message: ' . ($shopInfo['message'] ?? 'No message provided'));
                if (isset($shopInfo['validation_failures'])) {
                    $this->error('Validation Failures: ' . json_encode($shopInfo['validation_failures'], JSON_PRETTY_PRINT));
                }
                if (isset($shopInfo['request_id'])) {
                    $this->line('Request ID: ' . $shopInfo['request_id']);
                }
                $this->line('Full Response: ' . json_encode($shopInfo, JSON_PRETTY_PRINT));
                $this->info('Continuing with product data sync...');
            } else {
                $this->warn('⚠ Could not fetch shop info.');
                $this->info('Continuing with product data sync...');
            }
            
            // Proceed with syncing product data even if shop info fails
            $this->info('');
            $this->info('Fetching products from TikTok API...');
            $data = $this->tiktokService->syncAllProductData();

            if (!empty($data['errors'])) {
                foreach ($data['errors'] as $error) {
                    $this->error('Error: ' . $error);
                }
            }
            
            // Display detailed error information if API calls failed
            if (empty($data['products']) && empty($data['inventory']) && empty($data['analytics']) && empty($data['reviews'])) {
                $this->warn('');
                $this->warn('⚠ No data retrieved. Checking for API errors...');
                $lastResponse = $this->tiktokService->getLastResponse();
                if ($lastResponse) {
                    if (isset($lastResponse['code']) && $lastResponse['code'] != 0) {
                        $this->error('API Error Code: ' . $lastResponse['code']);
                        $this->error('API Error Message: ' . ($lastResponse['message'] ?? 'No message'));
                        if (isset($lastResponse['validation_failures'])) {
                            $this->error('Validation Failures: ' . json_encode($lastResponse['validation_failures'], JSON_PRETTY_PRINT));
                        }
                        if (isset($lastResponse['request_id'])) {
                            $this->line('Request ID: ' . $lastResponse['request_id']);
                        }
                    } else {
                        $this->info('Last API Response (no error code):');
                        $this->line(json_encode($lastResponse, JSON_PRETTY_PRINT));
                    }
                } else {
                    $this->warn('No last response available. Check logs for details.');
                }
            }

            // Process and store products
            $this->processProducts($data['products'] ?? []);
            
            // Process and store inventory
            $this->processInventory($data['inventory'] ?? []);
            
            // Process and store analytics/views
            $this->processAnalytics($data['analytics'] ?? []);
            
            // Process and store reviews/ratings
            $this->processReviews($data['reviews'] ?? []);

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
     * Process and store reviews/ratings data
     */
    protected function processReviews(array $reviews)
    {
        if (empty($reviews)) {
            $this->warn('No reviews data found');
            return;
        }

        $this->info('Processing ' . count($reviews) . ' reviews records...');
        $updated = 0;

        foreach ($reviews as $review) {
            try {
                $productId = $review['product_id'] ?? null;
                $sku = $review['sku'] ?? null;
                
                if (!$productId && !$sku) {
                    continue;
                }

                // Extract review count and rating
                $reviewCount = $review['review_count'] ?? $review['reviews'] ?? $review['total_reviews'] ?? 0;
                $rating = $review['rating'] ?? $review['average_rating'] ?? $review['avg_rating'] ?? null;

                // Try to find by product_id first, then by SKU
                $tiktokProduct = null;
                if ($productId) {
                    $tiktokProduct = TikTokProduct::where('product_id', $productId)->first();
                }
                
                if (!$tiktokProduct && $sku) {
                    $tiktokProduct = TikTokProduct::where('sku', strtoupper(trim($sku)))->first();
                }

                if ($tiktokProduct) {
                    if ($reviewCount > 0) {
                        $tiktokProduct->reviews = (int)$reviewCount;
                    }
                    if ($rating !== null && $rating > 0) {
                        $tiktokProduct->rating = (float)$rating;
                    }
                    $tiktokProduct->save();
                    $updated++;
                }

            } catch (\Exception $e) {
                $this->error('Error processing reviews: ' . ($review['product_id'] ?? 'unknown') . ' - ' . $e->getMessage());
                Log::error('TikTok reviews processing error', [
                    'review' => $review,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Reviews/Ratings: {$updated} records updated");
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
