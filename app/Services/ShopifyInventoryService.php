<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ShopifyInventoryService
{
    private $shopifyDomain;
    private $accessToken;
    private $apiVersion;
    private $locationId;

    public function __construct()
    {
        $this->shopifyDomain = config('services.shopify.store_url');
        $this->accessToken = config('services.shopify.access_token');
        $this->locationId = config('services.shopify.location_id');
        $this->apiVersion = '2024-01';
    }

    /**
     * Push inventory quantity to Shopify
     * 
     * @param string $inventoryItemId - Shopify inventory_item_id
     * @param int $qty - New quantity to set
     * @return array ['success' => bool, 'message' => string]
     */
    public function pushInventoryToShopify($inventoryItemId, $qty)
    {
        try {
            if (!$this->shopifyDomain || !$this->accessToken) {
                throw new Exception('Shopify domain or access token is missing in .env');
            }

            if (!$this->locationId) {
                throw new Exception('SHOPIFY_LOCATION_ID is missing in .env');
            }

            if (!$inventoryItemId) {
                throw new Exception('Inventory Item ID is required');
            }

            $url = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/inventory_levels/set.json";

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'location_id' => $this->locationId,
                'inventory_item_id' => $inventoryItemId,
                'available' => (int) $qty,
            ]);

            if ($response->successful()) {
                Log::info("Shopify inventory updated successfully", [
                    'inventory_item_id' => $inventoryItemId,
                    'qty' => $qty,
                    'location_id' => $this->locationId,
                ]);

                return [
                    'success' => true,
                    'message' => 'Inventory updated successfully',
                    'data' => $response->json(),
                ];
            }

            $errorMessage = $response->json()['errors'] ?? $response->body();
            
            Log::error("Failed to update Shopify inventory", [
                'inventory_item_id' => $inventoryItemId,
                'qty' => $qty,
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'message' => is_array($errorMessage) ? json_encode($errorMessage) : $errorMessage,
            ];

        } catch (Exception $e) {
            Log::error("Exception while pushing to Shopify", [
                'inventory_item_id' => $inventoryItemId,
                'qty' => $qty,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Adjust inventory quantity (relative change)
     * 
     * @param string $inventoryItemId
     * @param int $adjustment - Can be positive or negative
     * @return array
     */
    public function adjustInventory($inventoryItemId, $adjustment)
    {
        try {
            if (!$this->locationId) {
                throw new Exception('SHOPIFY_LOCATION_ID is missing in .env');
            }

            $url = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/inventory_levels/adjust.json";

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'location_id' => $this->locationId,
                'inventory_item_id' => $inventoryItemId,
                'available_adjustment' => (int) $adjustment,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Inventory adjusted successfully',
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json()['errors'] ?? $response->body(),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get inventory item ID from SKU
     * 
     * @param string $sku
     * @return string|null
     */
    public function getInventoryItemIdBySku($sku)
    {
        try {
            $url = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/products.json";
            
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->get($url, [
                'limit' => 250,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $products = $response->json()['products'] ?? [];

            foreach ($products as $product) {
                foreach ($product['variants'] as $variant) {
                    if ($variant['sku'] === $sku) {
                        return $variant['inventory_item_id'];
                    }
                }
            }

            return null;

        } catch (Exception $e) {
            Log::error("Error getting inventory_item_id for SKU: {$sku}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get current inventory level for an inventory item
     * 
     * @param string $inventoryItemId
     * @return array|null
     */
    public function getInventoryLevel($inventoryItemId)
    {
        try {
            $url = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/inventory_levels.json";
            
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->get($url, [
                'inventory_item_ids' => $inventoryItemId,
            ]);

            if ($response->successful()) {
                $levels = $response->json()['inventory_levels'] ?? [];
                return !empty($levels) ? $levels[0] : null;
            }

            return null;

        } catch (Exception $e) {
            Log::error("Error getting inventory level", [
                'inventory_item_id' => $inventoryItemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
