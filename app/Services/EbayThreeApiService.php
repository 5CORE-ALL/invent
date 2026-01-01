<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

class EbayThreeApiService
{

    protected $appId;
    protected $certId;
    protected $devId;
    protected $userToken;
    protected $endpoint;
    protected $siteId;
    protected $compatLevel;

    public function __construct()
    {
        $this->appId       = env('EBAY_3_APP_ID');
        $this->certId      = env('EBAY_3_CERT_ID');
        $this->devId       = env('EBAY_3_DEV_ID');
        $this->endpoint    = env('EBAY_TRADING_API_ENDPOINT', 'https://api.ebay.com/ws/api.dll');
        $this->siteId      = env('EBAY_SITE_ID', 0); // US = 0
        $this->compatLevel = env('EBAY_COMPAT_LEVEL', '1189');
    }
    public function generateBearerToken()
    {
        // 1. If cached token exists, return it immediately
        if (Cache::has('ebay3_bearer')) {
            echo "\nBearer Token in Cache";

            return Cache::get('ebay3_bearer');
        }


        echo "Generating New Ebay Token";

        // 2. Otherwise, request new token from eBay
        $clientId     = env('EBAY_3_APP_ID');
        $clientSecret = env('EBAY_3_CERT_ID');
        $refreshToken = env('EBAY_3_REFRESH_TOKEN');

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope'         => 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.inventory',
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to get eBay token: ' . $response->body());
        }

        $data        = $response->json();
        $accessToken = $data['access_token'];
        $expiresIn   = $data['expires_in'] ?? 3600; // seconds, defaults to 1h

        // 3. Store token in cache for slightly less than expiry time
        Cache::put('ebay3_bearer', $accessToken, now()->addSeconds($expiresIn - 60));

        return $accessToken;
    }


    public function reviseFixedPriceItem($itemId, $price, $quantity = null, $sku = null, $variationSpecifics = null, $variationSpecificsSet = null)
    {
                // Build XML body
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $credentials = $xml->addChild('RequesterCredentials');
        
        $authToken = $this->generateBearerToken();

        $credentials->addChild('eBayAuthToken', $authToken ?? '');


        $item = $xml->addChild('Item');
        $item->addChild('ItemID', $itemId);

        // Update price
        $item->addChild('StartPrice', $price);

        // Optionally update quantity
        if ($quantity !== null) {
            $item->addChild('Quantity', $quantity);
        }

        // If variation exists, use variation structure
        if ($variationSpecifics && $variationSpecificsSet) {
            $variations = $item->addChild('Variations');
            $variation = $variations->addChild('Variation');

            if ($sku) {
                $variation->addChild('SKU', $sku);
            }

            $variation->addChild('StartPrice', $price);
            if ($quantity !== null) {
                $variation->addChild('Quantity', $quantity);
            }

            // VariationSpecifics
            $vs = $variation->addChild('VariationSpecifics');
            foreach ($variationSpecifics as $name => $value) {
                $nvl = $vs->addChild('NameValueList');
                $nvl->addChild('Name', $name);
                $nvl->addChild('Value', $value);
            }

            // VariationSpecificsSet
            $vss = $item->addChild('VariationSpecificsSet');
            foreach ($variationSpecificsSet as $name => $values) {
                $nvl = $vss->addChild('NameValueList');
                $nvl->addChild('Name', $name);
                foreach ($values as $val) {
                    $nvl->addChild('Value', $val);
                }
            }
        }

        $xmlBody = $xml->asXML();

        // Prepare headers
        $headers = [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
            'X-EBAY-API-DEV-NAME'            => $this->devId,
            'X-EBAY-API-APP-NAME'            => $this->appId,
            'X-EBAY-API-CERT-NAME'           => $this->certId,
            'X-EBAY-API-CALL-NAME'           => 'ReviseFixedPriceItem',
            'X-EBAY-API-SITEID'              => $this->siteId,
            'Content-Type'                   => 'text/xml',
        ];

        // Send API request
        $response = Http::withHeaders($headers)
            ->withBody($xmlBody, 'text/xml')
            ->post($this->endpoint);

        $body = $response->body();

        // Parse XML response
        libxml_use_internal_errors(true);
        $xmlResp = simplexml_load_string(data: $body);
        dd($xmlResp);
        if ($xmlResp === false) {
            return [
                'success' => false,
                'message' => 'Invalid XML response',
                'raw' => $body,
            ];
        }

        $responseArray = json_decode(json_encode($xmlResp), true);
        $ack = $responseArray['Ack'] ?? 'Failure';

        if ($ack === 'Success' || $ack === 'Warning') {
            return [
                'success' => true,
                'message' => 'Item updated successfully.',
                'data' => $responseArray,
            ];
        } else {
            return [
                'success' => false,
                'errors' => $responseArray['Errors'] ?? 'Unknown error',
                'data' => $responseArray,
            ];
        }
    }

    /**
     * Get competitor prices using eBay Browse API with Category/EPID filter
     * This provides more accurate results than plain SKU search
     * 
     * @param string $title - Product title to search for
     * @param string|null $categoryId - eBay category ID for filtering
     * @param string|null $epid - eBay Product ID for exact product match
     * @param string|null $ourItemId - Our item ID to exclude from results
     * @return array - Array of competitor listings with price, shipping, link
     */
    public function doRepricingWithCategory($title, $categoryId = null, $epid = null, $ourItemId = null)
    {
        $token = $this->generateBrowseToken();
        if (!$token) {
            return [];
        }

        $constructedData = [];
        
        // Build search query - use first 80 chars of title for better matching
        $searchQuery = substr($title, 0, 80);
        
        // Build URL with category filter if available
        $url = 'https://api.ebay.com/buy/browse/v1/item_summary/search?q=' . urlencode($searchQuery) . '&limit=50';
        
        // Add category filter for more accurate results
        if ($categoryId) {
            $url .= '&category_ids=' . $categoryId;
        }
        
        // If EPID is available, use it for exact product matching
        if ($epid) {
            $url = 'https://api.ebay.com/buy/browse/v1/item_summary/search?epid=' . $epid . '&limit=50';
        }

        $maxPages = 2; // Limit pages since we have category filter
        $pageCount = 0;

        do {
            $pageCount++;
            
            try {
                $response = Http::withToken($token)
                    ->timeout(30)
                    ->connectTimeout(15)
                    ->get($url);

                if (!$response->successful()) {
                    break;
                }

                $responseJSON = $response->json();
                $items = $responseJSON['itemSummaries'] ?? [];

                foreach ($items as $data) {
                    // Skip our own item
                    $itemId = $data['itemId'] ?? '';
                    $legacyItemId = str_replace(['v1|', '|0'], '', $itemId);
                    if ($ourItemId && $legacyItemId == $ourItemId) {
                        continue;
                    }
                    
                    $price = floatval($data['price']['value'] ?? 0);
                    $shippingCost = 0;
                    
                    // Extract shipping cost if available
                    if (isset($data['shippingOptions'][0]['shippingCost']['value'])) {
                        $shippingCost = floatval($data['shippingOptions'][0]['shippingCost']['value']);
                    }

                    $constructedData[] = [
                        'title' => $data['title'] ?? '',
                        'item_id' => $itemId,
                        'link' => $data['itemWebUrl'] ?? '',
                        'condition' => $data['condition'] ?? '',
                        'price' => $price,
                        'shipping_cost' => $shippingCost,
                        'total_price' => $price + $shippingCost,
                        'seller' => $data['seller']['username'] ?? '',
                        'image' => $data['image']['imageUrl'] ?? '',
                    ];
                }

                $url = $responseJSON['next'] ?? null;

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('eBay Browse API error: ' . $e->getMessage());
                break;
            }

        } while (!empty($url) && $pageCount < $maxPages);

        // Sort by total price (lowest first)
        usort($constructedData, function($a, $b) {
            return $a['total_price'] <=> $b['total_price'];
        });

        return $constructedData;
    }

    /**
     * Get competitor prices using eBay Browse API
     * 
     * @param string $query - SKU, title or EPID to search for
     * @return array - Array of competitor listings with price, shipping, link
     */
    public function doRepricing($query)
    {
        $token = $this->generateBrowseToken();
        if (!$token) {
            return [];
        }

        $constructedData = [];
        $url = 'https://api.ebay.com/buy/browse/v1/item_summary/search?q=' . urlencode($query) . '&limit=50';

        $maxPages = 3; // Limit to 3 pages to avoid too many API calls
        $pageCount = 0;

        do {
            $pageCount++;
            
            try {
                $response = Http::withToken($token)
                    ->timeout(30)
                    ->connectTimeout(15)
                    ->get($url);

                if (!$response->successful()) {
                    break;
                }

                $responseJSON = $response->json();
                $items = $responseJSON['itemSummaries'] ?? [];

                foreach ($items as $data) {
                    $price = floatval($data['price']['value'] ?? 0);
                    $shippingCost = 0;
                    
                    // Extract shipping cost if available
                    if (isset($data['shippingOptions'][0]['shippingCost']['value'])) {
                        $shippingCost = floatval($data['shippingOptions'][0]['shippingCost']['value']);
                    }

                    $constructedData[] = [
                        'title' => $data['title'] ?? '',
                        'item_id' => $data['itemId'] ?? '',
                        'link' => $data['itemWebUrl'] ?? '',
                        'condition' => $data['condition'] ?? '',
                        'price' => $price,
                        'shipping_cost' => $shippingCost,
                        'total_price' => $price + $shippingCost,
                        'seller' => $data['seller']['username'] ?? '',
                        'image' => $data['image']['imageUrl'] ?? '',
                    ];
                }

                $url = $responseJSON['next'] ?? null;

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('eBay Browse API error: ' . $e->getMessage());
                break;
            }

        } while (!empty($url) && $pageCount < $maxPages);

        // Sort by total price (lowest first)
        usort($constructedData, function($a, $b) {
            return $a['total_price'] <=> $b['total_price'];
        });

        return $constructedData;
    }

    /**
     * Generate Bearer Token for Browse API (uses client credentials grant)
     */
    public function generateBrowseToken()
    {
        $cacheKey = 'ebay3_browse_bearer';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $clientId = env('EBAY_3_APP_ID');
        $clientSecret = env('EBAY_3_CERT_ID');

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://api.ebay.com/oauth/api_scope',
                ]);

            if ($response->failed()) {
                \Illuminate\Support\Facades\Log::error('Failed to get eBay Browse token: ' . $response->body());
                return null;
            }

            $data = $response->json();
            $accessToken = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 3600;

            if ($accessToken) {
                Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn - 60));
            }

            return $accessToken;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('eBay Browse token exception: ' . $e->getMessage());
            return null;
        }
    }
}
