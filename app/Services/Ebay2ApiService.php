<?php

namespace App\Services;

use App\Models\Ebay2Metric;
use App\Models\ProductStockMapping;
use App\Services\Concerns\ResolvesBulletPointIdentifier;
use App\Services\Support\Concerns\ResolvesEbayListingItemId;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\Support\EbaySellInventoryListingResolver;
use App\Services\Support\EbayTradingReviseItem;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use SimpleXMLElement;
use ZipArchive;

class Ebay2ApiService
{
    use ResolvesBulletPointIdentifier;
    use ResolvesEbayListingItemId;
    use SavesMarketplaceVideoMetrics;
    use VideoMasterMarketplaceMethods;

    protected $appId;
    protected $certId;
    protected $devId;
    protected $userToken;
    protected $endpoint;
    protected $siteId;
    protected $compatLevel;

    public function __construct()
    {
        $this->appId       = config('services.ebay2.app_id');
        $this->certId      = config('services.ebay2.cert_id');
        $this->devId       = config('services.ebay2.dev_id');
        $this->endpoint    = config('services.ebay.trading_api_endpoint');
        $this->siteId      = config('services.ebay.site_id'); // US = 0
        $this->compatLevel = config('services.ebay.compat_level');
    }
    // public function generateBearerToken()
    // {
    //     // 1. If cached token exists, return it immediately
    //     if (Cache::has('ebay_bearer')) {
    //         echo "\nBearer Token in Cache";

    //         return Cache::get('ebay_bearer');
    //     }
       
    //     echo "Generating New Ebay Token";


    //     // 2. Otherwise, request new token from eBay
    //     $clientId     = config('services.ebay.app_id');
    //     $clientSecret = config('services.ebay.cert_id');
    //     $refreshToken = config('services.ebay.refresh_token');

    //     $response = Http::asForm()
    //         ->withBasicAuth($clientId, $clientSecret)
    //         ->post('https://api.ebay.com/identity/v1/oauth2/token', [
    //             'grant_type'    => 'refresh_token',
    //             'refresh_token' => $refreshToken,
    //             'scope'         => 'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly https://api.ebay.com/oauth/api_scope/sell.inventory',
    //         ]);

    //     if ($response->failed()) {
    //         throw new \Exception('Failed to get eBay token: ' . $response->body());
    //     }

    //     $data        = $response->json();
    //     $accessToken = $data['access_token'];
    //     $expiresIn   = $data['expires_in'] ?? 3600; // seconds, defaults to 1h

    //     // 3. Store token in cache for slightly less than expiry time
    //     Cache::put('ebay_bearer', $accessToken, now()->addSeconds($expiresIn - 60));

    //     return $accessToken;
    // }
    public function generateBearerToken()
    {
        $clientId = config('services.ebay2.app_id');
        $clientSecret = config('services.ebay2.cert_id');
        $refreshToken = config('services.ebay2.refresh_token');

        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            Log::error('eBay2 token: missing required credentials', [
                'has_client_id' => !empty($clientId),
                'has_cert_id' => !empty($clientSecret),
                'has_refresh_token' => !empty($refreshToken),
            ]);
            throw new \Exception('eBay 2 credentials not configured (app_id/cert_id/refresh_token).');
        }

        $cacheKey = 'ebay2_bearer_token_' . md5((string) $clientId);
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached)) {
                return $cached;
            }
        }

        // IMPORTANT: When using refresh_token, omit the `scope` parameter.
        // eBay expects scopes to be inherited from the original authorization of the refresh token.
        $response = Http::withoutVerifying()->asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(30)
            ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                // no `scope`
            ]);

        $body = (string) $response->body();
        $status = $response->status();

        if ($response->failed()) {
            $json = json_decode($body, true) ?? [];
            $error = $json['error'] ?? null;
            $errorDescription = $json['error_description'] ?? null;

            Log::error('eBay2 token generation failed', [
                'http_status' => $status,
                'error' => $error,
                'error_description' => $errorDescription,
                'scope_parameter_sent' => false,
                'full_response_body' => substr($body, 0, 2000),
            ]);

            if ($error === 'invalid_grant') {
                throw new \Exception('eBay 2 refresh token expired. Please generate a new refresh token in eBay Developer Portal.');
            }

            if ($error === 'invalid_scope') {
                throw new \Exception('eBay 2 invalid_scope even though `scope` was omitted. Your refresh token likely does not include required Trading API scopes; regenerate the refresh token with Trading API access.');
            }

            throw new \Exception('Failed to get eBay 2 token: ' . ($errorDescription ?: $body));
        }

        $data = $response->json() ?? [];
        $accessToken = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        if (empty($accessToken)) {
            Log::error('eBay2 token generation succeeded but no access_token returned', [
                'full_response_body' => substr($body, 0, 2000),
            ]);
            throw new \Exception('No access token returned from eBay.');
        }

        $ttlSeconds = max(0, $expiresIn - 60);
        Cache::put($cacheKey, $accessToken, now()->addSeconds($ttlSeconds));

        return $accessToken;
    }


    public function reviseFixedPriceItem($itemId, $price, $quantity = null, $sku = null, $variationSpecifics = null, $variationSpecificsSet = null)
    {
        // Multi-variation listings ignore item-level StartPrice (ErrorCode 21916618).
        // When a SKU is provided, revise that variation only — same as EbayThreeApiService.
        $skuTrim = trim((string) $sku);
        $isVariationListing = $skuTrim !== '';

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $credentials = $xml->addChild('RequesterCredentials');

        $authToken = $this->generateBearerToken();
        $credentials->addChild('eBayAuthToken', $authToken ?? '');

        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');

        $item = $xml->addChild('Item');
        $item->addChild('ItemID', $itemId);

        if ($isVariationListing) {
            $variations = $item->addChild('Variations');
            $variation = $variations->addChild('Variation');
            $variation->addChild('SKU', $skuTrim);
            $variation->addChild('StartPrice', $price);
            if ($quantity !== null) {
                $variation->addChild('Quantity', $quantity);
            }

            if ($variationSpecifics) {
                $vs = $variation->addChild('VariationSpecifics');
                foreach ($variationSpecifics as $name => $value) {
                    $nvl = $vs->addChild('NameValueList');
                    $nvl->addChild('Name', $name);
                    $nvl->addChild('Value', $value);
                }
            }
            if ($variationSpecificsSet) {
                $vss = $item->addChild('VariationSpecificsSet');
                foreach ($variationSpecificsSet as $name => $values) {
                    $nvl = $vss->addChild('NameValueList');
                    $nvl->addChild('Name', $name);
                    foreach ($values as $val) {
                        $nvl->addChild('Value', $val);
                    }
                }
            }
        } else {
            $item->addChild('StartPrice', $price);
            if ($quantity !== null) {
                $item->addChild('Quantity', $quantity);
            }

            // Legacy path: only when caller supplies both specifics sets
            if ($variationSpecifics && $variationSpecificsSet) {
                $variations = $item->addChild('Variations');
                $variation = $variations->addChild('Variation');
                $variation->addChild('StartPrice', $price);
                if ($quantity !== null) {
                    $variation->addChild('Quantity', $quantity);
                }
                $vs = $variation->addChild('VariationSpecifics');
                foreach ($variationSpecifics as $name => $value) {
                    $nvl = $vs->addChild('NameValueList');
                    $nvl->addChild('Name', $name);
                    $nvl->addChild('Value', $value);
                }
                $vss = $item->addChild('VariationSpecificsSet');
                foreach ($variationSpecificsSet as $name => $values) {
                    $nvl = $vss->addChild('NameValueList');
                    $nvl->addChild('Name', $name);
                    foreach ($values as $val) {
                        $nvl->addChild('Value', $val);
                    }
                }
            }
        }

        $xmlBody = $xml->asXML();

        $headers = [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
            'X-EBAY-API-DEV-NAME'            => $this->devId,
            'X-EBAY-API-APP-NAME'            => $this->appId,
            'X-EBAY-API-CERT-NAME'           => $this->certId,
            'X-EBAY-API-CALL-NAME'           => 'ReviseFixedPriceItem',
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
            return [
                'success' => false,
                'message' => 'Invalid XML response',
                'raw' => $body,
            ];
        }

        $responseArray = json_decode(json_encode($xmlResp), true);
        $ack = $responseArray['Ack'] ?? 'Failure';

        if ($ack === 'Success' || $ack === 'Warning') {
            // Treat "item-level price ignored on variation listing" as failure (silent no-op).
            $warnErrors = $responseArray['Errors'] ?? [];
            if ($warnErrors !== [] && ! isset($warnErrors[0]) && is_array($warnErrors)) {
                $warnErrors = [$warnErrors];
            }
            if (! is_array($warnErrors)) {
                $warnErrors = [$warnErrors];
            }
            foreach ($warnErrors as $warnErr) {
                if (! is_array($warnErr)) {
                    continue;
                }
                $warnCode = (string) ($warnErr['ErrorCode'] ?? '');
                $warnMsg = (string) ($warnErr['LongMessage'] ?? $warnErr['ShortMessage'] ?? '');
                if (
                    $warnCode === '21916618'
                    || stripos($warnMsg, 'Item level start price will be ignored') !== false
                ) {
                    return [
                        'success' => false,
                        'message' => 'eBay2 ignored the price update because this is a multi-variation listing. Retry with the variation SKU.',
                        'errors' => [[
                            'code' => $warnCode ?: '21916618',
                            'message' => $warnMsg ?: 'Item level start price will be ignored on variation listings.',
                        ]],
                        'data' => $responseArray,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Item updated successfully.',
                'data' => $responseArray,
            ];
        }

        $errors = $responseArray['Errors'] ?? [];
        if ($errors !== [] && ! isset($errors[0]) && is_array($errors)) {
            $errors = [$errors];
        }
        if (! is_array($errors)) {
            $errors = [$errors];
        }
        $first = is_array($errors[0] ?? null) ? $errors[0] : [];
        $message = (string) ($first['LongMessage'] ?? $first['ShortMessage'] ?? $responseArray['message'] ?? 'Failed to update price on eBay2');
        if (! empty($first['ErrorCode']) && ! str_contains($message, (string) $first['ErrorCode'])) {
            $message = '[eBay #'.$first['ErrorCode'].'] '.$message;
        }

        // Variation SKU path used on a non-variation listing — retry item-level once.
        if (
            $isVariationListing
            && $this->ebayErrorLooksLikeNonVariationListing($errors, $message)
        ) {
            return $this->reviseFixedPriceItem($itemId, $price, $quantity, null, $variationSpecifics, $variationSpecificsSet);
        }

        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'data' => $responseArray,
        ];
    }

    /**
     * @param  list<mixed>  $errors
     */
    private function ebayErrorLooksLikeNonVariationListing(array $errors, string $message): bool
    {
        $blob = strtolower($message);
        foreach ($errors as $err) {
            if (! is_array($err)) {
                continue;
            }
            $blob .= ' '.strtolower((string) ($err['LongMessage'] ?? ''));
            $blob .= ' '.strtolower((string) ($err['ShortMessage'] ?? ''));
            $blob .= ' '.(string) ($err['ErrorCode'] ?? '');
        }

        return str_contains($blob, 'not a multi-variation')
            || str_contains($blob, 'not a multi-sku')
            || str_contains($blob, 'invalid multi-sku')
            || str_contains($blob, 'supplied with variations')
            || str_contains($blob, 'variations node')
            || str_contains($blob, 'does not have variations')
            || str_contains($blob, '21916587')
            || str_contains($blob, '21916613')
            || str_contains($blob, '21916317')
            || str_contains($blob, '21916635');
    }

    /**
     * Get item details from eBay Trading API (same pattern as eBay 1 / eBay 3).
     */
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

            $response = $this->tradingHttp(60)
                ->withHeaders($headers)
                ->withBody($xmlBody, 'text/xml')
                ->post($this->endpoint);

            $body = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);

            if ($xmlResp === false) {
                Log::warning('eBay2 GetItem: failed to parse XML response', ['itemId' => $itemId, 'body' => substr($body, 0, 1000)]);

                return null;
            }

            $responseArray = json_decode(json_encode($xmlResp), true);
            $ack           = $responseArray['Ack'] ?? 'Failure';

            if ($ack === 'Success' || $ack === 'Warning') {
                Log::debug('eBay2 GetItem success', ['itemId' => $itemId]);

                return $responseArray;
            }

            $errors = $responseArray['Errors'] ?? [];
            $errors = is_array($errors) ? $errors : [$errors];
            $errMsg = '';
            foreach ($errors as $err) {
                $errMsg .= ($errMsg ? '; ' : '') . ($this->parseEbayError(is_array($err) ? $err : ['ShortMessage' => (string) $err]));
            }
            Log::warning('eBay2 GetItem failed', ['itemId' => $itemId, 'ack' => $ack, 'errors' => $errors, 'parsed' => $errMsg]);

            return null;
        } catch (\Exception $e) {
            Log::warning('eBay2 GetItem exception', ['itemId' => $itemId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Description Master: return the eBay2 listing's raw Description HTML for one SKU (no DM2 parsing). Read-only.
     *
     * @return array{success: bool, message: string, html?: string}
     */
    public function fetchRawDescriptionHtml(string $identifier): array
    {
        if (trim($identifier) === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        try {
            $token = $this->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $resolved = $this->resolveEbayItemIdForPush(
            'ebay_2_metrics',
            $identifier,
            $token,
            $this->endpoint,
            $this->tradingApiHeadersBase()
        );
        $itemId = $resolved['item_id'] ?? null;
        if (! $itemId) {
            return ['success' => false, 'message' => 'No eBay2 listing found for this SKU (check ebay_2_metrics or seller inventory).'];
        }

        $resp = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $resp = $this->getItem($itemId);
            if ($resp !== null) {
                break;
            }
            if ($attempt < 3) {
                sleep([1, 2][$attempt - 1] ?? 2);
            }
        }

        if ($resp === null) {
            return ['success' => false, 'message' => 'GetItem failed for item '.$itemId.'.'];
        }

        $item = $resp['Item'] ?? null;
        if (! is_array($item)) {
            return ['success' => false, 'message' => 'Unexpected GetItem response (no Item).'];
        }

        $desc = $item['Description'] ?? '';
        if (is_array($desc)) {
            $desc = '';
        }
        $desc = html_entity_decode((string) $desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (trim($desc) === '') {
            return ['success' => false, 'message' => 'This listing has no description body.'];
        }

        return ['success' => true, 'message' => 'eBay2 listing description loaded.', 'html' => $desc];
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
     * Update listing title (eBay title max 80 chars).
     * GTC / fixed-price listings require ReviseFixedPriceItem; ReviseItem can
     * Ack Success without actually changing the live title.
     */
    public function updateTitle($itemId, $title)
    {
        $itemId = trim((string) $itemId);
        $title = mb_substr(trim((string) $title), 0, 80);

        if ($itemId === '') {
            Log::warning('eBay2 updateTitle: empty item ID');

            return ['success' => false, 'message' => 'Item ID is required.'];
        }
        if ($title === '') {
            Log::warning('eBay2 updateTitle: empty title', ['itemId' => $itemId]);

            return ['success' => false, 'message' => 'Title cannot be empty.'];
        }

        try {
            $authToken = $this->generateBearerToken();
            Log::info('eBay2 updateTitle: ReviseFixedPriceItem', [
                'itemId' => $itemId,
                'title' => $title,
            ]);

            return EbayTradingReviseItem::reviseListingTitle(
                (string) $this->endpoint,
                (string) $this->compatLevel,
                (string) $this->devId,
                (string) $this->appId,
                (string) $this->certId,
                (string) $this->siteId,
                (string) ($authToken ?? ''),
                $itemId,
                $title,
            );
        } catch (\Throwable $e) {
            Log::error('eBay2 updateTitle exception', [
                'itemId' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function generateEbayToken(): ?string
    {
        // Backwards-compatible wrapper:
        // consolidate to the same refresh-token flow as generateBearerToken()
        // (which omits `scope` and provides better invalid_grant handling).
        return $this->generateBearerToken();
    }
    
// ==========================================================================
 /**
     * Check API rate limits
     */
    public function getRateLimitForAPI(String $name, String $context)
    {
        $bearerToken = $this->generateEbayToken();
        $request= Http::withHeaders([
            'Authorization' => "Bearer {$bearerToken}"
        ]);
        
        if (config('filesystems.default') === 'local') {$request = $request->withoutVerifying();}

        $response=$request->get('https://api.ebay.com/developer/analytics/v1_beta/rate_limit', [
                'api_name' => $name,
                'api_context' => $context,
            ]);

        return $response->json();
    }
    public function getEbayInventory(){

        $token = $this->generateEbayToken();
         if (!$token) {
            Log::error('Failed to generate token.');
            return;
        }
        $listingData = $this->fetchAndParseReport('LMS_ACTIVE_INVENTORY_REPORT', null, $token);
        
        Log::info('Total Ebay2 inventory items collected: ' . count($listingData));
        foreach ($listingData as $sku => $data) {
        $sku = $data['sku'] ?? null;
        $quantity = $data['quantity'];
        
            // ProductStockMapping::updateOrCreate(
            //     ['sku' => $sku],
            //     ['inventory_ebay2'=>$quantity,]
            // );
            
            ProductStockMapping::where('sku', $sku)->update(['inventory_ebay2' => (int) $quantity]);    
        }
            return $listingData;

        $itemIdToSku = [];
        // foreach ($listingData as $row) {
        //     if (!empty($row['item_id']) && !empty($row['sku'])) {
        //         $itemIdToSku[$row['item_id']] = $row['sku'];
        //     }
        // }
    }

    public function fetchAndParseReport($reportType, $range, $token): array
{
    Log::info("Start Processing: $reportType");
    
    $apiUrl = 'https://api.ebay.com/sell/feed/v1/inventory_task';
    $payload = [
        'feedType' => $reportType,
        'format' => 'TSV_GZIP',
        'schemaVersion' => '1.0',
    ];

    try {
        // Create HTTP client with common settings
        $request = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])->timeout(60); // Add timeout

        // Disable SSL verification if needed (consider security implications)
        if (config('app.env') === 'local' || config('app.debug') === true) {
            $request = $request->withoutVerifying();
        }

        Log::info('Sending request to eBay2 API');
        $response = $request->post($apiUrl, $payload);
        
        // Check if request was successful
        if (!$response->successful()) {
            Log::error("API request failed", [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);
            return [];
        }

        $location = $response->header('Location');
        Log::info('Location header', ['location' => $location]);

        if (!$location) {
            Log::error("No 'Location' header returned");
            Log::error("Response headers", ['headers' => $response->headers()]);
            return [];
        }

        // Extract task ID from URL
        $taskId = basename($location); 
        Log::info("Task ID: $taskId");

        $status = null;
        $maxAttempts = 30; // 5 minutes max waiting (30 * 10 seconds)
        $attempts = 0;

        do {
            sleep(10);
            $attempts++;
            
            Log::info("Checking task status (attempt $attempts)");
            
            $statusRequest = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(30);

            if (config('app.env') === 'local' || config('app.debug') === true) {
                $statusRequest = $statusRequest->withoutVerifying();
            }

            $statusResponse = $statusRequest->get("https://api.ebay.com/sell/feed/v1/inventory_task/{$taskId}");
            
            if (!$statusResponse->successful()) {
                Log::error("Status check failed", [
                    'status' => $statusResponse->status(),
                    'body' => $statusResponse->body()
                ]);
                continue; // Continue waiting despite temporary failures
            }

            $responseData = $statusResponse->json();
            $status = $responseData['status'] ?? 'PENDING';
            Log::info("Task Status: $status");

            // Break if max attempts reached to prevent infinite loop
            if ($attempts >= $maxAttempts) {
                Log::error("Max attempts reached. Task did not complete in time.");
                return [];
            }
        
        } while (!in_array($status, ['COMPLETED', 'COMPLETED_WITH_ERROR', 'FAILED']));

        if ($status === 'FAILED') {
            Log::error("Inventory report task failed for task ID: $taskId");
            return [];
        }

        Log::info("Task completed with status: $status");
        $data = $this->downloadAndParseEbayReport($taskId, $token);
        
        return $data;

    } catch (\Exception $e) {
        Log::error("Exception in fetchAndParseReport: " . $e->getMessage());
        return [];
    }
}

public function downloadAndParseEbayReport(string $taskId, string $token): array
{  $data = [];
    Log::info("Downloading report for task: $taskId");
    
    $baseTaskUrl = "https://api.ebay.com/sell/feed/v1/task/{$taskId}/download_result_file";
    $filePath = storage_path("app/inventory_{$taskId}");
    $zipPath = $filePath . ".zip";
    $xmlPath = $filePath . ".xml";

    try {
        $request = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->timeout(120); // Longer timeout for file download

        if (config('app.env') === 'local' || config('app.debug') === true) {
            $request = $request->withoutVerifying();
        }

        Log::info("Downloading report from: $baseTaskUrl");
        $response = $request->get($baseTaskUrl);
        
        if (!$response->successful()) {
            Log::error("Download failed", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        }

        $content = $response->body();
        
        if (empty($content)) {
            Log::error("Empty response content");
            return [];
        }

        $magic = substr($content, 0, 2);
        Log::info("File type detection - Magic bytes: " . bin2hex($magic));

        // ZIP file: starts with "PK"
        if ($magic === "PK") {
            Log::info("Processing ZIP file");
            file_put_contents($zipPath, $content);

            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                $zip->extractTo(storage_path('app/'));
                $zip->close();

                // Find extracted XML file
                $extractedFiles = glob(storage_path('app/*.xml'));
                if (empty($extractedFiles)) {
                    Log::error("No XML file found in zip.");
                    @unlink($zipPath);
                    return [];
                }

                $xmlPath = $extractedFiles[0];
                $xml = simplexml_load_file($xmlPath);
                
                if (!$xml) {
                    Log::error("Failed to parse XML.");
                    @unlink($zipPath);
                    @unlink($xmlPath);
                    return [];
                }

                Log::info("Root Element: " . $xml->getName());
                Log::info("XML structure preview", json_decode(json_encode($xml), true));

              
                // Handle different XML structures
                if (isset($xml->ActiveInventoryReport->SKUDetails)) {
                    foreach ($xml->ActiveInventoryReport->SKUDetails as $item) {
                        $itemId = (string) ($item->ItemID ?? null);
                        if (!$itemId) continue;
                        
                        $data[] = [                            
                            'sku' => (string) ($item->SKU ?? ''),
                            'quantity' => (string) ($item->Quantity ?? ''),                            
                        ];

                        // Handle variations if any
                        if (!empty($item->Variations->Variation)) {
                            foreach ($item->Variations->Variation as $variation) {
                                $variationItemId = (string) ($variation->ItemID ?? $itemId);
                                if (!$variationItemId) continue;
                                
                                $data[] = [                                    
                                    'sku' => (string) ($variation->SKU ?? ''),
                                    'quantity' => (float) ($variation->Quantity ?? 0),
                                ];
                            }
                        }
                    }
                } else {
                    Log::warning("Unexpected XML structure. Trying alternative parsing.");
                    // Alternative parsing for different XML structures
                    foreach ($xml->children() as $child) {
                        if ($child->getName() === 'item' || isset($child->ItemID)) {
                            $itemId = (string) ($child->ItemID ?? null);
                            if (!$itemId) continue;
                            
                            $data[] = [
                                'item_id' => $itemId,
                                'sku' => (string) ($child->SKU ?? ''),
                                'price' => (float) ($child->Price ?? 0),
                            ];
                        }
                    }
                }

                @unlink($zipPath);
                @unlink($xmlPath);
                
                Log::info("Successfully parsed " . count($data) . " items from XML");
                Log::info('Sample parsed items:', array_slice($data, 0, 3));
                return $data;
            } else {
                Log::error("Failed to open ZIP file.");
                @unlink($zipPath);
                return [];
            }
        }

        // If not ZIP, check for GZ (GZIP compressed TSV)
        if (substr($content, 0, 2) === "\x1f\x8b") {
            Log::info("Processing GZIP compressed TSV file");
            $gzPath = $filePath . ".tsv.gz";
            $tsvPath = $filePath . ".tsv";
            
            file_put_contents($gzPath, $content);

            $gz = gzopen($gzPath, 'rb');
            if (!$gz) {
                Log::error("Failed to open GZ file");
                @unlink($gzPath);
                return [];
            }

            $tsv = fopen($tsvPath, 'wb');
            if (!$tsv) {
                Log::error("Failed to create TSV file");
                gzclose($gz);
                @unlink($gzPath);
                return [];
            }

            while (!gzeof($gz)) {
                fwrite($tsv, gzread($gz, 4096));
            }
            fclose($tsv);
            gzclose($gz);

            $lines = file($tsvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines || count($lines) < 2) {
                Log::error("No data found in TSV file");
                @unlink($gzPath);
                @unlink($tsvPath);
                return [];
            }

            $rows = array_map(function($line) {
                return str_getcsv($line, "\t");
            }, $lines);
            
            $headers = array_shift($rows);
            $data = [];

            Log::info("TSV Headers: " . implode(', ', $headers));

            foreach ($rows as $index => $row) {
                if (count($headers) !== count($row)) {
                    Log::warning("Skipping row $index - column count mismatch");
                    continue;
                }
                
                try {
                    $item = array_combine($headers, $row);
                    $itemId = $item['item_id'] ?? $item['itemId'] ?? null;
                    
                    if (!$itemId) {
                        Log::warning("Skipping row $index - no item ID found");
                        continue;
                    }

                    $data[] = [
                        'sku' => $item['sku'] ?? $item['SKU'] ?? null,
                        'quantity' => isset($item['Quantity']) ? (float) $item['Quantity'] : null,
                    ];
                } catch (\Exception $e) {
                    Log::warning("Error processing row $index: " . $e->getMessage());
                    continue;
                }
            }

            @unlink($gzPath);
            @unlink($tsvPath);
            
            Log::info("Successfully parsed " . count($data) . " items from TSV");
            Log::info('Sample parsed items:', array_slice($data, 0, 3));
            return $data;
        }

        // Unknown content type
        Log::error("Unknown file type", [
            'first_bytes' => bin2hex(substr($content, 0, 4)),
            'taskId' => $taskId,
            'content_length' => strlen($content)
        ]);
        
        // Log first 200 chars for debugging
        Log::debug("Content preview: " . substr($content, 0, 200));
        return [];

    } catch (\Throwable $e) {
        Log::error("Exception in downloadAndParseEbayReport: " . $e->getMessage());
        Log::error("Stack trace: " . $e->getTraceAsString());
        
        // Clean up any temporary files
        $tempFiles = [
            $zipPath ?? null,
            $xmlPath ?? null,
            $gzPath ?? null,
            $tsvPath ?? null
        ];
        
        foreach ($tempFiles as $tempFile) {
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
        
        return [];
    }
}
    

    // =====================================================


    public function getEbayInventory3()
{
    $token = $this->generateEbayToken();
    if (!$token) {
        Log::error('Failed to generate eBay token.');
        return [];
    }

    // ✅ Correct feed type (NO "LMS_" prefix)
    $reportType = 'LMS_ACTIVE_INVENTORY_REPORT';

    Log::info("Start Processing: $reportType");

    // ✅ Fixed URL: no trailing spaces
    $apiUrl = 'https://api.ebay.com/sell/feed/v1/inventory_task';

    // ✅ Correct schema version (v3.0 as of 2024)
    $payload = [
        'feedType' => $reportType,
        'format' => 'TSV_GZIP', // You can also use 'XML' if preferred
        'schemaVersion' => '1.0'
    ];

    // Log::info('Request Payload:', [$payload]);

    $request = Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
    ]);

    if (config('filesystems.default') === 'local') {
        $request = $request->withoutVerifying();
    }

    $response = $request->post($apiUrl, $payload);
     if (!$response->successful()) {
        Log::error('Failed to create inventory task', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        return [];
    }
    dd($response);

    if (!$response->successful()) {
        Log::error('Failed to create inventory task', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        return [];
    }

    $location = $response->header('Location');
    Log::info('Location header:', [$location]);

    if (!$location) {
        Log::error("No 'Location' header returned. Can't extract task ID.");
        logger()->error("Missing Location header", ['headers' => $response->headers()]);
        return [];
    }

    // ✅ Extract task ID correctly
    $taskId = basename($location);
    Log::info("Task ID: $taskId");

    // Poll until task is complete
    $status = 'PENDING';
    $maxAttempts = 30; // ~5 minutes max
    $attempts = 0;

    do {
        sleep(10);
        $attempts++;

        $statusRequest = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ]);

        if (config('filesystems.default') === 'local') {
            $statusRequest = $statusRequest->withoutVerifying();
        }

        // ✅ Fixed URL: no extra spaces
        $statusResponse = $statusRequest->get("https://api.ebay.com/sell/feed/v1/inventory_task/{$taskId}");

        if (!$statusResponse->successful()) {
            Log::warning('Failed to get task status', [
                'status' => $statusResponse->status(),
                'body' => $statusResponse->body()
            ]);
            continue;
        }

        $status = $statusResponse->json('status', 'PENDING');
        Log::info("Task status: $status (attempt $attempts)");

        if ($attempts >= $maxAttempts) {
            Log::error("Max polling attempts reached for task $taskId");
            return [];
        }

    } while (!in_array($status, ['COMPLETED', 'COMPLETED_WITH_ERROR', 'FAILED']));

    if ($status === 'FAILED') {
        Log::error("Inventory report task failed.", ['taskId' => $taskId]);
        return [];
    }

    Log::info('Downloading and parsing eBay report');

    // ✅ CORRECT download URL: must use /inventory_task/, not /task/
    $downloadUrl = "https://api.ebay.com/sell/feed/v1/inventory_task/{$taskId}/download_result_file";
    $filePath = storage_path("app/inventory_{$taskId}");

    try {
        $downloadRequest = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ]);

        if (config('filesystems.default') === 'local') {
            $downloadRequest = $downloadRequest->withoutVerifying();
        }

        $downloadResponse = $downloadRequest->get($downloadUrl);

        if (!$downloadResponse->successful()) {
            Log::error('Failed to download report', [
                'status' => $downloadResponse->status(),
                'body' => $downloadResponse->body()
            ]);
            return [];
        }

        $content = $downloadResponse->body();
        $magic = substr($content, 0, 2);

        // Handle ZIP (XML) format
        if ($magic === "PK") {
            $zipPath = $filePath . ".zip";
            file_put_contents($zipPath, $content);

            $zip = new \ZipArchive;
            if ($zip->open($zipPath) === true) {
                $zip->extractTo(storage_path('app/'));
                $zip->close();

                $extractedFiles = glob(storage_path('app/*.xml'));
                if (empty($extractedFiles)) {
                    Log::error("No XML file found in ZIP.");
                    @unlink($zipPath);
                    return [];
                }

                $xmlPath = $extractedFiles[0];
                $xml = simplexml_load_file($xmlPath);
                if (!$xml) {
                    Log::error("Failed to parse XML.");
                    @unlink($zipPath);
                    @unlink($xmlPath);
                    return [];
                }

                // ✅ Handle XML namespace (critical for v3.0)
                $xml->registerXPathNamespace('ns', 'http://www.ebay.com/marketplace/sell/v1/services');
                $inventoryItems = $xml->xpath('//ns:ActiveInventory');

                $data = [];
                foreach ($inventoryItems as $item) {
                    $itemId = (string)($item->ItemID ?? '');
                    if (empty($itemId)) continue;

                    $data[] = [
                        'item_id' => $itemId,
                        'sku' => (string)($item->SKU ?? ''),
                        'price' => (float)($item->Price ?? 0),
                    ];
                }

                @unlink($zipPath);
                @unlink($xmlPath);
                Log::info("Parsed " . count($data) . " XML items.");
                return $data;
            } else {
                Log::error("Failed to open ZIP file.");
                @unlink($zipPath);
                return [];
            }
        }

        // Handle GZIP (TSV) format
        if (substr($content, 0, 2) === "\x1f\x8b") {
            $gzPath = $filePath . ".tsv.gz";
            $tsvPath = $filePath . ".tsv";
            file_put_contents($gzPath, $content);

            $gz = gzopen($gzPath, 'rb');
            $tsv = fopen($tsvPath, 'wb');
            while (!gzeof($gz)) {
                fwrite($tsv, gzread($gz, 4096));
            }
            fclose($tsv);
            gzclose($gz);

            $lines = @file($tsvPath, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
            if (!$lines || count($lines) < 2) {
                Log::error("TSV file is empty or invalid.");
                @unlink($gzPath);
                @unlink($tsvPath);
                return [];
            }

            $rows = array_map('str_getcsv', $lines, array_fill(0, count($lines), "\t"));
            $headers = array_shift($rows);
            $data = [];

            foreach ($rows as $row) {
                if (count($row) !== count($headers)) continue;
                $item = array_combine($headers, $row);
                $itemId = $item['item_id'] ?? null; // ✅ correct column name
                if (!$itemId) continue;

                $data[] = [
                    'item_id' => $itemId,
                    'sku' => $item['sku'] ?? '',
                    'price' => isset($item['price']) ? (float)$item['price'] : 0,
                ];
            }

            @unlink($gzPath);
            @unlink($tsvPath);
            Log::info("Parsed " . count($data) . " TSV items.");
            return $data;
        }

        // Unknown format
        Log::error("Unknown report file format", [
            'first_bytes_hex' => bin2hex(substr($content, 0, 8)),
            'taskId' => $taskId,
        ]);
        return [];

    } catch (\Throwable $e) {
        Log::error("Exception during report download/parsing: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return [];
    }
}

    public function getEbayInventory1(){
         $token = $this->generateEbayToken();
        if (!$token) { Log::error('Failed to generate token.'); return; }
        $reportType='LMS_ACTIVE_INVENTORY_REPORT';

        // $listingData = $this->fetchAndParseReport('LMS_ACTIVE_INVENTORY_REPORT', null, $token);
        Log::info("Start Processing: $reportType");

        $apiUrl = 'https://api.ebay.com/sell/feed/v1/inventory_task';

        $payload = ['feedType' => $reportType,'format' => 'TSV_GZIP','schemaVersion' => '1.0'];
         Log::info('Request Payload:', [$payload]);

        $request=Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ]);
         if (config('filesystems.default') === 'local') {$request = $request->withoutVerifying();}
        $response = $request->post($apiUrl, $payload);

        $location = $response->header('Location');
         Log::info('location', [$location]);

        if (!$location) {
            Log::error("No 'Location' header returned. Can't extract task ID.");
            logger()->error("Missing Location header", ['headers' => $response->headers()]);
            return [];
        }

        // Step 2: Extract the task ID from URL
        $taskId = basename($location); 
         Log::info("Task ID: $taskId");

         Log::info("Task/Report ID: $taskId");

        $status = null;
        $downloadUrl = null;


          do {
            sleep(10);
            $request2=Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ]);
            if (config('filesystems.default') === 'local') {$request2 = $request2->withoutVerifying();}
            $statusResponse = $request2->get("https://api.ebay.com/sell/feed/v1/inventory_task/{$taskId}");
        
            $status = $statusResponse['status'] ?? 'PENDING';
             Log::info("Status: $status");
        
        } while (!in_array($status, ['COMPLETED', 'COMPLETED_WITH_ERROR', 'FAILED']));
        
        if ($status === 'FAILED') {
             Log::error("Inventory report task failed.");
            return [];
        }


        info('downloadAndParseEbayReport');
        $baseTaskUrl = "https://api.ebay.com/sell/feed/v1/task/{$taskId}/download_result_file";
        $filePath = storage_path("app/inventory_{$taskId}");
        $zipPath = $filePath . ".zip";
        $xmlPath = $filePath . ".xml";

         Log::info("Downloading report from: $baseTaskUrl");

        try {
            $request3=Http::withHeaders(['Authorization' => 'Bearer ' . $token,]);

            if (config('filesystems.default') === 'local') {$request3 = $request3->withoutVerifying();}

            $response = $request3->get($baseTaskUrl);

            $content = $response->body();
            $magic = substr($content, 0, 2);

            // ZIP file: starts with "PK"
            if ($magic === "PK") {
                file_put_contents($zipPath, $content);

                $zip = new ZipArchive;
                if ($zip->open($zipPath) === TRUE) {
                    $zip->extractTo(storage_path('app/'));
                    $zip->close();

                    // Find extracted XML file
                    $extractedFiles = glob(storage_path('app/*.xml'));
                    if (empty($extractedFiles)) {
                        logger()->error("No XML file found in zip.");
                        return [];
                    }

                    $xmlPath = $extractedFiles[0];
                    $xml = simplexml_load_file($xmlPath);
                    if (!$xml) {
                        logger()->error("Failed to parse XML.");
                        return [];
                    }

                    logger()->info("Root Element: " . $xml->getName());
                    logger()->info("XML Preview", json_decode(json_encode($xml), true));

                    // Example conversion (customize based on XML structure)
                    $data = [];
                    foreach ($xml->ActiveInventoryReport->SKUDetails as $item) {
                        $itemId = (string) $item->ItemID ?? null;
                        if (!$itemId) continue;
                    
                        $data[] = [
                            'item_id' => $itemId,
                            'sku' => $item->SKU ?? '',
                            'price' => (float) ($item->Price ?? 0),
                        ];
                    
                        // Handle variations if any
                        if (!empty($item->Variations->Variation)) {
                            foreach ($item->Variations->Variation as $variation) {
                                $itemId = (string) $item->ItemID ?? null;
                                $data[] = [
                                    'item_id' => $itemId,
                                    'sku' => $variation->SKU ?? '',
                                    'price' => (float) ($variation->Price ?? 0),
                                ];
                            }
                        }
                    }

                    @unlink($zipPath);
                    @unlink($xmlPath);
                    
                     Log::info("Parsed " . count($data) . " XML items.");
                    logger()->info('Sample parsed items:', array_slice($data, 0, 5));
                    
                    return $data;
                } else {
                     Log::error("Failed to open ZIP file.");
                    return [];
                }
            }

            // If not ZIP, check for GZ
            if (substr($content, 0, 2) === "\x1f\x8b") {
                $gzPath = $filePath . ".tsv.gz";
                $tsvPath = $filePath . ".tsv";
                file_put_contents($gzPath, $content);

                $gz = gzopen($gzPath, 'rb');
                $tsv = fopen($tsvPath, 'wb');
                while (!gzeof($gz)) {
                    fwrite($tsv, gzread($gz, 4096));
                }
                fclose($tsv);
                gzclose($gz);

                $lines = file($tsvPath, FILE_SKIP_EMPTY_LINES);
                if (!$lines || count($lines) < 2) return [];

                $rows = array_map('str_getcsv', $lines, array_fill(0, count($lines), "\t"));
                $headers = array_shift($rows);
                $data = [];

                foreach ($rows as $row) {
                    if (count($headers) !== count($row)) continue;
                    $item = array_combine($headers, $row);
                    $itemId = $item['itemId'] ?? null;
                    if (!$itemId) continue;

                    $data[$itemId] = [
                        'price' => $item['price'] ?? null,
                        'sku' => $item['sku'] ?? null,
                    ];
                }

                @unlink($gzPath);
                @unlink($tsvPath);
                 Log::info("Parsed " . count($data) . " TSV items.");
                return $data;
            }

            // Unknown content
             Log::error("Unknown file type", [
                'first_bytes' => bin2hex(substr($content, 0, 4)),
                'taskId' => $taskId,
            ]);
            return [];

        } catch (\Throwable $e) {
             Log::error("Exception: " . $e->getMessage());
            return [];
        }

    }
    public function getValidTrackingRate()
{
    $accessToken = $this->generateBearerToken();
    $url = "https://api.ebay.com/sell/analytics/v1/seller_standards_profile";

    $response = Http::withToken($accessToken)
        ->withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->get($url);

    if ($response->failed()) {
        return [
            'success' => false,
            'message' => 'Failed to fetch seller standards: ' . $response->body(),
        ];
    }

    $data = $response->json();

    // Get the first profile
    $profile = $data['standardsProfiles'][1] ?? null;

    if (!$profile || empty($profile['metrics'])) {
        return [
            'success' => false,
            'message' => 'Standards profile or metrics not found',
            'data' => $data,
        ];
    }
    $vtrMetric = null;
    foreach ($profile['metrics'] as $metric) {
        if (($metric['metricKey'] ?? null) === 'VALID_TRACKING_UPLOADED_WITHIN_HANDLING_RATE') {
            $vtrMetric = $metric;
            break;
        }
    }

    if (!$vtrMetric) {
        return [
            'success' => false,
            'message' => 'Valid Tracking Rate metric not found',
            'data' => $data,
        ];
    }

    return [
        'success' => true,
        'Channels' => 'Ebay1',
        'vtr' => $vtrMetric['value']['value'] ?? null,
        'numerator' => $vtrMetric['value']['numerator'] ?? null,
        'denominator' => $vtrMetric['value']['denominator'] ?? null,
        'thresholdLower' => $vtrMetric['thresholdLowerBound']['value'] ?? null,
    ];
 }

    /**
     * Push bullet points to the visible eBay seller Description while preserving the rest of the description HTML.
     *
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        if (trim($identifier) === '') {
            return ['success' => false, 'message' => 'SKU (or item_id) is required.'];
        }

        $row = $this->findMetricRowBySkuOrAlternateIds('ebay_2_metrics', $identifier, ['item_id']);
        try {
            $token = $this->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $resolved = $this->resolveEbayItemIdForPush(
            'ebay_2_metrics',
            $identifier,
            $token,
            $this->endpoint,
            $this->tradingApiHeadersBase()
        );
        $itemId = $resolved['item_id'];
        $row = $resolved['row'] ?? $row;
        if (! $itemId) {
            return ['success' => false, 'message' => 'No eBay2 listing found for this SKU or item_id (check ebay_2_metrics or Inventory / GetSellerList).'];
        }

        $getItemResponse = $this->getItem((string) $itemId);
        if (! is_array($getItemResponse)) {
            return ['success' => false, 'message' => 'Could not fetch current eBay 2 item before bullet update.'];
        }

        $currentDescription = $getItemResponse['Item']['Description'] ?? '';
        if (is_array($currentDescription)) {
            $currentDescription = '';
        }
        $replacementMeta = [];
        $updatedDescription = EbayTradingReviseItem::replaceFirstDescriptionBulletList((string) $currentDescription, $bulletPoints, $replacementMeta);
        $bulletCount = count(array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($bulletPoints)) ?: []), fn ($line) => $line !== '')));

        Log::info('eBay2 bullet description prepared', [
            'sku_or_identifier' => $identifier,
            'item_id' => (string) $itemId,
            'strategy' => $replacementMeta['strategy'] ?? null,
            'offset' => $replacementMeta['offset'] ?? null,
            'replaced_length' => $replacementMeta['replaced_length'] ?? null,
            'removed_extra_top_list_count' => $replacementMeta['removed_extra_top_list_count'] ?? 0,
            'removed_extra_top_list_length' => $replacementMeta['removed_extra_top_list_length'] ?? 0,
            'description_offset' => $replacementMeta['description_offset'] ?? null,
            'bullet_count' => $bulletCount,
            'target_api_field' => 'Item.Description',
            'target_section' => 'top bullet block before product description',
            'seller_html_changed_for_bullet_section' => sha1((string) $currentDescription) !== sha1($updatedDescription),
            'current_seller_html_length' => strlen((string) $currentDescription),
            'updated_seller_html_length' => strlen($updatedDescription),
            'current_seller_html_sha1' => sha1((string) $currentDescription),
            'updated_seller_html_sha1' => sha1($updatedDescription),
        ]);

        $result = EbayTradingReviseItem::reviseItemDescription(
            $this->endpoint,
            $this->compatLevel,
            $this->devId,
            $this->appId,
            $this->certId,
            $this->siteId,
            $token,
            (string) $itemId,
            $updatedDescription,
            'seller description bullet section'
        );

        Log::info('eBay2 bullet description push result', [
            'sku_or_identifier' => $identifier,
            'item_id' => (string) $itemId,
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'strategy' => $replacementMeta['strategy'] ?? null,
        ]);

        return $result;
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateListingImages(string $identifier, array $imageUrls): array
    {
        return $this->updateImages($identifier, $imageUrls);
    }

    /**
     * Image Master: push up to 12 image URLs and persist image_urls in ebay_2_metrics on success.
     *
     * @param  list<string>  $images
     * @return array{success: bool, message: string}
     */
    public function updateImages(string $identifier, array $images): array
    {
        if (trim($identifier) === '') {
            return ['success' => false, 'message' => 'SKU (or item_id) is required.'];
        }

        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 12);
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        $row = $this->findMetricRowBySkuOrAlternateIds('ebay_2_metrics', $identifier, ['item_id']);
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
            return ['success' => false, 'message' => 'No eBay2 listing found for this SKU or item_id (check ebay_2_metrics or Inventory / GetSellerList).'];
        }

        $res = EbayTradingReviseItem::reviseItemImages(
            $this->endpoint,
            $this->compatLevel,
            $this->devId,
            $this->appId,
            $this->certId,
            $this->siteId,
            $token,
            (string) $itemId,
            $images
        );

        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $urlsForMetrics = isset($res['normalized_urls']) && is_array($res['normalized_urls'])
            ? array_values($res['normalized_urls'])
            : $images;

        $saved = $this->saveImageUrlsToMetrics('ebay_2_metrics', $identifier, $row, $urlsForMetrics);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'eBay2 images updated.').' Metrics save failed.';
        }

        $res['normalized_urls'] = $urlsForMetrics;

        return $res;
    }

    /**
     * @param  list<string>  $videoUrls
     * @return array{success: bool, message: string}
     */
    public function updateListingVideos(string $identifier, array $videoUrls): array
    {
        return $this->updateVideos($identifier, $videoUrls);
    }

    /**
     * Video Master: push product video URL(s) via Trading API VideoDetails.
     *
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        if (trim($identifier) === '') {
            return ['success' => false, 'message' => 'SKU (or item_id) is required.'];
        }

        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 3);
        if ($videos === []) {
            return ['success' => false, 'message' => 'At least one video URL is required.'];
        }

        $row = $this->findMetricRowBySkuOrAlternateIds('ebay_2_metrics', $identifier, ['item_id']);
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
            return ['success' => false, 'message' => 'No eBay2 listing found for this SKU or item_id (check ebay_2_metrics or Inventory / GetSellerList).'];
        }

        $res = EbayTradingReviseItem::reviseItemVideos(
            $this->endpoint,
            $this->compatLevel,
            $this->devId,
            $this->appId,
            $this->certId,
            $this->siteId,
            $token,
            (string) $itemId,
            $videos
        );

        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $urlsForMetrics = isset($res['normalized_urls']) && is_array($res['normalized_urls'])
            ? array_values($res['normalized_urls'])
            : $videos;

        $sku = trim((string) ($row->sku ?? $identifier));
        $saved = $this->saveVideoUrlsToMetricsRow('ebay_2_metrics', $sku, $urlsForMetrics);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'eBay2 video updated.').' Metrics save failed.';
        }

        $res['normalized_urls'] = $urlsForMetrics;

        return $res;
    }

    /**
     * @param  list<string>  $images
     */
    private function saveImageUrlsToMetrics(string $table, string $identifier, ?object $row, array $images): bool
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return false;
            }
            $payload = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }

            $sku = trim((string) ($row->sku ?? $identifier));
            if ($sku === '') {
                return false;
            }

            $update = [];
            if (Schema::hasColumn($table, 'image_urls')) {
                $update['image_urls'] = $payload;
            }
            if (Schema::hasColumn($table, 'image_master_json')) {
                $update['image_master_json'] = $payload;
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table($table)->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn($table, 'created_at')) {
                DB::table($table)->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('eBay2 metrics image_urls save failed', ['table' => $table, 'identifier' => $identifier, 'error' => $e->getMessage()]);

            return false;
        }
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
    public function updateDescription(string $identifier, string $description): array
    {
        return $this->updateProductDescription($identifier, $description);
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

        $row = $this->findMetricRowBySkuOrAlternateIds('ebay_2_metrics', $identifier, ['item_id']);
        try {
            $token = $this->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $resolved = $this->resolveEbayItemIdForPush(
            'ebay_2_metrics',
            $identifier,
            $token,
            $this->endpoint,
            $this->tradingApiHeadersBase()
        );
        $itemId = $resolved['item_id'];
        $row = $resolved['row'] ?? $row;
        if (! $itemId) {
            return ['success' => false, 'message' => 'No eBay2 listing found for this SKU or item_id.'];
        }

        $html = '<div class="product-description">'.
            DescriptionWithImagesFormatter::buildHtmlWithImages(
                $description,
                (string) $identifier,
                isset($row->sku) ? (string) $row->sku : (string) $identifier,
                'Product Image',
                12
            )['html'].
            '</div>';

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

    public function isConfigured(): bool
    {
        $appId = config('services.ebay2.app_id', env('EBAY_2_APP_ID'));
        $certId = config('services.ebay2.cert_id', env('EBAY_2_CERT_ID'));
        $refresh = config('services.ebay2.refresh_token', env('EBAY_2_REFRESH_TOKEN'));

        return trim((string) $appId) !== ''
            && trim((string) $certId) !== ''
            && trim((string) $refresh) !== '';
    }

    /**
     * Create a new fixed-price listing on Ebay 2 (Trading API AddFixedPriceItem).
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, item_id?: string|null, raw?: string}
     */
    public function addFixedPriceItem(array $payload): array
    {
        $sku = trim((string) ($payload['sku'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $description = (string) ($payload['description'] ?? '');
        $price = (float) ($payload['price'] ?? 0);
        $quantity = (int) ($payload['quantity'] ?? 0);
        $categoryId = trim((string) ($payload['primary_category_id'] ?? ''));
        $conditionId = trim((string) ($payload['condition_id'] ?? '1000')) ?: '1000';
        $images = $payload['images'] ?? [];
        if (! is_array($images)) {
            $images = [];
        }
        $images = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $images)));

        if ($title === '' || $description === '' || $price <= 0 || $categoryId === '' || $images === []) {
            return ['success' => false, 'message' => 'Missing required fields for AddFixedPriceItem.'];
        }

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><AddFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            $credentials->addChild('eBayAuthToken', $this->generateBearerToken() ?? '');
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');

            $item = $xml->addChild('Item');
            $item->addChild('Title', htmlspecialchars($title, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $item->addChild('Description', htmlspecialchars($description, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $item->addChild('SKU', htmlspecialchars($sku, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $item->addChild('Currency', 'USD');
            $item->addChild('Country', trim((string) ($payload['location_country'] ?? 'US')) ?: 'US');
            $item->addChild('ListingDuration', trim((string) ($payload['duration'] ?? 'GTC')) ?: 'GTC');
            $item->addChild('ListingType', 'FixedPriceItem');
            $item->addChild('StartPrice', number_format($price, 2, '.', ''));
            $item->addChild('Quantity', (string) max(0, $quantity));
            $item->addChild('ConditionID', $conditionId);

            $city = trim((string) ($payload['location_city'] ?? ''));
            $postal = trim((string) ($payload['location_postal_code'] ?? ''));
            $location = trim($city . ($postal !== '' ? ' ' . $postal : ''));
            if ($location !== '') {
                $item->addChild('Location', htmlspecialchars($location, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
            if ($postal !== '') {
                $item->addChild('PostalCode', htmlspecialchars($postal, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }

            $primary = $item->addChild('PrimaryCategory');
            $primary->addChild('CategoryID', $categoryId);
            $secondaryId = trim((string) ($payload['secondary_category_id'] ?? ''));
            if ($secondaryId !== '') {
                $secondary = $item->addChild('SecondaryCategory');
                $secondary->addChild('CategoryID', $secondaryId);
            }

            $pictureDetails = $item->addChild('PictureDetails');
            foreach (array_slice($images, 0, 12) as $url) {
                $pictureDetails->addChild('PictureURL', htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
            if (! empty($payload['gallery_plus'])) {
                $pictureDetails->addChild('GalleryType', 'Plus');
            }

            $shippingId = trim((string) ($payload['shipping_policy_id'] ?? ''));
            $paymentId = trim((string) ($payload['payment_policy_id'] ?? ''));
            $returnId = trim((string) ($payload['return_policy_id'] ?? ''));
            if ($shippingId !== '' || $paymentId !== '' || $returnId !== '') {
                $profiles = $item->addChild('SellerProfiles');
                if ($shippingId !== '') {
                    $profiles->addChild('SellerShippingProfile')->addChild('ShippingProfileID', $shippingId);
                }
                if ($paymentId !== '') {
                    $profiles->addChild('SellerPaymentProfile')->addChild('PaymentProfileID', $paymentId);
                }
                if ($returnId !== '') {
                    $profiles->addChild('SellerReturnProfile')->addChild('ReturnProfileID', $returnId);
                }
            }

            // Required identifiers: Brand / MPN (item specifics) + UPC (ProductListingDetails)
            $brand = trim((string) ($payload['brand'] ?? ''));
            $mpn = trim((string) ($payload['mpn'] ?? ''));
            $upc = trim((string) ($payload['upc'] ?? ''));
            if ($brand === '') {
                $brand = trim((string) config('listing_manager.default_brand', '5 Core')) ?: '5 Core';
            }
            if ($mpn === '') {
                $mpn = $sku;
            }

            $specifics = is_array($payload['item_specifics'] ?? null) ? $payload['item_specifics'] : [];
            if ($brand !== '') {
                $specifics['Brand'] = $brand;
            }
            if ($mpn !== '') {
                $specifics['MPN'] = $mpn;
            }
            if ($upc !== '') {
                $specifics['UPC'] = $upc;
            }

            if ($specifics !== []) {
                $itemSpecifics = $item->addChild('ItemSpecifics');
                foreach ($specifics as $name => $value) {
                    $name = trim((string) $name);
                    $value = trim((string) $value);
                    if ($name === '' || $value === '') {
                        continue;
                    }
                    $nvl = $itemSpecifics->addChild('NameValueList');
                    $nvl->addChild('Name', htmlspecialchars($name, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                    $nvl->addChild('Value', htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                }
            }

            if ($upc !== '') {
                $pld = $item->addChild('ProductListingDetails');
                $pld->addChild('UPC', htmlspecialchars($upc, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }

            $length = (float) ($payload['package_length'] ?? 0);
            $width = (float) ($payload['package_width'] ?? 0);
            $height = (float) ($payload['package_height'] ?? 0);
            $lb = (float) ($payload['package_weight_lb'] ?? 0);
            $oz = (float) ($payload['package_weight_oz'] ?? 0);
            if ($length > 0 || $width > 0 || $height > 0 || $lb > 0 || $oz > 0) {
                $package = $item->addChild('ShippingPackageDetails');
                if ($length > 0) {
                    $package->addChild('PackageLength', (string) $length);
                }
                if ($width > 0) {
                    $package->addChild('PackageWidth', (string) $width);
                }
                if ($height > 0) {
                    $package->addChild('PackageDepth', (string) $height);
                }
                $weightMajor = (int) floor($lb);
                $weightMinor = (int) round($oz + (($lb - $weightMajor) * 16));
                if ($weightMajor > 0 || $weightMinor > 0) {
                    $package->addChild('WeightMajor', (string) $weightMajor);
                    $package->addChild('WeightMinor', (string) $weightMinor);
                }
            }

            if (! empty($payload['best_offer'])) {
                $item->addChild('BestOfferDetails')->addChild('BestOfferEnabled', 'true');
            }
            if (! empty($payload['private_listing'])) {
                $item->addChild('PrivateListing', 'true');
            }

            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
                'X-EBAY-API-DEV-NAME' => $this->devId,
                'X-EBAY-API-APP-NAME' => $this->appId,
                'X-EBAY-API-CERT-NAME' => $this->certId,
                'X-EBAY-API-CALL-NAME' => 'AddFixedPriceItem',
                'X-EBAY-API-SITEID' => $this->siteId,
                'Content-Type' => 'text/xml',
            ];

            $response = Http::timeout(90)
                ->withHeaders($headers)
                ->withBody($xml->asXML(), 'text/xml')
                ->post($this->endpoint);

            $body = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            if ($xmlResp === false) {
                Log::warning('eBay2 AddFixedPriceItem: invalid XML', [
                    'sku' => $sku,
                    'status' => $response->status(),
                    'body' => substr($body, 0, 1500),
                ]);

                return ['success' => false, 'message' => 'Invalid XML response from eBay.', 'raw' => $body];
            }

            $data = json_decode(json_encode($xmlResp), true) ?: [];
            $ack = $data['Ack'] ?? 'Failure';
            if ($ack === 'Success' || $ack === 'Warning') {
                $itemId = trim((string) ($data['ItemID'] ?? ''));

                return [
                    'success' => true,
                    'message' => $itemId !== '' ? "Published to Ebay 2 (ItemID {$itemId})." : 'Published to Ebay 2.',
                    'item_id' => $itemId !== '' ? $itemId : null,
                    'raw' => $body,
                ];
            }

            $errors = $data['Errors'] ?? null;
            $messages = [];
            if (is_array($errors)) {
                $list = isset($errors[0]) ? $errors : [$errors];
                foreach ($list as $err) {
                    $messages[] = trim((string) ($err['LongMessage'] ?? $err['ShortMessage'] ?? 'eBay error'));
                }
            }

            return [
                'success' => false,
                'message' => $messages !== [] ? implode(' | ', $messages) : 'eBay rejected AddFixedPriceItem.',
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('eBay2 AddFixedPriceItem failed: '.$e->getMessage(), ['sku' => $sku]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fast quantity (and optional price) update without GetItem.
     *
     * @return array{success: bool, message: string, data?: array, raw?: string}
     */
    public function reviseInventoryStatus(string $itemId, int $quantity, ?string $sku = null, ?float $price = null): array
    {
        $itemId = trim($itemId);
        $sku = $sku !== null ? trim($sku) : null;
        if ($itemId === '') {
            return ['success' => false, 'message' => 'ItemID is required.'];
        }

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseInventoryStatusRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            $credentials->addChild('eBayAuthToken', $this->generateBearerToken() ?? '');
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');

            $status = $xml->addChild('InventoryStatus');
            $status->addChild('ItemID', $itemId);
            if ($sku !== null && $sku !== '') {
                $status->addChild('SKU', $sku);
            }
            $status->addChild('Quantity', (string) max(0, $quantity));
            if ($price !== null && $price > 0) {
                $status->addChild('StartPrice', number_format($price, 2, '.', ''));
            }

            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
                'X-EBAY-API-DEV-NAME' => $this->devId,
                'X-EBAY-API-APP-NAME' => $this->appId,
                'X-EBAY-API-CERT-NAME' => $this->certId,
                'X-EBAY-API-CALL-NAME' => 'ReviseInventoryStatus',
                'X-EBAY-API-SITEID' => $this->siteId,
                'Content-Type' => 'text/xml',
            ];

            $response = $this->tradingHttp(60)
                ->withHeaders($headers)
                ->withBody($xml->asXML(), 'text/xml')
                ->post($this->endpoint);

            $body = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            if ($xmlResp === false) {
                Log::warning('eBay2 ReviseInventoryStatus: invalid XML', [
                    'itemId' => $itemId,
                    'sku' => $sku,
                    'status' => $response->status(),
                    'body' => substr($body, 0, 1000),
                ]);

                return ['success' => false, 'message' => 'Invalid XML response from eBay.', 'raw' => $body];
            }

            $data = json_decode(json_encode($xmlResp), true) ?: [];
            $ack = $data['Ack'] ?? 'Failure';
            $errBlob = json_encode($data['Errors'] ?? []);
            if ($this->listingLooksEnded($errBlob) || $this->listingLooksEnded((string) ($data['Errors']['LongMessage'] ?? ''))) {
                $msg = $this->flattenEbayErrors($data);
                Log::warning('eBay2 ReviseInventoryStatus: listing ended', [
                    'itemId' => $itemId,
                    'sku' => $sku,
                    'qty' => $quantity,
                    'message' => $msg,
                ]);

                return ['success' => false, 'ended' => true, 'message' => $msg ?: 'Listing ended.', 'data' => $data];
            }

            if ($ack === 'Success' || $ack === 'Warning') {
                $returnedQty = $this->extractReturnedInventoryQuantity($data);

                return [
                    'success' => true,
                    'quantity_confirmed' => $returnedQty === null || $returnedQty === $quantity,
                    'returned_qty' => $returnedQty,
                    'message' => 'Inventory updated.',
                    'data' => $data,
                ];
            }

            $msg = $this->flattenEbayErrors($data) ?: 'ReviseInventoryStatus failed.';

            Log::warning('eBay2 ReviseInventoryStatus failed', [
                'itemId' => $itemId,
                'sku' => $sku,
                'qty' => $quantity,
                'ack' => $ack,
                'message' => $msg,
            ]);

            return [
                'success' => false,
                'ended' => $this->listingLooksEnded($msg),
                'message' => $msg,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::warning('eBay2 ReviseInventoryStatus exception', [
                'itemId' => $itemId,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Up to 4 inventory rows per Trading API call (eBay ReviseInventoryStatus max).
     *
     * @param  list<array{item_id: string, sku?: string|null, quantity: int, price?: float|null}>  $items
     * @return array{success: bool, message: string, data?: array, ended?: bool}
     */
    public function reviseInventoryStatusMany(array $items): array
    {
        $rows = [];
        foreach (array_slice(array_values($items), 0, 4) as $item) {
            $itemId = trim((string) ($item['item_id'] ?? ''));
            if ($itemId === '') {
                continue;
            }
            $rows[] = [
                'item_id' => $itemId,
                'sku' => trim((string) ($item['sku'] ?? '')),
                'quantity' => max(0, (int) ($item['quantity'] ?? 0)),
                'price' => isset($item['price']) && is_numeric($item['price']) && (float) $item['price'] > 0
                    ? (float) $item['price']
                    : null,
            ];
        }
        if ($rows === []) {
            return ['success' => false, 'message' => 'No inventory rows to revise.'];
        }
        if (count($rows) === 1) {
            $one = $rows[0];

            return $this->reviseInventoryStatus(
                $one['item_id'],
                $one['quantity'],
                $one['sku'] !== '' ? $one['sku'] : null,
                $one['price']
            );
        }

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseInventoryStatusRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            $credentials->addChild('eBayAuthToken', $this->generateBearerToken() ?? '');
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');
            foreach ($rows as $row) {
                $status = $xml->addChild('InventoryStatus');
                $status->addChild('ItemID', $row['item_id']);
                if ($row['sku'] !== '') {
                    $status->addChild('SKU', $row['sku']);
                }
                $status->addChild('Quantity', (string) $row['quantity']);
                if ($row['price'] !== null) {
                    $status->addChild('StartPrice', number_format($row['price'], 2, '.', ''));
                }
            }

            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
                'X-EBAY-API-DEV-NAME' => $this->devId,
                'X-EBAY-API-APP-NAME' => $this->appId,
                'X-EBAY-API-CERT-NAME' => $this->certId,
                'X-EBAY-API-CALL-NAME' => 'ReviseInventoryStatus',
                'X-EBAY-API-SITEID' => $this->siteId,
                'Content-Type' => 'text/xml',
            ];

            $response = $this->tradingHttp(60)
                ->withHeaders($headers)
                ->withBody($xml->asXML(), 'text/xml')
                ->post($this->endpoint);

            $body = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            if ($xmlResp === false) {
                return ['success' => false, 'message' => 'Invalid XML response from eBay.', 'raw' => $body];
            }

            $data = json_decode(json_encode($xmlResp), true) ?: [];
            $ack = $data['Ack'] ?? 'Failure';
            $msg = $this->flattenEbayErrors($data);
            if ($ack === 'Success' || $ack === 'Warning') {
                return [
                    'success' => true,
                    'quantity_confirmed' => true,
                    'message' => 'Inventory updated.',
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'ended' => $this->listingLooksEnded($msg),
                'message' => $msg !== '' ? $msg : 'ReviseInventoryStatus failed.',
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Qty-only variation revise (no StartPrice) when ReviseInventoryStatus is a no-op.
     *
     * @return array{success: bool, message: string, data?: array, ended?: bool}
     */
    public function reviseVariationQuantity(string $itemId, string $sku, int $quantity): array
    {
        $itemId = trim($itemId);
        $sku = trim($sku);
        if ($itemId === '' || $sku === '') {
            return ['success' => false, 'message' => 'ItemID and SKU are required.'];
        }

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            $credentials->addChild('eBayAuthToken', $this->generateBearerToken() ?? '');
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');

            $item = $xml->addChild('Item');
            $item->addChild('ItemID', $itemId);
            $variations = $item->addChild('Variations');
            $variation = $variations->addChild('Variation');
            $variation->addChild('SKU', $sku);
            $variation->addChild('Quantity', (string) max(0, $quantity));

            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
                'X-EBAY-API-DEV-NAME' => $this->devId,
                'X-EBAY-API-APP-NAME' => $this->appId,
                'X-EBAY-API-CERT-NAME' => $this->certId,
                'X-EBAY-API-CALL-NAME' => 'ReviseFixedPriceItem',
                'X-EBAY-API-SITEID' => $this->siteId,
                'Content-Type' => 'text/xml',
            ];

            $response = $this->tradingHttp(60)
                ->withHeaders($headers)
                ->withBody($xml->asXML(), 'text/xml')
                ->post($this->endpoint);

            $body = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            if ($xmlResp === false) {
                return ['success' => false, 'message' => 'Invalid XML response from eBay.', 'raw' => $body];
            }

            $data = json_decode(json_encode($xmlResp), true) ?: [];
            $ack = $data['Ack'] ?? 'Failure';
            $msg = $this->flattenEbayErrors($data);
            if ($ack === 'Success' || $ack === 'Warning') {
                if ($this->ebayErrorLooksLikeNonVariationListing(
                    isset($data['Errors'][0]) ? $data['Errors'] : (isset($data['Errors']) && is_array($data['Errors']) ? [$data['Errors']] : []),
                    $msg
                )) {
                    return ['success' => false, 'message' => $msg ?: 'Not a multi-SKU listing.', 'data' => $data];
                }

                return ['success' => true, 'message' => 'Variation quantity updated.', 'data' => $data];
            }

            return [
                'success' => false,
                'ended' => $this->listingLooksEnded($msg),
                'message' => $msg ?: 'ReviseFixedPriceItem quantity failed.',
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Relist an ended fixed-price listing. Returns the new ItemID when eBay assigns one.
     *
     * @return array{success: bool, message: string, item_id?: string, data?: array}
     */
    public function relistFixedPriceItem(string $itemId, ?string $sku = null, ?int $quantity = null): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return ['success' => false, 'message' => 'ItemID is required.'];
        }

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><RelistFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            $credentials->addChild('eBayAuthToken', $this->generateBearerToken() ?? '');
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');

            $item = $xml->addChild('Item');
            $item->addChild('ItemID', $itemId);
            $sku = $sku !== null ? trim($sku) : '';
            if ($sku !== '' && $quantity !== null) {
                $variations = $item->addChild('Variations');
                $variation = $variations->addChild('Variation');
                $variation->addChild('SKU', $sku);
                $variation->addChild('Quantity', (string) max(0, $quantity));
            } elseif ($quantity !== null && $sku === '') {
                $item->addChild('Quantity', (string) max(0, $quantity));
            }

            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
                'X-EBAY-API-DEV-NAME' => $this->devId,
                'X-EBAY-API-APP-NAME' => $this->appId,
                'X-EBAY-API-CERT-NAME' => $this->certId,
                'X-EBAY-API-CALL-NAME' => 'RelistFixedPriceItem',
                'X-EBAY-API-SITEID' => $this->siteId,
                'Content-Type' => 'text/xml',
            ];

            $response = $this->tradingHttp(60)
                ->withHeaders($headers)
                ->withBody($xml->asXML(), 'text/xml')
                ->post($this->endpoint);

            $body = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            if ($xmlResp === false) {
                return ['success' => false, 'message' => 'Invalid XML response from eBay.', 'raw' => $body];
            }

            $data = json_decode(json_encode($xmlResp), true) ?: [];
            $ack = $data['Ack'] ?? 'Failure';
            $newId = trim((string) ($data['ItemID'] ?? ''));
            if ($ack === 'Success' || $ack === 'Warning') {
                return [
                    'success' => true,
                    'message' => 'Listing relisted.',
                    'item_id' => $newId !== '' ? $newId : $itemId,
                    'data' => $data,
                ];
            }

            $msg = $this->flattenEbayErrors($data);
            if ($sku !== '' && (str_contains($msg, '21916635') || str_contains(strtolower($msg), 'invalid multi-sku'))) {
                return $this->relistFixedPriceItem($itemId, null, $quantity);
            }

            return [
                'success' => false,
                'message' => $msg ?: 'RelistFixedPriceItem failed.',
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::warning('eBay2 RelistFixedPriceItem exception', [
                'itemId' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Available qty for a listing or variation SKU from GetItem.
     */
    public function variationAvailableQty(string $itemId, ?string $sku = null): ?int
    {
        $raw = $this->getItem($itemId);
        if (! is_array($raw) || ! isset($raw['Item'])) {
            return null;
        }

        $item = $raw['Item'];
        $status = strtolower((string) ($item['SellingStatus']['ListingStatus'] ?? ''));
        if (in_array($status, ['completed', 'ended'], true)) {
            return 0;
        }

        $sku = trim((string) $sku);
        $vars = $item['Variations']['Variation'] ?? null;
        if (is_array($vars) && $sku !== '') {
            $list = isset($vars['SKU']) || isset($vars['Quantity']) ? [$vars] : $vars;
            $needle = strtoupper($sku);
            foreach ($list as $v) {
                if (! is_array($v)) {
                    continue;
                }
                if (strtoupper(trim((string) ($v['SKU'] ?? ''))) !== $needle) {
                    continue;
                }

                return $this->availableFromQtyAndSold($v['Quantity'] ?? null, $v['SellingStatus']['QuantitySold'] ?? ($v['QuantitySold'] ?? 0));
            }
        }

        return $this->availableFromQtyAndSold(
            $item['Quantity'] ?? null,
            $item['SellingStatus']['QuantitySold'] ?? 0
        );
    }

    public function listingStatus(string $itemId): ?string
    {
        $raw = $this->getItem($itemId);
        if (! is_array($raw) || ! isset($raw['Item']['SellingStatus']['ListingStatus'])) {
            return null;
        }

        return strtolower(trim((string) $raw['Item']['SellingStatus']['ListingStatus']));
    }

    /**
     * @return list<string>
     */
    public function findItemIdsBySku(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $headers = [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
            'X-EBAY-API-DEV-NAME' => $this->devId,
            'X-EBAY-API-APP-NAME' => $this->appId,
            'X-EBAY-API-CERT-NAME' => $this->certId,
            'X-EBAY-API-SITEID' => $this->siteId,
        ];
        $token = (string) ($this->generateBearerToken() ?? '');
        $ids = [];
        foreach (['ActiveList', 'UnsoldList'] as $list) {
            foreach ($this->myEbaySellingItemIdsForSku($headers, $token, $sku, $list) as $id) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    public function listingLooksEnded(?string $message): bool
    {
        $blob = strtolower((string) $message);
        if ($blob === '') {
            return false;
        }

        return str_contains($blob, 'this item cannot be accessed')
            || str_contains($blob, 'listing has been ended')
            || str_contains($blob, 'auction has been closed')
            || str_contains($blob, 'ended')
            || str_contains($blob, 'completed')
            || str_contains($blob, 'errorcode>17')
            || str_contains($blob, '"17"')
            || str_contains($blob, '21916250')
            || str_contains($blob, 'error 291')
            || str_contains($blob, '#291');
    }

    protected function tradingHttp(int $timeout = 60)
    {
        return Http::timeout($timeout)->withoutVerifying();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function flattenEbayErrors(array $data): string
    {
        $errors = $data['Errors'] ?? [];
        $errors = is_array($errors) ? $errors : [$errors];
        if ($errors !== [] && ! isset($errors[0]) && isset($errors['ShortMessage'])) {
            $errors = [$errors];
        }
        $messages = [];
        foreach ($errors as $err) {
            $messages[] = $this->parseEbayError(is_array($err) ? $err : ['ShortMessage' => (string) $err]);
        }

        return implode('; ', array_filter($messages));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractReturnedInventoryQuantity(array $data): ?int
    {
        $status = $data['InventoryStatus'] ?? null;
        if (! is_array($status)) {
            return null;
        }
        if (isset($status['Quantity'])) {
            return (int) $status['Quantity'];
        }
        if (isset($status[0]['Quantity'])) {
            return (int) $status[0]['Quantity'];
        }

        return null;
    }

    protected function availableFromQtyAndSold(mixed $quantity, mixed $sold): int
    {
        $qty = max(0, (int) $quantity);
        $soldQty = max(0, (int) $sold);
        if ($soldQty > 0 && $qty >= $soldQty) {
            return $qty - $soldQty;
        }

        return $qty;
    }

    /**
     * @param  array<string, string>  $headers
     * @return list<string>
     */
    protected function myEbaySellingItemIdsForSku(array $headers, string $token, string $sku, string $listName): array
    {
        $target = strtoupper($sku);
        $ids = [];
        $page = 1;
        $totalPages = 1;
        $listXml = $listName === 'UnsoldList'
            ? '<UnsoldList><Include>true</Include><DurationInDays>60</DurationInDays><Pagination><EntriesPerPage>200</EntriesPerPage><PageNumber>%d</PageNumber></Pagination></UnsoldList>'
            : '<ActiveList><Include>true</Include><Pagination><EntriesPerPage>200</EntriesPerPage><PageNumber>%d</PageNumber></Pagination></ActiveList>';

        while ($page <= min($totalPages, 8)) {
            $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
                .'<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
                .sprintf($listXml, $page)
                .'</GetMyeBaySellingRequest>';
            try {
                $h = $headers;
                $h['X-EBAY-API-CALL-NAME'] = 'GetMyeBaySelling';
                $h['Content-Type'] = 'text/xml';
                $h['X-EBAY-API-IAF-TOKEN'] = $token;
                $response = $this->tradingHttp(60)->withHeaders($h)->withBody($xmlBody, 'text/xml')->post($this->endpoint);
                $xml = simplexml_load_string((string) $response->body());
                if ($xml === false) {
                    break;
                }
                $arr = json_decode(json_encode($xml), true) ?: [];
                $ack = $arr['Ack'] ?? 'Failure';
                if ($ack !== 'Success' && $ack !== 'Warning') {
                    break;
                }
                $block = $arr[$listName] ?? [];
                if ($page === 1) {
                    $totalPages = max(1, (int) ($block['PaginationResult']['TotalNumberOfPages'] ?? 1));
                }
                $items = $block['ItemArray']['Item'] ?? null;
                if ($items === null) {
                    $page++;
                    continue;
                }
                if (isset($items['ItemID'])) {
                    $items = [$items];
                }
                foreach ((array) $items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $itemId = trim((string) ($item['ItemID'] ?? ''));
                    if ($itemId === '') {
                        continue;
                    }
                    $skus = [strtoupper(trim((string) ($item['SKU'] ?? '')))];
                    $variations = $item['Variations']['Variation'] ?? null;
                    if (is_array($variations)) {
                        if (isset($variations['SKU'])) {
                            $variations = [$variations];
                        }
                        foreach ($variations as $variation) {
                            if (is_array($variation)) {
                                $skus[] = strtoupper(trim((string) ($variation['SKU'] ?? '')));
                            }
                        }
                    }
                    if (in_array($target, $skus, true)) {
                        $ids[] = $itemId;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('eBay2 GetMyeBaySelling scan failed', [
                    'sku' => $sku,
                    'list' => $listName,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
            $page++;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{success: bool, message: string, sample_item_id?: string|null}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Configure EBAY_2_APP_ID, EBAY_2_CERT_ID, and EBAY_2_REFRESH_TOKEN in .env.',
            ];
        }

        try {
            $token = $this->generateBearerToken();
            if (empty($token)) {
                return ['success' => false, 'message' => 'Failed to generate eBay 2 bearer token.'];
            }

            $itemId = null;
            if (Schema::hasTable('ebay_2_metrics')) {
                $itemId = Ebay2Metric::query()
                    ->whereNotNull('item_id')
                    ->where('item_id', '!=', '')
                    ->whereColumn('item_id', '!=', 'sku')
                    ->value('item_id');
            }

            if ($itemId) {
                $item = $this->getItem((string) $itemId);
                $ok = is_array($item) && (isset($item['Item']) || (($item['Ack'] ?? '') === 'Success'));

                return [
                    'success' => (bool) $ok,
                    'message' => $ok
                        ? 'Connected. Trading API GetItem succeeded for item '.$itemId.'.'
                        : 'Token OK but GetItem failed for sample item '.$itemId.'.',
                    'sample_item_id' => (string) $itemId,
                ];
            }

            return [
                'success' => true,
                'message' => 'Connected. Bearer token generated (no sample item_id in ebay_2_metrics yet).',
                'sample_item_id' => null,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Connection test failed: '.$e->getMessage()];
        }
    }

    /**
     * Application (client_credentials) token for public Browse/Taxonomy APIs.
     * User refresh tokens often lack commerce.taxonomy scope (403 Access denied).
     */
    public function generateApplicationToken(): string
    {
        $clientId = config('services.ebay2.app_id');
        $clientSecret = config('services.ebay2.cert_id');
        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('eBay 2 app_id/cert_id not configured.');
        }

        $cacheKey = 'ebay2_app_token_' . md5((string) $clientId);
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (! empty($cached)) {
                return (string) $cached;
            }
        }

        $response = Http::withoutVerifying()->asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(30)
            ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'https://api.ebay.com/oauth/api_scope',
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to get eBay 2 application token: '.$response->body());
        }

        $token = (string) ($response->json('access_token') ?? '');
        $expiresIn = (int) ($response->json('expires_in') ?? 7200);
        if ($token === '') {
            throw new \Exception('No application access_token returned from eBay.');
        }

        Cache::put($cacheKey, $token, now()->addSeconds(max(60, $expiresIn - 60)));

        return $token;
    }

    /**
     * Keyword category search (Taxonomy API) — LitCommerce-style results.
     *
     * @return array{success: bool, categories?: list<array{id: string, path: string}>, message?: string}
     */
    public function getCategorySuggestions(string $query, int $categoryTreeId = 0): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['success' => true, 'categories' => []];
        }

        try {
            // Taxonomy requires application token (not user refresh token)
            $token = $this->generateApplicationToken();
            $url = "https://api.ebay.com/commerce/taxonomy/v1/category_tree/{$categoryTreeId}/get_category_suggestions";
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get($url, ['q' => $query]);

            if ($response->failed()) {
                Log::warning('eBay2 category suggestions failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 1000),
                ]);

                return [
                    'success' => false,
                    'categories' => [],
                    'message' => 'Category search failed: '.($response->json('errors.0.longMessage')
                        ?? $response->json('errors.0.message')
                        ?? ('HTTP '.$response->status())),
                ];
            }

            $data = $response->json() ?? [];
            $suggestions = $data['categorySuggestions'] ?? [];
            $categories = [];
            foreach ($suggestions as $row) {
                $node = $row['category'] ?? [];
                $id = trim((string) ($node['categoryId'] ?? ''));
                $name = trim((string) ($node['categoryName'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $ancestors = $row['categoryTreeNodeAncestors'] ?? [];
                $parts = [];
                if (is_array($ancestors)) {
                    // ancestors are usually leaf→root; reverse for breadcrumb
                    $names = [];
                    foreach ($ancestors as $a) {
                        $n = trim((string) ($a['categoryName'] ?? ''));
                        if ($n !== '') {
                            $names[] = $n;
                        }
                    }
                    $parts = array_reverse($names);
                }
                if ($name !== '') {
                    $parts[] = $name;
                }
                $categories[] = [
                    'id' => $id,
                    'path' => $parts !== [] ? implode(' > ', $parts) : ($name !== '' ? $name : $id),
                ];
            }

            return ['success' => true, 'categories' => $categories];
        } catch (\Throwable $e) {
            return ['success' => false, 'categories' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * Business policies for dropdowns (Sell Account API).
     *
     * @return array{
     *   success: bool,
     *   shipping: list<array{id: string, name: string}>,
     *   payment: list<array{id: string, name: string}>,
     *   return: list<array{id: string, name: string}>,
     *   message?: string
     * }
     */
    public function getBusinessPolicies(string $marketplaceId = 'EBAY_US'): array
    {
        $empty = ['shipping' => [], 'payment' => [], 'return' => []];
        try {
            $token = $this->generateBearerToken();
            $map = [
                'shipping' => "https://api.ebay.com/sell/account/v1/fulfillment_policy?marketplace_id={$marketplaceId}",
                'payment' => "https://api.ebay.com/sell/account/v1/payment_policy?marketplace_id={$marketplaceId}",
                'return' => "https://api.ebay.com/sell/account/v1/return_policy?marketplace_id={$marketplaceId}",
            ];
            $out = $empty;
            foreach ($map as $key => $url) {
                $response = Http::withoutVerifying()
                    ->withToken($token)
                    ->acceptJson()
                    ->timeout(30)
                    ->get($url);
                if ($response->failed()) {
                    Log::warning("eBay2 {$key} policies failed", [
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 800),
                    ]);
                    continue;
                }
                $json = $response->json() ?? [];
                $listKey = match ($key) {
                    'shipping' => 'fulfillmentPolicies',
                    'payment' => 'paymentPolicies',
                    default => 'returnPolicies',
                };
                $idKey = match ($key) {
                    'shipping' => 'fulfillmentPolicyId',
                    'payment' => 'paymentPolicyId',
                    default => 'returnPolicyId',
                };
                foreach (($json[$listKey] ?? []) as $policy) {
                    $id = trim((string) ($policy[$idKey] ?? ''));
                    $name = trim((string) ($policy['name'] ?? $id));
                    if ($id === '') {
                        continue;
                    }
                    $out[$key][] = ['id' => $id, 'name' => $name];
                }
            }

            return array_merge(['success' => true], $out);
        } catch (\Throwable $e) {
            return array_merge(['success' => false, 'message' => $e->getMessage()], $empty);
        }
    }
}
