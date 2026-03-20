<?php

namespace App\Services\ListingMirror;

use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifySyncService
{
    protected $shopifyStoreUrl;
    protected $shopifyAccessToken;
    protected $amazonService;

    public function __construct()
    {
        $this->shopifyStoreUrl = config('services.shopify.store_url');
        $this->shopifyAccessToken = config('services.shopify.password');
        $this->amazonService = new AmazonSpApiService();
    }

    /**
     * Sync inventory from Amazon to Shopify
     */
    public function syncInventory(string $sku, int $amazonQuantity): array
    {
        try {
            // Find Shopify variant by SKU
            $shopifySku = ShopifySku::where('sku', $sku)->first();
            
            if (!$shopifySku || !$shopifySku->variant_id) {
                return [
                    'success' => false,
                    'message' => "Shopify listing not found for SKU: {$sku}"
                ];
            }

            $variantId = $shopifySku->variant_id;
            
            // Get inventory item ID
            $inventoryItemId = $this->getInventoryItemId($variantId);
            if (!$inventoryItemId) {
                return [
                    'success' => false,
                    'message' => "Inventory item ID not found for variant: {$variantId}"
                ];
            }

            // Get location ID
            $locationId = $this->getLocationId($inventoryItemId);
            if (!$locationId) {
                return [
                    'success' => false,
                    'message' => "Location ID not found for inventory item: {$inventoryItemId}"
                ];
            }

            // Set inventory level (not adjust, but set absolute value)
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ])->post("https://{$this->shopifyStoreUrl}/admin/api/2025-01/inventory_levels/set.json", [
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'available' => max(0, $amazonQuantity), // Ensure non-negative
            ]);

            if ($response->successful()) {
                Log::info('Shopify inventory synced successfully', [
                    'sku' => $sku,
                    'quantity' => $amazonQuantity
                ]);

                return [
                    'success' => true,
                    'message' => "Inventory synced successfully",
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => "Failed to sync inventory: " . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Shopify inventory sync error', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Sync price from Amazon to Shopify
     */
    public function syncPrice(string $sku, float $amazonPrice): array
    {
        try {
            // Find Shopify variant by SKU
            $shopifySku = ShopifySku::where('sku', $sku)->first();
            
            if (!$shopifySku || !$shopifySku->variant_id) {
                return [
                    'success' => false,
                    'message' => "Shopify listing not found for SKU: {$sku}"
                ];
            }

            $variantId = $shopifySku->variant_id;

            // Update variant price
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ])->put("https://{$this->shopifyStoreUrl}/admin/api/2025-01/variants/{$variantId}.json", [
                'variant' => [
                    'id' => $variantId,
                    'price' => number_format($amazonPrice, 2, '.', '')
                ]
            ]);

            if ($response->successful()) {
                Log::info('Shopify price synced successfully', [
                    'sku' => $sku,
                    'price' => $amazonPrice
                ]);

                return [
                    'success' => true,
                    'message' => "Price synced successfully",
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => "Failed to sync price: " . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Shopify price sync error', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get inventory item ID from variant ID
     */
    protected function getInventoryItemId(int $variantId): ?int
    {
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ])->get("https://{$this->shopifyStoreUrl}/admin/api/2025-01/variants/{$variantId}.json");

            if ($response->successful()) {
                $data = $response->json();
                return $data['variant']['inventory_item_id'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting inventory item ID', [
                'variant_id' => $variantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get location ID for inventory item
     */
    protected function getLocationId(int $inventoryItemId): ?int
    {
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ])->get("https://{$this->shopifyStoreUrl}/admin/api/2025-01/inventory_items/{$inventoryItemId}/inventory_levels.json");

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['inventory_levels'])) {
                    return $data['inventory_levels'][0]['location_id'] ?? null;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting location ID', [
                'inventory_item_id' => $inventoryItemId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
