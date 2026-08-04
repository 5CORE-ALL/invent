<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTikTokApiData extends Command
{
    use ProcessesUpdatesInChunks;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:tiktok-api-data
        {--channel=tiktok : tiktok (shop 1 → tiktok_products) or tiktok2 (shop 2 → tiktok_products_two)}
        {--chunk= : Override DB write chunk size (default from cron-monitor config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync TikTok / TikTok 2 product data (price, stock, views) from TikTok Shop API';

    protected $tiktokService;

    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected string $productModel = TikTokProduct::class;

    protected string $channel = 'tiktok';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $channel = strtolower(trim((string) $this->option('channel')));
        if (! in_array($channel, ['tiktok', 'tiktok2'], true)) {
            $this->error('Invalid --channel. Use tiktok or tiktok2.');

            return 1;
        }
        $this->channel = $channel;
        $this->productModel = $channel === 'tiktok2' ? TikTokProductTwo::class : TikTokProduct::class;
        $this->tiktokService = $channel === 'tiktok2' ? new TikTok2ShopService() : new TikTokShopService();
        $configKey = $channel === 'tiktok2' ? 'tiktok2' : 'tiktok';
        $label = $channel === 'tiktok2' ? 'TikTok 2' : 'TikTok';

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
            $accessToken = config("services.{$configKey}.access_token");
            $refreshToken = config("services.{$configKey}.refresh_token");
            
            if ($accessToken) {
                $this->info('Loading tokens from environment variables...');
                $this->tiktokService->setTokens($accessToken, $refreshToken);
            }
        }

        // Check if authenticated
        if (!$this->tiktokService->isAuthenticated()) {
            $this->error("{$label} API: No access token found. Please authenticate first.");
            $this->info('');
            if ($channel === 'tiktok2') {
                $this->info('Open /tiktok2/connect (or /tiktok2/exchange) then set:');
                $this->info('  TIKTOK2_ACCESS_TOKEN=...');
                $this->info('  TIKTOK2_REFRESH_TOKEN=...');
            } else {
                $this->info('To set tokens, use:');
                $this->info('  php artisan tiktok:set-tokens --access-token=YOUR_TOKEN --refresh-token=YOUR_REFRESH_TOKEN');
                $this->info('');
                $this->info('Or set in .env file:');
                $this->info('  TIKTOK_ACCESS_TOKEN=your_token');
                $this->info('  TIKTOK_REFRESH_TOKEN=your_refresh_token');
            }
            $this->info('');
            $this->info('Or get authorization URL:');
            $this->info($this->tiktokService->getAuthorizationUrl());
            return 1;
        }
        
        // Verify credentials are loaded
        $this->info("Verifying {$label} credentials...");
        $clientKey = config("services.{$configKey}.client_key");
        $clientSecret = config("services.{$configKey}.client_secret");
        $shopId = config("services.{$configKey}.shop_id");
        
        if (empty($clientKey) || empty($clientSecret)) {
            $this->error("❌ Missing {$label} credentials in config!");
            $this->info('Please check your .env file has:');
            $prefix = strtoupper($configKey);
            $this->info("  {$prefix}_CLIENT_KEY");
            $this->info("  {$prefix}_CLIENT_SECRET");
            return 1;
        }
        
        $this->info('✓ Client Key: ' . substr($clientKey, 0, 10) . '...');
        $this->info('✓ Client Secret: ' . (strlen($clientSecret) > 0 ? substr($clientSecret, 0, 10) . '...' : 'MISSING'));
        $this->info('✓ Shop ID: ' . ($shopId ?? 'NOT SET'));
        $this->info('✓ Access Token: ' . (strlen(config("services.{$configKey}.access_token") ?? '') > 0 ? substr(config("services.{$configKey}.access_token"), 0, 20) . '...' : 'MISSING'));
        $this->info('✓ Target table: ' . ($channel === 'tiktok2' ? 'tiktok_products_two' : 'tiktok_products'));

        $this->info("Starting {$label} API data sync...");

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
            
            // Process and store analytics/views (API only → tiktok_products)
            $this->processAnalytics($data['analytics'] ?? []);
            
            // Process and store reviews/ratings
            $this->processReviews($data['reviews'] ?? []);

            $this->info('✅ ' . ($this->channel === 'tiktok2' ? 'TikTok 2' : 'TikTok') . ' API data sync completed successfully!');
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
        $chunkSize = $this->monitoredChunkSize();

        $this->writeItemsInChunks($products, function (array $chunk) use (&$created, &$updated) {
            $chunkUpdated = 0;
            foreach ($chunk as $product) {
                try {
                    $productId = $product['id'] ?? null;
                    $sku = $this->extractSku($product);

                    if (!$sku) {
                        continue;
                    }

                    $price = $this->extractPrice($product);
                    $normalizedSku = strtoupper(trim($sku));

                    $tiktokProduct = ($this->productModel)::updateOrCreate(
                        ['sku' => $normalizedSku],
                        [
                            'product_id' => $productId,
                            'price' => $price,
                        ]
                    );

                    if ($tiktokProduct->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                    $chunkUpdated++;
                } catch (\Exception $e) {
                    $this->error('Error processing product: '.($product['id'] ?? 'unknown').' - '.$e->getMessage());
                    Log::error('TikTok product processing error', [
                        'product' => $product,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ['updated' => $chunkUpdated, 'failed' => 0];
        }, $chunkSize);

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
        $chunkSize = $this->monitoredChunkSize();

        $this->writeItemsInChunks($inventory, function (array $chunk) use (&$updated) {
            $chunkUpdated = 0;
            foreach ($chunk as $item) {
                try {
                    $productId = $item['product_id'] ?? null;
                    if (!$productId) {
                        continue;
                    }

                    $tiktokProduct = ($this->productModel)::where('product_id', $productId)->first();
                    if (!$tiktokProduct) {
                        continue;
                    }

                    $stock = $this->extractStock($item);

                    $tiktokProduct->stock = $stock;
                    $tiktokProduct->save();
                    $updated++;
                    $chunkUpdated++;
                } catch (\Exception $e) {
                    $this->error('Error processing inventory: '.($item['product_id'] ?? 'unknown').' - '.$e->getMessage());
                    Log::error('TikTok inventory processing error', [
                        'item' => $item,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ['updated' => $chunkUpdated, 'failed' => 0];
        }, $chunkSize);

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
                // Extract product_id from various possible fields
                $productId = $analytic['product_id'] 
                    ?? $analytic['id'] 
                    ?? $analytic['productId']
                    ?? $analytic['product']['id'] ?? null;
                
                // Extract SKU if available
                $sku = $analytic['sku'] 
                    ?? $analytic['seller_sku']
                    ?? $analytic['product']['sku'] ?? null;
                
                if (!$productId && !$sku) {
                    continue;
                }

                // Extract views from various possible fields
                $views = $analytic['product_views'] 
                    ?? $analytic['views'] 
                    ?? $analytic['total_views']
                    ?? $analytic['view_count']
                    ?? $analytic['page_views']
                    ?? $analytic['metrics']['product_views'] 
                    ?? $analytic['metrics']['views']
                    ?? $analytic['metrics']['total_views']
                    ?? $analytic['performance']['product_views']
                    ?? $analytic['performance']['views']
                    ?? $analytic['data']['product_views']
                    ?? $analytic['data']['views'] ?? 0;
                
                // Ensure views is an integer
                $views = (int) $views;

                // Try to find by product_id first, then by SKU
                $tiktokProduct = null;
                if ($productId) {
                    $tiktokProduct = ($this->productModel)::where('product_id', $productId)->first();
                }
                
                if (!$tiktokProduct && $sku) {
                    $tiktokProduct = ($this->productModel)::where('sku', strtoupper(trim($sku)))->first();
                }

                if ($tiktokProduct) {
                    $tiktokProduct->views = $views;
                    $tiktokProduct->save();
                    $updated++;
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
                    $tiktokProduct = ($this->productModel)::where('product_id', $productId)->first();
                }
                
                if (!$tiktokProduct && $sku) {
                    $tiktokProduct = ($this->productModel)::where('sku', strtoupper(trim($sku)))->first();
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
     * Extract price from product data.
     * TikTok searchProducts returns: skus[].price.tax_exclusive_price / sale_price.
     */
    protected function extractPrice(array $product): float
    {
        $candidates = [];

        if (isset($product['skus']) && is_array($product['skus'])) {
            foreach ($product['skus'] as $sku) {
                $priceNode = $sku['price'] ?? null;
                if (is_array($priceNode)) {
                    $candidates[] = $priceNode['sale_price']
                        ?? $priceNode['tax_exclusive_price']
                        ?? $priceNode['amount']
                        ?? $priceNode['price']
                        ?? null;
                } elseif (is_numeric($priceNode)) {
                    $candidates[] = $priceNode;
                }
                $candidates[] = $sku['sale_price'] ?? $sku['price_amount'] ?? null;
            }
        }

        if (isset($product['variants']) && is_array($product['variants'])) {
            foreach ($product['variants'] as $variant) {
                $priceNode = $variant['price'] ?? null;
                if (is_array($priceNode)) {
                    $candidates[] = $priceNode['sale_price']
                        ?? $priceNode['tax_exclusive_price']
                        ?? $priceNode['amount']
                        ?? $priceNode['price']
                        ?? null;
                } elseif (is_numeric($priceNode)) {
                    $candidates[] = $priceNode;
                }
                $candidates[] = $variant['sale_price'] ?? null;
            }
        }

        if (is_array($product['price'] ?? null)) {
            $candidates[] = $product['price']['sale_price']
                ?? $product['price']['tax_exclusive_price']
                ?? $product['price']['amount']
                ?? null;
        } elseif (isset($product['price']) && is_numeric($product['price'])) {
            $candidates[] = $product['price'];
        }

        $candidates[] = $product['sale_price'] ?? null;
        $candidates[] = $product['price_info']['price'] ?? null;

        foreach ($candidates as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $price = (float) $value;
            if ($price > 0) {
                return $price;
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
