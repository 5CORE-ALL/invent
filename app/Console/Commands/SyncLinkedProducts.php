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
                // Use X-Shopify-Access-Token header (standard Shopify API authentication)
                $request = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->shopifyPassword,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->timeout(60);
                
                if ($method === 'GET') {
                    // GET requests: data as query parameters
                    $response = $request->get($endpoint, $data);
                } else {
                    // POST requests: data as JSON body
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
            $totalPieces = 0; // Count pieces from ALL SKUs (1pcs + larger pack sizes)
            $totalPiecesFromOnePcs = 0; // ONLY count pieces from 1pcs SKUs for the "has stock" check
            $totalL30 = 0;
            $hasOnePcsWithStock = false; // Check if any 1pcs SKU has stock > 0

            foreach ($products as $product) {
                $sku = $normalizeSku($product->sku);
                $shopifySkuRecord = $shopifyData[$sku] ?? null;
                
                // Use database stock field directly (already synced from Shopify)
                // Try 'inv' first, then 'available_to_sell' as fallback
                if ($shopifySkuRecord) {
                    // Try inv field first
                    if (isset($shopifySkuRecord->inv) && $shopifySkuRecord->inv !== null && $shopifySkuRecord->inv > 0) {
                        $availableQty = (int)$shopifySkuRecord->inv;
                    } elseif (isset($shopifySkuRecord->available_to_sell) && $shopifySkuRecord->available_to_sell !== null) {
                        $availableQty = (int)$shopifySkuRecord->available_to_sell;
                    } else {
                        $availableQty = 0;
                    }
                    
                    $l30 = (int)$shopifySkuRecord->quantity;
                } else {
                    $availableQty = 0;
                    $l30 = 0;
                    $this->warn("  SKU {$sku}: Not found in shopify_skus table");
                }

                // detect pack size
                if (preg_match('/(\d+)\s*P(?:c|cs)/i', $product->sku, $matches)) {
                    $packSize = (int)$matches[1];
                } else {
                    $packSize = 1;
                }

                $product->calc_available_qty = $availableQty;
                $product->calc_l30 = $l30;
                $product->dil = $availableQty > 0 ? round(($l30 / $availableQty) * 100, 2) : 0;

                // Calculate pieces from this SKU (packs × pack size)
                $piecesFromThisSku = $availableQty * $packSize;
                
                $packData[$sku] = [
                    'product'  => $product,
                    'packSize' => $packSize,
                    'l30'      => $l30,
                ];

                // Check if this is a 1pcs SKU with stock > 0 (required condition)
                if ($packSize === 1 && $availableQty > 0) {
                    $hasOnePcsWithStock = true;
                }

                // Count pieces from ALL SKUs for total pool
                $totalPieces += $piecesFromThisSku;
                
                // Count pieces from ONLY 1pcs SKUs for the "greater than" check
                if ($packSize === 1) {
                    $totalPiecesFromOnePcs += $piecesFromThisSku;
                    $this->info("1pcs SKU {$sku}: Qty={$availableQty} packs, Pieces={$piecesFromThisSku} (contributing to pool)");
                } else {
                    $this->info("{$packSize}pcs SKU {$sku}: Qty={$availableQty} packs, Pieces={$piecesFromThisSku} (contributing to pool)");
                }

                $totalL30 += $l30;
            }

            // CRITICAL: Only split if at least one 1pcs SKU has stock > 0
            // If all 1pcs SKUs have 0 stock, skip this group entirely (don't touch anything)
            if (!$hasOnePcsWithStock) {
                $this->warn("Group {$groupId} skipped: No 1pcs SKUs with stock > 0. All SKUs remain unchanged.");
                continue;
            }

            if ($totalPieces == 0) {
                $this->warn("Group {$groupId} has no stock, skipping.");
                continue;
            }

            // Check if any larger pack size SKU has MORE stock (in PACKS, not pieces) than total from 1pcs SKUs (in pieces)
            // For 2pcs/3pcs/4pcs SKUs, compare pack count with 1pcs piece count
            $skipDueToLargerStock = false;
            $largerSkuDetails = [];
            foreach ($packData as $sku => $data) {
                if ($data['packSize'] > 1) {
                    $availableQty = $data['product']->calc_available_qty; // This is pack count for larger pack sizes
                    // Compare pack count of larger SKU with piece count from 1pcs SKUs
                    if ($availableQty > $totalPiecesFromOnePcs) {
                        $skipDueToLargerStock = true;
                        $largerSkuDetails[] = "{$sku} ({$data['packSize']}pcs): {$availableQty} packs > {$totalPiecesFromOnePcs} pieces from 1pcs";
                    }
                }
            }

            if ($skipDueToLargerStock) {
                $this->warn("Group {$groupId} skipped: Some larger pack size SKUs have MORE stock (packs) than 1pcs SKUs (pieces):");
                foreach ($largerSkuDetails as $detail) {
                    $this->warn("  - {$detail}");
                }
                $this->warn("All SKUs remain unchanged.");
                continue;
            }

            $numSkus = count($packData);
            $this->info("=== TOTAL: {$totalPieces} pieces from ALL SKUs ===");
            $this->info("Distributing equally among all {$numSkus} SKUs");

            // Calculate equal distribution in pieces
            $piecesPerSku = intdiv($totalPieces, $numSkus);
            $leftoverPieces = $totalPieces % $numSkus;

            $this->info("Distributing equally: {$piecesPerSku} pieces per SKU (leftover: {$leftoverPieces})");

            // First pass: allocate base pieces to each SKU (converting to packs)
            $allocations = [];
            $piecesAllocatedPerSku = [];
            foreach ($packData as $sku => $data) {
                // Allocate base pieces per SKU
                $packs = intdiv($piecesPerSku, $data['packSize']);
                $allocations[$sku] = $packs;
                $piecesAllocated = $packs * $data['packSize'];
                $piecesAllocatedPerSku[$sku] = $piecesAllocated;
                $this->line("  {$sku}: {$piecesPerSku} pieces ÷ {$data['packSize']} = {$packs} packs ({$piecesAllocated} pieces)");
            }

            // Calculate total leftover after first pass
            $totalAssigned = array_sum($piecesAllocatedPerSku);
            $totalLeftover = $totalPieces - $totalAssigned;
            $this->line("Total assigned: {$totalAssigned} pieces, Leftover: {$totalLeftover} pieces");
            
            // Distribute leftover pieces more intelligently to balance the distribution
            if ($totalLeftover > 0) {
                // Sort SKUs by pack size (smallest first) to prioritize them for leftover pieces
                $sortedSkus = collect($packData)->sortBy('packSize')->keys()->toArray();
                
                $remainingLeftover = $totalLeftover;
                $this->line("Distributing {$remainingLeftover} leftover pieces to balance allocation...");
                
                foreach ($sortedSkus as $sku) {
                    if ($remainingLeftover <= 0) break;
                    
                    $data = $packData[$sku];
                    $packSize = $data['packSize'];
                    
                    // Try to add one pack if it helps balance
                    if ($remainingLeftover >= $packSize) {
                        $allocations[$sku] += 1;
                        $piecesAllocatedPerSku[$sku] += $packSize;
                        $remainingLeftover -= $packSize;
                        $this->line("  Added 1 pack to {$sku} ({$packSize} pieces), remaining leftover: {$remainingLeftover}");
                    }
                }
                
                // If there's still leftover (can't make full packs), add to smallest pack size
                if ($remainingLeftover > 0) {
                    $smallestSku = $sortedSkus[0];
                    $smallestPackSize = $packData[$smallestSku]['packSize'];
                    $allocations[$smallestSku] += 1;
                    $piecesAllocatedPerSku[$smallestSku] += $smallestPackSize;
                    $waste = $smallestPackSize - $remainingLeftover;
                    $this->line("  Added 1 more pack to smallest SKU {$smallestSku} to accommodate remaining {$remainingLeftover} pieces (waste: {$waste} pieces)");
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
                $shopifySkuRecord = $shopifyData[$sku] ?? null;
                $variantId = $shopifySkuRecord->variant_id ?? null;
                $shopifyResult = $this->updateShopifyQty($sku, $newQty, $normalizeSku, $variantId);

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

    private function updateShopifyQty($sku, $newQty, $normalizeSku, $variantId = null)
    {
        $inventoryItemId = null;
        $apiDisabled = false;

        // Use GraphQL API to get inventory_item_id from variant_id (REST API is disabled)
        if ($variantId) {
            try {
                $graphqlQuery = [
                    'query' => '
                        query getVariant($id: ID!) {
                            productVariant(id: $id) {
                                id
                                inventoryItem {
                                    id
                                }
                            }
                        }
                    ',
                    'variables' => [
                        'id' => 'gid://shopify/ProductVariant/' . $variantId
                    ]
                ];

                $graphqlResponse = $this->makeShopifyRequest(
                    'POST',
                    "https://{$this->shopifyDomain}/admin/api/2025-01/graphql.json",
                    $graphqlQuery
                );

                if ($graphqlResponse->successful()) {
                    $graphqlData = $graphqlResponse->json();
                    if (isset($graphqlData['data']['productVariant']['inventoryItem']['id'])) {
                        // Extract numeric ID from GraphQL ID format: "gid://shopify/InventoryItem/123456"
                        $inventoryItemGid = $graphqlData['data']['productVariant']['inventoryItem']['id'];
                        $inventoryItemId = str_replace('gid://shopify/InventoryItem/', '', $inventoryItemGid);
                        $this->line("  Found inventory_item_id via GraphQL for SKU: {$sku} (ID: {$inventoryItemId})");
                    } else {
                        $errors = $graphqlData['errors'] ?? [];
                        $this->warn("  GraphQL query returned no inventory_item_id for SKU {$sku}");
                        if (!empty($errors)) {
                            $this->warn("    GraphQL errors: " . json_encode($errors));
                        }
                    }
                } else {
                    $errorBody = $graphqlResponse->body();
                    $statusCode = $graphqlResponse->status();
                    
                    // Check if API access is disabled
                    if ($statusCode === 403 && strpos($errorBody, 'API Access has been disabled') !== false) {
                        $apiDisabled = true;
                        $this->error("  ⚠ Shopify API Access is DISABLED for this store");
                        $this->error("    This is a Shopify configuration issue, not a code issue.");
                        $this->error("    To fix:");
                        $this->error("    1. Go to Shopify Admin > Settings > Apps and sales channels");
                        $this->error("    2. Find your API app/access token");
                        $this->error("    3. Re-enable API access");
                        $this->error("    OR contact Shopify support to re-enable API access for your store.");
                    } else {
                        $this->error("  Failed GraphQL query for variant_id {$variantId} (SKU: {$sku})");
                        $this->error("    Status: {$statusCode}");
                        $this->error("    Response: " . substr($errorBody, 0, 200));
                    }
                    
                    Log::error("Shopify GraphQL API error for SKU {$sku}", [
                        'status' => $statusCode,
                        'response' => $errorBody,
                        'variant_id' => $variantId
                    ]);
                }
            } catch (\Exception $e) {
                $this->warn("  Exception in GraphQL query for variant_id {$variantId} (SKU: {$sku}): " . $e->getMessage());
            }
        }

        if (!$inventoryItemId) {
            if (!$variantId) {
                $this->error("✗ SKU '{$sku}' missing variant_id in database");
                Log::warning("variant_id not found in shopify_skus table for SKU: {$sku}");
                return [
                    'success' => false, 
                    'error' => 'variant_id missing', 
                    'details' => "SKU '{$sku}' does not have variant_id in shopify_skus table. Please sync Shopify data to populate variant_id."
                ];
            } else {
                $this->error("✗ Failed to get inventory_item_id for SKU '{$sku}' using variant_id {$variantId}");
                Log::warning("Failed to get inventory_item_id for SKU: {$sku} with variant_id: {$variantId}");
                
                $errorDetails = "Could not retrieve inventory_item_id from Shopify GraphQL API.";
                if ($apiDisabled) {
                    $errorDetails = "Shopify API Access is DISABLED. Please enable API access in Shopify Admin > Settings > Apps and sales channels > API access.";
                }
                
                return [
                    'success' => false, 
                    'error' => 'Failed to get inventory_item_id', 
                    'details' => $errorDetails
                ];
            }
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
