<?php

namespace App\Services;

use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Ads\GoogleAds\Lib\V25\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V25\Services\AdGroupOperation;
use Google\Ads\GoogleAds\V25\Services\AdGroupCriterionOperation;
use Google\Ads\GoogleAds\V25\Resources\AdGroup;
use Google\Ads\GoogleAds\V25\Resources\AdGroupCriterion;
use Google\Protobuf\FieldMask;
use Google\Ads\GoogleAds\V25\Services\SearchGoogleAdsStreamRequest;
use Google\Ads\GoogleAds\V25\Services\MutateAdGroupsRequest;
use Illuminate\Support\Facades\Log;
use Google\Ads\GoogleAds\V25\Services\MutateAdGroupCriteriaRequest;
use Google\Ads\GoogleAds\V25\Services\CampaignBudgetOperation;
use Google\Ads\GoogleAds\V25\Services\MutateCampaignBudgetsRequest;
use Google\Ads\GoogleAds\V25\Resources\CampaignBudget;
use Google\Ads\GoogleAds\V25\Services\AdGroupAdOperation;
use Google\Ads\GoogleAds\V25\Services\MutateAdGroupAdsRequest;
use Google\Ads\GoogleAds\V25\Resources\AdGroupAd;
use Google\Ads\GoogleAds\V25\Enums\AdGroupAdStatusEnum\AdGroupAdStatus;
use Google\Ads\GoogleAds\V25\Services\CampaignOperation;
use Google\Ads\GoogleAds\V25\Services\MutateCampaignsRequest;
use Google\Ads\GoogleAds\V25\Resources\Campaign;
use Google\Ads\GoogleAds\V25\Resources\Campaign\ShoppingSetting;
use Google\Ads\GoogleAds\V25\Resources\Ad;
use Google\Ads\GoogleAds\V25\Common\MaximizeConversions;
use Google\Ads\GoogleAds\V25\Common\TargetSpend;
use Google\Ads\GoogleAds\V25\Common\KeywordInfo;
use Google\Ads\GoogleAds\V25\Common\ManualCpc;
use Google\Ads\GoogleAds\V25\Common\ListingDimensionInfo;
use Google\Ads\GoogleAds\V25\Common\ListingGroupInfo;
use Google\Ads\GoogleAds\V25\Common\ProductItemIdInfo;
use Google\Ads\GoogleAds\V25\Common\ShoppingProductAdInfo;
use Google\Ads\GoogleAds\Util\V25\ResourceNames;
use Google\Ads\GoogleAds\V25\Enums\CampaignStatusEnum\CampaignStatus;
use Google\Ads\GoogleAds\V25\Enums\AdvertisingChannelTypeEnum\AdvertisingChannelType;
use Google\Ads\GoogleAds\V25\Enums\AdGroupTypeEnum\AdGroupType;
use Google\Ads\GoogleAds\V25\Enums\AdGroupStatusEnum\AdGroupStatus;
use Google\Ads\GoogleAds\V25\Enums\AdGroupCriterionStatusEnum\AdGroupCriterionStatus;
use Google\Ads\GoogleAds\V25\Enums\KeywordMatchTypeEnum\KeywordMatchType;
use Google\Ads\GoogleAds\V25\Enums\ListingGroupTypeEnum\ListingGroupType;
use Google\Ads\GoogleAds\V25\Enums\BudgetDeliveryMethodEnum\BudgetDeliveryMethod;
use Google\Ads\GoogleAds\V25\Enums\EuPoliticalAdvertisingStatusEnum\EuPoliticalAdvertisingStatus;
use Google\Ads\GoogleAds\V25\Resources\CampaignCriterion;
use Google\Ads\GoogleAds\V25\Services\CampaignCriterionOperation;
use Google\Ads\GoogleAds\V25\Services\MutateCampaignCriteriaRequest;

class GoogleAdsSbidService
{
    protected $client;

    public function __construct()
    {
        // Lazy initialization - client will be built when first needed
    }

    private function buildClient()
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $developerToken = config('services.google_ads.developer_token');
        $clientId = config('services.google_ads.client_id');
        $clientSecret = config('services.google_ads.client_secret');
        $refreshToken = config('services.google_ads.refresh_token');
        $loginCustomerId = config('services.google_ads.login_customer_id');

        // Validate required credentials
        if (empty($developerToken)) {
            throw new \Exception('Google Ads Developer Token is not configured');
        }
        if (empty($clientId)) {
            throw new \Exception('Google Ads Client ID is not configured');
        }
        if (empty($clientSecret)) {
            throw new \Exception('Google Ads Client Secret is not configured');
        }
        if (empty($refreshToken)) {
            throw new \Exception('Google Ads Refresh Token is not configured');
        }
        if (empty($loginCustomerId)) {
            throw new \Exception('Google Ads Login Customer ID is not configured');
        }

        $oAuth2Credential = new UserRefreshCredentials(
            ['https://www.googleapis.com/auth/adwords'],
            [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
            ]
        );

        $this->client = (new GoogleAdsClientBuilder())
            ->withDeveloperToken($developerToken)
            ->withLoginCustomerId($loginCustomerId)
            ->withOAuth2Credential($oAuth2Credential)
            ->build();

        return $this->client;
    }

    private function getClient()
    {
        if ($this->client === null) {
            $this->buildClient();
        }
        return $this->client;
    }

    /**
     * Run GAQL query
     */
    public function runQuery($customerId, $query)
    {
        $googleAdsService = $this->getClient()->getGoogleAdsServiceClient();

        $request = new SearchGoogleAdsStreamRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $stream = $googleAdsService->searchStream($request);

        $results = [];
        foreach ($stream->iterateAllElements() as $row) {
            $results[] = json_decode($row->serializeToJsonString(), true);
        }

        return $results;
    }

    /**
     * Run a GAQL query and invoke $onRow for each returned row without buffering the
     * full result set in memory. Use for large reads (e.g. account-wide negative
     * keywords) where accumulating every row would exhaust memory.
     *
     * @param  callable(array<string, mixed>): void  $onRow
     */
    public function streamQuery($customerId, $query, callable $onRow): void
    {
        $googleAdsService = $this->getClient()->getGoogleAdsServiceClient();

        $request = new SearchGoogleAdsStreamRequest([
            'customer_id' => $customerId,
            'query' => $query,
        ]);

        $stream = $googleAdsService->searchStream($request);

        foreach ($stream->iterateAllElements() as $row) {
            $onRow(json_decode($row->serializeToJsonString(), true));
        }
    }


    /**
     * Update Ad Group SBID
     */
    public function updateAdGroupSbid($customerId, $adGroupResourceName, $newSbid)
    {
        try {
            // Validate inputs
            if (empty($customerId) || empty($adGroupResourceName) || !is_numeric($newSbid)) {
                throw new \InvalidArgumentException("Invalid parameters for SBID update");
            }

            if ($newSbid <= 0) {
                throw new \InvalidArgumentException("SBID must be greater than 0, got: {$newSbid}");
            }

            $adGroupService = $this->getClient()->getAdGroupServiceClient();

            $bidMicros = round($newSbid * 1_000_000);
            $billableUnit = 10000; // $0.01 in micros
            $bidMicros = round($bidMicros / $billableUnit) * $billableUnit;
            
            // Ensure minimum bid (usually $0.01)
            if ($bidMicros < $billableUnit) {
                $bidMicros = $billableUnit;
            }

            $adGroup = new AdGroup([
                'resource_name' => $adGroupResourceName,
                'cpc_bid_micros' => $bidMicros
            ]);

            $operation = new AdGroupOperation();
            $operation->setUpdate($adGroup);
            $operation->setUpdateMask(new FieldMask(['paths' => ['cpc_bid_micros']]));

            $request = new MutateAdGroupsRequest([
                'customer_id' => $customerId,
                'operations' => [$operation]
            ]);

            $response = $adGroupService->mutateAdGroups($request);
            
            // Validate response
            if (!$response || !$response->getResults()) {
                throw new \Exception("No response received from Google Ads API");
            }

            $results = $response->getResults();
            if (count($results) === 0) {
                throw new \Exception("No results returned from ad group update operation");
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error("Failed to update AdGroup SBID", [
                'customer_id' => $customerId,
                'ad_group' => $adGroupResourceName,
                'new_sbid' => $newSbid,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function updateProductGroupSbid($customerId, $productGroupResourceName, $newSbid)
    {
        try {
            // Validate inputs
            if (empty($customerId) || empty($productGroupResourceName) || !is_numeric($newSbid)) {
                throw new \InvalidArgumentException("Invalid parameters for product group SBID update");
            }

            if ($newSbid <= 0) {
                throw new \InvalidArgumentException("SBID must be greater than 0, got: {$newSbid}");
            }

            $adGroupCriterionService = $this->getClient()->getAdGroupCriterionServiceClient();

            $bidMicros = round($newSbid * 1_000_000);
            $billableUnit = 10000; // $0.01 in micros
            $bidMicros = round($bidMicros / $billableUnit) * $billableUnit;
            
            // Ensure minimum bid (usually $0.01)
            if ($bidMicros < $billableUnit) {
                $bidMicros = $billableUnit;
            }

            $criterion = new AdGroupCriterion([
                'resource_name' => $productGroupResourceName,
                'cpc_bid_micros' => $bidMicros
            ]);

            $operation = new AdGroupCriterionOperation();
            $operation->setUpdate($criterion);
            $operation->setUpdateMask(new FieldMask(['paths' => ['cpc_bid_micros']]));

            // Wrap operation in a proper request object
            $request = new MutateAdGroupCriteriaRequest([
                'customer_id' => $customerId,
                'operations' => [$operation]
            ]);

            $response = $adGroupCriterionService->mutateAdGroupCriteria($request);

            // Validate response
            if (!$response || !$response->getResults()) {
                throw new \Exception("No response received from Google Ads API for product group update");
            }

            $results = $response->getResults();
            if (count($results) === 0) {
                throw new \Exception("No results returned from product group update operation");
            }

            return $response;
            
        } catch (\Exception $e) {
            Log::error("Failed to update Product Group SBID", [
                'customer_id' => $customerId,
                'product_group' => $productGroupResourceName,
                'new_sbid' => $newSbid,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    /**
     * Short, readable reason from a Google Ads exception. The raw message is a long JSON document.
     */
    public static function briefGoogleError(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (preg_match('/"message"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"[^{}]*"errorCode"/s', $msg, $m)
            || preg_match('/"errors"\s*:\s*\[\s*\{.*?"message"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $msg, $m)) {
            return stripslashes($m[1]);
        }
        if (preg_match('/UNSUPPORTED_VERSION/', $msg)) {
            return 'Google Ads API version is no longer supported';
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $msg)), 0, 200);
    }

    public function updateCampaignSbids($customerId, $campaignId, $sbidFactor, bool $includeProductGroups = true)
    {
        try {

            $adGroupQuery = "
                SELECT ad_group.resource_name, metrics.clicks, metrics.cost_micros
                FROM ad_group
                WHERE ad_group.campaign = 'customers/{$customerId}/campaigns/{$campaignId}'
            ";

            $adGroups = $this->runQuery($customerId, $adGroupQuery);

            if (empty($adGroups)) {
                Log::warning("No ad groups found for campaign", ['campaign_id' => $campaignId]);
                throw new \Exception("No ad groups found for campaign ID: {$campaignId}");
            }

            $processedAdGroups = 0;
            $processedProductGroups = 0;
            // Some updates can fail while others work. Keep going, but never report success for the campaign.
            $partialFailures = [];

            foreach ($adGroups as $row) {
                // Fix: Use correct field names from Google Ads API response
                $adGroup = $row['adGroup'] ?? [];
                $metrics = $row['metrics'] ?? [];
                $adGroupResource = $adGroup['resourceName'] ?? null;

                if ($adGroupResource) {
                    try {
                        $this->updateAdGroupSbid($customerId, $adGroupResource, $sbidFactor);
                        $processedAdGroups++;
                    } catch (\Exception $e) {
                        Log::error("Failed to update ad group SBID", [
                            'ad_group_resource' => $adGroupResource,
                            'error' => $e->getMessage()
                        ]);
                        $partialFailures[] = 'ad group '.$adGroupResource.': '.self::briefGoogleError($e);
                        // Continue with other ad groups
                    }

                    // Shopping campaigns only — SERP/Search/Video pages pass $includeProductGroups = false.
                    if ($includeProductGroups) {
                    $productGroupQuery = "
                        SELECT ad_group_criterion.resource_name, 
                               ad_group_criterion.listing_group.type,
                               ad_group_criterion.negative
                        FROM ad_group_criterion
                        WHERE ad_group_criterion.ad_group = '{$adGroupResource}'
                          AND ad_group_criterion.listing_group.type = 'UNIT'
                          AND ad_group_criterion.negative = FALSE
                    ";

                    try {
                        $productGroups = $this->runQuery($customerId, $productGroupQuery);
                        
                        foreach ($productGroups as $pgRow) {
                            $pgResource = $pgRow['adGroupCriterion']['resourceName'] ?? null;
                            if ($pgResource) {
                                try {
                                    $this->updateProductGroupSbid($customerId, $pgResource, $sbidFactor);
                                    $processedProductGroups++;
                                } catch (\Exception $e) {
                                    Log::error("Failed to update product group SBID", [
                                        'product_group_resource' => $pgResource,
                                        'error' => $e->getMessage()
                                    ]);
                                    $partialFailures[] = 'product group '.$pgResource.': '.self::briefGoogleError($e);
                                    // Continue with other product groups
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to query product groups", [
                            'ad_group_resource' => $adGroupResource,
                            'error' => $e->getMessage()
                        ]);
                        $partialFailures[] = 'product groups of '.$adGroupResource.': '.self::briefGoogleError($e);
                    }
                    }
                } else {
                    Log::warning("No resource name found for ad group", ['row' => $row]);
                }
            }

            // If no ad groups were processed, throw an exception
            if ($processedAdGroups === 0) {
                throw new \Exception("Failed to update any ad groups for campaign ID: {$campaignId}");
            }
            if ($partialFailures !== []) {
                throw new \Exception(
                    "SBID only partly updated for campaign ID {$campaignId} ({$processedAdGroups} ad group(s), {$processedProductGroups} product group(s) done; "
                    .count($partialFailures).' failed). First error: '.$partialFailures[0]
                );
            }

        } catch (\Exception $e) {
            Log::error("Failed to update campaign SBIDs", [
                'customer_id' => $customerId,
                'campaign_id' => $campaignId,
                'sbid_factor' => $sbidFactor,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update Campaign Budget
     */
    public function updateCampaignBudget($customerId, $budgetResourceName, $newBudgetAmount)
    {
        try {
            // Validate inputs
            if (empty($customerId) || empty($budgetResourceName) || !is_numeric($newBudgetAmount)) {
                throw new \InvalidArgumentException("Invalid parameters for budget update");
            }

            if ($newBudgetAmount <= 0) {
                throw new \InvalidArgumentException("Budget must be greater than 0, got: {$newBudgetAmount}");
            }

            $campaignBudgetService = $this->getClient()->getCampaignBudgetServiceClient();

            // Convert dollars to micros (multiply by 1,000,000)
            $budgetMicros = round($newBudgetAmount * 1_000_000);
            
            // Minimum budget is usually $1.00 (1,000,000 micros)
            if ($budgetMicros < 1_000_000) {
                $budgetMicros = 1_000_000;
            }

            $campaignBudget = new CampaignBudget([
                'resource_name' => $budgetResourceName,
                'amount_micros' => $budgetMicros
            ]);

            $operation = new CampaignBudgetOperation();
            $operation->setUpdate($campaignBudget);
            $operation->setUpdateMask(new FieldMask(['paths' => ['amount_micros']]));

            $request = new MutateCampaignBudgetsRequest([
                'customer_id' => $customerId,
                'operations' => [$operation]
            ]);

            $response = $campaignBudgetService->mutateCampaignBudgets($request);
            
            // Validate response
            if (!$response || !$response->getResults()) {
                throw new \Exception("No response received from Google Ads API");
            }

            $results = $response->getResults();
            if (count($results) === 0) {
                throw new \Exception("No results returned from budget update operation");
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error("Failed to update Campaign Budget", [
                'customer_id' => $customerId,
                'budget_resource' => $budgetResourceName,
                'new_budget' => $newBudgetAmount,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Pause an ad by setting its status to PAUSED
     */
    public function pauseAd($customerId, $adGroupAdResourceName)
    {
        try {
            // Validate inputs
            if (empty($customerId) || empty($adGroupAdResourceName)) {
                throw new \InvalidArgumentException("Invalid parameters for ad pause");
            }

            $adGroupAdService = $this->getClient()->getAdGroupAdServiceClient();

            $adGroupAd = new AdGroupAd([
                'resource_name' => $adGroupAdResourceName,
                'status' => AdGroupAdStatus::PAUSED
            ]);

            $operation = new AdGroupAdOperation();
            $operation->setUpdate($adGroupAd);
            $operation->setUpdateMask(new FieldMask(['paths' => ['status']]));

            $request = new MutateAdGroupAdsRequest([
                'customer_id' => $customerId,
                'operations' => [$operation]
            ]);

            $response = $adGroupAdService->mutateAdGroupAds($request);
            
            // Validate response
            if (!$response || !$response->getResults()) {
                throw new \Exception("No response received from Google Ads API");
            }

            $results = $response->getResults();
            if (count($results) === 0) {
                throw new \Exception("No results returned from ad pause operation");
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error("Failed to pause ad", [
                'customer_id' => $customerId,
                'ad_group_ad_resource' => $adGroupAdResourceName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Remove (delete) a campaign by setting its status to REMOVED.
     * Google Ads does not hard-delete campaigns; REMOVED is the API delete.
     */
    public function removeCampaign(string $customerId, string $campaignIdOrResourceName): array
    {
        $customerId = str_replace('-', '', trim($customerId));
        $raw = trim($campaignIdOrResourceName);
        if ($customerId === '' || $raw === '') {
            throw new \InvalidArgumentException('customer_id and campaign_id are required');
        }

        if (str_contains($raw, '/campaigns/')) {
            $campaignResourceName = $raw;
            $campaignId = preg_replace('/^.*\//', '', $raw) ?: '';
        } else {
            $campaignId = preg_replace('/\D+/', '', $raw) ?: '';
            if ($campaignId === '') {
                throw new \InvalidArgumentException('Invalid campaign_id');
            }
            $campaignResourceName = ResourceNames::forCampaign($customerId, $campaignId);
        }

        try {
            // Google Ads rejects status=REMOVED updates (INVALID_ENUM_VALUE).
            // Use the remove operation with the campaign resource name instead.
            $operation = new CampaignOperation();
            $operation->setRemove($campaignResourceName);

            $response = $this->getClient()->getCampaignServiceClient()->mutateCampaigns(
                new MutateCampaignsRequest([
                    'customer_id' => $customerId,
                    'operations' => [$operation],
                ])
            );

            if (! $response || ! $response->getResults() || count($response->getResults()) === 0) {
                throw new \RuntimeException('No results returned from campaign remove operation');
            }

            Log::info('Removed Google Ads campaign', [
                'customer_id' => $customerId,
                'campaign_id' => $campaignId,
                'campaign_resource' => $campaignResourceName,
            ]);

            return [
                'campaign_id' => (string) $campaignId,
                'campaign_resource_name' => $campaignResourceName,
                'status' => 'REMOVED',
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to remove Google Ads campaign', [
                'customer_id' => $customerId,
                'campaign_resource' => $campaignResourceName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Pause a campaign by setting its status to PAUSED
     */
    public function pauseCampaign($customerId, $campaignResourceName)
    {
        try {
            // Validate inputs
            if (empty($customerId) || empty($campaignResourceName)) {
                throw new \InvalidArgumentException("Invalid parameters for campaign pause");
            }

            $campaignService = $this->getClient()->getCampaignServiceClient();

            $campaign = new Campaign([
                'resource_name' => $campaignResourceName,
                'status' => CampaignStatus::PAUSED
            ]);

            $operation = new CampaignOperation();
            $operation->setUpdate($campaign);
            $operation->setUpdateMask(new FieldMask(['paths' => ['status']]));

            $request = new MutateCampaignsRequest([
                'customer_id' => $customerId,
                'operations' => [$operation]
            ]);

            $response = $campaignService->mutateCampaigns($request);
            
            // Validate response
            if (!$response || !$response->getResults()) {
                throw new \Exception("No response received from Google Ads API");
            }

            $results = $response->getResults();
            if (count($results) === 0) {
                throw new \Exception("No results returned from campaign pause operation");
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error("Failed to pause campaign", [
                'customer_id' => $customerId,
                'campaign_resource' => $campaignResourceName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Enable a campaign by setting its status to ENABLED
     */
    public function enableCampaign($customerId, $campaignResourceName)
    {
        try {
            // Validate inputs
            if (empty($customerId) || empty($campaignResourceName)) {
                throw new \InvalidArgumentException("Invalid parameters for campaign enable");
            }

            $campaignService = $this->getClient()->getCampaignServiceClient();

            $campaign = new Campaign([
                'resource_name' => $campaignResourceName,
                'status' => CampaignStatus::ENABLED
            ]);

            $operation = new CampaignOperation();
            $operation->setUpdate($campaign);
            $operation->setUpdateMask(new FieldMask(['paths' => ['status']]));

            $request = new MutateCampaignsRequest([
                'customer_id' => $customerId,
                'operations' => [$operation]
            ]);

            $response = $campaignService->mutateCampaigns($request);
            
            // Validate response
            if (!$response || !$response->getResults()) {
                throw new \Exception("No response received from Google Ads API");
            }

            $results = $response->getResults();
            if (count($results) === 0) {
                throw new \Exception("No results returned from campaign enable operation");
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error("Failed to enable campaign", [
                'customer_id' => $customerId,
                'campaign_resource' => $campaignResourceName,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Push SBID for Google Search (SERP) campaigns using the bidding strategy that
     * campaign actually uses. Manual CPC updates ad-group + keyword bids; automated
     * strategies update the max CPC bid ceiling visible in Google Ads settings.
     *
     * @return string Human-readable note for push logs
     */
    public function updateSearchCampaignSbid($customerId, $campaignId, float $sbidDollars, ?string $biddingStrategyType = null): string
    {
        $strategy = strtoupper(trim((string) ($biddingStrategyType ?? '')));
        if ($strategy === '') {
            $strategy = $this->resolveCampaignBiddingStrategyType($customerId, (string) $campaignId);
        }

        $bidMicros = $this->sbidDollarsToMicros($sbidDollars);
        $resourceName = "customers/{$customerId}/campaigns/{$campaignId}";

        if (in_array($strategy, ['MANUAL_CPC', 'MANUAL_CPM', 'ENHANCED_CPC'], true)) {
            $this->updateCampaignSbids($customerId, $campaignId, $sbidDollars, false);
            $keywordCount = $this->updateSearchKeywordSbids($customerId, $campaignId, $sbidDollars);

            return "manual CPC — ad groups + {$keywordCount} keyword(s) set to \$".number_format($sbidDollars, 2);
        }

        if ($strategy === 'MAXIMIZE_CONVERSIONS') {
            $this->mutateCampaignNestedBidSetting(
                $customerId,
                $resourceName,
                static function (Campaign $campaign) use ($bidMicros) {
                    $campaign->setMaximizeConversions(new MaximizeConversions([
                        'target_cpa_micros' => $bidMicros,
                    ]));
                },
                'maximize_conversions.target_cpa_micros'
            );

            return 'Maximize Conversions — target CPA set to $'.number_format($sbidDollars, 2);
        }

        if (in_array($strategy, ['TARGET_SPEND', 'MAXIMIZE_CLICKS'], true)) {
            $this->mutateCampaignNestedBidSetting(
                $customerId,
                $resourceName,
                static function (Campaign $campaign) use ($bidMicros) {
                    $campaign->setTargetSpend(new TargetSpend([
                        'cpc_bid_ceiling_micros' => $bidMicros,
                    ]));
                },
                'target_spend.cpc_bid_ceiling_micros'
            );

            return 'Maximize clicks — max CPC bid limit set to $'.number_format($sbidDollars, 2);
        }

        throw new \InvalidArgumentException(
            "Unsupported Search bidding strategy {$strategy} for campaign {$campaignId}. "
            .'SBID push supports MANUAL_CPC, MAXIMIZE_CONVERSIONS, and TARGET_SPEND.'
        );
    }

    /**
     * @return int Number of keyword bids updated
     */
    public function updateSearchKeywordSbids($customerId, $campaignId, float $sbidDollars): int
    {
        $query = "
            SELECT ad_group_criterion.resource_name
            FROM ad_group_criterion
            WHERE campaign.id = {$campaignId}
              AND ad_group_criterion.type = 'KEYWORD'
              AND ad_group_criterion.negative = FALSE
              AND ad_group_criterion.status != 'REMOVED'
        ";

        $rows = $this->runQuery($customerId, $query);
        $updated = 0;

        foreach ($rows as $row) {
            $resource = $row['adGroupCriterion']['resourceName'] ?? null;
            if ($resource === null || $resource === '') {
                continue;
            }

            try {
                $this->updateProductGroupSbid($customerId, $resource, $sbidDollars);
                $updated++;
            } catch (\Exception $e) {
                Log::error('Failed to update Search keyword SBID', [
                    'campaign_id' => $campaignId,
                    'criterion_resource' => $resource,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $updated;
    }

    private function resolveCampaignBiddingStrategyType($customerId, string $campaignId): string
    {
        $query = "
            SELECT campaign.bidding_strategy_type
            FROM campaign
            WHERE campaign.id = {$campaignId}
        ";
        $rows = $this->runQuery($customerId, $query);
        $type = $rows[0]['campaign']['biddingStrategyType'] ?? '';

        return strtoupper(trim((string) $type));
    }

    private function sbidDollarsToMicros(float $sbidDollars): int
    {
        $bidMicros = round($sbidDollars * 1_000_000);
        $billableUnit = 10000;
        $bidMicros = (int) (round($bidMicros / $billableUnit) * $billableUnit);

        return max($billableUnit, $bidMicros);
    }

    /**
     * @param  callable(Campaign): void  $configure
     */
    private function mutateCampaignNestedBidSetting($customerId, string $campaignResourceName, callable $configure, string $updateMaskPath): void
    {
        $campaign = new Campaign([
            'resource_name' => $campaignResourceName,
        ]);
        $configure($campaign);

        $campaignService = $this->getClient()->getCampaignServiceClient();
        $operation = new CampaignOperation();
        $operation->setUpdate($campaign);
        $operation->setUpdateMask(new FieldMask(['paths' => [$updateMaskPath]]));

        $request = new MutateCampaignsRequest([
            'customer_id' => $customerId,
            'operations' => [$operation],
        ]);

        $response = $campaignService->mutateCampaigns($request);

        if (! $response || ! $response->getResults() || count($response->getResults()) === 0) {
            throw new \Exception('No results returned from campaign bid update operation');
        }
    }

    /**
     * Create a standard Shopping product campaign (budget + campaign + ad group +
     * shopping product ad + default "All products" listing group), matching Google's
     * AddShoppingProductAd sample. Created PAUSED so it does not serve immediately.
     *
     * @param  array{
     *   campaign_name: string,
     *   budget_amount?: float|int,
     *   merchant_id?: int|string,
     *   campaign_priority?: int,
     *   feed_label?: string|null,
     *   cpc_bid?: float|int,
     *   enable_local?: bool
     * }  $params
     * @return array{
     *   campaign_id: string,
     *   campaign_resource_name: string,
     *   budget_resource_name: string,
     *   ad_group_resource_name: string,
     *   campaign_name: string
     * }
     */
    public function createShoppingProductCampaign(string $customerId, array $params): array
    {
        $customerId = str_replace('-', '', trim($customerId));
        $campaignName = trim((string) ($params['campaign_name'] ?? ''));
        if ($customerId === '' || $campaignName === '') {
            throw new \InvalidArgumentException('customer_id and campaign_name are required');
        }

        $budgetAmount = (float) ($params['budget_amount'] ?? 1.0);
        if ($budgetAmount <= 0) {
            $budgetAmount = 1.0;
        }
        $budgetMicros = max(1_000_000, (int) round($budgetAmount * 1_000_000));

        $merchantId = (int) ($params['merchant_id'] ?? 0);
        if ($merchantId <= 0) {
            throw new \InvalidArgumentException('merchant_id is required');
        }

        $priority = (int) ($params['campaign_priority'] ?? 0);
        if ($priority < 0 || $priority > 2) {
            $priority = 0;
        }

        $cpcBid = (float) ($params['cpc_bid'] ?? 0.5);
        if ($cpcBid <= 0) {
            $cpcBid = 0.5;
        }
        $cpcBidMicros = $this->sbidDollarsToMicros($cpcBid);

        $feedLabel = trim((string) ($params['feed_label'] ?? ''));
        $enableLocal = array_key_exists('enable_local', $params)
            ? (bool) $params['enable_local']
            : true;

        // One or more Merchant Center Item IDs (shopify_us_{productId}_{variantId}).
        // All included under a single campaign listing-group tree.
        $itemIds = [];
        if (! empty($params['item_ids']) && is_array($params['item_ids'])) {
            foreach ($params['item_ids'] as $rawId) {
                $id = trim((string) $rawId);
                if ($id !== '') {
                    $itemIds[] = $id;
                }
            }
        } elseif (! empty($params['item_id'])) {
            $id = trim((string) $params['item_id']);
            if ($id !== '') {
                $itemIds[] = $id;
            }
        }
        $itemIds = array_values(array_unique($itemIds));
        if ($itemIds === []) {
            throw new \InvalidArgumentException('item_id / item_ids is required (Merchant Center product Item ID)');
        }

        try {
            // 1) Budget
            $budget = new CampaignBudget([
                'name' => $campaignName,
                'delivery_method' => BudgetDeliveryMethod::STANDARD,
                'amount_micros' => $budgetMicros,
                'explicitly_shared' => false,
            ]);
            $budgetOp = new CampaignBudgetOperation();
            $budgetOp->setCreate($budget);
            $budgetResp = $this->getClient()->getCampaignBudgetServiceClient()->mutateCampaignBudgets(
                new MutateCampaignBudgetsRequest([
                    'customer_id' => $customerId,
                    'operations' => [$budgetOp],
                ])
            );
            $budgetResourceName = $budgetResp->getResults()[0]->getResourceName();

            // 2) Campaign
            $shoppingSetting = new ShoppingSetting([
                'merchant_id' => $merchantId,
                'campaign_priority' => $priority,
                'enable_local' => $enableLocal,
            ]);
            if ($feedLabel !== '') {
                $shoppingSetting->setFeedLabel($feedLabel);
            }

            $campaign = new Campaign([
                'name' => $campaignName,
                'advertising_channel_type' => AdvertisingChannelType::SHOPPING,
                'status' => CampaignStatus::PAUSED,
                'campaign_budget' => $budgetResourceName,
                'manual_cpc' => new ManualCpc(),
                'shopping_setting' => $shoppingSetting,
                'contains_eu_political_advertising' => EuPoliticalAdvertisingStatus::DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING,
            ]);
            $campaignOp = new CampaignOperation();
            $campaignOp->setCreate($campaign);
            $campaignResp = $this->getClient()->getCampaignServiceClient()->mutateCampaigns(
                new MutateCampaignsRequest([
                    'customer_id' => $customerId,
                    'operations' => [$campaignOp],
                ])
            );
            $campaignResourceName = $campaignResp->getResults()[0]->getResourceName();

            // 3) Ad group
            $adGroup = new AdGroup([
                'name' => $campaignName,
                'campaign' => $campaignResourceName,
                'status' => AdGroupStatus::ENABLED,
                'type' => AdGroupType::SHOPPING_PRODUCT_ADS,
                'cpc_bid_micros' => $cpcBidMicros,
            ]);
            $adGroupOp = new AdGroupOperation();
            $adGroupOp->setCreate($adGroup);
            $adGroupResp = $this->getClient()->getAdGroupServiceClient()->mutateAdGroups(
                new MutateAdGroupsRequest([
                    'customer_id' => $customerId,
                    'operations' => [$adGroupOp],
                ])
            );
            $adGroupResourceName = $adGroupResp->getResults()[0]->getResourceName();

            // 4) Shopping product ad
            $adGroupAd = new AdGroupAd([
                'ad_group' => $adGroupResourceName,
                'status' => AdGroupAdStatus::ENABLED,
                'ad' => new Ad([
                    'shopping_product_ad' => new ShoppingProductAdInfo(),
                ]),
            ]);
            $adGroupAdOp = new AdGroupAdOperation();
            $adGroupAdOp->setCreate($adGroupAd);
            $this->getClient()->getAdGroupAdServiceClient()->mutateAdGroupAds(
                new MutateAdGroupAdsRequest([
                    'customer_id' => $customerId,
                    'operations' => [$adGroupAdOp],
                ])
            );

            // 5) Listing group tree: include selected Merchant Center Item IDs only
            $adGroupId = (int) (preg_replace('/^.*\//', '', $adGroupResourceName) ?: 0);
            if ($adGroupId <= 0) {
                throw new \RuntimeException('Failed to parse ad group ID from resource name');
            }

            $rootResourceName = ResourceNames::forAdGroupCriterion($customerId, $adGroupId, -1);

            $listingOps = [];
            $listingOps[] = (new AdGroupCriterionOperation())->setCreate(new AdGroupCriterion([
                'resource_name' => $rootResourceName,
                'status' => AdGroupCriterionStatus::ENABLED,
                'listing_group' => new ListingGroupInfo([
                    'type' => ListingGroupType::SUBDIVISION,
                ]),
            ]));

            foreach ($itemIds as $itemId) {
                $listingOps[] = (new AdGroupCriterionOperation())->setCreate(new AdGroupCriterion([
                    'ad_group' => $adGroupResourceName,
                    'status' => AdGroupCriterionStatus::ENABLED,
                    'listing_group' => new ListingGroupInfo([
                        'type' => ListingGroupType::UNIT,
                        'parent_ad_group_criterion' => $rootResourceName,
                        'case_value' => new ListingDimensionInfo([
                            'product_item_id' => new ProductItemIdInfo(['value' => $itemId]),
                        ]),
                    ]),
                    'cpc_bid_micros' => $cpcBidMicros,
                ]));
            }

            // "Everything else" — required sibling; exclude from bidding
            $listingOps[] = (new AdGroupCriterionOperation())->setCreate(new AdGroupCriterion([
                'ad_group' => $adGroupResourceName,
                'status' => AdGroupCriterionStatus::ENABLED,
                'negative' => true,
                'listing_group' => new ListingGroupInfo([
                    'type' => ListingGroupType::UNIT,
                    'parent_ad_group_criterion' => $rootResourceName,
                    'case_value' => new ListingDimensionInfo([
                        'product_item_id' => new ProductItemIdInfo(),
                    ]),
                ]),
            ]));

            $this->getClient()->getAdGroupCriterionServiceClient()->mutateAdGroupCriteria(
                new MutateAdGroupCriteriaRequest([
                    'customer_id' => $customerId,
                    'operations' => $listingOps,
                ])
            );

            $campaignId = preg_replace('/^.*\//', '', $campaignResourceName) ?: '';

            Log::info('Created Google Shopping product campaign', [
                'customer_id' => $customerId,
                'campaign_name' => $campaignName,
                'campaign_id' => $campaignId,
                'item_ids' => $itemIds,
                'item_count' => count($itemIds),
                'budget_resource' => $budgetResourceName,
                'ad_group_resource' => $adGroupResourceName,
            ]);

            return [
                'campaign_id' => (string) $campaignId,
                'campaign_resource_name' => $campaignResourceName,
                'budget_resource_name' => $budgetResourceName,
                'ad_group_resource_name' => $adGroupResourceName,
                'campaign_name' => $campaignName,
                'item_ids' => $itemIds,
                'item_id' => $itemIds[0],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create Google Shopping product campaign', [
                'customer_id' => $customerId,
                'campaign_name' => $campaignName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Push campaign-level negative keywords to a Google Ads campaign.
     *
     * @param  list<string>  $keywords
     * @return array{added: int, skipped: int, failed: int, campaign_id: string, match_type: string, errors: list<string>}
     */
    public function pushCampaignNegativeKeywords(
        string $customerId,
        string $campaignId,
        array $keywords,
        string $matchType = 'PHRASE'
    ): array {
        $customerId = str_replace('-', '', trim($customerId));
        $campaignId = preg_replace('/\D+/', '', trim($campaignId)) ?: '';
        if ($customerId === '' || $campaignId === '') {
            throw new \InvalidArgumentException('customer_id and campaign_id are required');
        }

        $matchType = strtoupper(trim($matchType));
        $matchEnum = match ($matchType) {
            'EXACT' => KeywordMatchType::EXACT,
            'BROAD' => KeywordMatchType::BROAD,
            default => KeywordMatchType::PHRASE,
        };
        if (! in_array($matchType, ['EXACT', 'BROAD', 'PHRASE'], true)) {
            $matchType = 'PHRASE';
        }

        $clean = [];
        $seen = [];
        foreach ($keywords as $kw) {
            $text = preg_replace('/\s+/', ' ', trim((string) $kw));
            if ($text === '') {
                continue;
            }
            // Google Ads keyword text max length is 80 chars.
            if (mb_strlen($text) > 80) {
                $text = mb_substr($text, 0, 80);
            }
            $key = strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = $text;
        }

        if ($clean === []) {
            return [
                'added' => 0,
                'skipped' => 0,
                'failed' => 0,
                'campaign_id' => $campaignId,
                'match_type' => $matchType,
                'errors' => ['No keywords to push.'],
            ];
        }

        $campaignResource = ResourceNames::forCampaign($customerId, $campaignId);
        $added = 0;
        $failed = 0;
        $errors = [];

        foreach (array_chunk($clean, 100) as $chunk) {
            $operations = [];
            foreach ($chunk as $text) {
                $operations[] = (new CampaignCriterionOperation())->setCreate(new CampaignCriterion([
                    'campaign' => $campaignResource,
                    'negative' => true,
                    'keyword' => new KeywordInfo([
                        'text' => $text,
                        'match_type' => $matchEnum,
                    ]),
                ]));
            }

            try {
                $resp = $this->getClient()->getCampaignCriterionServiceClient()->mutateCampaignCriteria(
                    new MutateCampaignCriteriaRequest([
                        'customer_id' => $customerId,
                        'operations' => $operations,
                        'partial_failure' => true,
                    ])
                );
                $added += $resp->getResults() ? $resp->getResults()->count() : 0;

                if ($resp->hasPartialFailureError()) {
                    $msg = $resp->getPartialFailureError()->getMessage();
                    if ($msg !== '') {
                        $errors[] = $msg;
                    }
                    $failed += max(0, count($chunk) - ($resp->getResults() ? $resp->getResults()->count() : 0));
                }
            } catch (\Throwable $e) {
                Log::error('Failed pushing Google Ads negative keywords', [
                    'customer_id' => $customerId,
                    'campaign_id' => $campaignId,
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
                $failed += count($chunk);
                $errors[] = $e->getMessage();
            }
        }

        Log::info('Pushed Google Ads campaign negative keywords', [
            'customer_id' => $customerId,
            'campaign_id' => $campaignId,
            'match_type' => $matchType,
            'requested' => count($clean),
            'added' => $added,
            'failed' => $failed,
        ]);

        return [
            'added' => $added,
            'skipped' => 0,
            'failed' => $failed,
            'campaign_id' => $campaignId,
            'match_type' => $matchType,
            'errors' => array_slice($errors, 0, 5),
        ];
    }

    /**
     * Fetch live Shopping product Item IDs (listing-group UNIT case values) for campaigns.
     *
     * @param  list<string|int>  $campaignIds
     * @return array<string, list<string>> campaign_id => unique product_item_id values
     */
    public function fetchShoppingProductItemIdsByCampaignIds($customerId, array $campaignIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => preg_replace('/\D+/', '', (string) $id),
            $campaignIds
        ), static fn ($id) => $id !== '' && $id !== null)));

        if ($ids === [] || empty($customerId)) {
            return [];
        }

        $out = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $inList = implode(', ', $chunk);
            $query = "
                SELECT
                    campaign.id,
                    ad_group_criterion.listing_group.type,
                    ad_group_criterion.listing_group.case_value.product_item_id.value,
                    ad_group_criterion.negative
                FROM ad_group_criterion
                WHERE campaign.id IN ({$inList})
                  AND ad_group_criterion.listing_group.type = 'UNIT'
                  AND ad_group_criterion.negative = FALSE
                  AND ad_group_criterion.status != 'REMOVED'
                  AND ad_group_criterion.listing_group.case_value.product_item_id.value IS NOT NULL
            ";

            try {
                $rows = $this->runQuery($customerId, $query);
            } catch (\Throwable $e) {
                Log::error('Failed to fetch Shopping product item IDs', [
                    'customer_id' => $customerId,
                    'campaign_ids' => $chunk,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            foreach ($rows as $row) {
                $cid = (string) ($row['campaign']['id'] ?? '');
                if ($cid === '') {
                    continue;
                }
                $itemId = $this->extractListingGroupProductItemId($row);
                if ($itemId === '') {
                    continue;
                }
                if (! isset($out[$cid])) {
                    $out[$cid] = [];
                }
                $out[$cid][$itemId] = true;
            }
        }

        $normalized = [];
        foreach ($out as $cid => $set) {
            $normalized[$cid] = array_keys($set);
            sort($normalized[$cid]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function extractListingGroupProductItemId(array $row): string
    {
        $criterion = $row['adGroupCriterion'] ?? $row['ad_group_criterion'] ?? [];
        $listing = $criterion['listingGroup'] ?? $criterion['listing_group'] ?? [];
        $caseValue = $listing['caseValue'] ?? $listing['case_value'] ?? [];
        $productItem = $caseValue['productItemId'] ?? $caseValue['product_item_id'] ?? [];

        return trim((string) ($productItem['value'] ?? ''));
    }


    /**
     * Align a Shopping campaign's listing-group Item IDs with the expected Merchant Center IDs:
     *  - add Item IDs that are missing,
     *  - re-include (excluded) or enable (paused) Item IDs that already exist (regardless of inventory),
     *  - remove Item IDs that do not belong to the campaign's SKU(s).
     * Only the simple tree this app builds is touched (root SUBDIVISION + one UNIT per Item ID +
     * "everything else"); any other structure is reported as unsupported and left unchanged.
     *
     * @param  list<string>  $expectedIds
     * @return array{status: string, add: list<string>, reinclude: list<string>, enable: list<string>, remove: list<string>, skip: list<string>, applied: bool, message: string}
     *         status: ok | nothing | unsupported | error
     */
    public function syncShoppingItemIds(string $customerId, string $campaignId, array $expectedIds, bool $dryRun = true): array
    {
        $customerId = str_replace('-', '', trim($customerId));
        $campaignId = preg_replace('/\D+/', '', $campaignId) ?: '';
        $expected = array_values(array_unique(array_filter(array_map(static fn ($v) => trim((string) $v), $expectedIds), static fn ($v) => $v !== '')));
        sort($expected);

        $result = static fn (string $status, string $message, array $p = [], bool $applied = false) => [
            'status' => $status,
            'add' => $p['add'] ?? [],
            'reinclude' => $p['reinclude'] ?? [],
            'enable' => $p['enable'] ?? [],
            'remove' => $p['remove'] ?? [],
            'skip' => $p['skip'] ?? [],
            'applied' => $applied,
            'message' => $message,
        ];

        if ($customerId === '' || $campaignId === '') {
            return $result('error', 'customer_id and campaign_id are required.');
        }
        if ($expected === []) {
            return $result('unsupported', 'No Merchant Center Item IDs found for this campaign\'s SKUs — nothing safe to apply.');
        }

        $query = "
            SELECT
                ad_group.id,
                ad_group.resource_name,
                ad_group.cpc_bid_micros,
                ad_group_criterion.resource_name,
                ad_group_criterion.criterion_id,
                ad_group_criterion.status,
                ad_group_criterion.negative,
                ad_group_criterion.cpc_bid_micros,
                ad_group_criterion.listing_group.type,
                ad_group_criterion.listing_group.parent_ad_group_criterion,
                ad_group_criterion.listing_group.case_value.product_item_id.value
            FROM ad_group_criterion
            WHERE campaign.id = {$campaignId}
              AND ad_group_criterion.type = 'LISTING_GROUP'
              AND ad_group_criterion.status != 'REMOVED'
        ";

        try {
            $rows = $this->runQuery($customerId, $query);
        } catch (\Throwable $e) {
            return $result('error', self::briefGoogleError($e));
        }

        $adGroups = [];
        $nodes = [];
        foreach ($rows as $row) {
            $ag = $row['adGroup'] ?? [];
            $crit = $row['adGroupCriterion'] ?? [];
            $lg = $crit['listingGroup'] ?? [];
            $agResource = (string) ($ag['resourceName'] ?? '');
            $adGroups[$agResource] = [
                'id' => (string) ($ag['id'] ?? ''),
                'cpc' => (int) ($ag['cpcBidMicros'] ?? 0),
            ];
            $nodes[] = [
                'resource' => (string) ($crit['resourceName'] ?? ''),
                'type' => (string) ($lg['type'] ?? ''),
                'parent' => (string) ($lg['parentAdGroupCriterion'] ?? ''),
                'status' => (string) ($crit['status'] ?? 'ENABLED'),
                'negative' => (bool) ($crit['negative'] ?? false),
                'item' => trim((string) ($lg['caseValue']['productItemId']['value'] ?? '')),
                'cpc' => (int) ($crit['cpcBidMicros'] ?? 0),
            ];
        }

        if (count($adGroups) > 1) {
            return $result('unsupported', 'Campaign has several ad groups with listing groups — fix manually in Google Ads.');
        }

        if ($nodes === []) {
            try {
                $agRows = $this->runQuery($customerId, "SELECT ad_group.id, ad_group.resource_name, ad_group.cpc_bid_micros FROM ad_group WHERE campaign.id = {$campaignId} AND ad_group.status != 'REMOVED'");
            } catch (\Throwable $e) {
                return $result('error', self::briefGoogleError($e));
            }
            if (count($agRows) !== 1) {
                return $result('unsupported', count($agRows) === 0 ? 'Campaign has no ad group.' : 'Campaign has several ad groups — fix manually in Google Ads.');
            }
            $ag = $agRows[0]['adGroup'] ?? [];
            $agResource = (string) ($ag['resourceName'] ?? '');
            $agId = (int) ($ag['id'] ?? 0);
            $bid = (int) ($ag['cpcBidMicros'] ?? 0) ?: 500000;
            if ($dryRun) {
                return $result('ok', 'No listing groups exist — a full tree will be created.', ['add' => $expected]);
            }
            try {
                $this->createFlatListingTree($customerId, $agResource, $agId, $expected, $bid);
            } catch (\Throwable $e) {
                return $result('error', self::briefGoogleError($e), ['add' => $expected]);
            }

            return $result('ok', 'Created listing groups for '.count($expected).' Item ID(s).', ['add' => $expected], true);
        }

        $agResource = (string) array_key_first($adGroups);
        $agInfo = $adGroups[$agResource];

        $roots = array_values(array_filter($nodes, static fn ($n) => $n['parent'] === ''));
        if (count($roots) !== 1 || $roots[0]['type'] !== 'SUBDIVISION') {
            return $result('unsupported', 'Campaign targets "All products" or a custom tree — fix manually in Google Ads.');
        }
        $rootResource = $roots[0]['resource'];

        // Item IDs are matched case-insensitively (Google treats them as the same product).
        $byItem = [];
        $siblingBid = 0;
        $hasOthers = false;
        foreach ($nodes as $n) {
            if ($n['parent'] === '') {
                continue;
            }
            if ($n['parent'] !== $rootResource || $n['type'] !== 'UNIT') {
                return $result('unsupported', 'Listing group tree has custom subdivisions — fix manually in Google Ads.');
            }
            if ($n['item'] === '') {
                $hasOthers = true;

                continue;
            }
            $byItem[strtolower($n['item'])][] = $n;
            if (! $n['negative']) {
                $siblingBid = $siblingBid ?: $n['cpc'];
            }
        }
        if (! $hasOthers) {
            return $result('unsupported', 'Listing group tree has no "everything else" node — fix manually in Google Ads.');
        }

        $expectedKeys = [];
        foreach ($expected as $e) {
            $expectedKeys[strtolower($e)] = $e;
        }

        $add = $reinclude = $enable = $remove = $skip = [];
        $removeOps = [];
        $updateOps = [];
        foreach ($expectedKeys as $key => $itemId) {
            $existing = $byItem[$key] ?? [];
            if ($existing === []) {
                $add[] = $itemId;

                continue;
            }
            $positive = array_values(array_filter($existing, static fn ($n) => ! $n['negative']));
            if (array_filter($positive, static fn ($n) => $n['status'] === 'ENABLED') !== []) {
                continue; // already attached and enabled
            }
            if ($positive !== []) {
                $enable[] = $itemId;
                $updateOps[] = $positive[0]['resource'];
            } else {
                $reinclude[] = $itemId;
                foreach ($existing as $n) {
                    $removeOps[] = $n['resource'];
                }
            }
        }
        foreach ($byItem as $key => $existing) {
            if (isset($expectedKeys[$key])) {
                continue;
            }
            foreach ($existing as $n) {
                if (! $n['negative']) {
                    $remove[] = $n['item'];
                    $removeOps[] = $n['resource'];
                }
            }
        }
        $remove = array_values(array_unique($remove));
        sort($add);
        sort($reinclude);
        sort($enable);
        sort($remove);

        $plan = ['add' => $add, 'reinclude' => $reinclude, 'enable' => $enable, 'remove' => $remove, 'skip' => $skip];
        if ($add === [] && $reinclude === [] && $enable === [] && $remove === []) {
            return $result('nothing', 'Item IDs already match Merchant Center — nothing to fix.', $plan);
        }
        if ($dryRun) {
            return $result('ok', 'Ready to apply.', $plan);
        }

        $bid = $siblingBid ?: ($agInfo['cpc'] ?: 500000);
        $ops = [];
        foreach (array_unique($removeOps) as $resource) {
            $ops[] = (new AdGroupCriterionOperation())->setRemove($resource);
        }
        foreach ($updateOps as $resource) {
            $ops[] = (new AdGroupCriterionOperation())
                ->setUpdate(new AdGroupCriterion(['resource_name' => $resource, 'status' => AdGroupCriterionStatus::ENABLED]))
                ->setUpdateMask(new FieldMask(['paths' => ['status']]));
        }
        foreach (array_merge($add, $reinclude) as $itemId) {
            $ops[] = (new AdGroupCriterionOperation())->setCreate(new AdGroupCriterion([
                'ad_group' => $agResource,
                'status' => AdGroupCriterionStatus::ENABLED,
                'listing_group' => new ListingGroupInfo([
                    'type' => ListingGroupType::UNIT,
                    'parent_ad_group_criterion' => $rootResource,
                    'case_value' => new ListingDimensionInfo([
                        'product_item_id' => new ProductItemIdInfo(['value' => $itemId]),
                    ]),
                ]),
                'cpc_bid_micros' => $bid,
            ]));
        }

        try {
            $this->getClient()->getAdGroupCriterionServiceClient()->mutateAdGroupCriteria(
                new MutateAdGroupCriteriaRequest([
                    'customer_id' => $customerId,
                    'operations' => $ops,
                ])
            );
        } catch (\Throwable $e) {
            Log::error('Failed to sync Shopping Item IDs', ['campaign_id' => $campaignId] + $plan + ['error' => $e->getMessage()]);

            return $result('error', self::briefGoogleError($e), $plan);
        }

        Log::info('Synced Shopping Item IDs', ['campaign_id' => $campaignId] + $plan);

        return $result('ok', 'Added '.count($add).', re-included '.count($reinclude).', enabled '.count($enable).', removed '.count($remove).' Item ID(s) in Google Ads.', $plan, true);
    }

    /**
     * Listing-group nodes of a Shopping campaign (non-removed), with the ad groups they live in.
     *
     * @return array{nodes: list<array<string, mixed>>, adGroups: array<string, array{id: string, cpc: int}>}
     */
    private function fetchListingGroupNodes(string $customerId, string $campaignId): array
    {
        $rows = $this->runQuery($customerId, "
            SELECT
                ad_group.id,
                ad_group.resource_name,
                ad_group.cpc_bid_micros,
                ad_group_criterion.resource_name,
                ad_group_criterion.status,
                ad_group_criterion.negative,
                ad_group_criterion.cpc_bid_micros,
                ad_group_criterion.listing_group.type,
                ad_group_criterion.listing_group.parent_ad_group_criterion,
                ad_group_criterion.listing_group.case_value.product_item_id.value
            FROM ad_group_criterion
            WHERE campaign.id = {$campaignId}
              AND ad_group_criterion.type = 'LISTING_GROUP'
              AND ad_group_criterion.status != 'REMOVED'
        ");

        $adGroups = [];
        $nodes = [];
        foreach ($rows as $row) {
            $ag = $row['adGroup'] ?? [];
            $crit = $row['adGroupCriterion'] ?? [];
            $lg = $crit['listingGroup'] ?? [];
            $agResource = (string) ($ag['resourceName'] ?? '');
            $adGroups[$agResource] = ['id' => (string) ($ag['id'] ?? ''), 'cpc' => (int) ($ag['cpcBidMicros'] ?? 0)];
            $nodes[] = [
                'resource' => (string) ($crit['resourceName'] ?? ''),
                'type' => (string) ($lg['type'] ?? ''),
                'parent' => (string) ($lg['parentAdGroupCriterion'] ?? ''),
                'status' => (string) ($crit['status'] ?? 'ENABLED'),
                'negative' => (bool) ($crit['negative'] ?? false),
                'item' => trim((string) ($lg['caseValue']['productItemId']['value'] ?? '')),
                'cpc' => (int) ($crit['cpcBidMicros'] ?? 0),
                'adGroup' => $agResource,
            ];
        }

        return ['nodes' => $nodes, 'adGroups' => $adGroups];
    }

    /**
     * Current state of every Item ID in a Shopping campaign's listing groups.
     * state: ENABLED | PAUSED | EXCLUDED (negative unit only). Keys are lower-cased Item IDs.
     *
     * @return array{status: string, message: string, items: array<string, array{item: string, state: string}>}
     */
    public function listShoppingItemStates(string $customerId, string $campaignId): array
    {
        $customerId = str_replace('-', '', trim($customerId));
        $campaignId = preg_replace('/\D+/', '', $campaignId) ?: '';
        if ($customerId === '' || $campaignId === '') {
            return ['status' => 'error', 'message' => 'customer_id and campaign_id are required.', 'items' => []];
        }
        try {
            $data = $this->fetchListingGroupNodes($customerId, $campaignId);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => self::briefGoogleError($e), 'items' => []];
        }

        $byItem = [];
        foreach ($data['nodes'] as $n) {
            if ($n['item'] === '') {
                continue;
            }
            $byItem[strtolower($n['item'])][] = $n;
        }
        $items = [];
        foreach ($byItem as $key => $nodes) {
            $positive = array_values(array_filter($nodes, static fn ($n) => ! $n['negative']));
            if ($positive === []) {
                $state = 'EXCLUDED';
            } elseif (array_filter($positive, static fn ($n) => $n['status'] === 'ENABLED') !== []) {
                $state = 'ENABLED';
            } else {
                $state = 'PAUSED';
            }
            $items[$key] = ['item' => $nodes[0]['item'], 'state' => $state];
        }

        return ['status' => 'ok', 'message' => $data['nodes'] === [] ? 'No listing groups in this campaign.' : '', 'items' => $items];
    }

    /**
     * Enable, pause or remove one Item ID in a Shopping campaign's listing groups.
     *  - pause:  paused unit (the product stops serving).
     *  - enable: enables a paused unit; re-includes an excluded one; adds one that is not attached (flat tree only).
     *  - remove: removes the unit(s) for the Item ID.
     *
     * @return array{ok: bool, message: string, state: string}
     */
    public function setShoppingItemState(string $customerId, string $campaignId, string $itemId, string $action): array
    {
        $customerId = str_replace('-', '', trim($customerId));
        $campaignId = preg_replace('/\D+/', '', $campaignId) ?: '';
        $itemId = trim($itemId);
        $action = strtolower(trim($action));
        $fail = static fn (string $m) => ['ok' => false, 'message' => $m, 'state' => ''];

        if ($customerId === '' || $campaignId === '' || $itemId === '') {
            return $fail('customer_id, campaign_id and item_id are required.');
        }
        if (! in_array($action, ['enable', 'pause', 'remove'], true)) {
            return $fail('Unknown action.');
        }

        try {
            $data = $this->fetchListingGroupNodes($customerId, $campaignId);
        } catch (\Throwable $e) {
            return $fail(self::briefGoogleError($e));
        }
        $nodes = $data['nodes'];
        $key = strtolower($itemId);
        $mine = array_values(array_filter($nodes, static fn ($n) => $n['item'] !== '' && strtolower($n['item']) === $key));
        $positive = array_values(array_filter($mine, static fn ($n) => ! $n['negative']));

        $ops = [];
        $state = '';
        $note = '';
        if ($action === 'pause') {
            if ($positive === []) {
                return $fail('This Item ID is not attached in the ad — nothing to pause.');
            }
            foreach ($positive as $n) {
                if ($n['status'] !== 'PAUSED') {
                    $ops[] = (new AdGroupCriterionOperation())
                        ->setUpdate(new AdGroupCriterion(['resource_name' => $n['resource'], 'status' => AdGroupCriterionStatus::PAUSED]))
                        ->setUpdateMask(new FieldMask(['paths' => ['status']]));
                }
            }
            $state = 'PAUSED';
            $note = $ops === [] ? 'Already paused.' : 'Item paused in the ad.';
        } elseif ($action === 'remove') {
            if ($mine === []) {
                return $fail('This Item ID is not in the ad — nothing to remove.');
            }
            foreach ($mine as $n) {
                $ops[] = (new AdGroupCriterionOperation())->setRemove($n['resource']);
            }
            $state = 'NOT_ATTACHED';
            $note = 'Item removed from the ad.';
        } else { // enable
            if ($positive !== []) {
                foreach ($positive as $n) {
                    if ($n['status'] !== 'ENABLED') {
                        $ops[] = (new AdGroupCriterionOperation())
                            ->setUpdate(new AdGroupCriterion(['resource_name' => $n['resource'], 'status' => AdGroupCriterionStatus::ENABLED]))
                            ->setUpdateMask(new FieldMask(['paths' => ['status']]));
                    }
                }
                $state = 'ENABLED';
                $note = $ops === [] ? 'Already enabled.' : 'Item enabled in the ad.';
            } else {
                // Excluded or not attached: (re)create it as an included unit under the root — flat tree only.
                if (count($data['adGroups']) > 1) {
                    return $fail('Campaign has several ad groups with listing groups — fix manually in Google Ads.');
                }
                $roots = array_values(array_filter($nodes, static fn ($n) => $n['parent'] === ''));
                if (count($roots) !== 1 || $roots[0]['type'] !== 'SUBDIVISION') {
                    return $fail('Campaign targets "All products" or a custom tree — fix manually in Google Ads.');
                }
                $agResource = (string) array_key_first($data['adGroups']);
                $bid = 0;
                foreach ($nodes as $n) {
                    if (! $n['negative'] && $n['item'] !== '' && $n['cpc'] > 0) {
                        $bid = $n['cpc'];
                        break;
                    }
                }
                $bid = $bid ?: ($data['adGroups'][$agResource]['cpc'] ?: 500000);
                foreach ($mine as $n) {
                    $ops[] = (new AdGroupCriterionOperation())->setRemove($n['resource']);
                }
                $ops[] = (new AdGroupCriterionOperation())->setCreate(new AdGroupCriterion([
                    'ad_group' => $agResource,
                    'status' => AdGroupCriterionStatus::ENABLED,
                    'listing_group' => new ListingGroupInfo([
                        'type' => ListingGroupType::UNIT,
                        'parent_ad_group_criterion' => $roots[0]['resource'],
                        'case_value' => new ListingDimensionInfo([
                            'product_item_id' => new ProductItemIdInfo(['value' => $itemId]),
                        ]),
                    ]),
                    'cpc_bid_micros' => $bid,
                ]));
                $state = 'ENABLED';
                $note = $mine === [] ? 'Item added and enabled in the ad.' : 'Item re-included and enabled in the ad.';
            }
        }

        if ($ops === []) {
            return ['ok' => true, 'message' => $note, 'state' => $state];
        }

        try {
            $this->getClient()->getAdGroupCriterionServiceClient()->mutateAdGroupCriteria(
                new MutateAdGroupCriteriaRequest(['customer_id' => $customerId, 'operations' => $ops])
            );
        } catch (\Throwable $e) {
            Log::error('Failed to set Shopping Item ID state', ['campaign_id' => $campaignId, 'item' => $itemId, 'action' => $action, 'error' => $e->getMessage()]);

            return $fail(self::briefGoogleError($e));
        }
        Log::info('Set Shopping Item ID state', ['campaign_id' => $campaignId, 'item' => $itemId, 'action' => $action]);

        return ['ok' => true, 'message' => $note, 'state' => $state];
    }

    /**
     * Listing-group nodes (non-removed) for many Shopping campaigns at once, grouped by campaign id.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, list<array<string, mixed>>>
     */
    public function fetchListingGroupNodesByCampaign(string $customerId, array $campaignIds): array
    {
        $customerId = str_replace('-', '', trim($customerId));
        $ids = array_values(array_unique(array_filter(array_map(static fn ($id) => preg_replace('/\D+/', '', (string) $id), $campaignIds))));
        $out = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $rows = $this->runQuery($customerId, '
                SELECT
                    campaign.id,
                    ad_group_criterion.resource_name,
                    ad_group_criterion.status,
                    ad_group_criterion.negative,
                    ad_group_criterion.listing_group.type,
                    ad_group_criterion.listing_group.parent_ad_group_criterion,
                    ad_group_criterion.listing_group.case_value.product_item_id.value
                FROM ad_group_criterion
                WHERE campaign.id IN ('.implode(', ', $chunk).")
                  AND ad_group_criterion.type = 'LISTING_GROUP'
                  AND ad_group_criterion.status != 'REMOVED'
            ");
            foreach ($rows as $row) {
                $cid = (string) ($row['campaign']['id'] ?? '');
                $crit = $row['adGroupCriterion'] ?? [];
                $lg = $crit['listingGroup'] ?? [];
                if ($cid === '') {
                    continue;
                }
                $out[$cid][] = [
                    'resource' => (string) ($crit['resourceName'] ?? ''),
                    'type' => (string) ($lg['type'] ?? ''),
                    'parent' => (string) ($lg['parentAdGroupCriterion'] ?? ''),
                    'status' => (string) ($crit['status'] ?? 'ENABLED'),
                    'negative' => (bool) ($crit['negative'] ?? false),
                    'item' => trim((string) ($lg['caseValue']['productItemId']['value'] ?? '')),
                ];
            }
        }

        return $out;
    }

    /**
     * Remove the Item ID units that are not serving — excluded (grey "−" in Google Ads) or paused — from a campaign.
     * Only the simple tree this app builds is touched (root SUBDIVISION + one UNIT per Item ID + "everything else");
     * the "everything else" node is never removed. Any other structure is reported as skipped.
     *
     * @param  list<array<string, mixed>>  $nodes  from fetchListingGroupNodesByCampaign()
     * @return array{status: string, removed: list<array{item: string, reason: string}>, applied: bool, message: string}
     */
    public function removeInactiveShoppingItems(string $customerId, string $campaignId, array $nodes, bool $dryRun = true): array
    {
        $customerId = str_replace('-', '', trim($customerId));
        $res = static fn (string $status, string $message, array $removed = [], bool $applied = false) => [
            'status' => $status, 'removed' => $removed, 'applied' => $applied, 'message' => $message,
        ];

        $roots = array_values(array_filter($nodes, static fn ($n) => $n['parent'] === ''));
        if (count($roots) !== 1 || $roots[0]['type'] !== 'SUBDIVISION') {
            return $res('skipped', 'Campaign targets "All products", has no listing groups or a custom tree.');
        }
        $rootResource = $roots[0]['resource'];
        foreach ($nodes as $n) {
            if ($n['parent'] !== '' && ($n['parent'] !== $rootResource || $n['type'] !== 'UNIT')) {
                return $res('skipped', 'Listing group tree has custom subdivisions.');
            }
        }

        $removed = [];
        $ops = [];
        foreach ($nodes as $n) {
            if ($n['parent'] === '' || $n['item'] === '') {
                continue; // root, or the "everything else" node
            }
            $reason = $n['negative'] ? 'excluded' : ($n['status'] === 'PAUSED' ? 'paused' : '');
            if ($reason === '') {
                continue;
            }
            $removed[] = ['item' => $n['item'], 'reason' => $reason];
            $ops[] = (new AdGroupCriterionOperation())->setRemove($n['resource']);
        }
        if ($ops === []) {
            return $res('nothing', 'No excluded or paused Item IDs.');
        }
        if ($dryRun) {
            return $res('ok', 'Would remove '.count($ops).' Item ID(s).', $removed);
        }

        try {
            $this->getClient()->getAdGroupCriterionServiceClient()->mutateAdGroupCriteria(
                new MutateAdGroupCriteriaRequest(['customer_id' => $customerId, 'operations' => $ops])
            );
        } catch (\Throwable $e) {
            Log::error('Failed to remove inactive Shopping Item IDs', ['campaign_id' => $campaignId, 'error' => $e->getMessage()]);

            return $res('error', self::briefGoogleError($e), $removed);
        }
        Log::info('Removed inactive Shopping Item IDs', ['campaign_id' => $campaignId, 'count' => count($ops)]);

        return $res('ok', 'Removed '.count($ops).' Item ID(s).', $removed, true);
    }

    /**
     * @param  list<string>  $itemIds
     */
    private function createFlatListingTree(string $customerId, string $adGroupResourceName, int $adGroupId, array $itemIds, int $bidMicros): void
    {
        $rootResourceName = ResourceNames::forAdGroupCriterion($customerId, $adGroupId, -1);
        $ops = [];
        $ops[] = (new AdGroupCriterionOperation())->setCreate(new AdGroupCriterion([
            'resource_name' => $rootResourceName,
            'status' => AdGroupCriterionStatus::ENABLED,
            'listing_group' => new ListingGroupInfo(['type' => ListingGroupType::SUBDIVISION]),
        ]));
        foreach ($itemIds as $itemId) {
            $ops[] = (new AdGroupCriterionOperation())->setCreate(new AdGroupCriterion([
                'ad_group' => $adGroupResourceName,
                'status' => AdGroupCriterionStatus::ENABLED,
                'listing_group' => new ListingGroupInfo([
                    'type' => ListingGroupType::UNIT,
                    'parent_ad_group_criterion' => $rootResourceName,
                    'case_value' => new ListingDimensionInfo([
                        'product_item_id' => new ProductItemIdInfo(['value' => $itemId]),
                    ]),
                ]),
                'cpc_bid_micros' => $bidMicros,
            ]));
        }
        $ops[] = (new AdGroupCriterionOperation())->setCreate(new AdGroupCriterion([
            'ad_group' => $adGroupResourceName,
            'status' => AdGroupCriterionStatus::ENABLED,
            'negative' => true,
            'listing_group' => new ListingGroupInfo([
                'type' => ListingGroupType::UNIT,
                'parent_ad_group_criterion' => $rootResourceName,
                'case_value' => new ListingDimensionInfo(['product_item_id' => new ProductItemIdInfo()]),
            ]),
        ]));
        $this->getClient()->getAdGroupCriterionServiceClient()->mutateAdGroupCriteria(
            new MutateAdGroupCriteriaRequest(['customer_id' => $customerId, 'operations' => $ops])
        );
    }
}
