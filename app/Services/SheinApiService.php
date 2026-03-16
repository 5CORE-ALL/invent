<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use ZipArchive;
use Illuminate\Support\Str;
use App\Models\ProductStockMapping;
use App\Models\SheinMetric;
class SheinApiService
{

      protected $appId;
    protected $appSecret;
    protected $baseUrl = 'https://openapi.sheincorp.com'; // or sandbox: openapi-test01.sheincorp.cn

    public function __construct()
    {
        $this->appId     = config('services.shein.app_id');
        $this->appSecret = config('services.shein.app_secret');
    }

  function generateSheinSignature($path, $timestamp, $randomKey)
    {
        $openKeyId = config('services.shein.open_key_id');
        $secretKey = config('services.shein.secret_key');

        $value = $openKeyId . "&" . $timestamp . "&" . $path;

        $key = $secretKey . $randomKey;

        $hmacResult = hash_hmac('sha256', $value, $key, false); // false means return hexadecimal

        $base64Signature = base64_encode($hmacResult);

        $finalSignature = $randomKey . $base64Signature;

        return $finalSignature;
    }


    /**
     * Fetch product by SPU name
     */
    public function fetchBySpu(string $spuName)
    {
        $endpoint = "/open-api/openapi-business-backend/product/full-detail";
        $timestamp = round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
        $url = $this->baseUrl . $endpoint;

        $payload = [
            "skuCodes" => [$spuName]
        ];

        $response = Http::withoutVerifying()->withHeaders([
            "Language" => "en-us",
            "x-lt-openKeyId" => config('services.shein.open_key_id'),
            "x-lt-timestamp" => $timestamp,
            "x-lt-signature" => $signature,
            "Content-Type" => "application/json",
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error("Shein API Error: " . $response->body());
            throw new \Exception("Shein API Error: " . $response->body());
        }

        $data = $response->json();
        return $data["info"] ?? [];
    }

     public function listAllProducts()
    {
        $endpoint  = "/open-api/openapi-business-backend/product/query";
        $pageSize  = 400;
        $allProducts = [];

        // Loop max 1000 pages (safe upper bound)
        for ($pageNum = 1; $pageNum <= 1000; $pageNum++) {

            $timestamp = round(microtime(true) * 1000);
            $random    = Str::random(5);
            $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);

            $url = $this->baseUrl . $endpoint;

            $payload = [
                "pageNum"         => $pageNum,
                "pageSize"        => $pageSize,
                "insertTimeEnd"   => "",
                "insertTimeStart" => "",
                "updateTimeEnd"   => "",
                "updateTimeStart" => "",
            ];
            $request= Http::withoutVerifying()->withHeaders([
                "Language"       => "en-us",
                "x-lt-openKeyId" => config('services.shein.open_key_id'),
                "x-lt-timestamp" => $timestamp,
                "x-lt-signature" => $signature,
                "Content-Type"   => "application/json",
            ]);
            if (config('filesystems.default') === 'local') {$request = $request->withoutVerifying();}
            $response =$request->post($url, $payload);

            if (!$response->successful()) {
                throw new \Exception("Shein API Error: " . $response->body());
            }

            $data = $response->json();
            $products = $data["info"]["data"] ?? [];
            // dd($products);
            // If no products returned → stop looping
            if (empty($products)) {
                break;
            }

            $allProducts = array_merge($allProducts, $products);
        }
        
        $spuNames = array_map(function ($item) {
    return $item['skuCodeList'][0] ?? null;
}, $allProducts);

$spuNames = array_filter($spuNames); // remove nulls if any

    $result = $this->getStock($spuNames);
    
    $createdCount = 0;
    $updatedCount = 0;
    
    foreach($result as $item){
        $sku = $item['sku'] ?? null;
        
        if (!$sku) {
            Log::warning('Missing SKU in Shein inventory data', $item);
            continue;
        }
        
        // Prepare data for shein_metrics table
        $metricData = [
            'sku' => $sku,
            'inventory' => $item['quantity'] ?? 0,
            'price' => $item['price'] ?? null,
            'retail_price' => $item['retail_price'] ?? null,
            'views' => $item['views'] ?? 0,
            'rating' => $item['rating'] ?? null,
            'review_count' => $item['review_count'] ?? 0,
            'last_synced_at' => now(),
        ];
        
        // Add raw data if available
        if (isset($item['raw_data'])) {
            $metricData['raw_data'] = $item['raw_data'];
        }
        
        // Add additional fields if available
        if (isset($item['product_name'])) {
            $metricData['product_name'] = $item['product_name'];
        }
        
        if (isset($item['spu_name'])) {
            $metricData['spu_name'] = $item['spu_name'];
        }
        
        if (isset($item['status'])) {
            $metricData['status'] = $item['status'];
        }
        
        if (isset($item['description'])) {
            $metricData['description'] = $item['description'];
        }
        
        if (isset($item['image_url'])) {
            $metricData['image_url'] = $item['image_url'];
        }
        
        if (isset($item['category'])) {
            $metricData['category'] = $item['category'];
        }
        
        // Update or create record in shein_metrics table
        $metric = SheinMetric::updateOrCreate(
            ['sku' => $sku],
            $metricData
        );
        
        if ($metric->wasRecentlyCreated) {
            $createdCount++;
        } else {
            $updatedCount++;
        }
    }
    
    Log::info('Shein Data Sync Complete', [
        'total_items' => count($result),
        'created_records' => $createdCount,
        'updated_records' => $updatedCount
    ]);
    
    return $result;
    }


public function getStock(array $skuCodes)
{
    $endpoint = "/open-api/openapi-business-backend/product/full-detail";
    $chunkSize = 100;
    $allStock = [];

    // Split SKU codes into chunks of 100
    $chunks = array_chunk($skuCodes, $chunkSize);

    foreach ($chunks as $chunk) {
        $timestamp = round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
        $url = $this->baseUrl . $endpoint;

        $payload = [
            "skuCodes" => $chunk
        ];

        $response = Http::withoutVerifying()->withHeaders([
            "Language" => "en-us",
            "x-lt-openKeyId" => config('services.shein.open_key_id'),
            "x-lt-timestamp" => $timestamp,
            "x-lt-signature" => $signature,
            "Content-Type" => "application/json",
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error("Shein API Error: " . $response->body());
            throw new \Exception("Shein API Error: " . $response->body());
        }

        $data = $response->json();

        if (isset($data["info"]) && is_array($data["info"])) {
            foreach ($data["info"] as $item) {
                $skuCode = $item['sellerSku'] ?? null;
                $quantity = $item['goodsInventory']['inventoryQuantity'] ?? null;
                
                // Extract price data from currentPrices array (actual API structure)
                $price = null;
                $retailPrice = null;
                $costPrice = null;
                
                if (isset($item['currentPrices']) && is_array($item['currentPrices']) && count($item['currentPrices']) > 0) {
                    $priceData = $item['currentPrices'][0];
                    $price = $priceData['salePrice'] ?? $priceData['specialPrice'] ?? null;
                    $retailPrice = $priceData['shopPrice'] ?? $priceData['suggestedRetailPrice'] ?? null;
                }
                
                // Extract views/visits data
                $views = $item['viewCount'] ?? $item['visits'] ?? $item['pageViews'] ?? null;
                
                // Extract rating data
                $rating = $item['rating'] ?? $item['averageRating'] ?? $item['starRating'] ?? null;
                $reviewCount = $item['reviewCount'] ?? $item['ratingCount'] ?? null;
                
                // Extract additional product info (matching actual API structure)
                $productName = $item['productName'] ?? null;
                $spuName = $item['spuName'] ?? null;
                $categoryName = $item['categoryName'] ?? null;
                $description = $item['productDesc'] ?? null;
                
                // Extract main image from imageList
                $imageUrl = null;
                if (isset($item['imageList']) && is_array($item['imageList'])) {
                    foreach ($item['imageList'] as $image) {
                        if (isset($image['imageType']) && $image['imageType'] === 'MAIN') {
                            $imageUrl = $image['imageUrl'] ?? null;
                            break;
                        }
                    }
                    // If no MAIN image, get first image
                    if (!$imageUrl && count($item['imageList']) > 0) {
                        $imageUrl = $item['imageList'][0]['imageUrl'] ?? null;
                    }
                }
                
                // Extract status from shelfDetails
                $status = null;
                if (isset($item['shelfDetails']) && is_array($item['shelfDetails']) && count($item['shelfDetails']) > 0) {
                    $shelfDetail = $item['shelfDetails'][0];
                    $isOnShelf = $shelfDetail['isOnShelf'] ?? false;
                    $status = $isOnShelf ? 'active' : 'inactive';
                }
                
                // Determine status from inventory if not set
                if (!$status) {
                    if ($quantity === 0) {
                        $status = 'out_of_stock';
                    } elseif ($quantity > 0 && $quantity < 10) {
                        $status = 'low_stock';
                    } else {
                        $status = 'active';
                    }
                }

                if ($skuCode !== null) {
                    $stockData = [
                        'sku' => $skuCode,
                        'quantity' => $quantity !== null ? (int) $quantity : 0,
                    ];
                    
                    // Add price if available
                    if ($price !== null) {
                        $stockData['price'] = (float) $price;
                    }
                    
                    if ($retailPrice !== null) {
                        $stockData['retail_price'] = (float) $retailPrice;
                    }
                    
                    if ($costPrice !== null) {
                        $stockData['cost_price'] = (float) $costPrice;
                    }
                    
                    // Add views if available
                    if ($views !== null) {
                        $stockData['views'] = (int) $views;
                    }
                    
                    // Add rating if available
                    if ($rating !== null) {
                        $stockData['rating'] = (float) $rating;
                    }
                    
                    if ($reviewCount !== null) {
                        $stockData['review_count'] = (int) $reviewCount;
                    }
                    
                    // Add product info
                    if ($productName !== null) {
                        $stockData['product_name'] = $productName;
                    }
                    
                    if ($spuName !== null) {
                        $stockData['spu_name'] = $spuName;
                    }
                    
                    if ($status !== null) {
                        $stockData['status'] = $status;
                    }
                    
                    if ($description !== null) {
                        $stockData['description'] = $description;
                    }
                    
                    if ($imageUrl !== null) {
                        $stockData['image_url'] = $imageUrl;
                    }
                    
                    if ($categoryName !== null) {
                        $stockData['category'] = $categoryName;
                    }
                    
                    // Store raw API data
                    $stockData['raw_data'] = $item;
                    
                    $allStock[] = $stockData;
                }
            }
        }
    }

    Log::info('Shein Stock API - Chunks processed: ' . count($chunks) . ', Products: ' . count($allStock));
    return $allStock;
}

    public function getStock1(array $spus)
{
    $endpoint = "/open-api/stock/stock-query";
    $chunkSize = 10;
    $allStock = [];

    // Split SPUs into chunks of 100
    $chunks = array_chunk($spus, $chunkSize);

    foreach ($chunks as $chunk) {
        $timestamp = round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
        $url = $this->baseUrl . $endpoint;

        $payload = [
            "languageList" => ["en"],
            "skuCodeList" => [],       // must be empty
            "skcNameList" => [],       // must be empty
            "spuNameList" => $chunk,   // only this populated
            "warehouseType" => "3",    // required: 1, 2, or 3
        ];

        $response = Http::withoutVerifying()->withHeaders([
            "Language" => "en-us",
            "x-lt-openKeyId" => config('services.shein.open_key_id'),
            "x-lt-timestamp" => $timestamp,
            "x-lt-signature" => $signature,
            "Content-Type" => "application/json",
        ])->post($url, $payload);

        if (!$response->successful()) {
            throw new \Exception("Shein API Error: " . $response->body());
        }

        $data = $response->json();
        // dd($data['info']);
        if (isset($data["info"]["data"]) && is_array($data["info"]["data"])) {
            $allStock = array_merge($allStock, $data["info"]["data"]);
        }
    }

    return $allStock;
}

    /**
     * Fetch detailed product information by SKU
     * Includes: Price, Views, Rating, Inventory
     */
    public function getProductDetails(string $sku)
    {
        $endpoint = "/open-api/openapi-business-backend/product/full-detail";
        $timestamp = round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
        $url = $this->baseUrl . $endpoint;

        $payload = [
            "skuCodes" => [$sku]
        ];

        $response = Http::withoutVerifying()->withHeaders([
            "Language" => "en-us",
            "x-lt-openKeyId" => config('services.shein.open_key_id'),
            "x-lt-timestamp" => $timestamp,
            "x-lt-signature" => $signature,
            "Content-Type" => "application/json",
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error("Shein Product Details API Error for SKU: {$sku}", ['response' => $response->body()]);
            throw new \Exception("Shein API Error: " . $response->body());
        }

        $data = $response->json();

        if (isset($data["info"]) && is_array($data["info"]) && count($data["info"]) > 0) {
            $item = $data["info"][0];
            
            // Extract price from currentPrices array
            $price = null;
            $retailPrice = null;
            if (isset($item['currentPrices']) && is_array($item['currentPrices']) && count($item['currentPrices']) > 0) {
                $priceData = $item['currentPrices'][0];
                $price = $priceData['salePrice'] ?? $priceData['specialPrice'] ?? null;
                $retailPrice = $priceData['shopPrice'] ?? $priceData['suggestedRetailPrice'] ?? null;
            }
            
            // Extract main image from imageList
            $imageUrl = null;
            if (isset($item['imageList']) && is_array($item['imageList'])) {
                foreach ($item['imageList'] as $image) {
                    if (isset($image['imageType']) && $image['imageType'] === 'MAIN') {
                        $imageUrl = $image['imageUrl'] ?? null;
                        break;
                    }
                }
                if (!$imageUrl && count($item['imageList']) > 0) {
                    $imageUrl = $item['imageList'][0]['imageUrl'] ?? null;
                }
            }
            
            // Extract status from shelfDetails
            $status = null;
            $quantity = $item['goodsInventory']['inventoryQuantity'] ?? 0;
            if (isset($item['shelfDetails']) && is_array($item['shelfDetails']) && count($item['shelfDetails']) > 0) {
                $shelfDetail = $item['shelfDetails'][0];
                $isOnShelf = $shelfDetail['isOnShelf'] ?? false;
                $status = $isOnShelf ? 'active' : 'inactive';
            }
            if (!$status) {
                $status = $quantity === 0 ? 'out_of_stock' : ($quantity < 10 ? 'low_stock' : 'active');
            }
            
            $productDetails = [
                'sku' => $item['sellerSku'] ?? $sku,
                'product_name' => $item['productName'] ?? null,
                'spu_name' => $item['spuName'] ?? null,
                'quantity' => $quantity,
                'price' => $price,
                'retail_price' => $retailPrice,
                'cost_price' => null,
                'views' => $item['viewCount'] ?? $item['visits'] ?? $item['pageViews'] ?? 0,
                'rating' => $item['rating'] ?? $item['averageRating'] ?? $item['starRating'] ?? null,
                'review_count' => $item['reviewCount'] ?? $item['ratingCount'] ?? 0,
                'status' => $status,
                'description' => $item['productDesc'] ?? null,
                'image_url' => $imageUrl,
                'category' => $item['categoryName'] ?? null,
                'raw_data' => $item, // Store full response for debugging
            ];
            
            // Save to shein_metrics table
            SheinMetric::updateOrCreate(
                ['sku' => $productDetails['sku']],
                array_merge($productDetails, ['last_synced_at' => now()])
            );
            
            Log::info('Shein Product Details Fetched', ['sku' => $sku, 'details' => $productDetails]);
            
            return $productDetails;
        }

        Log::warning('No product details found for SKU: ' . $sku);
        return null;
    }

    /**
     * Sync all product data to database
     * Updates: Price, Views, Rating, Inventory
     */
    public function syncAllProductData()
    {
        Log::info('Starting Shein Product Data Sync...');
        
        try {
            $result = $this->listAllProducts();
            
            return [
                'success' => true,
                'total_products' => count($result),
                'message' => 'Shein product data synced successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Shein Sync Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

}
