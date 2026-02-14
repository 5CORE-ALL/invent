<?php

namespace App\Services;

use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Ads\GoogleAds\Lib\V20\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V20\Services\AdGroupOperation;
use Google\Ads\GoogleAds\V20\Services\AdGroupCriterionOperation;
use Google\Ads\GoogleAds\V20\Resources\AdGroup;
use Google\Ads\GoogleAds\V20\Resources\AdGroupCriterion;
use Google\Protobuf\FieldMask;
use Google\Ads\GoogleAds\V20\Services\SearchGoogleAdsStreamRequest;
use Google\Ads\GoogleAds\V20\Services\MutateAdGroupsRequest;
use Illuminate\Support\Facades\Log;
use Google\Ads\GoogleAds\V20\Services\MutateAdGroupCriteriaRequest;
use Google\Ads\GoogleAds\V20\Services\CampaignBudgetOperation;
use Google\Ads\GoogleAds\V20\Services\MutateCampaignBudgetsRequest;
use Google\Ads\GoogleAds\V20\Resources\CampaignBudget;
use Google\Ads\GoogleAds\V20\Services\AdGroupAdOperation;
use Google\Ads\GoogleAds\V20\Services\MutateAdGroupAdsRequest;
use Google\Ads\GoogleAds\V20\Resources\AdGroupAd;
use Google\Ads\GoogleAds\V20\Enums\AdGroupAdStatusEnum\AdGroupAdStatus;
use Google\Ads\GoogleAds\V20\Services\CampaignOperation;
use Google\Ads\GoogleAds\V20\Services\MutateCampaignsRequest;
use Google\Ads\GoogleAds\V20\Resources\Campaign;
use Google\Ads\GoogleAds\V20\Enums\CampaignStatusEnum\CampaignStatus;

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
    public function updateCampaignSbids($customerId, $campaignId, $sbidFactor)
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

                    // Query product groups for this ad group
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

}
