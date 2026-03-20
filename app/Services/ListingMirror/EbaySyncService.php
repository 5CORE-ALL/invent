<?php

namespace App\Services\ListingMirror;

use App\Models\EbayMetric;
use App\Services\EbayApiService;
use Illuminate\Support\Facades\Log;

class EbaySyncService
{
    protected $ebayService;

    public function __construct()
    {
        $this->ebayService = new EbayApiService();
    }

    /**
     * Sync inventory from Amazon to eBay
     */
    public function syncInventory(string $sku, int $amazonQuantity, string $account = 'ebay1'): array
    {
        try {
            // Get eBay item ID from metrics table based on account
            $ebayMetric = $this->getEbayMetric($sku, $account);
            
            if (!$ebayMetric || !$ebayMetric->item_id) {
                return [
                    'success' => false,
                    'message' => "eBay listing not found for SKU: {$sku} (account: {$account})"
                ];
            }

            $itemId = $ebayMetric->item_id;

            // Get current item details to preserve price
            $itemDetails = $this->ebayService->getItem($itemId);
            if (!$itemDetails) {
                return [
                    'success' => false,
                    'message' => "Failed to fetch eBay item details"
                ];
            }

            // Extract current price from item details
            $currentPrice = $this->extractPriceFromItem($itemDetails);
            
            if ($currentPrice === null) {
                return [
                    'success' => false,
                    'message' => "Could not determine current price from eBay listing"
                ];
            }

            // Update quantity while preserving price
            $result = $this->ebayService->reviseFixedPriceItem(
                $itemId,
                $currentPrice,
                max(0, $amazonQuantity), // Ensure non-negative
                $sku
            );

            if (isset($result['success']) && $result['success']) {
                Log::info('eBay inventory synced successfully', [
                    'sku' => $sku,
                    'quantity' => $amazonQuantity,
                    'account' => $account
                ]);

                return [
                    'success' => true,
                    'message' => "Inventory synced successfully",
                    'data' => $result
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? "Failed to sync inventory"
            ];

        } catch (\Exception $e) {
            Log::error('eBay inventory sync error', [
                'sku' => $sku,
                'account' => $account,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Sync price from Amazon to eBay
     */
    public function syncPrice(string $sku, float $amazonPrice, string $account = 'ebay1'): array
    {
        try {
            // Get eBay item ID from metrics table
            $ebayMetric = $this->getEbayMetric($sku, $account);
            
            if (!$ebayMetric || !$ebayMetric->item_id) {
                return [
                    'success' => false,
                    'message' => "eBay listing not found for SKU: {$sku} (account: {$account})"
                ];
            }

            $itemId = $ebayMetric->item_id;

            // Update price using eBay service
            $result = $this->ebayService->reviseFixedPriceItem(
                $itemId,
                round($amazonPrice, 2),
                null, // Don't change quantity
                $sku
            );

            if (isset($result['success']) && $result['success']) {
                Log::info('eBay price synced successfully', [
                    'sku' => $sku,
                    'price' => $amazonPrice,
                    'account' => $account
                ]);

                return [
                    'success' => true,
                    'message' => "Price synced successfully",
                    'data' => $result
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? "Failed to sync price"
            ];

        } catch (\Exception $e) {
            Log::error('eBay price sync error', [
                'sku' => $sku,
                'account' => $account,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get eBay metric model based on account
     */
    protected function getEbayMetric(string $sku, string $account)
    {
        $modelClass = null;
        
        switch ($account) {
            case 'ebay1':
                $modelClass = EbayMetric::class;
                break;
            case 'ebay2':
                $modelClass = \App\Models\Ebay2Metric::class;
                break;
            case 'ebay3':
                $modelClass = \App\Models\Ebay3Metric::class;
                break;
            default:
                return null;
        }

        return $modelClass::where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->first();
    }

    /**
     * Extract price from eBay item details
     */
    protected function extractPriceFromItem(array $itemDetails): ?float
    {
        try {
            // Try different paths to find price
            $price = null;

            if (isset($itemDetails['Item']['StartPrice'])) {
                $priceStr = $itemDetails['Item']['StartPrice'];
                if (is_array($priceStr) && isset($priceStr['#'])) {
                    $price = (float) $priceStr['#'];
                } else {
                    $price = (float) $priceStr;
                }
            } elseif (isset($itemDetails['Item']['BuyItNowPrice'])) {
                $priceStr = $itemDetails['Item']['BuyItNowPrice'];
                if (is_array($priceStr) && isset($priceStr['#'])) {
                    $price = (float) $priceStr['#'];
                } else {
                    $price = (float) $priceStr;
                }
            }

            return $price > 0 ? $price : null;
        } catch (\Exception $e) {
            Log::error('Error extracting price from eBay item', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
