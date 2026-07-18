<?php

namespace App\Services;

use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Ads\GoogleAds\Lib\V22\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V22\Services\AdGroupOperation;
use Google\Ads\GoogleAds\V22\Services\AdGroupCriterionOperation;
use Google\Ads\GoogleAds\V22\Resources\AdGroup;
use Google\Ads\GoogleAds\V22\Resources\AdGroupCriterion;
use Google\Protobuf\FieldMask;
use Google\Ads\GoogleAds\V22\Services\SearchGoogleAdsStreamRequest;
use Google\Ads\GoogleAds\V22\Services\MutateAdGroupsRequest;
use Illuminate\Support\Facades\Log;
use Google\Ads\GoogleAds\V22\Services\MutateAdGroupCriteriaRequest;
use Google\Ads\GoogleAds\V22\Services\CampaignBudgetOperation;
use Google\Ads\GoogleAds\V22\Services\MutateCampaignBudgetsRequest;
use Google\Ads\GoogleAds\V22\Resources\CampaignBudget;
use Google\Ads\GoogleAds\V22\Services\AdGroupAdOperation;
use Google\Ads\GoogleAds\V22\Services\MutateAdGroupAdsRequest;
use Google\Ads\GoogleAds\V22\Resources\AdGroupAd;
use Google\Ads\GoogleAds\V22\Enums\AdGroupAdStatusEnum\AdGroupAdStatus;
use Google\Ads\GoogleAds\V22\Services\CampaignOperation;
use Google\Ads\GoogleAds\V22\Services\MutateCampaignsRequest;
use Google\Ads\GoogleAds\V22\Resources\Campaign;
use Google\Ads\GoogleAds\V22\Resources\Campaign\ShoppingSetting;
use Google\Ads\GoogleAds\V22\Resources\Ad;
use Google\Ads\GoogleAds\V22\Common\MaximizeConversions;
use Google\Ads\GoogleAds\V22\Common\TargetSpend;
use Google\Ads\GoogleAds\V22\Common\KeywordInfo;
use Google\Ads\GoogleAds\V22\Common\ManualCpc;
use Google\Ads\GoogleAds\V22\Common\ListingDimensionInfo;
use Google\Ads\GoogleAds\V22\Common\ListingGroupInfo;
use Google\Ads\GoogleAds\V22\Common\ProductItemIdInfo;
use Google\Ads\GoogleAds\V22\Common\ShoppingProductAdInfo;
use Google\Ads\GoogleAds\Util\V22\ResourceNames;
use Google\Ads\GoogleAds\V22\Enums\CampaignStatusEnum\CampaignStatus;
use Google\Ads\GoogleAds\V22\Enums\AdvertisingChannelTypeEnum\AdvertisingChannelType;
use Google\Ads\GoogleAds\V22\Enums\AdGroupTypeEnum\AdGroupType;
use Google\Ads\GoogleAds\V22\Enums\AdGroupStatusEnum\AdGroupStatus;
use Google\Ads\GoogleAds\V22\Enums\AdGroupCriterionStatusEnum\AdGroupCriterionStatus;
use Google\Ads\GoogleAds\V22\Enums\KeywordMatchTypeEnum\KeywordMatchType;
use Google\Ads\GoogleAds\V22\Enums\ListingGroupTypeEnum\ListingGroupType;
use Google\Ads\GoogleAds\V22\Enums\BudgetDeliveryMethodEnum\BudgetDeliveryMethod;
use Google\Ads\GoogleAds\V22\Enums\EuPoliticalAdvertisingStatusEnum\EuPoliticalAdvertisingStatus;
use Google\Ads\GoogleAds\V22\Resources\CampaignCriterion;
use Google\Ads\GoogleAds\V22\Services\CampaignCriterionOperation;
use Google\Ads\GoogleAds\V22\Services\MutateCampaignCriteriaRequest;

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
                                    // Continue with other product groups
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to query product groups", [
                            'ad_group_resource' => $adGroupResource,
                            'error' => $e->getMessage()
                        ]);
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

}
