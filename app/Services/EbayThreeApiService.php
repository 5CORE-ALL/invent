<?php

namespace App\Services;

use App\Models\Ebay3Metric;
use App\Services\Concerns\ResolvesBulletPointIdentifier;
use App\Services\Support\EbaySellInventoryListingResolver;
use App\Services\Support\EbayTradingReviseItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class EbayThreeApiService
{
    use ResolvesBulletPointIdentifier;

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
    /**
     * Generate OAuth bearer token for Trading API (GetItem, ReviseItem).
     * Uses only the base Trading API scope - required for ReviseItem; other scopes
     * can cause "The requested scope is invalid" if not granted to the app keyset.
     */
    public function generateBearerToken()
    {
        $clientId     = env('EBAY_3_APP_ID');
        $clientSecret = env('EBAY_3_CERT_ID');
        $refreshToken = env('EBAY_3_REFRESH_TOKEN');

        if (empty($refreshToken)) {
            Log::error('eBay3 token: EBAY_3_REFRESH_TOKEN is not configured');
            throw new \Exception('eBay 3 refresh token is not configured. Check EBAY_3_REFRESH_TOKEN.');
        }

        if (empty($clientId) || empty($clientSecret)) {
            Log::error('eBay3 token: missing required credentials', [
                'has_client_id' => !empty($clientId),
                'has_cert_id' => !empty($clientSecret),
                'has_refresh_token' => !empty($refreshToken),
            ]);
            throw new \Exception('eBay 3 credentials not configured (app_id/cert_id/refresh_token).');
        }

        $cacheKey = 'ebay3_bearer_token_' . md5((string) $clientId);
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached)) {
                return $cached;
            }
        }

        try {
            $response = Http::withoutVerifying()->asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout(30)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    // IMPORTANT: When using refresh_token, omit `scope` so eBay inherits
                    // the scopes originally granted with the refresh token.
                ]);

            $body   = $response->body();
            $status = $response->status();

            if ($response->failed()) {
                $json = is_string($body) ? json_decode($body, true) : [];
                $errorCode        = $json['error'] ?? null;
                $errorDescription = $json['error_description'] ?? null;
                Log::error('eBay3 token generation failed', [
                    'http_status'     => $status,
                    'ebay_error'      => $errorCode,
                    'ebay_description' => $errorDescription,
                    'full_response'   => $body,
                    'client_id_prefix' => substr($clientId ?? '', 0, 8) . '...',
                    'scope_parameter_sent' => false,
                ]);
                $msg = 'Failed to get eBay 3 token. ';
                if ($status === 400 && ($errorCode || $errorDescription)) {
                    $err = $errorDescription ?: $errorCode;
                    if (stripos((string) $err, 'scope') !== false) {
                        $msg .= 'Scope error: ' . $err . ' (scope parameter was omitted). Your refresh token likely does not include required Trading API scopes; regenerate the refresh token with Trading API access.';
                    } elseif (
                        $errorCode === 'invalid_grant'
                        || stripos((string) $err, 'invalid_grant') !== false
                        || stripos((string) $err, 'refresh_token') !== false
                        || stripos((string) $err, 'expired') !== false
                    ) {
                        $msg .= 'Refresh token may be expired. Please re-authorize the application in eBay Developer Portal.';
                    } else {
                        $msg .= $err;
                    }
                } else {
                    $msg .= 'HTTP ' . $status . '. ' . ($errorDescription ?: substr($body, 0, 500));
                }
                throw new \Exception($msg);
            }

            $data        = json_decode($body, true) ?? [];
            $accessToken = $data['access_token'] ?? null;
            $expiresIn   = (int) ($data['expires_in'] ?? 3600);

            if (empty($accessToken)) {
                Log::error('eBay3 token: no access_token in response', ['body' => substr($body, 0, 500)]);
                throw new \Exception('No access token returned from eBay. Full response: ' . substr($body, 0, 300));
            }

            $ttlSeconds = max(0, $expiresIn - 60);
            Cache::put($cacheKey, $accessToken, now()->addSeconds($ttlSeconds));
            Log::debug('eBay3 token generated successfully', ['expires_in' => $expiresIn]);
            return $accessToken;
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Http\Client\RequestException) {
                Log::error('eBay3 token request exception', [
                    'message' => $e->getMessage(),
                    'response' => $e->response?->body(),
                ]);
            }
            throw $e;
        }
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
                Log::warning('eBay3 GetItem: failed to parse XML response', ['itemId' => $itemId, 'body' => substr($body, 0, 1000)]);
                return null;
            }

            $responseArray = json_decode(json_encode($xmlResp), true);
            $ack           = $responseArray['Ack'] ?? 'Failure';

            if ($ack === 'Success' || $ack === 'Warning') {
                Log::debug('eBay3 GetItem success', ['itemId' => $itemId]);
                return $responseArray;
            }

            $errors = $responseArray['Errors'] ?? [];
            $errors = is_array($errors) ? $errors : [$errors];
            $errMsg = '';
            foreach ($errors as $err) {
                $errMsg .= ($errMsg ? '; ' : '') . ($this->parseEbayError(is_array($err) ? $err : ['ShortMessage' => (string) $err]));
            }
            Log::warning('eBay3 GetItem failed', ['itemId' => $itemId, 'ack' => $ack, 'errors' => $errors, 'parsed' => $errMsg]);
            return null;
        } catch (\Exception $e) {
            Log::warning('eBay3 GetItem exception', ['itemId' => $itemId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse eBay API errors into a clear message (LongMessage, ShortMessage, ErrorCode, ErrorParameters).
     */
    private function parseEbayError(array $error): string
    {
        $long   = $error['LongMessage'] ?? null;
        $short  = $error['ShortMessage'] ?? null;
        $code   = $error['ErrorCode'] ?? null;
        $params = $error['ErrorParameters'] ?? [];
        $parts  = [];
        if ($long && $long !== $short) {
            $parts[] = $long;
        } elseif ($short) {
            $parts[] = $short;
        }
        if ($code) {
            $parts[] = "(eBay code: {$code})";
        }
        if (is_array($params)) {
            foreach ($params as $p) {
                if (is_array($p) && isset($p['Value'])) {
                    $val = is_string($p['Value']) ? strip_tags($p['Value']) : json_encode($p['Value']);
                    if (trim($val) !== '') {
                        $parts[] = $val;
                    }
                }
            }
        }
        return implode(' ', $parts) ?: 'Unknown error';
    }

    /**
     * Update listing title via ReviseItem (eBay title max 80 chars).
     * Fetches item details first and includes required fields (SKU, ListingType, Country, Currency, ConditionID).
     */
    public function updateTitle($itemId, $title)
    {
        $itemId = trim((string) $itemId);
        $title  = trim((string) $title);
        $title  = mb_substr($title, 0, 80);

        if ($itemId === '') {
            Log::warning('eBay3 updateTitle: empty item ID');
            return ['success' => false, 'message' => 'Item ID is required.'];
        }
        if ($title === '') {
            Log::warning('eBay3 updateTitle: empty title', ['itemId' => $itemId]);
            return ['success' => false, 'message' => 'Title cannot be empty.'];
        }

        $metric = Ebay3Metric::where('item_id', $itemId)->first();
        if (!$metric) {
            Log::warning('eBay3 updateTitle: item_id not found in Ebay3Metric', ['itemId' => $itemId]);
            return [
                'success' => false,
                'message' => "Item ID {$itemId} not found in eBay 3 metrics. Ensure the listing exists and is synced.",
            ];
        }

        try {
            $authToken = $this->generateBearerToken();
            Log::info('eBay3 updateTitle: token generated, fetching item details', ['itemId' => $itemId]);

            $itemDetails = $this->getItem($itemId);
            if (!$itemDetails || !isset($itemDetails['Item'])) {
                Log::error('eBay3 updateTitle: GetItem failed or returned no item', [
                    'itemId' => $itemId,
                    'getItemResult' => $itemDetails ? 'partial' : 'null',
                ]);
                return [
                    'success' => false,
                    'message' => 'Could not fetch item details from eBay. The item may not exist or the token may lack Trading API access.',
                ];
            }

            $existingItem = $itemDetails['Item'];
            Log::info('eBay3 updateTitle: item details fetched', [
                'itemId'      => $itemId,
                'listingType' => $existingItem['ListingType'] ?? null,
                'country'     => $existingItem['Country'] ?? null,
                'currency'    => $existingItem['Currency'] ?? null,
                'conditionId' => $existingItem['ConditionID'] ?? null,
                'sku'         => isset($existingItem['SKU']) ? substr($existingItem['SKU'], 0, 20) . '...' : null,
            ]);

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            $credentials->addChild('eBayAuthToken', $authToken ?? '');
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');
            $xml->addChild('DetailLevel', 'ReturnAll');

            $item = $xml->addChild('Item');
            $item->addChild('ItemID', $itemId);
            $item->addChild('Title', $title);

            if (isset($existingItem['SKU']) && $existingItem['SKU'] !== '' && $existingItem['SKU'] !== null) {
                $item->addChild('SKU', (string) $existingItem['SKU']);
            }
            if (isset($existingItem['ListingType'])) {
                $item->addChild('ListingType', (string) $existingItem['ListingType']);
            }
            if (isset($existingItem['Country'])) {
                $item->addChild('Country', (string) $existingItem['Country']);
            }
            if (isset($existingItem['Currency'])) {
                $item->addChild('Currency', (string) $existingItem['Currency']);
            }
            if (isset($existingItem['ConditionID'])) {
                $item->addChild('ConditionID', (string) $existingItem['ConditionID']);
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

            Log::info('eBay3 updateTitle: ReviseItem request', [
                'itemId'  => $itemId,
                'title'   => substr($title, 0, 80),
                'xml'     => $xmlBody,
            ]);

            $response = Http::withHeaders($headers)->withBody($xmlBody, 'text/xml')->post($this->endpoint);
            $body     = $response->body();
            $rlogId   = $response->header('rlogid') ?? $response->header('X-EBAY-API-SERVER-LOG-ID') ?? null;

            Log::info('eBay3 updateTitle: ReviseItem response', [
                'itemId'       => $itemId,
                'statusCode'   => $response->status(),
                'rlogId'       => $rlogId,
                'responseBody' => $body,
            ]);

            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            if ($xmlResp === false) {
                Log::error('eBay3 updateTitle: invalid XML response', [
                    'itemId' => $itemId,
                    'body'   => substr($body, 0, 1000),
                    'rlogId' => $rlogId,
                ]);
                return ['success' => false, 'message' => 'Invalid API response from eBay.'];
            }

            $responseArray = json_decode(json_encode($xmlResp), true);
            $ack           = $responseArray['Ack'] ?? 'Failure';

            if ($ack === 'Success' || $ack === 'Warning') {
                Log::info('eBay3 title updated', ['item_id' => $itemId]);
                return ['success' => true, 'message' => 'Title updated successfully.'];
            }

            $errors   = $responseArray['Errors'] ?? [];
            $errors   = is_array($errors) ? $errors : [$errors];
            $messages = [];
            foreach ($errors as $err) {
                $messages[] = $this->parseEbayError(is_array($err) ? $err : ['ShortMessage' => (string) $err]);
            }
            $msg = implode('; ', $messages) ?: 'Unknown error';

            Log::error('eBay3 updateTitle failed', [
                'itemId'       => $itemId,
                'ack'          => $ack,
                'rlogId'       => $rlogId,
                'errors'       => $errors,
                'parsedMsg'    => $msg,
            ]);

            return ['success' => false, 'message' => $msg];
        } catch (\Throwable $e) {
            Log::error('eBay3 updateTitle exception', [
                'itemId'   => $itemId,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
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

    /**
     * Push bullet points to eBay listing Description as HTML list.
     *
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        if (trim($identifier) === '') {
            return ['success' => false, 'message' => 'SKU (or item_id) is required.'];
        }

        $row = $this->findMetricRowBySkuOrAlternateIds('ebay_3_metrics', $identifier, ['item_id']);
        $itemId = $row->item_id ?? null;
        if (! $itemId) {
            return ['success' => false, 'message' => 'Product not found in ebay_3_metrics (try SKU or eBay item_id).'];
        }

        try {
            $token = $this->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $html = EbayTradingReviseItem::bulletsToDescriptionHtml($bulletPoints);

        return EbayTradingReviseItem::reviseItemDescription(
            $this->endpoint,
            $this->compatLevel,
            $this->devId,
            $this->appId,
            $this->certId,
            $this->siteId,
            $token,
            (string) $itemId,
            $html
        );
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateListingImages(string $identifier, array $imageUrls): array
    {
        if (trim($identifier) === '') {
            return ['success' => false, 'message' => 'SKU (or item_id) is required.'];
        }

        $row = $this->findMetricRowBySkuOrAlternateIds('ebay_3_metrics', $identifier, ['item_id']);
        $itemId = isset($row->item_id) && $row->item_id !== '' ? trim((string) $row->item_id) : null;

        try {
            $token = $this->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if (! $itemId) {
            $itemId = EbaySellInventoryListingResolver::resolveWithTradingFallback(
                $token,
                $this->endpoint,
                $this->tradingApiHeadersBase(),
                trim($identifier)
            );
        }

        if (! $itemId) {
            return ['success' => false, 'message' => 'No eBay3 listing found for this SKU or item_id (check ebay_3_metrics or Inventory / GetSellerList).'];
        }

        return EbayTradingReviseItem::reviseItemPictureUrls(
            $this->endpoint,
            $this->compatLevel,
            $this->devId,
            $this->appId,
            $this->certId,
            $this->siteId,
            $token,
            (string) $itemId,
            $imageUrls
        );
    }

    /**
     * @return array<string, string>
     */
    private function tradingApiHeadersBase(): array
    {
        return [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
            'X-EBAY-API-DEV-NAME' => $this->devId,
            'X-EBAY-API-APP-NAME' => $this->appId,
            'X-EBAY-API-CERT-NAME' => $this->certId,
            'X-EBAY-API-SITEID' => (string) $this->siteId,
        ];
    }

    public function getTradingEndpoint(): string
    {
        return (string) $this->endpoint;
    }

    /**
     * @return array<string, string>
     */
    public function getTradingHeadersForResolver(): array
    {
        return $this->tradingApiHeadersBase();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $identifier, string $description): array
    {
        $description = trim($description);
        if (trim($identifier) === '' || $description === '') {
            return ['success' => false, 'message' => 'SKU (or item_id) and description are required.'];
        }

        $row = $this->findMetricRowBySkuOrAlternateIds('ebay_3_metrics', $identifier, ['item_id']);
        $itemId = $row->item_id ?? null;
        if (! $itemId) {
            return ['success' => false, 'message' => 'Product not found in ebay_3_metrics (try SKU or eBay item_id).'];
        }

        try {
            $token = $this->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $html = '<div class="product-description">'.nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</div>';

        return EbayTradingReviseItem::reviseItemDescription(
            $this->endpoint,
            $this->compatLevel,
            $this->devId,
            $this->appId,
            $this->certId,
            $this->siteId,
            $token,
            (string) $itemId,
            $html
        );
    }
}
