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
        $clientId     = env('EBAY_3_APP_ID');
        $clientSecret = env('EBAY_3_CERT_ID');
        $refreshToken = env('EBAY_3_REFRESH_TOKEN');

        $response = Http::withoutVerifying()->asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope'         => 'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly https://api.ebay.com/oauth/api_scope/sell.inventory',
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to get eBay token: ' . $response->body());
        }

        $data        = $response->json();
        $accessToken = $data['access_token'] ?? null;
        $expiresIn   = $data['expires_in'] ?? 3600; 

        if (!$accessToken) {
            throw new \Exception('No access token returned from eBay.');
        }

        return $accessToken;
    }


    public function getItem($itemId)
    {
        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            
            $authToken = $this->generateBearerToken();
            $credentials->addChild('eBayAuthToken', $authToken ?? '');
            
            $xml->addChild('ItemID', $itemId);
            $xml->addChild('DetailLevel', 'ReturnAll');
            
            $xmlBody = $xml->asXML();
            
            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
                'X-EBAY-API-DEV-NAME'            => $this->devId,
                'X-EBAY-API-APP-NAME'            => $this->appId,
                'X-EBAY-API-CERT-NAME'           => $this->certId,
                'X-EBAY-API-CALL-NAME'           => 'GetItem',
                'X-EBAY-API-SITEID'              => $this->siteId,
                'Content-Type'                   => 'text/xml',
            ];
            
            $response = Http::withHeaders($headers)
                ->withBody($xmlBody, 'text/xml')
                ->post($this->endpoint);
            
            $body = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            
            if ($xmlResp === false) {
                \Illuminate\Support\Facades\Log::warning('Failed to parse GetItem response', ['body' => $body]);
                return null;
            }
            
            $responseArray = json_decode(json_encode($xmlResp), true);
            $ack = $responseArray['Ack'] ?? 'Failure';
            
            if ($ack === 'Success' || $ack === 'Warning') {
                return $responseArray;
            }
            
            return null;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Error fetching item details', ['itemId' => $itemId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function reviseFixedPriceItem($itemId, $price, $quantity = null, $sku = null, $variationSpecifics = null, $variationSpecificsSet = null)
    {
        // First, try to get item details to ensure we have all required fields
        $itemDetails = $this->getItem($itemId);
        
        // Build XML body
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $credentials = $xml->addChild('RequesterCredentials');
        
        $authToken = $this->generateBearerToken();

        $credentials->addChild('eBayAuthToken', $authToken ?? '');

        // Add ErrorLanguage and WarningLevel to help with validation
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $xml->addChild('DetailLevel', 'ReturnAll');

        $item = $xml->addChild('Item');
        $item->addChild('ItemID', $itemId);

        // Update price
        $item->addChild('StartPrice', $price);

        // Optionally update quantity
        if ($quantity !== null) {
            $item->addChild('Quantity', $quantity);
        }
        
        // If we have item details, include required fields to pass validation
        if ($itemDetails && isset($itemDetails['Item'])) {
            $existingItem = $itemDetails['Item'];
            
            // Include SKU if available (helps with validation)
            if (isset($existingItem['SKU']) && !empty($existingItem['SKU'])) {
                $item->addChild('SKU', $existingItem['SKU']);
            }
            
            // Include ListingType if available
            if (isset($existingItem['ListingType'])) {
                $item->addChild('ListingType', $existingItem['ListingType']);
            }
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

        // Log request details
        \Illuminate\Support\Facades\Log::info('eBay3 API Request - ReviseFixedPriceItem', [
            'itemId' => $itemId,
            'price' => $price,
            'endpoint' => $this->endpoint,
            'xmlRequest' => $xmlBody
        ]);

        // Send API request
        $response = Http::withHeaders($headers)
            ->withBody($xmlBody, 'text/xml')
            ->post($this->endpoint);

        $body = $response->body();
        
        // Try multiple header names for RlogID (eBay uses lowercase 'rlogid')
        $rlogId = $response->header('rlogid')
               ?? $response->header('X-EBAY-API-SERVER-LOG-ID') 
               ?? $response->header('X-EBAY-API-RLOG-ID')
               ?? $response->header('X-Ebay-Api-Server-Log-Id')
               ?? 'Not provided by eBay';
        
        // Decode RlogID if it's URL encoded
        if ($rlogId && $rlogId !== 'Not provided by eBay') {
            $rlogId = urldecode($rlogId);
        }
        
        // Log ALL response headers to see what eBay is sending
        $allHeaders = $response->headers();
        
        // Parse XML response first to get all details
        libxml_use_internal_errors(true);
        $xmlResp = simplexml_load_string($body);
        
        if ($xmlResp === false) {
            \Illuminate\Support\Facades\Log::error('❌ eBay3 Invalid XML Response', [
                'itemId' => $itemId,
                'price' => $price,
                'statusCode' => $response->status(),
                'rawBody' => $body
            ]);
            return [
                'success' => false,
                'message' => 'Invalid XML response',
                'raw' => $body,
            ];
        }

        $responseArray = json_decode(json_encode($xmlResp), true);
        $ack = $responseArray['Ack'] ?? 'Failure';
        
        // Extract CorrelationID and Build from XML response (eBay's tracking IDs)
        $correlationId = $responseArray['CorrelationID'] ?? null;
        $build = $responseArray['Build'] ?? null;
        $timestamp = $responseArray['Timestamp'] ?? null;
        
        // Log complete response details with all tracking IDs
        \Illuminate\Support\Facades\Log::info('eBay3 API Response - ReviseFixedPriceItem', [
            'itemId' => $itemId,
            'price' => $price,
            'statusCode' => $response->status(),
            'ack' => $ack,
            'rlogId_header' => $rlogId,
            'correlationId' => $correlationId,
            'build' => $build,
            'timestamp' => $timestamp,
            'allHeaders' => $allHeaders,
            'fullResponse' => $responseArray,
            'responseBody' => $body
        ]);

        if ($ack === 'Success' || $ack === 'Warning') {
            \Illuminate\Support\Facades\Log::info('✅ eBay3 Price Updated Successfully', [
                'itemId' => $itemId,
                'price' => $price,
                'ack' => $ack,
                'rlogId_header' => $rlogId,
                'correlationId' => $correlationId,
                'build' => $build,
                'timestamp' => $timestamp,
                'response' => $responseArray
            ]);
            
            return [
                'success' => true,
                'message' => 'Item updated successfully.',
                'data' => $responseArray,
                'rlogId' => $rlogId,
                'correlationId' => $correlationId,
                'build' => $build,
                'timestamp' => $timestamp
            ];
        } else {
            // Check for Lvis error (ErrorCode 21916293)
            $errors = $responseArray['Errors'] ?? [];
            $hasLvisError = false;
            
            // Handle both single error and array of errors
            if (!is_array($errors)) {
                $errors = [$errors];
            }
            
            $isAccountRestricted = false;
            
            // Log all errors with details
            \Illuminate\Support\Facades\Log::error('❌ eBay3 API Error - ReviseFixedPriceItem Failed', [
                'itemId' => $itemId,
                'price' => $price,
                'ack' => $ack,
                'rlogId_header' => $rlogId,
                'correlationId' => $correlationId,
                'build' => $build,
                'timestamp' => $timestamp,
                'errors' => $errors,
                'fullResponse' => $responseArray
            ]);
            
            foreach ($errors as $error) {
                $errorCode = is_array($error) ? ($error['ErrorCode'] ?? '') : '';
                $errorMsg = is_array($error) ? ($error['LongMessage'] ?? $error['ShortMessage'] ?? '') : '';
                $errorParams = is_array($error) ? ($error['ErrorParameters'] ?? []) : [];
                
                // Extract error parameter messages
                $paramMessages = [];
                if (is_array($errorParams)) {
                    foreach ($errorParams as $param) {
                        if (is_array($param) && isset($param['Value'])) {
                            $paramMessages[] = strip_tags($param['Value']);
                        }
                    }
                }
                $fullErrorText = $errorMsg . ' ' . implode(' ', $paramMessages);
                
                // Log individual error details
                \Illuminate\Support\Facades\Log::error('eBay3 Error Details', [
                    'errorCode' => $errorCode,
                    'errorMessage' => $errorMsg,
                    'errorParameters' => $paramMessages,
                    'fullErrorText' => $fullErrorText,
                    'rlogId' => $rlogId
                ]);
                
                // Check for account restriction (cannot be bypassed)
                if (stripos($fullErrorText, 'account is restricted') !== false || 
                    stripos($fullErrorText, 'restrictions on your account') !== false ||
                    stripos($fullErrorText, 'embargoed country') !== false) {
                    $isAccountRestricted = true;
                    \Illuminate\Support\Facades\Log::warning('🚫 Account restriction detected - skipping alternative methods', [
                        'itemId' => $itemId,
                        'errorText' => substr($fullErrorText, 0, 200),
                        'rlogId' => $rlogId
                    ]);
                    break; // Don't try alternative methods for account restrictions
                }
                
                if ($errorCode == '21916293' || strpos($errorMsg, 'Lvis') !== false) {
                    $hasLvisError = true;
                }
            }
            
            // If account is restricted, return error immediately (no point trying alternatives)
            if ($isAccountRestricted) {
                return [
                    'success' => false,
                    'errors' => $errors,
                    'data' => $responseArray,
                    'accountRestricted' => true,
                    'rlogId' => $rlogId
                ];
            }
            
            // If Lvis error and we have item details, try alternative approach with ReviseItem
            if ($hasLvisError && $itemDetails && isset($itemDetails['Item'])) {
                \Illuminate\Support\Facades\Log::info('🔄 Lvis error detected, trying alternative revision method', [
                    'itemId' => $itemId,
                    'rlogId' => $rlogId
                ]);
                return $this->reviseItemWithFullDetails($itemId, $price, $quantity, $itemDetails['Item']);
            }
            
            return [
                'success' => false,
                'errors' => $errors,
                'data' => $responseArray,
                'rlogId' => $rlogId
            ];
        }
    }
    
    /**
     * Alternative revision method with full item details to bypass Lvis validation
     */
    private function reviseItemWithFullDetails($itemId, $price, $quantity, $existingItem)
    {
        try {
            \Illuminate\Support\Facades\Log::info('🔄 Attempting alternative revision with ReviseItem', [
                'itemId' => $itemId,
                'price' => $price,
                'hasExistingItem' => !empty($existingItem)
            ]);
            
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            
            $authToken = $this->generateBearerToken();
            $credentials->addChild('eBayAuthToken', $authToken ?? '');
            
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');
            $xml->addChild('DetailLevel', 'ReturnAll');
            
            $item = $xml->addChild('Item');
            $item->addChild('ItemID', $itemId);
            $item->addChild('StartPrice', $price);
            
            // Include SKU if available (critical for validation)
            if (isset($existingItem['SKU']) && !empty($existingItem['SKU'])) {
                $item->addChild('SKU', $existingItem['SKU']);
                \Illuminate\Support\Facades\Log::debug('Including SKU in revision', ['sku' => $existingItem['SKU']]);
            }
            
            // Include ListingType
            if (isset($existingItem['ListingType'])) {
                $item->addChild('ListingType', $existingItem['ListingType']);
            }
            
            // Include Condition if available (sometimes required)
            if (isset($existingItem['ConditionID'])) {
                $condition = $item->addChild('ConditionID', $existingItem['ConditionID']);
                if (isset($existingItem['ConditionDescription'])) {
                    $item->addChild('ConditionDescription', $existingItem['ConditionDescription']);
                }
            }
            
            // Include Country if available
            if (isset($existingItem['Country'])) {
                $item->addChild('Country', $existingItem['Country']);
            }
            
            // Include Currency if available
            if (isset($existingItem['Currency'])) {
                $item->addChild('Currency', $existingItem['Currency']);
            }
            
            if ($quantity !== null) {
                $item->addChild('Quantity', $quantity);
            }
            
            $xmlBody = $xml->asXML();
            
            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
                'X-EBAY-API-DEV-NAME'            => $this->devId,
                'X-EBAY-API-APP-NAME'            => $this->appId,
                'X-EBAY-API-CERT-NAME'           => $this->certId,
                'X-EBAY-API-CALL-NAME'           => 'ReviseItem',
                'X-EBAY-API-SITEID'              => $this->siteId,
                'Content-Type'                   => 'text/xml',
            ];
            
            // Log alternative method request
            \Illuminate\Support\Facades\Log::info('eBay3 API Request - ReviseItem (Alternative)', [
                'itemId' => $itemId,
                'price' => $price,
                'xmlRequest' => $xmlBody
            ]);
            
            $response = Http::withHeaders($headers)
                ->withBody($xmlBody, 'text/xml')
                ->post($this->endpoint);
            
            $body = $response->body();
            
            // Try multiple header names for RlogID (eBay uses lowercase 'rlogid')
            $rlogId = $response->header('rlogid')
                   ?? $response->header('X-EBAY-API-SERVER-LOG-ID') 
                   ?? $response->header('X-EBAY-API-RLOG-ID')
                   ?? $response->header('X-Ebay-Api-Server-Log-Id')
                   ?? 'Not provided by eBay';
            
            // Decode RlogID if it's URL encoded
            if ($rlogId && $rlogId !== 'Not provided by eBay') {
                $rlogId = urldecode($rlogId);
            }
            
            // Log alternative method response
            \Illuminate\Support\Facades\Log::info('eBay3 API Response - ReviseItem (Alternative)', [
                'itemId' => $itemId,
                'statusCode' => $response->status(),
                'rlogId' => $rlogId,
                'allHeaders' => $response->headers(),
                'responseBody' => substr($body, 0, 2000)
            ]);
            
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            
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
                \Illuminate\Support\Facades\Log::info('✅ Alternative revision method succeeded', [
                    'itemId' => $itemId,
                    'price' => $price,
                    'ack' => $ack,
                    'rlogId' => $rlogId,
                    'response' => $responseArray
                ]);
                return [
                    'success' => true,
                    'message' => 'Item updated successfully (alternative method).',
                    'data' => $responseArray,
                    'rlogId' => $rlogId
                ];
            } else {
                $errors = $responseArray['Errors'] ?? [];
                if (!is_array($errors)) {
                    $errors = [$errors];
                }
                
                \Illuminate\Support\Facades\Log::warning('⚠️ Alternative revision method also failed', [
                    'itemId' => $itemId,
                    'price' => $price,
                    'ack' => $ack,
                    'rlogId' => $rlogId,
                    'errors' => $errors,
                    'responseBody' => substr($body, 0, 500)
                ]);
                
                // Try one more time with minimal ReviseItem (just ItemID and StartPrice)
                \Illuminate\Support\Facades\Log::info('🔄 Attempting minimal ReviseItem as final fallback', [
                    'itemId' => $itemId,
                    'rlogId' => $rlogId
                ]);
                return $this->reviseItemMinimal($itemId, $price);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Exception in reviseItemWithFullDetails', [
                'itemId' => $itemId,
                'error' => $e->getMessage()
            ]);
            // Try minimal approach as last resort
            return $this->reviseItemMinimal($itemId, $price);
        }
    }
    
    /**
     * Minimal ReviseItem - absolute last resort with only ItemID and StartPrice
     */
    private function reviseItemMinimal($itemId, $price)
    {
        try {
            \Illuminate\Support\Facades\Log::info('🔄 Attempting minimal ReviseItem (final fallback)', [
                'itemId' => $itemId,
                'price' => $price
            ]);
            
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            
            $authToken = $this->generateBearerToken();
            $credentials->addChild('eBayAuthToken', $authToken ?? '');
            
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');
            
            $item = $xml->addChild('Item');
            $item->addChild('ItemID', $itemId);
            $item->addChild('StartPrice', $price);
            
            $xmlBody = $xml->asXML();
            
            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
                'X-EBAY-API-DEV-NAME'            => $this->devId,
                'X-EBAY-API-APP-NAME'            => $this->appId,
                'X-EBAY-API-CERT-NAME'           => $this->certId,
                'X-EBAY-API-CALL-NAME'           => 'ReviseItem',
                'X-EBAY-API-SITEID'              => $this->siteId,
                'Content-Type'                   => 'text/xml',
            ];
            
            // Log minimal method request
            \Illuminate\Support\Facades\Log::info('eBay3 API Request - ReviseItem (Minimal)', [
                'itemId' => $itemId,
                'price' => $price,
                'xmlRequest' => $xmlBody
            ]);
            
            $response = Http::withHeaders($headers)
                ->withBody($xmlBody, 'text/xml')
                ->post($this->endpoint);
            
            $body = $response->body();
            
            // Try multiple header names for RlogID (eBay uses lowercase 'rlogid')
            $rlogId = $response->header('rlogid')
                   ?? $response->header('X-EBAY-API-SERVER-LOG-ID') 
                   ?? $response->header('X-EBAY-API-RLOG-ID')
                   ?? $response->header('X-Ebay-Api-Server-Log-Id')
                   ?? 'Not provided by eBay';
            
            // Decode RlogID if it's URL encoded
            if ($rlogId && $rlogId !== 'Not provided by eBay') {
                $rlogId = urldecode($rlogId);
            }
            
            // Log minimal method response
            \Illuminate\Support\Facades\Log::info('eBay3 API Response - ReviseItem (Minimal)', [
                'itemId' => $itemId,
                'statusCode' => $response->status(),
                'rlogId' => $rlogId,
                'allHeaders' => $response->headers(),
                'responseBody' => substr($body, 0, 2000)
            ]);
            
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            
            if ($xmlResp === false) {
                \Illuminate\Support\Facades\Log::error('❌ Minimal ReviseItem: Invalid XML response', [
                    'body' => substr($body, 0, 500),
                    'rlogId' => $rlogId
                ]);
                return [
                    'success' => false,
                    'message' => 'Invalid XML response',
                    'raw' => $body,
                    'rlogId' => $rlogId
                ];
            }
            
            $responseArray = json_decode(json_encode($xmlResp), true);
            $ack = $responseArray['Ack'] ?? 'Failure';
            
            if ($ack === 'Success' || $ack === 'Warning') {
                \Illuminate\Support\Facades\Log::info('✅ Minimal ReviseItem succeeded', [
                    'itemId' => $itemId,
                    'price' => $price,
                    'rlogId' => $rlogId,
                    'response' => $responseArray
                ]);
                return [
                    'success' => true,
                    'message' => 'Item updated successfully (minimal method).',
                    'data' => $responseArray,
                    'rlogId' => $rlogId
                ];
            } else {
                $errors = $responseArray['Errors'] ?? [];
                if (!is_array($errors)) {
                    $errors = [$errors];
                }
                
                \Illuminate\Support\Facades\Log::error('❌ Minimal ReviseItem also failed - all methods exhausted', [
                    'itemId' => $itemId,
                    'price' => $price,
                    'ack' => $ack,
                    'rlogId' => $rlogId,
                    'errors' => $errors,
                    'fullResponse' => $responseArray
                ]);
                
                return [
                    'success' => false,
                    'errors' => $errors,
                    'data' => $responseArray,
                    'rlogId' => $rlogId
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('❌ Exception in reviseItemMinimal', [
                'itemId' => $itemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'errors' => [['code' => 'Exception', 'message' => $e->getMessage()]],
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
