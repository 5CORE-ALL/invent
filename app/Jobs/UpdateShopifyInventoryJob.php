<?php

namespace App\Jobs;

use App\Models\ShopifyInventoryLog;
use App\Models\ShopifySku;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateShopifyInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 120;
    public $backoff = [60, 120, 300, 600, 1200]; // Exponential backoff: 1min, 2min, 5min, 10min, 20min

    protected $logId;
    protected $sku;
    protected $adjustment;
    protected $shopifyDomain;
    protected $shopifyApiKey;
    protected $shopifyPassword;

    public function __construct(int $logId, string $sku, int $adjustment)
    {
        $this->logId = $logId;
        $this->sku = $sku;
        $this->adjustment = $adjustment;
        $this->shopifyDomain = config('services.shopify.store_url');
        $this->shopifyApiKey = config('services.shopify.api_key');
        $this->shopifyPassword = config('services.shopify.password');
    }

    public function handle(): void
    {
        $log = ShopifyInventoryLog::find($this->logId);
        
        if (!$log) {
            Log::error('ShopifyInventoryLog not found', ['log_id' => $this->logId]);
            return;
        }

        // Check if already succeeded
        if ($log->status === 'success') {
            Log::info('Shopify inventory already updated successfully', ['sku' => $this->sku]);
            return;
        }

        // Check if max attempts reached
        if (!$log->shouldRetry()) {
            Log::warning('Max retry attempts reached for Shopify inventory update', [
                'sku' => $this->sku,
                'attempts' => $log->attempt
            ]);
            return;
        }

        $log->incrementAttempt();

        try {
            // Normalize SKU
            $normalizedSku = strtoupper(preg_replace('/\s+/u', ' ', trim($this->sku)));
            
            $inventoryItemId = $log->inventory_item_id;
            $locationId = $log->location_id;

            // Step 1: Get inventory_item_id if not already stored
            if (!$inventoryItemId) {
                $inventoryItemId = $this->findInventoryItemId($normalizedSku);
                
                if (!$inventoryItemId) {
                    throw new \Exception("SKU not found in Shopify: {$normalizedSku}");
                }
                
                $log->update(['inventory_item_id' => $inventoryItemId]);
            }

            // Step 2: Get location_id if not already stored
            if (!$locationId) {
                $locationId = $this->getLocationId($inventoryItemId);
                
                if (!$locationId) {
                    throw new \Exception("Location not found for inventory item: {$inventoryItemId}");
                }
                
                $log->update(['location_id' => $locationId]);
            }

            // Step 3: Update inventory
            $this->adjustInventory($inventoryItemId, $locationId, $this->adjustment);

            // Mark as successful
            $log->markSuccess();

            Log::info('Shopify inventory updated successfully', [
                'sku' => $this->sku,
                'adjustment' => $this->adjustment,
                'attempt' => $log->attempt
            ]);

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            
            Log::error('Failed to update Shopify inventory', [
                'sku' => $this->sku,
                'attempt' => $log->attempt,
                'error' => $errorMsg,
                'trace' => $e->getTraceAsString()
            ]);

            // Check if this was the last attempt
            if ($log->attempt >= $log->max_attempts) {
                $log->markFailed($errorMsg);
            } else {
                // Will retry automatically via queue
                $log->update(['error_message' => $errorMsg]);
                throw $e; // Re-throw to trigger queue retry
            }
        }
    }

    protected function findInventoryItemId(string $normalizedSku): ?string
    {
        // Try local database first
        $shopifyRow = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [$normalizedSku])->first();
        
        if ($shopifyRow && $shopifyRow->variant_id) {
            try {
                $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                    ->timeout(45)
                    ->get("https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$shopifyRow->variant_id}.json");

                if ($response->successful()) {
                    $inventoryItemId = $response->json('variant.inventory_item_id');
                    if ($inventoryItemId) {
                        return (string) $inventoryItemId;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Variant lookup failed, falling back to product search', [
                    'variant_id' => $shopifyRow->variant_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Fallback: Search through products
        return $this->searchProductsForSku($normalizedSku);
    }

    protected function searchProductsForSku(string $normalizedSku): ?string
    {
        $pageInfo = null;
        $maxPages = 50;
        $currentPage = 0;

        do {
            $currentPage++;
            
            if ($currentPage > $maxPages) {
                Log::warning('Max pages reached during product search', [
                    'sku' => $normalizedSku,
                    'max_pages' => $maxPages
                ]);
                break;
            }

            $queryParams = ['limit' => 250, 'fields' => 'id,variants'];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->timeout(45)
                ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

            // Handle rate limiting
            if ($response->status() === 429) {
                $retryAfter = $response->header('Retry-After') ?? 2;
                Log::info('Rate limited, waiting before retry', ['retry_after' => $retryAfter]);
                sleep((int)$retryAfter + 1);
                continue;
            }

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch products: HTTP {$response->status()}");
            }

            $products = $response->json('products') ?? [];

            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $variant) {
                    $variantSku = strtoupper(preg_replace('/\s+/u', ' ', trim($variant['sku'] ?? '')));
                    
                    if ($variantSku === $normalizedSku) {
                        return (string) ($variant['inventory_item_id'] ?? '');
                    }
                }
            }

            // Handle pagination
            $linkHeader = $response->header('Link');
            $pageInfo = null;
            if ($linkHeader && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>;\s*rel="next"/', $linkHeader, $matches)) {
                $pageInfo = $matches[1];
            }

        } while ($pageInfo);

        return null;
    }

    protected function getLocationId(string $inventoryItemId): ?string
    {
        $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
            ->timeout(30)
            ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
                'inventory_item_ids' => $inventoryItemId
            ]);

        // Handle rate limiting
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After') ?? 2;
            sleep((int)$retryAfter + 1);
            return $this->getLocationId($inventoryItemId); // Retry
        }

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch inventory levels: HTTP {$response->status()}");
        }

        $levels = $response->json('inventory_levels') ?? [];
        return $levels[0]['location_id'] ?? null;
    }

    protected function adjustInventory(string $inventoryItemId, string $locationId, int $adjustment): void
    {
        $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
            ->timeout(45)
            ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'available_adjustment' => $adjustment,
            ]);

        // Handle rate limiting
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After') ?? 2;
            sleep((int)$retryAfter + 1);
            $this->adjustInventory($inventoryItemId, $locationId, $adjustment); // Retry
            return;
        }

        if (!$response->successful()) {
            throw new \Exception("Failed to adjust inventory: HTTP {$response->status()} - {$response->body()}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        $log = ShopifyInventoryLog::find($this->logId);
        
        if ($log) {
            $log->markFailed($exception->getMessage());
        }

        Log::error('UpdateShopifyInventoryJob failed permanently', [
            'log_id' => $this->logId,
            'sku' => $this->sku,
            'error' => $exception->getMessage()
        ]);
    }
}
