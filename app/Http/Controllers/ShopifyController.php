<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class ShopifyController extends Controller
{
    private $shopifyDomain;
    private $accessToken;
    private $apiVersion = '2025-01';

    public function __construct()
    {
        $this->shopifyDomain = env('SHOPIFY_STORE_URL');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
    }

   public function getProducts()
    {
        if (!$this->shopifyDomain || !$this->accessToken) {
            return ['error' => 'Shopify domain or access token is missing in .env'];
        }

        $client = new Client();
        $products = [];
        $url = "https://{$this->shopifyDomain}/admin/api/2025-01/products.json?limit=250";
        $nextPageUrl = $url;

        try {
            while ($nextPageUrl) {
                $response = $client->request('GET', $nextPageUrl, [
                    'headers' => [
                        'X-Shopify-Access-Token' => $this->accessToken,
                        'Accept' => 'application/json',
                    ],
                    'http_errors' => false,
                ]);

                $body = json_decode($response->getBody(), true);
                if (isset($body['products'])) {
                    $products = array_merge($products, $body['products']);
                }

                // Parse Link header for next page
                $linkHeader = $response->getHeader('Link');
                $nextPageUrl = null;
                if ($linkHeader) {
                    $links = explode(',', $linkHeader[0]);
                    foreach ($links as $link) {
                        if (strpos($link, 'rel="next"') !== false) {
                            preg_match('/<([^>]+)>/', $link, $matches);
                            if (isset($matches[1])) {
                                $nextPageUrl = $matches[1];
                            }
                        }
                    }
                }
            }

            return ['products' => $products];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function shopifyView(Request $request, $first, $second)
    {
        if ($first === 'ai' || $first === 'ai-admin') {
            abort(404, 'Route not found');
        }

        // Don't treat js/css as Blade view names (e.g. /js/app.js, /css/app.css would become view 'js.app.js' which doesn't exist)
        if ($first === 'js' || $first === 'css') {
            $subPath = $first . '/' . $second;
            $path = public_path($subPath);
            $realPath = $path ? realpath($path) : null;
            $publicReal = realpath(public_path());
            if ($realPath && is_file($realPath) && $publicReal && str_starts_with($realPath, $publicReal)) {
                return response()->file($realPath);
            }
            abort(404, 'Asset not found');
        }

  
        if ($first == "assets") {
            return redirect('home');
        }

        $products = $this->getProducts();

        $productList = isset($products['products']) ?
            array_map(function ($product) {
                return [
                    'id' => $product['id'],
                    'product' => $product['title'],
                    'status' => $product['status'],
                    'type' => $product['product_type'],
                    'vendor' => $product['vendor'],
                    'inventory' => isset($product['variants'][0]['inventory_quantity']) ? $product['variants'][0]['inventory_quantity'] : null,
                    'sku' => isset($product['variants'][0]['sku']) ? $product['variants'][0]['sku'] : null,
                    'image' => isset($product['image']['src']) ? $product['image']['src'] : null,
                ];
            }, $products['products']) : [];

        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $viewName = $first . '.' . $second;
        if ($first === 'js' || $first === 'css' || str_ends_with($second ?? '', '.js') || str_ends_with($second ?? '', '.css')) {
            abort(404, 'Asset not found');
        }
        if (!view()->exists($viewName)) {
            abort(404, 'Page not found');
        }

        return view($viewName, [
            'mode' => $mode,
            'demo' => $demo,
            'products' => $productList
        ]);
    }
    

    public function updateToAdjust(Request $request)
    {
        $sku = $request->sku;
        $toAdjust = (int) $request->to_adjust;

        $product = $this->getProductBySKU($sku);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found for SKU']);
        }

        $inventoryItemId = $product['variants'][0]['inventory_item_id'];

        // Step 2: Get location_id from inventory_levels.json
        $locationId = $this->getLocationIdForInventoryItem($inventoryItemId);
        if (!$locationId) {
            return response()->json(['success' => false, 'message' => 'Location ID not found']);
        }

        $adjusted = $this->adjustInventory($inventoryItemId, $locationId, $toAdjust);
        if (!$adjusted) {
            return response()->json(['success' => false, 'message' => 'Failed to adjust inventory']);
        }

        return response()->json(['success' => true, 'message' => 'Inventory adjusted successfully']);
    }

    private function getProductBySKU($sku)
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json'
        ])->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json");

        if ($response->failed()) return null;

        foreach ($response->json()['products'] as $product) {
            foreach ($product['variants'] as $variant) {
                if ($variant['sku'] === $sku) {
                    return $product;
                }
            }
        }

        return null;
    }

    
    private function getLocationIdForInventoryItem($inventoryItemId)
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json'
        ])->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
            'inventory_item_id' => $inventoryItemId
        ]);

        if ($response->failed()) return null;

        $levels = $response->json()['inventory_levels'];
        return $levels[0]['location_id'] ?? null;
    }

    

    private function adjustInventory($inventoryItemId, $locationId, $adjustment)
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json'
        ])->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'available_adjustment' => $adjustment
        ]);

        return $response->successful();
    }



}

