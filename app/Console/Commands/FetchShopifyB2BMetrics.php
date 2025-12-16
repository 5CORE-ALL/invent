<?php

namespace App\Console\Commands;

use App\Models\ShopifyB2BDailyData;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FetchShopifyB2BMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-shopify-b2b-metrics 
                            {--days=60 : Number of days to fetch (default: 60 for L30 and L60)}
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--fresh : Clear existing data before fetching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Shopify B2B order data (wsaio-app tagged orders only)';

    private $shopifyStoreUrl;
    private $shopifyAccessToken;
    private $totalFetched = 0;
    private $totalInserted = 0;
    private $totalUpdated = 0;
    private $totalSkipped = 0; // Orders skipped (non-B2B)

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Starting FetchShopifyB2BMetrics command');
        $this->info('Starting FetchShopifyB2BMetrics command');

        // Initialize Shopify credentials
        $this->shopifyStoreUrl = env('SHOPIFY_STORE_URL');
        $this->shopifyAccessToken = env('SHOPIFY_ACCESS_TOKEN');

        if (empty($this->shopifyStoreUrl) || empty($this->shopifyAccessToken)) {
            $this->error('Missing Shopify API credentials in .env file');
            $this->line('Required: SHOPIFY_STORE_URL, SHOPIFY_ACCESS_TOKEN');
            return 1;
        }

        $this->info("Store URL: {$this->shopifyStoreUrl}");
        $this->info("Access Token: " . substr($this->shopifyAccessToken, 0, 15) . "...");

        // Verify API connection
        if (!$this->verifyCredentials()) {
            $this->error('Failed to verify Shopify API credentials');
            return 1;
        }

        try {
            // Calculate date range
            $days = (int) $this->option('days');
            $fromDate = $this->option('from') 
                ? Carbon::parse($this->option('from')) 
                : Carbon::now()->subDays($days);
            $toDate = $this->option('to') 
                ? Carbon::parse($this->option('to'))->endOfDay() 
                : Carbon::now();

            $this->info("Fetching orders from {$fromDate->format('Y-m-d')} to {$toDate->format('Y-m-d')}");

            // Clear existing data if fresh option is set
            if ($this->option('fresh')) {
                $this->info('Clearing existing data...');
                ShopifyB2BDailyData::truncate();
                $this->info('✅ Existing data cleared');
            }

            // Fetch orders from Shopify API
            $this->info('Step 1/3: Fetching orders from Shopify API...');
            $this->fetchOrders($fromDate, $toDate);

            // Update period labels (L30/L60)
            $this->info('Step 2/3: Updating period labels...');
            $this->updatePeriodLabels();

            // Debug summary
            $this->info('Step 3/3: Generating summary...');
            $this->debugStatus();

            Log::info('Completed FetchShopifyB2BMetrics command successfully', [
                'total_fetched' => $this->totalFetched,
                'total_inserted' => $this->totalInserted,
                'total_updated' => $this->totalUpdated,
            ]);
            
            $this->info('✅ Completed FetchShopifyB2BMetrics command successfully');
            return 0;
        } catch (\Exception $e) {
            Log::error('Error in FetchShopifyB2BMetrics command: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Error in FetchShopifyB2BMetrics command: ' . $e->getMessage());
            return 1;
        }
    }

    private function verifyCredentials()
    {
        $this->info("Testing Shopify API connection...");
        
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get(
                "https://{$this->shopifyStoreUrl}/admin/api/2024-10/orders.json",
                ['limit' => 1, 'status' => 'any']
            );

            if ($response->successful()) {
                $this->info("✅ Shopify API Connection Successful!");
                return true;
            } else {
                $this->error("❌ Shopify API Connection Failed!");
                $this->error("Status: " . $response->status());
                $this->error("Response: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            $this->error("Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    private function fetchOrders($fromDate, $toDate)
    {
        Log::info('Starting fetchOrders for B2B', [
            'from' => $fromDate->format('Y-m-d'),
            'to' => $toDate->format('Y-m-d')
        ]);

        $pageInfo = null;
        $hasMore = true;
        $pageCount = 0;

        while ($hasMore) {
            $pageCount++;
            
            $queryParams = [
                'limit' => 250,
                'status' => 'any',
                'created_at_min' => $fromDate->toIso8601String(),
                'created_at_max' => $toDate->toIso8601String(),
            ];

            if ($pageInfo) {
                $queryParams = ['limit' => 250, 'page_info' => $pageInfo];
            }

            try {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                    'Content-Type' => 'application/json',
                ])->timeout(120)->retry(3, 1000)->get(
                    "https://{$this->shopifyStoreUrl}/admin/api/2024-10/orders.json",
                    $queryParams
                );

                if (!$response->successful()) {
                    $this->error("Failed to fetch orders (Page {$pageCount}): " . $response->body());
                    Log::error("Failed to fetch orders", [
                        'page' => $pageCount,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    break;
                }

                $orders = $response->json()['orders'] ?? [];
                $orderCount = count($orders);
                $this->totalFetched += $orderCount;

                $this->info("  Page {$pageCount}: Processing {$orderCount} orders (Total fetched: {$this->totalFetched})");

                foreach ($orders as $order) {
                    $this->processOrder($order);
                }

                // Get next page info from Link header
                $pageInfo = $this->getNextPageInfo($response);
                $hasMore = (bool) $pageInfo;

                if ($hasMore) {
                    usleep(600000); // 0.6 second delay to respect rate limits (2 calls/sec max)
                }

            } catch (\Exception $e) {
                // Handle rate limit error (429) with retry
                if (strpos($e->getMessage(), '429') !== false) {
                    $this->warn("Rate limit hit, waiting 2 seconds before retry...");
                    sleep(2);
                    continue;
                }
                $this->error("Error fetching page {$pageCount}: " . $e->getMessage());
                Log::error("Error fetching orders", [
                    'page' => $pageCount,
                    'error' => $e->getMessage()
                ]);
                break;
            }
        }

        $this->info("✅ Fetched {$this->totalFetched} orders across {$pageCount} pages");
        $this->info("   Shopify B2B: Inserted: {$this->totalInserted} | Updated: {$this->totalUpdated}");
        $this->info("   Non-B2B Orders Skipped: {$this->totalSkipped}");
    }

    private function processOrder($order)
    {
        $orderId = $order['id'] ?? null;
        $orderNumber = $order['order_number'] ?? null;
        $orderDate = isset($order['created_at']) ? Carbon::parse($order['created_at']) : null;
        $financialStatus = $order['financial_status'] ?? null;
        $fulfillmentStatus = $order['fulfillment_status'] ?? null;
        $sourceName = $order['source_name'] ?? null;
        $tags = $order['tags'] ?? '';

        // =====================================================
        // FILTER: Only process Shopify B2B orders
        // Must have exact "wsaio-app" tag
        // =====================================================
        
        // Check if tag is exactly "wsaio-app"
        $isB2BOrder = (trim($tags) === 'wsaio-app');
        
        // Skip if not a B2B order (tag is not exactly "wsaio-app")
        if (!$isB2BOrder) {
            $this->totalSkipped++;
            return; // Skip this order
        }
        // =====================================================

        // Customer info
        $customerName = null;
        $customerEmail = $order['email'] ?? null;
        if (isset($order['customer'])) {
            $firstName = $order['customer']['first_name'] ?? '';
            $lastName = $order['customer']['last_name'] ?? '';
            $customerName = trim("{$firstName} {$lastName}");
        }

        // Shipping info
        $shippingCity = null;
        $shippingCountry = null;
        if (isset($order['shipping_address'])) {
            $shippingCity = $order['shipping_address']['city'] ?? null;
            $shippingCountry = $order['shipping_address']['country'] ?? null;
        }

        // Tracking info (from first fulfillment)
        $trackingCompany = null;
        $trackingNumber = null;
        $trackingUrl = null;
        if (!empty($order['fulfillments'])) {
            $fulfillment = $order['fulfillments'][0];
            $trackingCompany = $fulfillment['tracking_company'] ?? null;
            $trackingNumber = $fulfillment['tracking_number'] ?? null;
            $trackingUrl = $fulfillment['tracking_url'] ?? null;
        }

        // Process each line item
        $lineItems = $order['line_items'] ?? [];
        foreach ($lineItems as $item) {
            $sku = $item['sku'] ?? '';
            
            // Skip items without SKU or with PARENT in SKU
            if (empty($sku) || stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $lineItemId = $item['id'] ?? null;
            $productId = $item['product_id'] ?? null;
            $variantId = $item['variant_id'] ?? null;
            $productTitle = $item['title'] ?? '';
            $quantity = (int) ($item['quantity'] ?? 0);
            
            // Get price and discount from API
            $originalPrice = (float) ($item['price'] ?? 0);
            $discountAmount = 0;
            if (!empty($item['discount_allocations'])) {
                foreach ($item['discount_allocations'] as $discount) {
                    $discountAmount += (float) ($discount['amount'] ?? 0);
                }
            }
            // finalPrice = price after discount (for PFT/ROI)
            $finalPrice = $originalPrice - ($quantity > 0 ? $discountAmount / $quantity : 0);
            // totalAmount = (original * qty) - discount
            $totalAmount = ($originalPrice * $quantity) - $discountAmount;

            $data = [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'line_item_id' => $lineItemId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'order_date' => $orderDate,
                'financial_status' => $financialStatus,
                'fulfillment_status' => $fulfillmentStatus,
                'sku' => $sku,
                'product_title' => $productTitle,
                'quantity' => $quantity,
                'price' => $finalPrice,
                'original_price' => $originalPrice,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'shipping_city' => $shippingCity,
                'shipping_country' => $shippingCountry,
                'tracking_company' => $trackingCompany,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'source_name' => $sourceName,
                'tags' => $tags,
            ];

            try {
                $existing = ShopifyB2BDailyData::where('order_id', $orderId)
                    ->where('line_item_id', $lineItemId)
                    ->first();

                if ($existing) {
                    $existing->update($data);
                    $this->totalUpdated++;
                } else {
                    ShopifyB2BDailyData::create($data);
                    $this->totalInserted++;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to save order item", [
                    'order_id' => $orderId,
                    'line_item_id' => $lineItemId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function updatePeriodLabels()
    {
        $today = Carbon::today();
        $l30Start = $today->copy()->subDays(30);
        $l60Start = $today->copy()->subDays(60);

        // Update L30 period
        $l30Updated = ShopifyB2BDailyData::where('order_date', '>=', $l30Start)
            ->update(['period' => 'l30']);
        $this->info("  Updated {$l30Updated} records to L30 period");

        // Update L60 period (between 31-60 days ago)
        $l60Updated = ShopifyB2BDailyData::where('order_date', '>=', $l60Start)
            ->where('order_date', '<', $l30Start)
            ->update(['period' => 'l60']);
        $this->info("  Updated {$l60Updated} records to L60 period");

        Log::info('Updated period labels for B2B', [
            'l30_count' => $l30Updated,
            'l60_count' => $l60Updated
        ]);
    }

    private function getNextPageInfo($response)
    {
        $linkHeader = $response->header('Link');
        if (!$linkHeader) {
            return null;
        }

        // Parse Link header for next page
        if (preg_match('/<[^>]*page_info=([^>&>]+)[^>]*>;\s*rel=["\']?next["\']?/', $linkHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function debugStatus()
    {
        $totalRecords = ShopifyB2BDailyData::count();
        $l30Count = ShopifyB2BDailyData::where('period', 'l30')->count();
        $l60Count = ShopifyB2BDailyData::where('period', 'l60')->count();
        $uniqueSkus = ShopifyB2BDailyData::distinct('sku')->count('sku');
        $uniqueOrders = ShopifyB2BDailyData::distinct('order_id')->count('order_id');
        
        // Calculate totals
        $totalQuantity = ShopifyB2BDailyData::sum('quantity');
        $totalSales = ShopifyB2BDailyData::sum('total_amount');
        $l30Sales = ShopifyB2BDailyData::where('period', 'l30')->sum('total_amount');
        $l60Sales = ShopifyB2BDailyData::where('period', 'l60')->sum('total_amount');

        // Get date range
        $minDate = ShopifyB2BDailyData::min('order_date');
        $maxDate = ShopifyB2BDailyData::max('order_date');

        $this->line("\n📊 Shopify B2B Order Statistics:");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Total Records: " . number_format($totalRecords));
        $this->line("Unique Orders: " . number_format($uniqueOrders));
        $this->line("Unique SKUs: " . number_format($uniqueSkus));
        $this->line("Date Range: {$minDate} to {$maxDate}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("L30 Records: " . number_format($l30Count));
        $this->line("L60 Records: " . number_format($l60Count));
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Total Quantity: " . number_format($totalQuantity));
        $this->line("Total Sales: $" . number_format($totalSales, 2));
        $this->line("L30 Sales: $" . number_format($l30Sales, 2));
        $this->line("L60 Sales: $" . number_format($l60Sales, 2));
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        // Top 10 SKUs by quantity
        $this->line("🔝 Top 10 SKUs by Quantity (L30):");
        $topSkus = ShopifyB2BDailyData::where('period', 'l30')
            ->select('sku', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_amount) as total_sales'))
            ->groupBy('sku')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        foreach ($topSkus as $i => $sku) {
            $this->line(sprintf("  %2d. %-20s Qty: %5d  Sales: $%10.2f", 
                $i + 1, 
                substr($sku->sku, 0, 20), 
                $sku->total_qty, 
                $sku->total_sales
            ));
        }

        Log::info('Debug status complete for B2B', [
            'total_records' => $totalRecords,
            'l30_count' => $l30Count,
            'l60_count' => $l60Count,
            'unique_skus' => $uniqueSkus,
            'total_sales' => $totalSales,
        ]);
    }
}
