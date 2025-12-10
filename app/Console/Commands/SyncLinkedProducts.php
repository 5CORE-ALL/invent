<?php

namespace App\Console\Commands;

use App\Models\LinkedProductData;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncLinkedProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-linked-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto balance linked products by dilution % and available qty and update Shopify + DB with shopify APIs';

    private $shopifyDomain;
    private $shopifyApiKey;
    private $shopifyPassword;
    private $lastApiCallTime = 0;
    private $minDelayBetweenCalls = 600000; // 0.6 seconds in microseconds (stays under 2 req/sec)
    private $consecutiveRateLimits = 0;
    private $lastRateLimitTime = 0;

    public function __construct()
    {
        parent::__construct();
        $this->shopifyDomain  = env('SHOPIFY_5CORE_DOMAIN');
        $this->shopifyApiKey  = env('SHOPIFY_5CORE_API_KEY');
        $this->shopifyPassword = env('SHOPIFY_5CORE_PASSWORD');
    }

    /**
     * Check if we need to wait due to recent rate limiting
     */
    private function checkRateLimitCooldown()
    {
        if ($this->consecutiveRateLimits > 0 && $this->lastRateLimitTime > 0) {
            $timeSinceLastLimit = time() - $this->lastRateLimitTime;
            // If we hit rate limits recently, wait longer
            if ($timeSinceLastLimit < 60) {
                $cooldown = 10 * $this->consecutiveRateLimits; // 10s per consecutive limit
                if ($cooldown > $timeSinceLastLimit) {
                    $waitTime = $cooldown - $timeSinceLastLimit;
                    $this->warn("⏳ Cooldown period: waiting {$waitTime}s due to {$this->consecutiveRateLimits} recent rate limits...");
                    sleep($waitTime);
                }
            } else {
                // Reset counter after 60 seconds
                $this->consecutiveRateLimits = 0;
            }
        }
    }

    /**
     * Throttle API calls to respect Shopify's 2 req/sec rate limit
     */
    private function throttleApiCall()
    {
        $this->checkRateLimitCooldown();
        
        if ($this->lastApiCallTime > 0) {
            $timeSinceLastCall = (microtime(true) * 1000000) - $this->lastApiCallTime;
            if ($timeSinceLastCall < $this->minDelayBetweenCalls) {
                $sleepTime = $this->minDelayBetweenCalls - $timeSinceLastCall;
                usleep((int)$sleepTime);
            }
        }
        $this->lastApiCallTime = microtime(true) * 1000000;
    }

    /**
     * Make Shopify API call with automatic rate limit handling
     */
    private function makeShopifyRequest($method, $endpoint, $data = [], $maxRetries = 10)
    {
        $this->throttleApiCall();
        
        $attempt = 0;
        $baseDelay = 3; // Increased from 2 to 3 seconds
        
        while ($attempt < $maxRetries) {
            try {
                $request = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                    ->timeout(60); // Increased timeout
                
                if ($method === 'GET') {
                    $response = $request->get($endpoint, $data);
                } else {
                    $response = $request->post($endpoint, $data);
                }
                
                // Handle 429 rate limit with exponential backoff
                if ($response->status() === 429) {
                    $this->consecutiveRateLimits++;
                    $this->lastRateLimitTime = time();
                    
                    $retryAfter = $response->header('Retry-After');
                    if ($retryAfter) {
                        $waitTime = (int)$retryAfter;
                    } else {
                        // Exponential backoff: 3, 6, 12, 24, 48... seconds
                        $waitTime = $baseDelay * pow(2, $attempt);
                    }
                    
                    $this->warn("⚠ Rate limited (429). Waiting {$waitTime} seconds before retry " . ($attempt + 1) . "/{$maxRetries}...");
                    sleep($waitTime);
                    $attempt++;
                    continue;
                }
                
                // Reset rate limit counter on success
                if ($response->successful()) {
                    $this->consecutiveRateLimits = 0;
                }
                
                // Handle other 5xx server errors with retry
                if ($response->status() >= 500) {
                    $waitTime = $baseDelay * pow(2, $attempt);
                    $this->warn("⚠ Server error ({$response->status()}). Waiting {$waitTime}s before retry " . ($attempt + 1) . "/{$maxRetries}...");
                    sleep($waitTime);
                    $attempt++;
                    continue;
                }
                
                // Success or non-retryable error (4xx except 429)
                return $response;
                
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                $delay = $baseDelay * pow(2, $attempt - 1);
                $this->warn("⚠ API exception: {$e->getMessage()}. Retrying in {$delay}s... ({$attempt}/{$maxRetries})");
                sleep($delay);
            }
        }
        
        throw new \Exception("Failed after {$maxRetries} attempts - rate limit not clearing");
    }


    /**
     * Execute the console command.
     */

    public function handle()
    {
        // Check if we need to wait for rate limit to clear
        $this->info("🚀 Starting Linked Product Sync...");
        $this->info("⏳ Waiting 5 seconds to ensure rate limits are clear...");
        sleep(5);
        
        // Normalize SKU: uppercase, trim, collapse multiple spaces
        $normalizeSku = function($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);         // collapse spaces
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);  // remove hidden whitespace
            return $sku;
        };

        $groups = ProductMaster::whereNotNull('group_id')->get()->groupBy('group_id');

        $allSkus = $groups->flatten(1)->pluck('sku')->map($normalizeSku)->unique()->values()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $allSkus)
            ->get()
            ->keyBy(fn($item) => $normalizeSku($item->sku));

        foreach ($groups as $groupId => $products) {
            $this->info("Processing Group {$groupId}");

            $packData = [];
            $totalPieces = 0;
            $totalL30 = 0;

            foreach ($products as $product) {
                $sku = $normalizeSku($product->sku);
                $availableQty = $this->getShopifyAvailableQty($sku, $normalizeSku);
                $l30 = isset($shopifyData[$sku]) ? (int)$shopifyData[$sku]->quantity : 0;

                // detect pack size
                if (preg_match('/(\d+)\s*P(?:c|cs)/i', $product->sku, $matches)) {
                    $packSize = (int)$matches[1];
                } else {
                    $packSize = 1;
                }

                $product->calc_available_qty = $availableQty;
                $product->calc_l30 = $l30;
                $product->dil = $availableQty > 0 ? round(($l30 / $availableQty) * 100, 2) : 0;

                $piecesFromThisSku = $availableQty * $packSize;
                $this->info("SKU {$sku}: PackSize={$packSize}, Qty={$availableQty}, Pieces={$piecesFromThisSku}");

                $packData[$sku] = [
                    'product'  => $product,
                    'packSize' => $packSize,
                    'l30'      => $l30,
                ];

                $totalPieces += $piecesFromThisSku;
                $totalL30 += $l30;
            }

            if ($totalPieces == 0) {
                $this->warn("Group {$groupId} has no stock, skipping.");
                continue;
            }

            $numSkus = count($packData);
            $this->info("=== TOTAL: {$totalPieces} pieces from {$numSkus} SKUs ===");

            
            // Distribute EQUALLY across all SKUs in the group
           
            $allocations = [];
            $assignedPieces = 0;

            // Calculate equal distribution in pieces
            $piecesPerSku = intdiv($totalPieces, $numSkus);
            $leftoverPieces = $totalPieces % $numSkus;

            $this->info("Distributing equally: {$piecesPerSku} pieces per SKU (leftover: {$leftoverPieces})");

            foreach ($packData as $sku => $data) {
                // Convert pieces to packs for this SKU
                $packs = intdiv($piecesPerSku, $data['packSize']);
                $allocations[$sku] = $packs;
                $piecesAllocated = $packs * $data['packSize'];
                $assignedPieces += $piecesAllocated;
                $this->line("  {$sku}: {$piecesPerSku} pieces ÷ {$data['packSize']} = {$packs} packs ({$piecesAllocated} pieces)");
            }

            // Distribute ALL leftover pieces to smallest pack size SKU
            $totalLeftover = $totalPieces - $assignedPieces;
            $this->line("Total assigned: {$assignedPieces} pieces, Leftover: {$totalLeftover} pieces");
            
            if ($totalLeftover > 0) {
                // Find the SKU with smallest pack size
                $smallestSku = null;
                $smallestPackSize = PHP_INT_MAX;
                foreach ($packData as $sku => $data) {
                    if ($data['packSize'] < $smallestPackSize) {
                        $smallestPackSize = $data['packSize'];
                        $smallestSku = $sku;
                    }
                }

                $this->line("Assigning ALL {$totalLeftover} leftover pieces to smallest pack SKU: {$smallestSku} (pack size: {$smallestPackSize})");

                // Add as many full packs as possible
                $extraPacks = intdiv($totalLeftover, $smallestPackSize);
                if ($extraPacks > 0) {
                    $allocations[$smallestSku] += $extraPacks;
                    $assignedPieces += $extraPacks * $smallestPackSize;
                    $this->line("  Added {$extraPacks} full packs ({$extraPacks} × {$smallestPackSize} = " . ($extraPacks * $smallestPackSize) . " pieces)");
                }

                // If there are STILL pieces left (can't make a full pack), add one more pack
                $finalLeftover = $totalPieces - $assignedPieces;
                if ($finalLeftover > 0) {
                    $allocations[$smallestSku] += 1;
                    $assignedPieces += $smallestPackSize;
                    $this->line("  Added 1 more pack to accommodate remaining {$finalLeftover} pieces (waste: " . ($smallestPackSize - $finalLeftover) . " pieces)");
                }
            }
            
            $this->info("=== FINAL ALLOCATION ===");
            foreach ($packData as $sku => $data) {
                $finalQty = $allocations[$sku];
                $finalPieces = $finalQty * $data['packSize'];
                $this->info("  {$sku}: {$finalQty} packs × {$data['packSize']} = {$finalPieces} pieces");
            }

           
            // Update DB + Shopify
           
            $groupResults = [];
            
            foreach ($packData as $sku => $data) {
                $oldQty = $data['product']->calc_available_qty;
                $oldDil = $data['product']->dil;

                $newQty = $allocations[$sku];
                $newDil = $newQty > 0
                    ? round(($data['l30'] / ($newQty * $data['packSize'])) * 100, 2)
                    : 0;

                $this->info("SKU {$sku} old_qty={$oldQty}, new_qty={$newQty}");

                // Update Shopify FIRST
                $this->line("Updating Shopify for SKU: {$sku}...");
                $shopifyResult = $this->updateShopifyQty($sku, $newQty, $normalizeSku);

                // Only save to database if Shopify succeeded
                if ($shopifyResult['success']) {
                    try {
                        LinkedProductData::create([
                            'group_id' => $groupId,
                            'sku'      => $sku,
                            'old_qty'  => $oldQty,
                            'new_qty'  => $newQty,
                            'old_dil'  => $oldDil,
                            'new_dil'  => $newDil,
                        ]);
                        $this->info("✓ SKU {$sku} updated in Shopify (qty={$newQty}) and saved to database");
                        $groupResults[$sku] = 'SUCCESS';
                    } catch (\Exception $e) {
                        $this->error("✓ Shopify updated but database save failed for {$sku}: " . $e->getMessage());
                        $groupResults[$sku] = 'DB_FAILED';
                    }
                } else {
                    $this->error("✗ SKU {$sku} FAILED to update in Shopify - database NOT updated");
                    $this->error("   Reason: " . ($shopifyResult['error'] ?? 'Unknown error'));
                    $this->error("   Details: " . ($shopifyResult['details'] ?? 'No additional details'));
                    $groupResults[$sku] = 'SHOPIFY_FAILED: ' . ($shopifyResult['error'] ?? 'Unknown');
                }
            }
            
            // Show group summary
            $this->newLine();
            $this->info("=== Group {$groupId} Summary ===");
            $successCount = count(array_filter($groupResults, fn($r) => $r === 'SUCCESS'));
            $totalCount = count($groupResults);
            $this->info("Success: {$successCount}/{$totalCount} SKUs");
            foreach ($groupResults as $sku => $result) {
                $icon = $result === 'SUCCESS' ? '✓' : '✗';
                $this->line("  {$icon} {$sku}: {$result}");
            }
            $this->newLine();
        }

        return Command::SUCCESS;
    }

    private function getShopifyAvailableQty($sku, $normalizeSku)
    {
        $inventoryItemId = null;
        $pageInfo = null;

        do {
            $queryParams = ['limit' => 250];
            if ($pageInfo) $queryParams['page_info'] = $pageInfo;

            $response = $this->makeShopifyRequest(
                'GET',
                "https://{$this->shopifyDomain}/admin/api/2025-01/products.json",
                $queryParams
            );

            if (!$response->successful()) {
                Log::warning("Shopify API failed for SKU {$sku}: " . $response->status());
                return 0;
            }

            $products = $response->json('products') ?? [];
            
            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $variant) {
                    $variantSku = $normalizeSku($variant['sku'] ?? '');
                    if ($variantSku === $sku) {
                        $inventoryItemId = $variant['inventory_item_id'];
                        break 2;
                    }
                }
            }

            if ($inventoryItemId) break;

            $linkHeader = $response->header('Link');
            $pageInfo = null;
            if ($linkHeader && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>; rel="next"/', $linkHeader, $matches)) {
                $pageInfo = $matches[1];
            } else {
                $pageInfo = null;
            }
        } while ($pageInfo);

        if (!$inventoryItemId) {
            Log::warning("Inventory item ID not found for SKU: {$sku}");
            return 0;
        }

        $invLevelResponse = $this->makeShopifyRequest(
            'GET',
            "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json",
            ['inventory_item_ids' => $inventoryItemId]
        );

        if (!$invLevelResponse->successful()) {
            Log::error("Failed to get inventory levels for SKU {$sku}");
            return 0;
        }

        return collect($invLevelResponse->json('inventory_levels') ?? [])->sum('available');
    }

    private function updateShopifyQty($sku, $newQty, $normalizeSku)
    {
        $inventoryItemId = null;
        $pageInfo = null;
        
        // Normalize the search SKU once
        $normalizedSearchSku = $normalizeSku($sku);
        $this->line("Searching for SKU: '{$normalizedSearchSku}'");
        
        $searchedPages = 0;
        $totalVariantsChecked = 0;

        do {
            $queryParams = ['limit' => 250];
            if ($pageInfo) $queryParams['page_info'] = $pageInfo;

            $response = $this->makeShopifyRequest(
                'GET',
                "https://{$this->shopifyDomain}/admin/api/2025-01/products.json",
                $queryParams
            );

            if (!$response->successful()) {
                Log::warning("Shopify API failed when updating SKU {$sku}: " . $response->status());
                return [
                    'success' => false, 
                    'error' => 'Shopify API error', 
                    'details' => "HTTP {$response->status()} - Failed to fetch products"
                ];
            }

            $products = $response->json('products') ?? [];
            $searchedPages++;
            
            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $variant) {
                    $totalVariantsChecked++;
                    $originalVariantSku = $variant['sku'] ?? '';
                    $variantSku = $normalizeSku($originalVariantSku);
                    
                    // Log SKUs that are close matches
                    if (stripos($variantSku, 'MS RBL 3T') !== false) {
                        $this->line("  Found similar: '{$originalVariantSku}' -> normalized: '{$variantSku}'");
                    }
                    
                    if ($variantSku === $normalizedSearchSku) {
                        $this->info("✓ Found exact match: '{$originalVariantSku}' (normalized: '{$variantSku}')");
                        $inventoryItemId = $variant['inventory_item_id'];
                        break 2;
                    }
                }
            }
            
            if ($searchedPages % 5 === 0) {
                $this->line("  Searched {$searchedPages} pages, {$totalVariantsChecked} variants so far...");
            }

            if ($inventoryItemId) break;

            $linkHeader = $response->header('Link');
            $pageInfo = null;
            if ($linkHeader && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>; rel="next"/', $linkHeader, $matches)) {
                $pageInfo = $matches[1];
            } else {
                $pageInfo = null;
            }
        } while ($pageInfo);

        if (!$inventoryItemId) {
            $this->warn("✗ SKU '{$normalizedSearchSku}' not found after searching {$searchedPages} pages and {$totalVariantsChecked} variants");
            Log::warning("Inventory item ID not found when updating SKU: {$sku}");
            return [
                'success' => false, 
                'error' => 'SKU not found in Shopify', 
                'details' => "The SKU '{$normalizedSearchSku}' does not exist in Shopify after searching {$searchedPages} pages. Check Shopify admin for exact SKU spelling."
            ];
        }

        $invLevelResponse = $this->makeShopifyRequest(
            'GET',
            "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json",
            ['inventory_item_ids' => $inventoryItemId]
        );

        if (!$invLevelResponse->successful()) {
            Log::error("Failed to get inventory levels for SKU {$sku}");
            return [
                'success' => false, 
                'error' => 'Failed to get inventory levels', 
                'details' => "HTTP {$invLevelResponse->status()}"
            ];
        }

        $locationId = $invLevelResponse->json('inventory_levels.0.location_id') ?? null;
        if (!$locationId) {
            Log::warning("No location ID found for inventory item {$inventoryItemId} (SKU: {$sku})");
            return [
                'success' => false, 
                'error' => 'No location found', 
                'details' => 'The inventory item has no associated location'
            ];
        }

        $updateResponse = $this->makeShopifyRequest(
            'POST',
            "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/set.json",
            [
                'location_id'       => $locationId,
                'inventory_item_id' => $inventoryItemId,
                'available'         => $newQty,
            ]
        );

        if (!$updateResponse->successful()) {
            $errorBody = $updateResponse->body();
            Log::error("Failed to update Shopify stock for SKU {$sku}: " . $updateResponse->status() . " - " . $errorBody);
            return [
                'success' => false, 
                'error' => 'Shopify API rejected update', 
                'details' => "HTTP {$updateResponse->status()}: {$errorBody}"
            ];
        }

        // Validate response
        $responseData = $updateResponse->json();
        if (!isset($responseData['inventory_level'])) {
            Log::error("Invalid Shopify response for SKU {$sku} - missing inventory_level");
            return [
                'success' => false, 
                'error' => 'Invalid Shopify response', 
                'details' => 'Response missing inventory_level field'
            ];
        }

        $actualQty = $responseData['inventory_level']['available'] ?? null;
        Log::info("✓ Successfully updated SKU {$sku} in Shopify to quantity {$newQty} (verified: {$actualQty})");
        return [
            'success' => true, 
            'message' => 'Updated successfully', 
            'verified_qty' => $actualQty
        ];
    }
}
