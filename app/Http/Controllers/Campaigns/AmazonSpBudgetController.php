<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSbCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\FbaTable;
use App\Models\MarketplacePercentage;
use AWS\CRT\Log;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as FacadesLog;

class AmazonSpBudgetController extends Controller
{
    protected $profileId;

    public function __construct()
    {
        $this->profileId = "4216505535403428";
    }

    public function getAccessToken()
    {
        return cache()->remember('amazon_ads_access_token', 55 * 60, function () {
            $client = new Client();

            $response = $client->post('https://api.amazon.com/auth/o2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => config('services.amazon_ads.refresh_token'),
                    'client_id' => config('services.amazon_ads.client_id'),
                    'client_secret' => config('services.amazon_ads.client_secret'),
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        });
    }

    public function getAdGroupsByCampaigns(array $campaignIds)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $normalizedCampaignIds = array_values(array_filter(array_map(function ($id) {
            if (is_null($id)) {
                return null;
            }
            // Force string to avoid JSON encoding large integers as floats
            return trim((string) $id);
        }, $campaignIds), function ($id) {
            return $id !== '';
        }));

        if (empty($normalizedCampaignIds)) {
            return [];
        }

        $url = 'https://advertising-api.amazon.com/sp/adGroups/list';
        $payload = [
            'campaignIdFilter' => ['include' => $normalizedCampaignIds],
            'stateFilter' => ['include' => ['ENABLED']],
        ];

        $response = $client->post($url, [
            'headers' => [
                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                'Authorization' => 'Bearer ' . $accessToken,
                'Amazon-Advertising-API-Scope' => $this->profileId,
                'Content-Type' => 'application/vnd.spAdGroup.v3+json',
                'Accept' => 'application/vnd.spAdGroup.v3+json',
            ],
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['adGroups'] ?? [];
    }

    public function getKeywordsByAdGroup($adGroupId)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $url = 'https://advertising-api.amazon.com/sp/keywords/list';
        $payload = [
            'adGroupIdFilter' => ['include' => [$adGroupId]],
        ];

        $response = $client->post($url, [
            'headers' => [
                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                'Authorization' => 'Bearer ' . $accessToken,
                'Amazon-Advertising-API-Scope' => $this->profileId,
                'Content-Type' => 'application/vnd.spKeyword.v3+json',
                'Accept' => 'application/vnd.spKeyword.v3+json',
            ],
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['keywords'] ?? [];
    }

    /**
     * Get SB ad groups by campaigns (for HL campaigns)
     */
    public function getSbAdGroupsByCampaigns(array $campaignIds)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $normalizedCampaignIds = array_values(array_filter(array_map(function ($id) {
            if (is_null($id)) {
                return null;
            }
            return trim((string) $id);
        }, $campaignIds), function ($id) {
            return $id !== '';
        }));

        if (empty($normalizedCampaignIds)) {
            return [];
        }

        $url = 'https://advertising-api.amazon.com/sb/v4/adGroups/list';
        $payload = [
            'campaignIdFilter' => ['include' => $normalizedCampaignIds],
            'stateFilter' => ['include' => ['ENABLED']],
        ];

        $response = $client->post($url, [
            'headers' => [
                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                'Authorization' => 'Bearer ' . $accessToken,
                'Amazon-Advertising-API-Scope' => $this->profileId,
                'Content-Type' => 'application/vnd.sbadgroupresource.v4+json',
                'Accept' => 'application/vnd.sbadgroupresource.v4+json',
            ],
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['adGroups'] ?? [];
    }

    /**
     * Get SB keywords by ad group (for HL campaigns)
     */
    public function getSbKeywordsByAdGroup($adGroupId)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $url = 'https://advertising-api.amazon.com/sb/keywords';
        
        $response = $client->get($url, [
            'headers' => [
                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                'Authorization' => 'Bearer ' . $accessToken,
                'Amazon-Advertising-API-Scope' => $this->profileId,
                'Accept' => 'application/vnd.sbkeyword.v3.2+json',
            ],
            'query' => [
                'adGroupIdFilter' => $adGroupId,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        return $data ?? [];
    }

    public function getTargetsAdByCampaign($campaignId)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $normalizedCampaignIds = array_values(array_filter(array_map(function ($id) {
            if (is_null($id)) {
                return null;
            }
            return trim((string) $id);
        }, is_array($campaignId) ? $campaignId : [$campaignId]), function ($id) {
            return $id !== '';
        }));

        if (empty($normalizedCampaignIds)) {
            return [];
        }

        $payload = [
            'campaignIdFilter' => [
                'include' => $normalizedCampaignIds,
            ],
        ];

        $response = $client->post('https://advertising-api.amazon.com/sp/targets/list', [
            'headers' => [
                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                'Authorization' => 'Bearer ' . $accessToken,
                'Amazon-Advertising-API-Scope' => $this->profileId,
                'Content-Type' => 'application/vnd.spTargetingClause.v3+json',
                'Accept' => 'application/vnd.spTargetingClause.v3+json',
            ],
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['targetingClauses'] ?? [];
    }

    public function updateAutoCampaignKeywordsBid(array $campaignIds, array $newBids)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        if (empty($campaignIds) || empty($newBids)) {
            return [
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ];
        }

        $allKeywords = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            AmazonSpCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', 'SPONSORED_PRODUCTS')
                ->whereIn('report_date_range', ['L7', 'L1'])
                ->update([
                    'apprSbid' => "approved"
                ]);

            $adGroups = $this->getAdGroupsByCampaigns([$campaignId]);
            if (empty($adGroups)) continue;

            foreach ($adGroups as $adGroup) {
                $keywords = $this->getKeywordsByAdGroup($adGroup['adGroupId']);
                foreach ($keywords as $kw) {
                    $allKeywords[] = [
                        'keywordId' => $kw['keywordId'],
                        'bid' => $newBid,
                    ];
                }
            }
        }

        if (empty($allKeywords)) {
            return response()->json([
                'message' => 'No keywords found to update',
                'status' => 404,
            ]);
        }

        $allKeywords = collect($allKeywords)
            ->unique('keywordId')
            ->values()
            ->toArray();

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sp/keywords';
        $results = [];

        try {
            $chunks = array_chunk($allKeywords, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.spKeyword.v3+json',
                        'Accept' => 'application/vnd.spKeyword.v3+json',
                    ],
                    'json' => [
                        'keywords' => $chunk
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }

            return [
                'message' => 'Keywords bid updated successfully',
                'data' => $results,
                'status' => 200,
            ];

        } catch (\Exception $e) {
            return [
                'message' => 'Error updating keywords bid',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    public function updateCampaignKeywordsBid(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $campaignIds = $request->input('campaign_ids', []);
        $newBids = $request->input('bids', []);

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $allKeywords = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            AmazonSpCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', 'SPONSORED_PRODUCTS')
                ->whereIn('report_date_range', ['L7', 'L1'])
                ->update([
                    'apprSbid' => "approved"
                ]);

            $adGroups = $this->getAdGroupsByCampaigns([$campaignId]);
            if (empty($adGroups)) continue;

            foreach ($adGroups as $adGroup) {
                $keywords = $this->getKeywordsByAdGroup($adGroup['adGroupId']);
                foreach ($keywords as $kw) {
                    $allKeywords[] = [
                        'keywordId' => $kw['keywordId'],
                        'bid' => $newBid,
                    ];
                }
            }
        }

        if (empty($allKeywords)) {
            return response()->json([
                'message' => 'No keywords found to update',
                'status' => 404,
            ]);
        }

        $allKeywords = collect($allKeywords)
            ->unique('keywordId')
            ->values()
            ->toArray();

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sp/keywords';
        $results = [];

        try {
            $chunks = array_chunk($allKeywords, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.spKeyword.v3+json',
                        'Accept' => 'application/vnd.spKeyword.v3+json',
                    ],
                    'json' => [
                        'keywords' => $chunk
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }

            return response()->json([
                'message' => 'Keywords bid updated successfully',
                'data' => $results,
                'status' => 200,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating keywords bid',
                'error' => $e->getMessage(),
                'status' => 500,
            ]);
        }
    }

    public function updateAutoCampaignTargetsBid(array $campaignIds, array $newBids)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $allTargets = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            AmazonSpCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', 'SPONSORED_PRODUCTS')
                ->whereIn('report_date_range', ['L7', 'L1'])
                ->update([
                    'apprSbid' => "approved"
                ]);

            $adTargets = $this->getTargetsAdByCampaign([$campaignId]);
            if (empty($adTargets)) continue;

            foreach ($adTargets as $adTarget) {
                $targetId = isset($adTarget['targetId']) ? trim((string) $adTarget['targetId']) : '';
                if ($targetId === '') {
                    continue;
                }
                $allTargets[] = [
                    'bid' => $newBid,
                    'targetId' => $targetId,
                ];
            }
        }

        if (empty($allTargets)) {
            return response()->json([
                'message' => 'No targets found to update',
                'status' => 404,
            ]);
        }

        $allTargets = collect($allTargets)
            ->unique('targetId')
            ->values()
            ->toArray();

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sp/targets';
        $results = [];

        try {
            $chunks = array_chunk($allTargets, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.spTargetingClause.v3+json',
                        'Accept' => 'application/vnd.spTargetingClause.v3+json',
                    ],
                    'json' => [
                        'targetingClauses' => $chunk
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }

            return  $results;

        } catch (\Exception $e) {
            return [
                'message' => 'Error updating target keywords bid',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    public function updateCampaignTargetsBid()
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $campaignIds = request('campaign_ids', []);
        $newBids = request('bids', []);

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $allTargets = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            AmazonSpCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', 'SPONSORED_PRODUCTS')
                ->whereIn('report_date_range', ['L7', 'L1'])
                ->update([
                    'apprSbid' => "approved"
                ]);

            $adTargets = $this->getTargetsAdByCampaign([$campaignId]);
            if (empty($adTargets)) continue;

            foreach ($adTargets as $adTarget) {
                $targetId = isset($adTarget['targetId']) ? trim((string) $adTarget['targetId']) : '';
                if ($targetId === '') {
                    continue;
                }
                $allTargets[] = [
                    'bid' => $newBid,
                    'targetId' => $targetId,
                ];
            }
        }

        if (empty($allTargets)) {
            return response()->json([
                'message' => 'No targets found to update',
                'status' => 404,
            ]);
        }

        $allTargets = collect($allTargets)
            ->unique('targetId')
            ->values()
            ->toArray();

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $url = 'https://advertising-api.amazon.com/sp/targets';
        $results = [];

        try {
            $chunks = array_chunk($allTargets, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.spTargetingClause.v3+json',
                        'Accept' => 'application/vnd.spTargetingClause.v3+json',
                    ],
                    'json' => [
                        'targetingClauses' => $chunk
                    ],
                    'timeout' => 60,
                    'connect_timeout' => 30,
                ]);

                $results[] = json_decode($response->getBody(), true);
            }

            return response()->json([
                'message' => 'Targets bid updated successfully',
                'data' => $results,
                'status' => 200,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating targets bid',
                'error' => $e->getMessage(),
                'status' => 500,
            ]);
        }
    }

    
    public function amzUtilizedBgtKw(){
        return view('campaign.amz-utilized-bgt-kw');
    }


    public function getAmazonUtilizationCounts(Request $request)
    {
        try {
            $campaignType = $request->get('type', 'KW'); // Default to KW, can be 'PT' or 'HL'
            $pageType = $request->get('page', ''); // Can be 'under', 'over', 'correctly' to match table counts
            
            $today = now()->format('Y-m-d');
            $skuKey = 'AMAZON_UTILIZATION_' . $campaignType . '_' . $today;
            
            $record = AmazonDataView::where('sku', $skuKey)->first();
            
            // Check if record exists and has valid data (not blank/zero data)
            // Blank data (all counts = 0) is inserted for next date, so we should calculate directly
            $isValidRecord = false;
            if ($record) {
                $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
                // Check if any count is greater than 0 (valid data)
                $totalCount = ($value['over_utilized_7ub'] ?? 0) + 
                             ($value['under_utilized_7ub'] ?? 0) + 
                             ($value['correctly_utilized_7ub'] ?? 0) +
                             ($value['over_utilized_7ub_1ub'] ?? 0) + 
                             ($value['under_utilized_7ub_1ub'] ?? 0) + 
                             ($value['correctly_utilized_7ub_1ub'] ?? 0);
                $isValidRecord = $totalCount > 0;
            }
            
            // If this is an "under-utilized" page, always calculate total campaigns to match table
            // regardless of whether we have stored data or not
            if ($pageType === 'under' && $campaignType === 'PT') {
                // For PT, call the actual controller method to get exact table data count
                // This is the most reliable way to ensure the count matches the table
                try {
                    $underUtilizedController = new \App\Http\Controllers\Campaigns\AmzUnderUtilizedBgtController();
                    $tableDataResponse = $underUtilizedController->getAmzUnderUtilizedBgtPt();
                    $tableDataContent = $tableDataResponse->getContent();
                    $tableDataArray = json_decode($tableDataContent, true);
                    
                    // Count the actual data returned (after unique filter is applied)
                    $totalCount = isset($tableDataArray['data']) && is_array($tableDataArray['data']) 
                        ? count($tableDataArray['data']) 
                        : $this->getTotalCampaignsCountForPt();
                } catch (\Exception $e) {
                    // Fallback to calculation method if controller call fails
                    FacadesLog::warning('Failed to get count from controller: ' . $e->getMessage());
                    $totalCount = $this->getTotalCampaignsCountForPt();
                }
                
                // Always return the table count (ignore blank/stored data)
                return response()->json([
                    'over_utilized' => 0,
                    'under_utilized' => $totalCount, // Return total campaigns to match table
                    'correctly_utilized' => 0,
                    'status' => 200,
                ]);
            }
            
            // If this is an "under-utilized" page for HL, always calculate total campaigns to match table
            if ($pageType === 'under' && $campaignType === 'HL') {
                // For HL, call the actual controller method to get exact table data count
                // This ensures the count matches exactly what's shown in the table
                try {
                    $sbBudgetController = new \App\Http\Controllers\Campaigns\AmazonSbBudgetController();
                    $tableDataResponse = $sbBudgetController->getAmzUnderUtilizedBgtHl();
                    $tableDataContent = $tableDataResponse->getContent();
                    $tableDataArray = json_decode($tableDataContent, true);
                    
                    // Count the actual data returned (after unique filter is applied)
                    // This should match the table's total campaigns count
                    $totalCount = isset($tableDataArray['data']) && is_array($tableDataArray['data']) 
                        ? count($tableDataArray['data']) 
                        : 0;
                    
                    // Log for debugging if needed
                    FacadesLog::info("HL under-utilized count from table: {$totalCount}");
                } catch (\Exception $e) {
                    // Fallback to calculation method if controller call fails
                    FacadesLog::error('Failed to get HL count from controller: ' . $e->getMessage());
                    $totalCount = 0;
                }
                
                // Always return the table count (ignore blank/stored data)
                return response()->json([
                    'over_utilized' => 0,
                    'under_utilized' => $totalCount, // Return total campaigns to match table
                    'correctly_utilized' => 0,
                    'status' => 200,
                ]);
            }
        
        // For PT campaigns, always get under count from actual table data to ensure accuracy
        // This ensures the count matches the under-utilized table regardless of which page is viewing it
        if ($campaignType === 'PT') {
            try {
                $underUtilizedController = new \App\Http\Controllers\Campaigns\AmzUnderUtilizedBgtController();
                $tableDataResponse = $underUtilizedController->getAmzUnderUtilizedBgtPt();
                $tableDataContent = $tableDataResponse->getContent();
                $tableDataArray = json_decode($tableDataContent, true);
                
                // Count the actual data returned (after unique filter and UB7 < 70 filter is applied)
                $underCountFromTable = isset($tableDataArray['data']) && is_array($tableDataArray['data']) 
                    ? count($tableDataArray['data']) 
                    : ($this->getTotalCampaignsCountForPt() ?? 0);
            } catch (\Exception $e) {
                FacadesLog::warning('Failed to get PT under count from controller: ' . $e->getMessage());
                $underCountFromTable = 0;
            }
        }
        
        // For KW campaigns, always get over count from actual table data to ensure accuracy
        // This ensures the count matches the over-utilized table regardless of which page is viewing it
        if ($campaignType === 'KW') {
            try {
                $sbBudgetController = new \App\Http\Controllers\Campaigns\AmazonSpBudgetController();
                $tableDataResponse = $sbBudgetController->getAmzUtilizedBgtKw();
                $tableDataContent = $tableDataResponse->getContent();
                $tableDataArray = json_decode($tableDataContent, true);
                
                // Count the actual data returned (after UB7 > 90 && UB1 > 90 filter is applied)
                $overCountFromTable = isset($tableDataArray['data']) && is_array($tableDataArray['data']) 
                    ? count($tableDataArray['data']) 
                    : 0;
            } catch (\Exception $e) {
                FacadesLog::warning('Failed to get KW over count from controller: ' . $e->getMessage());
                $overCountFromTable = 0;
            }
        }
        
        // Only use stored data if it's valid (not blank/zero data)
        if ($isValidRecord && $record) {
            $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
            return response()->json([
                // 7UB only condition
                'over_utilized_7ub' => $value['over_utilized_7ub'] ?? 0,
                'under_utilized_7ub' => $value['under_utilized_7ub'] ?? 0,
                'correctly_utilized_7ub' => $value['correctly_utilized_7ub'] ?? 0,
                // 7UB + 1UB condition
                'over_utilized_7ub_1ub' => $value['over_utilized_7ub_1ub'] ?? 0,
                'under_utilized_7ub_1ub' => $value['under_utilized_7ub_1ub'] ?? 0,
                'correctly_utilized_7ub_1ub' => $value['correctly_utilized_7ub_1ub'] ?? 0,
                'status' => 200,
            ]);
        }

        // If no valid data for today (blank/zero data or no record), calculate from current data
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Get NRA values to filter out NRA campaigns (same as in getAmzUnderUtilizedBgtKw)
        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        
        // Get INV values from ShopifySku for KW and PT campaigns (same as StoreAmazonUtilizationCounts)
        $shopifyData = [];
        if ($campaignType === 'KW' || $campaignType === 'PT') {
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        }

        // Handle HL campaigns differently (use AmazonSbCampaignReport)
        if ($campaignType === 'HL') {
            $amazonSbCampaignReportsL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L7')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();

            $amazonSbCampaignReportsL1 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L1')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
        } else {
            // For KW and PT, use AmazonSpCampaignReport
            $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L7')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                })
                ->where('campaignStatus', '!=', 'ARCHIVED');

            $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L1')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                })
                ->where('campaignStatus', '!=', 'ARCHIVED');

            // Filter by campaign type
            if ($campaignType === 'PT') {
                $amazonSpCampaignReportsL7->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                      ->orWhere('campaignName', 'LIKE', '% PT.')
                      ->orWhere('campaignName', 'LIKE', '%PT')
                      ->orWhere('campaignName', 'LIKE', '%PT.');
                });
                $amazonSpCampaignReportsL1->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                      ->orWhere('campaignName', 'LIKE', '% PT.')
                      ->orWhere('campaignName', 'LIKE', '%PT')
                      ->orWhere('campaignName', 'LIKE', '%PT.');
                });
            } else {
                // Exclude only Product Targeting (name ends with " PT" or " PT."). Do NOT use '%PT' - it excludes PARENT (contains "PT").
                $amazonSpCampaignReportsL7->where('campaignName', 'NOT LIKE', '% PT')
                                          ->where('campaignName', 'NOT LIKE', '% PT.');
                $amazonSpCampaignReportsL1->where('campaignName', 'NOT LIKE', '% PT')
                                          ->where('campaignName', 'NOT LIKE', '% PT.');
            }

            $amazonSpCampaignReportsL7 = $amazonSpCampaignReportsL7->get();
            $amazonSpCampaignReportsL1 = $amazonSpCampaignReportsL1->get();
        }

        // Fetch last_sbid from yesterday's date records
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $lastSbidMap = [];
        if ($campaignType === 'HL') {
            $lastSbidReports = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', $yesterday)
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
            foreach ($lastSbidReports as $report) {
                if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                    $lastSbidMap[$report->campaign_id] = $report->last_sbid;
                }
            }
        } else {
            $lastSbidReports = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', $yesterday)
                ->where('campaignStatus', '!=', 'ARCHIVED');
            
            if ($campaignType === 'PT') {
                $lastSbidReports->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                      ->orWhere('campaignName', 'LIKE', '% PT.')
                      ->orWhere('campaignName', 'LIKE', '%PT')
                      ->orWhere('campaignName', 'LIKE', '%PT.');
                });
            } else {
                $lastSbidReports->where('campaignName', 'NOT LIKE', '% PT')
                              ->where('campaignName', 'NOT LIKE', '% PT.');
            }
            
            $lastSbidReports = $lastSbidReports->get();
            foreach ($lastSbidReports as $report) {
                if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                    $lastSbidMap[$report->campaign_id] = $report->last_sbid;
                }
            }
        }

        // Counts for 7UB only condition
        $overUtilizedCount7ub = 0;
        $underUtilizedCount7ub = 0;
        $correctlyUtilizedCount7ub = 0;
        
        // Counts for 7UB + 1UB condition
        $overUtilizedCount7ub1ub = 0;
        $underUtilizedCount7ub1ub = 0;
        $correctlyUtilizedCount7ub1ub = 0;
        
        // For PT campaigns, we need to track unique SKUs (same as getAmzUnderUtilizedBgtPt)
        $processedSkus = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            
            // For PT campaigns, apply unique SKU filter (same as getAmzUnderUtilizedBgtPt line 637)
            if ($campaignType === 'PT' && in_array($sku, $processedSkus)) {
                continue;
            }

            // Check NRA filter (same as in getAmzUnderUtilizedBgtKw/getAmzUnderUtilizedBgtPt)
            $nra = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                }
            }
            
            // Skip if NRA === 'NRA' (same filter as in getAmzUnderUtilizedBgtKw/getAmzUnderUtilizedBgtPt)
            if ($nra === 'NRA') {
                continue;
            }
            
            // For KW and PT: Skip campaigns with INV = 0 (same as StoreAmazonUtilizationCounts)
            if ($campaignType === 'KW' || $campaignType === 'PT') {
                $shopify = $shopifyData[$pm->sku] ?? null;
                $inv = $shopify ? ($shopify->inv ?? 0) : 0;
                if (floatval($inv) <= 0) {
                    continue;
                }
            }

            if ($campaignType === 'HL') {
                // HL campaigns matching logic (SKU or SKU + ' HEAD')
                $matchedCampaignL7 = $amazonSbCampaignReportsL7->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });

                $matchedCampaignL1 = $amazonSbCampaignReportsL1->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });

                if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                    continue;
                }

                $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
                if ($campaignName === '') {
                    continue;
                }

                $budget = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
                $l7_spend = $matchedCampaignL7->cost ?? 0;
                $l1_spend = $matchedCampaignL1->cost ?? 0;
            } else {
                // KW and PT campaigns matching logic
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku, $campaignType) {
                    $campaignName = strtoupper(trim($item->campaignName));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    
                    if ($campaignType === 'PT') {
                        $ptSuffix = $cleanSku . ' PT';
                        $ptSuffixDot = $cleanSku . ' PT.';
                        return (substr($campaignName, -strlen($ptSuffix)) === $ptSuffix || substr($campaignName, -strlen($ptSuffixDot)) === $ptSuffixDot);
                    } else {
                        $cleanName = strtoupper(trim(rtrim($campaignName, '.')));
                        return $cleanName === $cleanSku;
                    }
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku, $campaignType) {
                    $campaignName = strtoupper(trim($item->campaignName));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    
                    if ($campaignType === 'PT') {
                        $ptSuffix = $cleanSku . ' PT';
                        $ptSuffixDot = $cleanSku . ' PT.';
                        return (substr($campaignName, -strlen($ptSuffix)) === $ptSuffix || substr($campaignName, -strlen($ptSuffixDot)) === $ptSuffixDot);
                    } else {
                        $cleanName = strtoupper(trim(rtrim($campaignName, '.')));
                        return $cleanName === $cleanSku;
                    }
                });

                if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                    continue;
                }

                // Also check if campaignName is not empty (same filter as in getAmzUnderUtilizedBgtKw/getAmzUnderUtilizedBgtPt)
                $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
                if ($campaignName === '') {
                    continue;
                }

                $budget = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
                $l7_spend = $matchedCampaignL7->spend ?? 0;
                $l1_spend = $matchedCampaignL1->spend ?? 0;
            }

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / ($budget * 1)) * 100 : 0;
            
            // For PT campaigns, mark SKU as processed (unique filter)
            // This must happen AFTER we've verified the campaign exists and passes all filters
            // but BEFORE we categorize by utilization, so we count all campaigns in the table
            if ($campaignType === 'PT') {
                $processedSkus[] = $sku;
            }

            // Categorize based on 7UB only condition
            if ($ub7 > 90) {
                $overUtilizedCount7ub++;
            } elseif ($ub7 < 70) {
                $underUtilizedCount7ub++;
            } elseif ($ub7 >= 70 && $ub7 <= 90) {
                $correctlyUtilizedCount7ub++;
            }
            
            // Categorize based on 7UB + 1UB condition
            if ($ub7 > 90 && $ub1 > 90) {
                $overUtilizedCount7ub1ub++;
            } elseif ($ub7 < 70 && $ub1 < 70) {
                $underUtilizedCount7ub1ub++;
            } elseif ($ub7 >= 70 && $ub7 <= 90 && $ub1 >= 70 && $ub1 <= 90) {
                $correctlyUtilizedCount7ub1ub++;
            }
        }
        
        return response()->json([
            // 7UB only condition
            'over_utilized_7ub' => $overUtilizedCount7ub,
            'under_utilized_7ub' => $underUtilizedCount7ub,
            'correctly_utilized_7ub' => $correctlyUtilizedCount7ub,
            // 7UB + 1UB condition
            'over_utilized_7ub_1ub' => $overUtilizedCount7ub1ub,
            'under_utilized_7ub_1ub' => $underUtilizedCount7ub1ub,
            'correctly_utilized_7ub_1ub' => $correctlyUtilizedCount7ub1ub,
            'status' => 200,
        ]);
        } catch (\Exception $e) {
            FacadesLog::error('Error in getAmazonUtilizationCounts: ' . $e->getMessage());
            return response()->json([
                // 7UB only condition
                'over_utilized_7ub' => 0,
                'under_utilized_7ub' => 0,
                'correctly_utilized_7ub' => 0,
                // 7UB + 1UB condition
                'over_utilized_7ub_1ub' => 0,
                'under_utilized_7ub_1ub' => 0,
                'correctly_utilized_7ub_1ub' => 0,
                'status' => 500,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function getTotalCampaignsCountForPt()
    {
        // Use EXACT same logic as getAmzUnderUtilizedBgtPt - replicate the entire method logic
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // Get ALL campaigns first (same as getAmzUnderUtilizedBgtPt - no filtering by campaign name)
        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));

            // Exact same matching logic as getAmzUnderUtilizedBgtPt (lines 545-557)
            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.');
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.');
            });

            // Get campaignName same way as getAmzUnderUtilizedBgtPt line 567
            // This is done BEFORE the filter check, just like in the original method
            $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');

            // Check NRA filter - same as getAmzUnderUtilizedBgtPt lines 616-630
            $nra = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                }
            }

            // Same filter as getAmzUnderUtilizedBgtPt line 632
            // This must match exactly: if($row['NRA'] !== 'NRA' && $row['campaignName'] !== '')
            if($nra !== 'NRA' && $campaignName !== ''){
                // Store as object with sku property, same structure as original method
                $result[] = (object)['sku' => $pm->sku];
            }
        }

        // Apply unique('sku') filter (same as getAmzUnderUtilizedBgtPt line 637)
        // The original uses: $uniqueResult = collect($result)->unique('sku')->values()->all();
        $uniqueResult = collect($result)->unique('sku')->values()->all();
        return count($uniqueResult);
    }
    
    private function getTotalCampaignsCount($campaignType)
    {
        // Use EXACT same logic as getAmzUnderUtilizedBgtPt for PT campaigns
        if ($campaignType === 'PT') {
            return $this->getTotalCampaignsCountForPt();
        }
        
        // For KW campaigns, use the existing logic
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $totalCount = 0;

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));

            $nra = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                }
            }
            
            if ($nra === 'NRA') {
                continue;
            }

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            if ($campaignName === '') {
                continue;
            }

            $totalCount++;
        }

        return $totalCount;
    }

    public function getAmazonUtilizationChartData(Request $request)
    {
        $campaignType = $request->get('type', 'KW'); // Default to KW, can be 'PT' or 'HL'
        
        // For backward compatibility, if type is KW and no data found, try old format
        $data = AmazonDataView::where('sku', 'LIKE', 'AMAZON_UTILIZATION_' . $campaignType . '_%')
            ->orderBy('sku', 'desc')
            ->limit(30)
            ->get();
            
        // If no data found for PT, KW, or HL with type prefix, try old format (for KW only)
        if ($data->isEmpty() && $campaignType === 'KW') {
            $data = AmazonDataView::where('sku', 'LIKE', 'AMAZON_UTILIZATION_%')
                ->where('sku', 'NOT LIKE', 'AMAZON_UTILIZATION_PT_%')
                ->where('sku', 'NOT LIKE', 'AMAZON_UTILIZATION_KW_%')
                ->where('sku', 'NOT LIKE', 'AMAZON_UTILIZATION_HL_%')
                ->orderBy('sku', 'desc')
                ->limit(30)
                ->get();
        }
        
        $condition = $request->get('condition', '7ub'); // Default to 7ub, can be '7ub-1ub'
        
        $data = $data->map(function ($item) use ($campaignType, $condition) {
                $value = is_array($item->value) ? $item->value : json_decode($item->value, true);
                
                // Handle both old format (AMAZON_UTILIZATION_YYYY-MM-DD) and new format (AMAZON_UTILIZATION_TYPE_YYYY-MM-DD)
                $date = str_replace('AMAZON_UTILIZATION_' . $campaignType . '_', '', $item->sku);
                $date = str_replace('AMAZON_UTILIZATION_', '', $date);
                
                if ($condition === '7ub') {
                    return [
                        'date' => $date,
                        'over_utilized_7ub' => $value['over_utilized_7ub'] ?? 0,
                        'under_utilized_7ub' => $value['under_utilized_7ub'] ?? 0,
                        'correctly_utilized_7ub' => $value['correctly_utilized_7ub'] ?? 0,
                    ];
                } else {
                    return [
                        'date' => $date,
                        'over_utilized_7ub_1ub' => $value['over_utilized_7ub_1ub'] ?? 0,
                        'under_utilized_7ub_1ub' => $value['under_utilized_7ub_1ub'] ?? 0,
                        'correctly_utilized_7ub_1ub' => $value['correctly_utilized_7ub_1ub'] ?? 0,
                    ];
                }
            })
            ->reverse()
            ->values();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $data,
            'status' => 200,
        ]);
    }

    /**
     * Filter chart data for Amazon utilized views (KW, PT, HL).
     * Returns daily clicks, spend, orders, sales for the selected date range.
     */
    public function filterAmazonUtilizedChart(Request $request)
    {
        $type = strtoupper($request->get('type', 'KW'));
        $start = $request->get('startDate');
        $end = $request->get('endDate');

        if (!$start || !$end) {
            $end = \Carbon\Carbon::now()->subDays(2)->format('Y-m-d');
            $start = \Carbon\Carbon::now()->subDays(31)->format('Y-m-d');
        }

        if ($type === 'HL') {
            return $this->filterAmazonUtilizedChartHl($start, $end);
        }
        if ($type === 'PT') {
            return $this->filterAmazonUtilizedChartPt($start, $end);
        }
        return $this->filterAmazonUtilizedChartKw($start, $end);
    }

    private function filterAmazonUtilizedChartKw($start, $end)
    {
        // Match the same logic as L30 totals calculation - include all campaigns except PT and ARCHIVED
        // Use unitsSoldClicks30d for orders to match L30 totals (which uses unitsSoldClicks30d)
        // Exclude PT campaigns with various naming conventions
        $rawData = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('
                report_date_range as report_date,
                campaignName,
                SUM(spend) as sum_spend,
                SUM(clicks) as clicks,
                SUM(unitsSoldClicks30d) as orders,
                SUM(sales30d) as sales
            ')
            ->whereNotNull('report_date_range')
            ->whereRaw("report_date_range >= ?", [$start])
            ->whereRaw("report_date_range <= ?", [$end])
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->whereRaw("campaignName NOT LIKE '% PT%'")
            ->whereRaw("campaignName NOT LIKE '%-PT%'")
            ->whereRaw("campaignName NOT LIKE '%.PT%'")
            ->groupBy('report_date_range', 'campaignName')
            ->get();

        return $this->buildUtilizedChartResponse($rawData, $start, $end, 'spend', 'sum_spend');
    }

    private function filterAmazonUtilizedChartPt($start, $end)
    {
        // Match the same logic as L30 totals calculation - include all PT campaigns except ARCHIVED
        // Use unitsSoldClicks30d for orders to match L30 totals (which uses unitsSoldClicks30d)
        // Use inclusive pattern to match PT campaigns with various naming conventions:
        // - "SKU PT", "SKU PT AUTO", "SKU PT V2" (space separator)
        // - "SKU-PT", "SKU-PT-AUTO" (hyphen separator)
        // - "SKU.PT" (dot separator)
        // - Ends with "PT" or "PT."
        $rawData = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('
                report_date_range as report_date,
                campaignName,
                SUM(spend) as sum_spend,
                SUM(clicks) as clicks,
                SUM(unitsSoldClicks30d) as orders,
                SUM(sales30d) as sales
            ')
            ->whereNotNull('report_date_range')
            ->whereRaw("report_date_range >= ?", [$start])
            ->whereRaw("report_date_range <= ?", [$end])
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->where(function ($query) {
                $query->whereRaw("campaignName LIKE '% PT%'")
                    ->orWhereRaw("campaignName LIKE '%-PT%'")
                    ->orWhereRaw("campaignName LIKE '%.PT%'")
                    ->orWhereRaw("campaignName LIKE '%PT'")
                    ->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->groupBy('report_date_range', 'campaignName')
            ->get();

        return $this->buildUtilizedChartResponse($rawData, $start, $end, 'spend', 'sum_spend');
    }

    private function filterAmazonUtilizedChartHl($start, $end)
    {
        $rawData = DB::table('amazon_sb_campaign_reports')
            ->selectRaw('
                report_date_range as report_date,
                campaignName,
                SUM(cost) as sum_cost,
                SUM(clicks) as clicks,
                SUM(purchases) as orders,
                SUM(sales) as sales
            ')
            ->whereNotNull('report_date_range')
            ->whereRaw("report_date_range >= ?", [$start])
            ->whereRaw("report_date_range <= ?", [$end])
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->groupBy('report_date_range', 'campaignName')
            ->get();

        $data = $rawData->groupBy('report_date')->map(function ($dateGroup) {
            return (object) [
                'report_date' => $dateGroup->first()->report_date,
                'clicks' => $dateGroup->sum('clicks'),
                'spend' => $dateGroup->sum('sum_cost'),
                'orders' => $dateGroup->sum('orders'),
                'sales' => $dateGroup->sum('sales'),
            ];
        });

        return $this->buildUtilizedChartResponseFromMap($data, $start, $end);
    }

    private function buildUtilizedChartResponse($rawData, $start, $end, $spendKey, $sumKey)
    {
        $data = $rawData->groupBy('report_date')->map(function ($dateGroup) use ($sumKey) {
            return (object) [
                'report_date' => $dateGroup->first()->report_date,
                'clicks' => $dateGroup->sum('clicks'),
                'spend' => $dateGroup->sum($sumKey),
                'orders' => $dateGroup->sum('orders'),
                'sales' => $dateGroup->sum('sales'),
            ];
        });

        return $this->buildUtilizedChartResponseFromMap($data, $start, $end);
    }

    private function buildUtilizedChartResponseFromMap($data, $start, $end)
    {
        $totals = [
            'clicks' => (int) $data->sum('clicks'),
            'spend' => (float) $data->sum('spend'),
            'orders' => (int) $data->sum('orders'),
            'sales' => (float) $data->sum('sales'),
        ];

        $startCarbon = \Carbon\Carbon::parse($start);
        $endCarbon = \Carbon\Carbon::parse($end);
        $dates = [];
        $clicks = [];
        $spend = [];
        $orders = [];
        $sales = [];

        for ($d = $startCarbon->copy(); $d->lte($endCarbon); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            $dates[] = $dateStr;
            if (isset($data[$dateStr])) {
                $row = $data[$dateStr];
                $clicks[] = (int) $row->clicks;
                $spend[] = (float) $row->spend;
                $orders[] = (int) $row->orders;
                $sales[] = (float) $row->sales;
            } else {
                $clicks[] = 0;
                $spend[] = 0.0;
                $orders[] = 0;
                $sales[] = 0.0;
            }
        }

        return response()->json([
            'dates' => $dates,
            'clicks' => $clicks,
            'spend' => $spend,
            'orders' => $orders,
            'sales' => $sales,
            'totals' => $totals,
        ]);
    }

    function getAmzUtilizedBgtKw()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch FBA data where seller_sku contains FBA, then key by base SKU (without FBA)
        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->get()
            ->keyBy(function ($item) {
                $sku = $item->seller_sku;
                $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                return strtoupper(trim($base));
            });

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L15')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            // Match FBA data by base SKU (FBA data is keyed by base SKU without FBA)
            $baseSku = strtoupper(trim($pm->sku));
            $row['FBA_INV'] = isset($fbaData[$baseSku]) ? ($fbaData[$baseSku]->quantity_available ?? 0) : 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');
            $row['sbid'] = $matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '');
            $row['crnt_bid'] = $matchedCampaignL7->currentSpBidPrice ?? ($matchedCampaignL1->currentSpBidPrice ?? '');
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });
            $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $sales30 = $matchedCampaignL30->sales30d ?? 0;
            $spend30 = $matchedCampaignL30->spend ?? 0;
            $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
            $spend15 = $matchedCampaignL15->spend ?? 0;
            $sales7 = $matchedCampaignL7->sales7d ?? 0;
            $spend7 = $matchedCampaignL7->spend ?? 0;

            // ACOS L30
            if ($spend30 > 0 && $sales30 > 0) {
                $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
            } elseif ($spend30 > 0 && $sales30 == 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            // ACOS L15
            if ($spend15 > 0 && $sales15 > 0) {
                $row['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
            } elseif ($spend15 > 0 && $sales15 == 0) {
                $row['acos_L15'] = 100;
            } else {
                $row['acos_L15'] = 0;
            }

            // ACOS L7
            if ($spend7 > 0 && $sales7 > 0) {
                $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
            } elseif ($spend7 > 0 && $sales7 == 0) {
                $row['acos_L7'] = 100;
            } else {
                $row['acos_L7'] = 0;
            }

            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
            $row['clicks_L15'] = $matchedCampaignL15->clicks ?? 0;
            $row['clicks_L7'] = $matchedCampaignL7->clicks ?? 0;

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['TPFT'] = $raw['TPFT'] ?? null;
                }
            }

            // Calculate UB7 and UB1 for filtering (same as frontend filter at line 776)
            $budget = $row['campaignBudgetAmount'] ?? 0;
            $l7_spend = $row['l7_spend'] ?? 0;
            $l1_spend = $row['l1_spend'] ?? 0;
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;
            
            // Only include campaigns where UB7 > 90 && UB1 > 90 (over-utilized) - same as frontend filter
            if($ub7 > 90 && $ub1 > 90){
                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function amzUtilizedBgtPt()
    {   
        return view('campaign.amz-utilized-bgt-pt');
    }

    function getAmzUtilizedBgtPt()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L15')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                $ptSuffix = $sku . ' PT';
                $ptSuffixDot = $sku . ' PT.';
                return ($cleanName === $ptSuffix || $cleanName === $ptSuffixDot);
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                $ptSuffix = $sku . ' PT';
                $ptSuffixDot = $sku . ' PT.';
                return ($cleanName === $ptSuffix || $cleanName === $ptSuffixDot);
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');
            $row['sbid'] = $matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '');
            $row['crnt_bid'] = $matchedCampaignL7->currentSpBidPrice ?? ($matchedCampaignL1->currentSpBidPrice ?? '');
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));

                return (
                    (substr($cleanName, -strlen($sku . ' PT')) === ($sku . ' PT') || substr($cleanName, -strlen($sku . ' PT.')) === ($sku . ' PT.'))
                );
            });

            $matchedCampaign15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));

                return (
                    (substr($cleanName, -strlen($sku . ' PT')) === ($sku . ' PT') || substr($cleanName, -strlen($sku . ' PT.')) === ($sku . ' PT.'))
                );
            });
            
            $sales30 = $matchedCampaignL30->sales30d ?? 0;
            $spend30 = $matchedCampaignL30->spend ?? 0;
            $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
            $spend15 = $matchedCampaignL15->spend ?? 0;
            $sales7 = $matchedCampaignL7->sales7d ?? 0;
            $spend7 = $matchedCampaignL7->spend ?? 0;

            // ACOS L30
            if ($spend30 > 0 && $sales30 > 0) {
                $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
            } elseif ($spend30 > 0 && $sales30 == 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            // ACOS L15
            if ($spend15 > 0 && $sales15 > 0) {
                $row['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
            } elseif ($spend15 > 0 && $sales15 == 0) {
                $row['acos_L15'] = 100;
            } else {
                $row['acos_L15'] = 0;
            }

            // ACOS L7
            if ($spend7 > 0 && $sales7 > 0) {
                $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
            } elseif ($spend7 > 0 && $sales7 == 0) {
                $row['acos_L7'] = 100;
            } else {
                $row['acos_L7'] = 0;
            }


            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
            $row['clicks_L15'] = $matchedCampaign15->clicks ?? 0;
            $row['clicks_L7'] = $matchedCampaignL7->clicks ?? 0;

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['TPFT'] = $raw['TPFT'] ?? null;
                }
            }

            if($row['NRA'] !== 'NRA' && $row['campaignName'] !== ''){
                $result[] = (object) $row;
            }
        }

        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
    }

    public function updateAmazonSpBidPrice(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',  
            'crnt_bid' => 'required|numeric',
            '_token' => 'required|string',
        ]);

        $updated = AmazonSpCampaignReport::where('campaign_id', $validated['id'])
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->whereIn('report_date_range', ['L7', 'L1'])
            ->update([
                'currentSpBidPrice' => $validated['crnt_bid'],
                'sbid' => $validated['crnt_bid'] * 0.9
            ]);

        if($updated){
            return response()->json([
                'message' => 'CRNT BID updated successfully for all matching campaigns',
                'status' => 200,
            ]);
        }

        return response()->json([
            'message' => 'No matching campaigns found',
            'status' => 404,
        ]);
    }

    public function updateNrNRLFba(Request $request)
    {
        $sku   = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        if (empty($sku) || $field === null || $field === '') {
            return response()->json([
                'status' => 422,
                'message' => 'SKU and field are required',
                'success' => false,
            ], 422);
        }

        $amazonDataView = AmazonDataView::where('sku', $sku)->first();

        $jsonData = [];
        if ($amazonDataView && $amazonDataView->value !== null) {
            $existing = $amazonDataView->value;
            $jsonData = is_array($existing) ? $existing : (is_string($existing) ? (json_decode($existing, true) ?: []) : []);
        }

        $jsonData[$field] = $value;

        AmazonDataView::updateOrCreate(
            ['sku' => $sku],
            ['value' => $jsonData]
        );

        return response()->json([
            'status' => 200,
            'message' => 'Data updated successfully',
            'success' => true,
            'updated_json' => $jsonData,
        ]);
    }

    public function amazonUtilizedView()
    {
        return view('campaign.amazon.amazon-utilized-kw');
    }

    public function amazonUtilizedPtView()
    {
        return view('campaign.amazon.amazon-utilized-pt');
    }

    public function getAmazonUtilizedPtAdsData(Request $request)
    {
        $request->merge(['type' => 'PT']);
        return $this->getAmazonUtilizedAdsData($request);
    }

    public function getAmazonUtilizedKwAdsData(Request $request)
    {
        $request->merge(['type' => 'KW']);
        return $this->getAmazonUtilizedAdsData($request);
    }

    public function getAmazonUtilizedHlAdsData(Request $request)
    {
        $request->merge(['type' => 'HL']);
        return $this->getAmazonUtilizedAdsData($request);
    }

    public function getAmazonUtilizedAdsData(Request $request)
    {
        $campaignType = $request->get('type', 'KW'); // KW, PT, or HL
        
        // Get all product masters excluding soft deleted ones
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Count total SKUs
        // For HL: count unique parent values (not SKUs starting with PARENT) to match product-master page
        // For KW/PT: count non-parent SKUs (SKU NOT LIKE 'PARENT %')
        if ($campaignType === 'HL') {
            // Use pluck->filter->unique to match product-master page logic exactly
            $totalSkuCount = ProductMaster::whereNull('deleted_at')
                ->pluck('parent')
                ->filter()
                ->unique()
                ->count();
        } else {
            $totalSkuCount = ProductMaster::whereNull('deleted_at')
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->count();
        }

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->get()
            ->keyBy(function ($item) {
                $sku = $item->seller_sku;
                $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                return strtoupper(trim($base));
            });

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // Fetch latest ACOS action history for each campaign/sku
        $acosHistoryMap = [];
        
        // Get all history records for this campaign type
        // Use a simpler approach: fetch all records and match in PHP
        $allHistoryRecords = DB::table('amazon_acos_action_history')
            ->where('campaign_type', $campaignType)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Build a map with multiple keys for flexible matching
        // Group by campaign_id|sku first to get latest for each combination
        $groupedByKey = [];
        foreach ($allHistoryRecords as $record) {
            $normalizedSku = $record->sku ? strtoupper(trim($record->sku)) : '';
            $normalizedCampaignId = $record->campaign_id ? trim($record->campaign_id) : '';
            
            // Primary key: campaign_id|sku
            $primaryKey = $normalizedCampaignId . '|' . $normalizedSku;
            if (!isset($groupedByKey[$primaryKey])) {
                $groupedByKey[$primaryKey] = $record;
            }
        }
        
        // Now build the map with all possible keys
        foreach ($groupedByKey as $record) {
            $normalizedSku = $record->sku ? strtoupper(trim($record->sku)) : '';
            $normalizedCampaignId = $record->campaign_id ? trim($record->campaign_id) : '';
            
            // Key 1: campaign_id|sku (most specific)
            if ($normalizedCampaignId && $normalizedSku) {
                $key1 = $normalizedCampaignId . '|' . $normalizedSku;
                $acosHistoryMap[$key1] = $record;
            }
            
            // Key 2: |sku (SKU only, for fallback) - only if not already set
            if ($normalizedSku) {
                $key2 = '|' . $normalizedSku;
                if (!isset($acosHistoryMap[$key2])) {
                    $acosHistoryMap[$key2] = $record;
                }
            }
            
            // Key 3: campaign_id| (campaign only, for fallback) - only if not already set
            if ($normalizedCampaignId) {
                $key3 = $normalizedCampaignId . '|';
                if (!isset($acosHistoryMap[$key3])) {
                    $acosHistoryMap[$key3] = $record;
                }
            }
        }

        // Get marketplace percentage for AD% calculation
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;

        // Calculate AVG CPC from all daily records (lifetime average)
        $avgCpcData = collect();
        try {
            if ($campaignType === 'HL') {
                $dailyRecords = DB::table('amazon_sb_campaign_reports')
                    ->select('campaign_id', DB::raw('AVG(CASE WHEN clicks > 0 THEN cost / clicks ELSE 0 END) as avg_cpc'))
                    ->where('ad_type', 'SPONSORED_BRANDS')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
                    ->whereNotNull('campaign_id')
                    ->groupBy('campaign_id')
                    ->get();
            } else {
                $dailyRecords = DB::table('amazon_sp_campaign_reports')
                    ->select('campaign_id', DB::raw('AVG(costPerClick) as avg_cpc'))
                    ->where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
                    ->where('costPerClick', '>', 0)
                    ->whereNotNull('campaign_id')
                    ->groupBy('campaign_id')
                    ->get();
            }
            
            foreach ($dailyRecords as $record) {
                if ($record->campaign_id && $record->avg_cpc > 0) {
                    $avgCpcData->put($record->campaign_id, round($record->avg_cpc, 2));
                }
            }
        } catch (\Exception $e) {
            FacadesLog::error('Error calculating AVG CPC: ' . $e->getMessage());
            // Continue without avg_cpc data if there's an error
        }

        // Fetch latest ratings and reviews from junglescout_product_data
        // Use subquery to get latest record for each sku/parent
        $junglescoutData = collect();
        $junglescoutReviews = collect();
        
        // Get latest by SKU
        $skuRatings = DB::table('junglescout_product_data as j1')
            ->select('j1.sku', 'j1.data')
            ->whereNotNull('j1.sku')
            ->whereIn('j1.sku', $skus)
            ->whereRaw('j1.updated_at = (SELECT MAX(j2.updated_at) FROM junglescout_product_data j2 WHERE j2.sku = j1.sku)')
            ->get();
        
        foreach ($skuRatings as $item) {
            $data = json_decode($item->data, true);
            $rating = $data['rating'] ?? null;
            $reviews = $data['reviews'] ?? null;
            if ($item->sku && !$junglescoutData->has($item->sku)) {
                $junglescoutData->put($item->sku, $rating);
                $junglescoutReviews->put($item->sku, $reviews);
            }
        }
        
        // Get latest by parent
        $parentRatings = DB::table('junglescout_product_data as j1')
            ->select('j1.parent', 'j1.data')
            ->whereNotNull('j1.parent')
            ->whereIn('j1.parent', $skus)
            ->whereRaw('j1.updated_at = (SELECT MAX(j2.updated_at) FROM junglescout_product_data j2 WHERE j2.parent = j1.parent)')
            ->get();
        
        foreach ($parentRatings as $item) {
            $data = json_decode($item->data, true);
            $rating = $data['rating'] ?? null;
            $reviews = $data['reviews'] ?? null;
            if ($item->parent && !$junglescoutData->has($item->parent)) {
                $junglescoutData->put($item->parent, $rating);
                $junglescoutReviews->put($item->parent, $reviews);
            }
        }

        // For HL, use SPONSORED_BRANDS, for KW and PT use SPONSORED_PRODUCTS
        if ($campaignType === 'HL') {
            $amazonSpCampaignReportsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L30')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
        } else {
            // For KW campaigns, get ALL campaigns (not filtered by SKU) to include unmatched campaigns
            if ($campaignType === 'KW') {
                // Exclude only Product Targeting (" PT" / " PT."). Do NOT use '%PT' - it excludes PARENT (contains "PT").
                $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L30')
                    ->where('campaignName', 'NOT LIKE', '% PT')
                    ->where('campaignName', 'NOT LIKE', '% PT.')
                    ->where('campaignName', 'NOT LIKE', '%FBA')
                    ->where('campaignName', 'NOT LIKE', '%FBA.')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
            } else {
                $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L30')
                    ->where(function ($q) use ($skus) {
                        foreach ($skus as $sku) {
                            $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                            $skuNoDot = rtrim($sku, '.');
                            if ($skuNoDot !== $sku) {
                                $q->orWhere('campaignName', 'LIKE', '%' . $skuNoDot . '%');
                            }
                        }
                    });
                
                if ($campaignType === 'PT') {
                    $amazonSpCampaignReportsL30->where(function($q) {
                        $q->where('campaignName', 'LIKE', '% PT')
                          ->orWhere('campaignName', 'LIKE', '% PT.')
                          ->orWhere('campaignName', 'LIKE', '%PT')
                          ->orWhere('campaignName', 'LIKE', '%PT.');
                    });
                } else {
                    $amazonSpCampaignReportsL30->where('campaignName', 'NOT LIKE', '% PT')
                                              ->where('campaignName', 'NOT LIKE', '% PT.');
                }
                
                $amazonSpCampaignReportsL30 = $amazonSpCampaignReportsL30->where('campaignStatus', '!=', 'ARCHIVED')->get();
            }
        }

        // Load ALL campaigns (KW + PT) for AD% calculation - need all types regardless of view
        $allSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();
        
        // Load HL (SB) campaigns for all views to calculate total AD% (KW + PT + HL)
        $amazonHlL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        if ($campaignType === 'HL') {
            $amazonSpCampaignReportsL15 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L15')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();

            $amazonSpCampaignReportsL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L7')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();

            $amazonSpCampaignReportsL1 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L1')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
        } else {
            // For KW campaigns, get ALL campaigns (not filtered by SKU) to include unmatched campaigns
            if ($campaignType === 'KW') {
                // Exclude only Product Targeting (" PT" / " PT."). Do NOT use '%PT' - it excludes PARENT (contains "PT").
                $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L15')
                    ->where('campaignName', 'NOT LIKE', '% PT')
                    ->where('campaignName', 'NOT LIKE', '% PT.')
                    ->where('campaignName', 'NOT LIKE', '%FBA')
                    ->where('campaignName', 'NOT LIKE', '%FBA.')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
                
                $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L7')
                    ->where('campaignName', 'NOT LIKE', '% PT')
                    ->where('campaignName', 'NOT LIKE', '% PT.')
                    ->where('campaignName', 'NOT LIKE', '%FBA')
                    ->where('campaignName', 'NOT LIKE', '%FBA.')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
                
                $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L1')
                    ->where('campaignName', 'NOT LIKE', '% PT')
                    ->where('campaignName', 'NOT LIKE', '% PT.')
                    ->where('campaignName', 'NOT LIKE', '%FBA')
                    ->where('campaignName', 'NOT LIKE', '%FBA.')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
            } else {
                // For PT campaigns, keep the existing SKU filter logic
                $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L15')
                    ->where(function ($q) use ($skus) {
                        foreach ($skus as $sku) {
                            $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                            $skuNoDot = rtrim($sku, '.');
                            if ($skuNoDot !== $sku) {
                                $q->orWhere('campaignName', 'LIKE', '%' . $skuNoDot . '%');
                            }
                        }
                    })
                    ->where(function($q) {
                        $q->where('campaignName', 'LIKE', '% PT')
                          ->orWhere('campaignName', 'LIKE', '% PT.')
                          ->orWhere('campaignName', 'LIKE', '%PT')
                          ->orWhere('campaignName', 'LIKE', '%PT.');
                    })
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
                
                $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L7')
                    ->where(function ($q) use ($skus) {
                        foreach ($skus as $sku) {
                            $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                            $skuNoDot = rtrim($sku, '.');
                            if ($skuNoDot !== $sku) {
                                $q->orWhere('campaignName', 'LIKE', '%' . $skuNoDot . '%');
                            }
                        }
                    })
                    ->where(function($q) {
                        $q->where('campaignName', 'LIKE', '% PT')
                          ->orWhere('campaignName', 'LIKE', '% PT.')
                          ->orWhere('campaignName', 'LIKE', '%PT')
                          ->orWhere('campaignName', 'LIKE', '%PT.');
                    })
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
                
                $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L1')
                    ->where(function ($q) use ($skus) {
                        foreach ($skus as $sku) {
                            $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                            $skuNoDot = rtrim($sku, '.');
                            if ($skuNoDot !== $sku) {
                                $q->orWhere('campaignName', 'LIKE', '%' . $skuNoDot . '%');
                            }
                        }
                    })
                    ->where(function($q) {
                        $q->where('campaignName', 'LIKE', '% PT')
                          ->orWhere('campaignName', 'LIKE', '% PT.')
                          ->orWhere('campaignName', 'LIKE', '%PT')
                          ->orWhere('campaignName', 'LIKE', '%PT.');
                    })
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
            }
        }

        $result = [];
        $campaignMap = [];

        // Fetch last_sbid from day-before-yesterday's date records
        // This ensures last_sbid shows the PREVIOUS day's calculated SBID, not the current day's
        // Example: On 15-01-2026, we fetch from 13-01-2026 records (which has SBID calculated on 14-01-2026)
        // So last_sbid = previous day's calculated SBID, SBID = current day's calculated SBID
        // This prevents both columns from showing the same value after page refresh
        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $lastSbidMap = [];
        $sbidMMap = [];
        $sbidApprovedMap = [];
        
        if ($campaignType === 'HL') {
            $lastSbidReports = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', $dayBeforeYesterday)
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
            foreach ($lastSbidReports as $report) {
                if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                    $lastSbidMap[$report->campaign_id] = $report->last_sbid;
                }
            }
            
            // Fetch sbid_m from yesterday's records first, then L1 as fallback
            $sbidMReports = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where(function($q) use ($yesterday) {
                    $q->where('report_date_range', $yesterday)
                      ->orWhere('report_date_range', 'L1');
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get()
                ->sortBy(function($report) use ($yesterday) {
                    // Prioritize yesterday's records over L1
                    return $report->report_date_range === $yesterday ? 0 : 1;
                })
                ->groupBy('campaign_id');
            
            foreach ($sbidMReports as $campaignId => $reports) {
                // Get the first report (prioritized by yesterday)
                $report = $reports->first();
                if (!empty($report->campaign_id)) {
                    if (!empty($report->sbid_m)) {
                        $sbidMMap[$report->campaign_id] = $report->sbid_m;
                    }
                    // Check if SBID is approved (sbid matches sbid_m)
                    if (!empty($report->sbid_m) && $report->sbid == $report->sbid_m) {
                        $sbidApprovedMap[$report->campaign_id] = true;
                    }
                }
            }
        } else {
            $lastSbidReports = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', $dayBeforeYesterday)
                ->where('campaignStatus', '!=', 'ARCHIVED');
            
            if ($campaignType === 'PT') {
                $lastSbidReports->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                      ->orWhere('campaignName', 'LIKE', '% PT.')
                      ->orWhere('campaignName', 'LIKE', '%PT')
                      ->orWhere('campaignName', 'LIKE', '%PT.');
                });
            } else {
                $lastSbidReports->where('campaignName', 'NOT LIKE', '% PT')
                              ->where('campaignName', 'NOT LIKE', '% PT.');
            }
            
            $lastSbidReports = $lastSbidReports->get();
            foreach ($lastSbidReports as $report) {
                if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                    $lastSbidMap[$report->campaign_id] = $report->last_sbid;
                }
            }
            
            // Fetch sbid_m from yesterday's records first, then L1 as fallback
            $sbidMReports = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where(function($q) use ($yesterday) {
                    $q->where('report_date_range', $yesterday)
                      ->orWhere('report_date_range', 'L1');
                })
                ->where('campaignStatus', '!=', 'ARCHIVED');
            
            if ($campaignType === 'PT') {
                $sbidMReports->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                      ->orWhere('campaignName', 'LIKE', '% PT.')
                      ->orWhere('campaignName', 'LIKE', '%PT')
                      ->orWhere('campaignName', 'LIKE', '%PT.');
                });
            } else {
                $sbidMReports->where('campaignName', 'NOT LIKE', '% PT')
                            ->where('campaignName', 'NOT LIKE', '% PT.');
            }
            
            $sbidMReports = $sbidMReports->get()
                ->sortBy(function($report) use ($yesterday) {
                    // Prioritize yesterday's records over L1
                    return $report->report_date_range === $yesterday ? 0 : 1;
                })
                ->groupBy('campaign_id');
            
            foreach ($sbidMReports as $campaignId => $reports) {
                // Get the first report (prioritized by yesterday)
                $report = $reports->first();
                if (!empty($report->campaign_id)) {
                    if (!empty($report->sbid_m)) {
                        $sbidMMap[$report->campaign_id] = $report->sbid_m;
                    }
                    // Check if SBID is approved (sbid matches sbid_m)
                    if (!empty($report->sbid_m) && $report->sbid == $report->sbid_m) {
                        $sbidApprovedMap[$report->campaign_id] = true;
                    }
                }
            }
        }

        // For PARENT rows: INV, OV L30, AL 30 = sum of child SKUs' values (so "PARENT 10 FR" shows total of its children)
        $childInvSumByParent = [];
        $childL30SumByParent = [];
        $childAL30SumByParent = [];
        foreach ($productMasters as $pm) {
            $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku);
            $norm = preg_replace('/\s+/', ' ', $norm);
            $skuUpper = strtoupper(trim($norm));
            if (stripos($skuUpper, 'PARENT') !== false) {
                continue;
            }
            $p = $pm->parent ?? '';
            if ($p === '') {
                continue;
            }
            $shopify = $shopifyData[$pm->sku] ?? null;
            $amazonSheetChild = $amazonDatasheetsBySku[$skuUpper] ?? null;
            $inv = ($shopify && isset($shopify->inv)) ? (int) $shopify->inv : 0;
            $l30 = ($shopify && isset($shopify->quantity)) ? (int) $shopify->quantity : 0;
            $al30 = ($amazonSheetChild && isset($amazonSheetChild->units_ordered_l30)) ? (int) $amazonSheetChild->units_ordered_l30 : 0;
            if (!isset($childInvSumByParent[$p])) {
                $childInvSumByParent[$p] = 0;
            }
            $childInvSumByParent[$p] += $inv;
            if (!isset($childL30SumByParent[$p])) {
                $childL30SumByParent[$p] = 0;
            }
            $childL30SumByParent[$p] += $l30;
            if (!isset($childAL30SumByParent[$p])) {
                $childAL30SumByParent[$p] = 0;
            }
            $childAL30SumByParent[$p] += $al30;
        }

        // For PARENT rows: avg price = average of child SKUs' prices (from amazon datashheet, or ProductMaster Values as fallback)
        $childPricesByParent = [];
        foreach ($productMasters as $pm) {
            $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku);
            $norm = preg_replace('/\s+/', ' ', $norm);
            $skuUpper = strtoupper(trim($norm));
            if (stripos($skuUpper, 'PARENT') !== false) {
                continue;
            }
            $p = $pm->parent ?? '';
            if ($p === '') {
                continue;
            }
            $amazonSheetChild = $amazonDatasheetsBySku[$skuUpper] ?? null;
            $childPrice = ($amazonSheetChild && isset($amazonSheetChild->price) && (float)$amazonSheetChild->price > 0)
                ? (float)$amazonSheetChild->price
                : null;
            if ($childPrice === null) {
                $values = $pm->Values;
                if (is_string($values)) {
                    $values = json_decode($values, true) ?: [];
                } elseif (is_object($values)) {
                    $values = (array) $values;
                } elseif (!is_array($values)) {
                    $values = [];
                }
                $childPrice = isset($values['msrp']) && (float)$values['msrp'] > 0
                    ? (float)$values['msrp']
                    : (isset($values['map']) && (float)$values['map'] > 0 ? (float)$values['map'] : null);
            }
            if ($childPrice !== null && $childPrice > 0) {
                $normParent = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $p ?? ''))));
                $normParent = rtrim($normParent, '.');
                if (!isset($childPricesByParent[$normParent])) {
                    $childPricesByParent[$normParent] = [];
                }
                $childPricesByParent[$normParent][] = $childPrice;
            }
        }
        $avgPriceByParent = [];
        $avgPriceByParentCanonical = []; // key = no spaces, so "PARENT MS 080 1PK" and "PARENT MS 080 1 PK" match
        foreach ($childPricesByParent as $p => $prices) {
            $avg = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
            $avgPriceByParent[$p] = $avg;
            $canonical = preg_replace('/\s+/', '', $p);
            if ($canonical !== '' && $avg > 0) {
                $avgPriceByParentCanonical[$canonical] = $avg;
            }
        }

        foreach ($productMasters as $pm) {
            // Normalize SKU first - normalize spaces (including non-breaking spaces) and convert to uppercase
            // Replace non-breaking spaces (UTF-8 c2a0) and other unicode spaces with regular spaces
            $normalizedSku = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku);
            // Replace multiple spaces with single space, then trim and uppercase
            $normalizedSku = preg_replace('/\s+/', ' ', $normalizedSku);
            $sku = strtoupper(trim($normalizedSku));
            $parent = $pm->parent;

            // For HL campaigns, only process parent SKUs
            if ($campaignType === 'HL' && stripos($sku, 'PARENT') === false) {
                continue;
            }

            // For KW/PT campaigns, do NOT skip parent SKUs - include them in result so "PARENT 10 FR" etc. show in table
            // (previously skipped here which caused PARENT rows to never appear)

            // PT: show all product_master rows (same count as KW/product_master), do not deduplicate by SKU
            // (removed unique SKU filter so PT row count matches product_master 1257)

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            // Initialize all campaign variables to avoid undefined variable errors
            $matchedCampaignL30 = null;
            $matchedCampaignL15 = null;
            $matchedCampaignL7 = null;
            $matchedCampaignL1 = null;

            if ($campaignType === 'PT') {
                // For PT: match with and without trailing dot on SKU so "PARENT DS 01." matches campaign "PARENT DS 01 PT"
                $cleanSkuPt = strtoupper(trim(rtrim($sku, '.')));
                $ptExpected = [
                    $sku . ' PT', $sku . ' PT.', $sku . 'PT', $sku . 'PT.',
                    $cleanSkuPt . ' PT', $cleanSkuPt . ' PT.', $cleanSkuPt . 'PT', $cleanSkuPt . 'PT.',
                ];
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($ptExpected) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return in_array($cleanName, $ptExpected, true);
                });

                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($ptExpected) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return in_array($cleanName, $ptExpected, true);
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($ptExpected) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return in_array($cleanName, $ptExpected, true);
                });
            } elseif ($campaignType === 'HL') {
                // For HL campaigns, also check L30 to determine if campaign exists
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });

                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });
            } else {
                // For KW campaigns, also check L30 to determine if campaign exists
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });

                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });
            }

            // SPONSORED_PRODUCTS: if no campaign matched by CHILD SKU, try matching by PARENT SKU (map parent campaign to all its children)
            if (($campaignType === 'KW' || $campaignType === 'PT') && stripos($sku, 'PARENT') === false && $parent !== '' && $parent !== null) {
                $parentNorm = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $parent ?? ''))));
                if (!$matchedCampaignL30 && $parentNorm !== '') {
                    if ($campaignType === 'PT') {
                        $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim($cn));
                            return $clean === $parentNorm . ' PT' || $clean === $parentNorm . ' PT.' || $clean === $parentNorm . 'PT' || $clean === $parentNorm . 'PT.';
                        });
                    } else {
                        $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim(rtrim($cn, '.')));
                            return $clean === $parentNorm;
                        });
                    }
                }
                if (!$matchedCampaignL7 && $parentNorm !== '') {
                    if ($campaignType === 'PT') {
                        $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim($cn));
                            return $clean === $parentNorm . ' PT' || $clean === $parentNorm . ' PT.' || $clean === $parentNorm . 'PT' || $clean === $parentNorm . 'PT.';
                        });
                    } else {
                        $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim(rtrim($cn, '.')));
                            return $clean === $parentNorm;
                        });
                    }
                }
                if (!$matchedCampaignL1 && $parentNorm !== '') {
                    if ($campaignType === 'PT') {
                        $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim($cn));
                            return $clean === $parentNorm . ' PT' || $clean === $parentNorm . ' PT.' || $clean === $parentNorm . 'PT' || $clean === $parentNorm . 'PT.';
                        });
                    } else {
                        $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim(rtrim($cn, '.')));
                            return $clean === $parentNorm;
                        });
                    }
                }
            }

            // Check if campaign exists - check L30, L7, or L1
            // Convert to boolean explicitly to ensure proper checking
            $hasCampaign = !empty($matchedCampaignL30) || !empty($matchedCampaignL7) || !empty($matchedCampaignL1);

            $campaignId = ($matchedCampaignL30 ? $matchedCampaignL30->campaign_id : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaign_id : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaign_id : null) ?? ''));
            $campaignName = ($matchedCampaignL30 ? $matchedCampaignL30->campaignName : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignName : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignName : null) ?? ''));
            
            // Set default values if no campaign found
            if (!$hasCampaign) {
                $campaignId = '';
                $campaignName = '';
            }

            // Check NRA filter and get TPFT, PFT, ROI, NRL, SPRICE, SGPFT, SROI from AmazonDataView
            $nra = '';
            $tpft = null;
            $pft = null;
            $roi = null;
            $nrl = 'REQ'; // Default value
            $sprice = null;
            $sgpft = null;
            $sroi = null;
            $spriceStatus = null;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                    $tpft = $raw['TPFT'] ?? null;
                    $pft = $raw['PFT'] ?? null;
                    $roi = $raw['ROI'] ?? null;
                    $nrl = $raw['NRL'] ?? 'REQ';
                    $sprice = $raw['SPRICE'] ?? null;
                    $sgpft = $raw['SGPFT'] ?? null;
                    $sroi = $raw['SROI'] ?? null;
                    $spriceStatus = $raw['SPRICE_STATUS'] ?? null;
                }
            }

            // For HL campaigns, don't skip parent SKUs with NRA = 'NRA' - include all parent SKUs
            // For KW/PT campaigns, skip SKUs with NRA = 'NRA'
            if ($campaignType !== 'HL' && $nra === 'NRA') {
                continue;
            }

            // Use SKU as key if no campaign, otherwise use campaignId
            $mapKey = !empty($campaignId) ? $campaignId : 'SKU_' . $sku;

            if (!isset($campaignMap[$mapKey])) {
                $baseSku = strtoupper(trim($pm->sku));
                $price = ($amazonSheet && isset($amazonSheet->price)) ? $amazonSheet->price : 0;
                // For parent SKU rows: use average of child SKUs' prices when direct price is 0
                if (($price === 0 || $price === null) && stripos($sku, 'PARENT') !== false) {
                    $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $sku))));
                    $normSku = rtrim($normSku, '.');
                    $normParentKey = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $parent ?? ''))));
                    $normParentKey = rtrim($normParentKey, '.');
                    $price = $avgPriceByParent[$normSku] ?? $avgPriceByParent[$normParentKey] ?? $avgPriceByParent[$parent ?? ''] ?? $avgPriceByParent[rtrim($parent ?? '', '.')] ?? 0;
                    if ($price === 0 && !empty($avgPriceByParentCanonical)) {
                        $canonicalSku = preg_replace('/\s+/', '', $normSku);
                        $canonicalParentKey = preg_replace('/\s+/', '', $normParentKey);
                        $price = $avgPriceByParentCanonical[$canonicalSku] ?? $avgPriceByParentCanonical[$canonicalParentKey] ?? 0;
                    }
                    if ($price === 0) {
                        $parentValues = $pm->Values;
                        if (is_string($parentValues)) {
                            $parentValues = json_decode($parentValues, true) ?: [];
                        } elseif (is_object($parentValues)) {
                            $parentValues = (array) $parentValues;
                        } else {
                            $parentValues = is_array($parentValues) ? $parentValues : [];
                        }
                        $price = (isset($parentValues['msrp']) && (float)$parentValues['msrp'] > 0)
                            ? (float)$parentValues['msrp']
                            : (isset($parentValues['map']) && (float)$parentValues['map'] > 0 ? (float)$parentValues['map'] : 0);
                    }
                }
                $price = (float) ($price ?? 0);

                // Get LP and Ship from ProductMaster
                $values = $pm->Values ?: [];
                $lp = $values['lp'] ?? 0;
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);
                
                // Calculate AD% (ad spend percentage) - use ALL campaign types (KW + PT + HL) like OverallAmazonController
                $adPercent = 0;
                $unitsL30 = ($amazonSheet && isset($amazonSheet->units_ordered_l30)) ? (int)$amazonSheet->units_ordered_l30 : 0;
                $totalRevenue = $price * $unitsL30;
                
                if ($totalRevenue > 0) {
                    // Get KW campaign spend (exact SKU match, excluding PT)
                    $kwSpend = 0;
                    $kwCampaign = $allSpCampaignReportsL30->first(function ($item) use ($sku) {
                        $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                        $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                        $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                        $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                        // Exclude PT campaigns
                        if (stripos($campaignName, ' PT') !== false || stripos($campaignName, 'PT.') !== false) {
                            return false;
                        }
                        return $campaignName === $cleanSku;
                    });
                    if ($kwCampaign) {
                        $kwSpend = $kwCampaign->spend ?? 0;
                    }
                    
                    // Get PT campaign spend (ends with PT or PT.)
                    $ptSpend = 0;
                    $ptCampaign = $allSpCampaignReportsL30->first(function ($item) use ($sku) {
                        $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                        $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                        $cleanName = strtoupper(trim($campaignName));
                        return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.' || 
                                $cleanName === $sku . 'PT' || $cleanName === $sku . 'PT.');
                    });
                    if ($ptCampaign) {
                        $ptSpend = $ptCampaign->spend ?? 0;
                    }
                    // If no KW/PT match by SKU and this is a CHILD row, try PARENT SKU for AD%
                    if ((!$kwCampaign || !$ptCampaign) && stripos($sku, 'PARENT') === false && $parent !== '' && $parent !== null) {
                        $parentNormAd = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $parent ?? ''))));
                        if (!$kwCampaign && $parentNormAd !== '') {
                            $kwCampaign = $allSpCampaignReportsL30->first(function ($item) use ($parentNormAd) {
                                $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                                $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                                $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                                if (stripos($campaignName, ' PT') !== false || stripos($campaignName, 'PT.') !== false) {
                                    return false;
                                }
                                return $campaignName === $parentNormAd;
                            });
                            if ($kwCampaign) {
                                $kwSpend = $kwCampaign->spend ?? 0;
                            }
                        }
                        if (!$ptCampaign && $parentNormAd !== '') {
                            $ptCampaign = $allSpCampaignReportsL30->first(function ($item) use ($parentNormAd) {
                                $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                                $cn = preg_replace('/\s+/', ' ', $cn);
                                $clean = strtoupper(trim($cn));
                                return $clean === $parentNormAd . ' PT' || $clean === $parentNormAd . ' PT.' || $clean === $parentNormAd . 'PT' || $clean === $parentNormAd . 'PT.';
                            });
                            if ($ptCampaign) {
                                $ptSpend = $ptCampaign->spend ?? 0;
                            }
                        }
                    }
                    
                    // Get HL campaign spend (SKU or SKU HEAD)
                    $hlSpend = 0;
                    if (isset($amazonHlL30)) {
                        $hlCampaign = $amazonHlL30->first(function ($item) use ($sku) {
                            $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                            $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                            $cleanName = strtoupper(trim($campaignName));
                            return ($cleanName === $sku || $cleanName === $sku . ' HEAD') && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
                        });
                        if ($hlCampaign) {
                            $hlSpend = $hlCampaign->cost ?? 0;
                        }
                    }
                    
                    // Total AD spend = KW + PT + HL
                    $totalAdSpend = $kwSpend + $ptSpend + $hlSpend;
                    $adPercent = round($totalAdSpend / $totalRevenue * 100, 4);
                }
                
                // Calculate GPFT% = ((price × 0.80 - ship - lp) / price) × 100
                $gpft = $price > 0
                    ? round((($price * 0.80 - $ship - $lp) / $price) * 100, 2)
                    : 0;
                
                // Calculate SPRICE, SGPFT, SROI if not set
                $calculatedSprice = $sprice;
                $calculatedSgpft = $sgpft;
                $calculatedSroi = $sroi;
                $hasCustomSprice = !empty($sprice);
                
                if (empty($calculatedSprice) && $price > 0) {
                    $calculatedSprice = $price;
                    $hasCustomSprice = false;
                    
                    // Calculate SGPFT based on default price (using 0.80 for Amazon)
                    $calculatedSgpft = round(
                        $price > 0 ? (($price * 0.80 - $ship - $lp) / $price) * 100 : 0,
                        2
                    );
                    
                    // Calculate SROI = ((SPRICE * (0.80 - AD%/100) - ship - lp) / lp) * 100
                    $adDecimal = $adPercent / 100;
                    $calculatedSroi = round(
                        $lp > 0 ? (($price * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
                        2
                    );
                } else if (!empty($calculatedSprice)) {
                    $hasCustomSprice = true;
                    
                    // Calculate SGPFT using custom SPRICE if not already set (using 0.80 for Amazon)
                    if (empty($calculatedSgpft)) {
                        $spriceFloat = floatval($calculatedSprice);
                        $calculatedSgpft = round(
                            $spriceFloat > 0 ? (($spriceFloat * 0.80 - $ship - $lp) / $spriceFloat) * 100 : 0,
                            2
                        );
                    }
                    
                    // Always recalculate SROI from SPRICE and current AD% (AD% might have changed)
                    $spriceFloat = floatval($calculatedSprice);
                    $adDecimal = $adPercent / 100;
                    $calculatedSroi = round(
                        $lp > 0 ? (($spriceFloat * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
                        2
                    );
                }
                
                // Always recalculate SPFT = SGPFT - AD% (AD% might have changed)
                $spft = !empty($calculatedSgpft) ? round($calculatedSgpft - $adPercent, 2) : 0;
                
                // Get ACOS history for this row - normalize SKU for matching
                $normalizedSkuForHistory = $pm->sku ? strtoupper(trim($pm->sku)) : '';
                $normalizedCampaignIdForHistory = $campaignId ? trim($campaignId) : '';
                
                // Try multiple matching strategies
                $historyRecord = null;
                
                // Strategy 1: Match by campaign_id + SKU (most specific)
                if ($normalizedCampaignIdForHistory && $normalizedSkuForHistory) {
                    $key1 = $normalizedCampaignIdForHistory . '|' . $normalizedSkuForHistory;
                    if (isset($acosHistoryMap[$key1])) {
                        $historyRecord = $acosHistoryMap[$key1];
                    }
                }
                
                // Strategy 2: Match by SKU only (fallback)
                if (!$historyRecord && $normalizedSkuForHistory) {
                    $key2 = '|' . $normalizedSkuForHistory;
                    if (isset($acosHistoryMap[$key2])) {
                        $historyRecord = $acosHistoryMap[$key2];
                    }
                }
                
                // Strategy 3: Match by campaign_id only (fallback)
                if (!$historyRecord && $normalizedCampaignIdForHistory) {
                    $key3 = $normalizedCampaignIdForHistory . '|';
                    if (isset($acosHistoryMap[$key3])) {
                        $historyRecord = $acosHistoryMap[$key3];
                    }
                }
                
                // Strategy 4: Loop through all records and match by SKU (case-insensitive)
                if (!$historyRecord && $normalizedSkuForHistory) {
                    foreach ($acosHistoryMap as $key => $record) {
                        if (isset($record->sku)) {
                            $recordSku = strtoupper(trim($record->sku));
                            if ($recordSku === $normalizedSkuForHistory) {
                                $historyRecord = $record;
                                break;
                            }
                        }
                    }
                }
                
                $targetIssues = [];
                $issueFound = '';
                $actionTaken = '';
                
                if ($historyRecord) {
                    // Get target issues
                    if (isset($historyRecord->target_issues) && $historyRecord->target_issues) {
                        $decoded = json_decode($historyRecord->target_issues, true);
                        $targetIssues = is_array($decoded) ? $decoded : [];
                    }
                    // Get issue found and action taken
                    $issueFound = $historyRecord->issue_found ?? '';
                    $actionTaken = $historyRecord->action_taken ?? '';
                }
                
                // is_parent: match INV/aggregation logic (contains PARENT) so Type=Sku shows 1005 child SKUs; frontend uses this for Type filter/counts
                $isParentRow = (stripos($pm->sku ?? '', 'PARENT') !== false);
                $campaignMap[$mapKey] = [
                    'parent' => $parent,
                    'sku' => $pm->sku,
                    'is_parent' => $isParentRow,
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'campaignBudgetAmount' => ($matchedCampaignL30 ? $matchedCampaignL30->campaignBudgetAmount : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignBudgetAmount : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignBudgetAmount : null) ?? 0)),
                    'campaignStatus' => ($matchedCampaignL30 ? $matchedCampaignL30->campaignStatus : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignStatus : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignStatus : null) ?? '')),
                    'pink_dil_paused_at' => ($matchedCampaignL30 ? $matchedCampaignL30->pink_dil_paused_at : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->pink_dil_paused_at : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->pink_dil_paused_at : null) ?? null)),
                    'INV' => (stripos($sku, 'PARENT') !== false) ? (int)($childInvSumByParent[$parent] ?? 0) : (($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0),
                    'FBA_INV' => isset($fbaData[$baseSku]) ? ($fbaData[$baseSku]->quantity_available ?? 0) : 0,
                    'L30' => (stripos($sku, 'PARENT') !== false) ? (int)($childL30SumByParent[$parent] ?? 0) : (($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0),
                    'A_L30' => (stripos($sku, 'PARENT') !== false) ? (int)($childAL30SumByParent[$parent] ?? 0) : (($amazonSheet && isset($amazonSheet->units_ordered_l30)) ? (int)$amazonSheet->units_ordered_l30 : 0),
                    'price' => $price,
                    'ratings' => $junglescoutData[$sku] ?? null,
                    'reviews' => $junglescoutReviews[$sku] ?? null,
                    'l7_spend' => 0,
                    'l7_cpc' => 0,
                    'l7_clicks' => 0,
                    'l7_sales' => 0,
                    'l7_purchases' => 0,
                    'l1_spend' => 0,
                    'l1_cpc' => 0,
                    'avg_cpc' => 0,
                    'acos' => 0,
                    'acos_L30' => 0,
                    'acos_L15' => 0,
                    'acos_L7' => 0,
                    'NRA' => $nra,
                    'TPFT' => $tpft,
                    'PFT' => $pft,
                    'roi' => $roi,
                    'NRL' => $nrl,
                    'hasCampaign' => $hasCampaign,
                    'GPFT' => $gpft,
                    'SPRICE' => $calculatedSprice,
                    'SGPFT' => $calculatedSgpft,
                    'Spft%' => $spft,
                    'SPFT' => $spft, // Keep both for backward compatibility
                    'SROI' => $calculatedSroi,
                    'has_custom_sprice' => $hasCustomSprice,
                    'SPRICE_STATUS' => $spriceStatus,
                    'last_sbid' => isset($lastSbidMap[$campaignId]) ? $lastSbidMap[$campaignId] : '',
                    'sbid_m' => isset($sbidMMap[$campaignId]) ? $sbidMMap[$campaignId] : '',
                    'sbid_approved' => isset($sbidApprovedMap[$campaignId]) ? $sbidApprovedMap[$campaignId] : false,
                    // ACOS Action History fields
                    'target_kw_issue' => $targetIssues['target_kw_issue'] ?? false,
                    'target_pt_issue' => $targetIssues['target_pt_issue'] ?? false,
                    'variation_issue' => $targetIssues['variation_issue'] ?? false,
                    'incorrect_product_added' => $targetIssues['incorrect_product_added'] ?? false,
                    'target_negative_kw_issue' => $targetIssues['target_negative_kw_issue'] ?? false,
                    'target_review_issue' => $targetIssues['target_review_issue'] ?? false,
                    'target_cvr_issue' => $targetIssues['target_cvr_issue'] ?? false,
                    'content_check' => $targetIssues['content_check'] ?? false,
                    'price_justification_check' => $targetIssues['price_justification_check'] ?? false,
                    'ad_not_req' => $targetIssues['ad_not_req'] ?? false,
                    'review_issue' => $targetIssues['review_issue'] ?? false,
                    'issue_found' => $issueFound,
                    'action_taken' => $actionTaken,
                ];
            }

            if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                if ($campaignType === 'HL') {
                    $campaignMap[$mapKey]['l7_spend'] = $matchedCampaignL7->cost ?? 0;
                    $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                        ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                        : 0;
                    $campaignMap[$mapKey]['l7_cpc'] = $costPerClick7;
                    $campaignMap[$mapKey]['l7_clicks'] = (int)($matchedCampaignL7->clicks ?? 0);
                    $campaignMap[$mapKey]['l7_sales'] = (float)($matchedCampaignL7->sales7d ?? 0);
                    $campaignMap[$mapKey]['l7_purchases'] = (int)($matchedCampaignL7->unitsSoldClicks7d ?? 0);
                } else {
                    // SP report may store L7 in 'cost'; use spend ?? cost; if still 0, derive from clicks * costPerClick
                    $l7SpendRaw = (float)($matchedCampaignL7->spend ?? $matchedCampaignL7->cost ?? 0);
                    $l7Clicks = (int)($matchedCampaignL7->clicks ?? 0);
                    $l7Cpc = (float)($matchedCampaignL7->costPerClick ?? 0);
                    if ($l7SpendRaw <= 0 && $l7Clicks > 0 && $l7Cpc > 0) {
                        $l7SpendRaw = round($l7Clicks * $l7Cpc, 2);
                    }
                    $campaignMap[$mapKey]['l7_spend'] = $l7SpendRaw;
                    $campaignMap[$mapKey]['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
                    $campaignMap[$mapKey]['l7_clicks'] = $l7Clicks;
                    // L7 sales: sales7d or attributedSalesSameSku7d (API may use either for L7)
                    $campaignMap[$mapKey]['l7_sales'] = (float)($matchedCampaignL7->sales7d ?? $matchedCampaignL7->attributedSalesSameSku7d ?? 0);
                    $campaignMap[$mapKey]['l7_purchases'] = (int)($matchedCampaignL7->unitsSoldClicks7d ?? 0);
                }
            }

            if (isset($matchedCampaignL1) && $matchedCampaignL1) {
                if ($campaignType === 'HL') {
                    $campaignMap[$mapKey]['l1_spend'] = $matchedCampaignL1->cost ?? 0;
                    $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                        ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                        : 0;
                    $campaignMap[$mapKey]['l1_cpc'] = $costPerClick1;
                } else {
                    // SP report may store L1 in 'cost'; use spend ?? cost
                    $campaignMap[$mapKey]['l1_spend'] = (float)($matchedCampaignL1->spend ?? $matchedCampaignL1->cost ?? 0);
                    $campaignMap[$mapKey]['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;
                }
            }

            if ($campaignType === 'PT') {
                $cleanSkuPt2 = strtoupper(trim(rtrim($sku, '.')));
                $ptExpected2 = [
                    $sku . ' PT', $sku . ' PT.', $sku . 'PT', $sku . 'PT.',
                    $cleanSkuPt2 . ' PT', $cleanSkuPt2 . ' PT.', $cleanSkuPt2 . 'PT', $cleanSkuPt2 . 'PT.',
                ];
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($ptExpected2) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return in_array($cleanName, $ptExpected2, true);
                });

                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($ptExpected2) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return in_array($cleanName, $ptExpected2, true);
                });
            } elseif ($campaignType === 'HL') {
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });

                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });
            } else {
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });

                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                    // Normalize campaign name: replace non-breaking spaces and multiple spaces
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });
            }

            // SPONSORED_PRODUCTS: if no L30/L15 match by SKU and this is a CHILD row, try matching by PARENT SKU
            if (($campaignType === 'KW' || $campaignType === 'PT') && stripos($sku, 'PARENT') === false && $parent !== '' && $parent !== null) {
                $parentNorm = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $parent ?? ''))));
                if (!$matchedCampaignL30 && $parentNorm !== '') {
                    if ($campaignType === 'PT') {
                        $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim($cn));
                            return $clean === $parentNorm . ' PT' || $clean === $parentNorm . ' PT.' || $clean === $parentNorm . 'PT' || $clean === $parentNorm . 'PT.';
                        });
                    } else {
                        $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim(rtrim($cn, '.')));
                            return $clean === $parentNorm;
                        });
                    }
                }
                if (!$matchedCampaignL15 && $parentNorm !== '') {
                    if ($campaignType === 'PT') {
                        $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim($cn));
                            return $clean === $parentNorm . ' PT' || $clean === $parentNorm . ' PT.' || $clean === $parentNorm . 'PT' || $clean === $parentNorm . 'PT.';
                        });
                    } else {
                        $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($parentNorm) {
                            $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                            $cn = preg_replace('/\s+/', ' ', $cn);
                            $clean = strtoupper(trim(rtrim($cn, '.')));
                            return $clean === $parentNorm;
                        });
                    }
                }
            }

            if (isset($matchedCampaignL30) && $matchedCampaignL30) {
                if ($campaignType === 'HL') {
                    $sales30 = $matchedCampaignL30->sales ?? 0;
                    $spend30 = $matchedCampaignL30->cost ?? 0;
                    $clicks30 = $matchedCampaignL30->clicks ?? 0;
                    $purchases30 = $matchedCampaignL30->unitsSold ?? 0;
                    $unitsSold30 = $matchedCampaignL30->unitsSold ?? 0;
                } else {
                    $sales30 = $matchedCampaignL30->sales30d ?? 0;
                    $spend30 = $matchedCampaignL30->spend ?? 0;
                    $clicks30 = $matchedCampaignL30->clicks ?? 0;
                    // Ad Sold L30: use unitsSoldClicks30d (units sold from clicks in L30)
                    $purchases30 = $matchedCampaignL30->unitsSoldClicks30d ?? 0;
                    $unitsSold30 = $matchedCampaignL30->unitsSoldClicks30d ?? 0;
                }
                if ($spend30 > 0 && $sales30 > 0) {
                    $campaignMap[$mapKey]['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
                } elseif ($spend30 > 0 && $sales30 == 0) {
                    $campaignMap[$mapKey]['acos_L30'] = 100;
                } else {
                    $campaignMap[$mapKey]['acos_L30'] = 0;
                }
                $campaignMap[$mapKey]['acos'] = $campaignMap[$mapKey]['acos_L30'];
                $campaignMap[$mapKey]['l30_spend'] = $spend30;
                $campaignMap[$mapKey]['l30_sales'] = $sales30;
                $campaignMap[$mapKey]['l30_clicks'] = $clicks30;
                $campaignMap[$mapKey]['l30_purchases'] = $unitsSold30;
                // Calculate AD CVR: (units sold from clicks / clicks) * 100
                $campaignMap[$mapKey]['ad_cvr'] = $clicks30 > 0 ? round(($purchases30 / $clicks30) * 100, 2) : 0;
                // Set lifetime avg_cpc
                $campaignMap[$mapKey]['avg_cpc'] = $avgCpcData->get($campaignId, 0);
            }

            if (isset($matchedCampaignL15) && isset($matchedCampaignL1) && $matchedCampaignL15 && $matchedCampaignL1) {
                if ($campaignType === 'HL') {
                    $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                    $spend15 = $matchedCampaignL15->cost ?? 0;
                } else {
                    $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                    $spend15 = $matchedCampaignL15->spend ?? 0;
                }
                if ($spend15 > 0 && $sales15 > 0) {
                    $campaignMap[$mapKey]['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
                } elseif ($spend15 > 0 && $sales15 == 0) {
                    $campaignMap[$mapKey]['acos_L15'] = 100;
                } else {
                    $campaignMap[$mapKey]['acos_L15'] = 0;
                }
            }

            if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                if ($campaignType === 'HL') {
                    $sales7 = $matchedCampaignL7->sales7d ?? 0;
                    $spend7 = $matchedCampaignL7->cost ?? 0;
                } else {
                    $sales7 = (float)($matchedCampaignL7->sales7d ?? $matchedCampaignL7->attributedSalesSameSku7d ?? 0);
                    $spend7 = (float)($matchedCampaignL7->spend ?? $matchedCampaignL7->cost ?? 0);
                    if ($spend7 <= 0) {
                        $clicks7 = (int)($matchedCampaignL7->clicks ?? 0);
                        $cpc7 = (float)($matchedCampaignL7->costPerClick ?? 0);
                        if ($clicks7 > 0 && $cpc7 > 0) {
                            $spend7 = round($clicks7 * $cpc7, 2);
                        }
                    }
                }
                if ($spend7 > 0 && $sales7 > 0) {
                    $campaignMap[$mapKey]['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
                } elseif ($spend7 > 0 && $sales7 == 0) {
                    $campaignMap[$mapKey]['acos_L7'] = 100;
                } else {
                    $campaignMap[$mapKey]['acos_L7'] = 0;
                }
            }
        }

        // Calculate total ACOS from ALL campaigns for over-utilized logic
        if ($campaignType === 'HL') {
            $allL30Campaigns = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L30')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
        } else {
            $allL30Campaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30');
            
            if ($campaignType === 'PT') {
                // Match PT campaigns with various naming conventions:
                // - "SKU PT", "SKU PT AUTO", "SKU PT V2" (space separator)
                // - "SKU-PT", "SKU-PT-AUTO" (hyphen separator)
                // - "SKU.PT" (dot separator)
                // - Ends with "PT" or "PT."
                $allL30Campaigns->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT%')
                      ->orWhere('campaignName', 'LIKE', '%-PT%')
                      ->orWhere('campaignName', 'LIKE', '%.PT%')
                      ->orWhere('campaignName', 'LIKE', '%PT')
                      ->orWhere('campaignName', 'LIKE', '%PT.');
                });
            } else {
                // Exclude PT campaigns for KW - exclude anything with PT patterns
                $allL30Campaigns->where('campaignName', 'NOT LIKE', '% PT%')
                               ->where('campaignName', 'NOT LIKE', '%-PT%')
                               ->where('campaignName', 'NOT LIKE', '%.PT%');
            }
            
            $allL30Campaigns = $allL30Campaigns->where('campaignStatus', '!=', 'ARCHIVED')->get();
        }

        $totalSpendAll = 0;
        $totalSalesAll = 0;
        $totalClicksAll = 0;
        $totalOrdersAll = 0;

        foreach ($allL30Campaigns as $campaign) {
            if ($campaignType === 'HL') {
                $spend = $campaign->cost ?? 0;
                // SB (Sponsored Brands) table uses 'sales' for L30 range; sales30d may not be populated
                $sales = (float)($campaign->sales ?? $campaign->sales30d ?? 0);
                $clicks = (int)($campaign->clicks ?? 0);
                $orders = (int)($campaign->purchases ?? 0);
            } else {
                $spend = $campaign->spend ?? 0;
                $sales = (float)($campaign->sales30d ?? 0);
                $clicks = (int)($campaign->clicks ?? 0);
                $orders = (int)($campaign->unitsSoldClicks30d ?? 0);
            }
            $totalSpendAll += $spend;
            $totalSalesAll += $sales;
            $totalClicksAll += $clicks;
            $totalOrdersAll += $orders;
        }

        $totalACOSAll = $totalSalesAll > 0 ? ($totalSpendAll / $totalSalesAll) * 100 : 0;

        // For KW/PT: Aggregate child spend/budget/CPC per parent so PARENT rows get SBID rules applied (ub7, ub1, CPC)
        $childL7SpendByParent = [];
        $childL1SpendByParent = [];
        $childBudgetByParent = [];
        $childSalesByParent = [];
        $childL7CpcSumByParent = [];
        $childL7CpcCountByParent = [];
        $childL1CpcSumByParent = [];
        $childL1CpcCountByParent = [];
        // Pre-compute parent 7 UB% / 1 UB% from ALL children (product_master) so utilization_budget and L7/L1 sums are correct
        $parentUtilizationFromAllChildren = [];
        if ($campaignType === 'KW' || $campaignType === 'PT') {
            foreach ($productMasters as $pm) {
                $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku ?? '');
                $norm = preg_replace('/\s+/', ' ', $norm);
                $skuUpper = strtoupper(trim($norm));
                if (stripos($skuUpper, 'PARENT') !== false) {
                    continue;
                }
                $p = $pm->parent ?? '';
                if ($p === '') {
                    continue;
                }
                if (!isset($parentUtilizationFromAllChildren[$p])) {
                    $parentUtilizationFromAllChildren[$p] = ['l7_spend' => 0, 'l1_spend' => 0, 'budget' => 0];
                }
                $matchL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($skuUpper, $campaignType) {
                    $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                    $cn = preg_replace('/\s+/', ' ', $cn);
                    $clean = strtoupper(trim($cn));
                    if ($campaignType === 'PT') {
                        return $clean === $skuUpper . ' PT' || $clean === $skuUpper . ' PT.' || $clean === $skuUpper . 'PT' || $clean === $skuUpper . 'PT.';
                    }
                    return $clean === $skuUpper;
                });
                $matchL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($skuUpper, $campaignType) {
                    $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                    $cn = preg_replace('/\s+/', ' ', $cn);
                    $clean = strtoupper(trim($cn));
                    if ($campaignType === 'PT') {
                        return $clean === $skuUpper . ' PT' || $clean === $skuUpper . ' PT.' || $clean === $skuUpper . 'PT' || $clean === $skuUpper . 'PT.';
                    }
                    return $clean === $skuUpper;
                });
                $matchL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($skuUpper, $campaignType) {
                    $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                    $cn = preg_replace('/\s+/', ' ', $cn);
                    $clean = strtoupper(trim($cn));
                    if ($campaignType === 'PT') {
                        return $clean === $skuUpper . ' PT' || $clean === $skuUpper . ' PT.' || $clean === $skuUpper . 'PT' || $clean === $skuUpper . 'PT.';
                    }
                    return $clean === $skuUpper;
                });
                $bgt = $matchL7 ? (float)($matchL7->campaignBudgetAmount ?? 0) : ($matchL1 ? (float)($matchL1->campaignBudgetAmount ?? 0) : ($matchL30 ? (float)($matchL30->campaignBudgetAmount ?? 0) : 0));
                $parentUtilizationFromAllChildren[$p]['l7_spend'] += $matchL7 ? (float)($matchL7->spend ?? $matchL7->cost ?? 0) : 0;
                $parentUtilizationFromAllChildren[$p]['l1_spend'] += $matchL1 ? (float)($matchL1->spend ?? $matchL1->cost ?? 0) : 0;
                $parentUtilizationFromAllChildren[$p]['budget'] += $bgt;
            }
            foreach ($campaignMap as $mapKey => $row) {
                $skuStr = $row['sku'] ?? '';
                if (stripos($skuStr, 'PARENT') !== false) {
                    continue;
                }
                $p = $row['parent'] ?? '';
                if ($p === '') {
                    continue;
                }
                $l7 = (float)($row['l7_spend'] ?? 0);
                $l1 = (float)($row['l1_spend'] ?? 0);
                $budget = (float)($row['campaignBudgetAmount'] ?? 0);
                $sales30 = (float)($row['l30_sales'] ?? 0);
                $childL7SpendByParent[$p] = ($childL7SpendByParent[$p] ?? 0) + $l7;
                $childL1SpendByParent[$p] = ($childL1SpendByParent[$p] ?? 0) + $l1;
                $childBudgetByParent[$p] = ($childBudgetByParent[$p] ?? 0) + $budget;
                $childSalesByParent[$p] = ($childSalesByParent[$p] ?? 0) + $sales30;
                $l7Cpc = (float)($row['l7_cpc'] ?? 0);
                $l1Cpc = (float)($row['l1_cpc'] ?? 0);
                if ($l7Cpc > 0) {
                    $childL7CpcSumByParent[$p] = ($childL7CpcSumByParent[$p] ?? 0) + $l7Cpc;
                    $childL7CpcCountByParent[$p] = ($childL7CpcCountByParent[$p] ?? 0) + 1;
                }
                if ($l1Cpc > 0) {
                    $childL1CpcSumByParent[$p] = ($childL1CpcSumByParent[$p] ?? 0) + $l1Cpc;
                    $childL1CpcCountByParent[$p] = ($childL1CpcCountByParent[$p] ?? 0) + 1;
                }
            }
            foreach ($campaignMap as $mapKey => &$row) {
                $skuStr = $row['sku'] ?? '';
                if (stripos($skuStr, 'PARENT') === false) {
                    continue;
                }
                $p = $row['parent'] ?? '';
                if ($p === '') {
                    continue;
                }
                // Parent row: use DIRECT campaign L7/L1 spend and budget (DB value), NOT sum of children
                // So l7_spend/l1_spend match the campaign report for this parent SKU (e.g. PARENT DS GT -> 6.95)
                $row['l7_spend'] = (float)($row['l7_spend'] ?? 0);
                $row['l1_spend'] = (float)($row['l1_spend'] ?? 0);
                $row['utilization_budget'] = (float)($row['campaignBudgetAmount'] ?? 0);
                $row['hasCampaign'] = ($row['utilization_budget'] ?? 0) > 0;
                // l7_cpc, l1_cpc, avg_cpc already set from matchedCampaignL7/L1 in main loop — do not overwrite with child averages
                // ACOS = (Spend / Sales) * 100; when Spend L30 and Sales L30 both 0, ACOS must be 0
                $rowSpend = (float)($row['l30_spend'] ?? 0);
                $rowSales = (float)($row['l30_sales'] ?? 0);
                if ($rowSpend == 0 && $rowSales == 0) {
                    $row['acos_L30'] = 0;
                    $row['acos'] = 0;
                } else {
                    $row['acos_L30'] = $rowSales > 0 ? round(($rowSpend / $rowSales) * 100, 2) : ($rowSpend > 0 ? 100 : 0);
                    $row['acos'] = $row['acos_L30'];
                }
            }
            unset($row);
        }

        // ACOS must be 0 when Spend L30 and Sales L30 are both 0 (for ALL rows including parents with no children in aggregation)
        foreach ($campaignMap as $mapKey => &$row) {
            $rowSpend = (float)($row['l30_spend'] ?? 0);
            $rowSales = (float)($row['l30_sales'] ?? 0);
            if ($rowSpend <= 0 && $rowSales <= 0) {
                $row['acos_L30'] = 0;
                $row['acos'] = 0;
            }
        }
        unset($row);

        // Add all SKUs that were processed
        foreach ($campaignMap as $campaignId => $row) {
            $result[] = (object) $row;
        }

        // Second pass: fix parent SKU price when still 0 (avg from children or parent's own Values)
        foreach ($result as $item) {
            $skuStr = $item->sku ?? '';
            if (stripos($skuStr, 'PARENT') === false) {
                continue;
            }
            $currentPrice = (float)($item->price ?? 0);
            if ($currentPrice > 0) {
                continue;
            }
            $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $skuStr))));
            $normSku = rtrim($normSku, '.');
            $normParent = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->parent ?? ''))));
            $normParent = rtrim($normParent, '.');
            $item->price = $avgPriceByParent[$normSku] ?? $avgPriceByParent[$normParent] ?? $avgPriceByParent[$item->parent ?? ''] ?? $avgPriceByParent[rtrim($item->parent ?? '', '.')] ?? 0;
            if ($item->price <= 0 && !empty($avgPriceByParentCanonical)) {
                $canonicalSku = preg_replace('/\s+/', '', $normSku);
                $canonicalParent = preg_replace('/\s+/', '', $normParent);
                $item->price = $avgPriceByParentCanonical[$canonicalSku] ?? $avgPriceByParentCanonical[$canonicalParent] ?? 0;
            }
            if ($item->price <= 0) {
                $pmForParent = $productMasters->first(function ($p) use ($skuStr) {
                    $n = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $p->sku ?? ''))));
                    $itemSkuNorm = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $skuStr))));
                    return $n === $itemSkuNorm;
                });
                if ($pmForParent) {
                    $vals = $pmForParent->Values;
                    if (is_string($vals)) {
                        $vals = json_decode($vals, true) ?: [];
                    } elseif (is_object($vals)) {
                        $vals = (array) $vals;
                    } else {
                        $vals = is_array($vals) ? $vals : [];
                    }
                    if (isset($vals['msrp']) && (float)$vals['msrp'] > 0) {
                        $item->price = (float)$vals['msrp'];
                    } elseif (isset($vals['map']) && (float)$vals['map'] > 0) {
                        $item->price = (float)$vals['map'];
                    }
                }
            }
            $item->price = (float)($item->price ?? 0);
        }

        // Final pass: parent rows use direct campaign data only; ensure l1_spend=0 when no L1 report so 7 UB% / 1 UB% match DB
        foreach ($result as $item) {
            $skuStr = $item->sku ?? '';
            if (stripos($skuStr, 'PARENT') === false) {
                continue;
            }
            $item->l7_spend = (float)($item->l7_spend ?? 0);
            $item->l1_spend = (float)($item->l1_spend ?? 0);
            if (($item->utilization_budget ?? null) === null || ($item->utilization_budget ?? '') === '') {
                $item->utilization_budget = (float)($item->campaignBudgetAmount ?? 0);
            }
        }

        // For HL campaigns, use a direct approach to ensure ALL parent SKUs are included
        if ($campaignType === 'HL') {
            // Build a map of existing SKUs in result (using same normalization as main loop)
            $existingSkusMap = [];
            foreach ($result as $item) {
                if (empty($item->sku)) {
                    continue;
                }
                // Apply same normalization as main loop
                $normalizedSku = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->sku);
                $normalizedSku = preg_replace('/\s+/', ' ', $normalizedSku);
                $existingSku = strtoupper(trim($normalizedSku));
                $existingSkusMap[$existingSku] = true;
            }
            
            // Get ALL parent SKUs directly from database to ensure we have the complete list
            $allParentSkus = ProductMaster::whereNull('deleted_at')
                ->where('sku', 'LIKE', 'PARENT %')
                ->get();
            
            foreach ($allParentSkus as $pm) {
                // Normalize SKU
                $normalizedSku = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku);
                $normalizedSku = preg_replace('/\s+/', ' ', $normalizedSku);
                $sku = strtoupper(trim($normalizedSku));
                
                // Skip if SKU already exists in result
                if (isset($existingSkusMap[$sku])) {
                    continue;
                }
                
                // This parent SKU is missing - add it
                $baseSku = strtoupper(trim($pm->sku));
                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$pm->sku] ?? null;
                
                // Get NRA, TPFT, PFT, ROI and NRL from AmazonDataView
                $nra = '';
                $tpft = null;
                $pft = null;
                $roi = null;
                $nrl = 'REQ';
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $nra = $raw['NRA'] ?? '';
                        $tpft = $raw['TPFT'] ?? null;
                        $pft = $raw['PFT'] ?? null;
                        $roi = $raw['ROI'] ?? null;
                        $nrl = $raw['NRL'] ?? 'REQ';
                    }
                }
                
                // Check for campaign
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                
                $hasCampaign = !empty($matchedCampaignL30) || !empty($matchedCampaignL7) || !empty($matchedCampaignL1);
                $campaignId = ($matchedCampaignL30 ? $matchedCampaignL30->campaign_id : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaign_id : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaign_id : null) ?? ''));
                $campaignName = ($matchedCampaignL30 ? $matchedCampaignL30->campaignName : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignName : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignName : null) ?? ''));
                
                // Calculate spend and CPC
                $l7Spend = 0;
                $l7Cpc = 0;
                $l1Spend = 0;
                $l1Cpc = 0;
                $acosL30 = 0;
                $acosL15 = 0;
                $acosL7 = 0;
                $acos = 0;
                
                if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                    $l7Spend = $matchedCampaignL7->cost ?? 0;
                    $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                        ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                        : 0;
                    $l7Cpc = $costPerClick7;
                }
                
                if (isset($matchedCampaignL1) && $matchedCampaignL1) {
                    $l1Spend = $matchedCampaignL1->cost ?? 0;
                    $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                        ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                        : 0;
                    $l1Cpc = $costPerClick1;
                }
                
                if (isset($matchedCampaignL30) && $matchedCampaignL30) {
                    $sales30 = $matchedCampaignL30->sales30d ?? 0;
                    $spend30 = $matchedCampaignL30->cost ?? 0;
                    if ($spend30 > 0 && $sales30 > 0) {
                        $acosL30 = round(($spend30 / $sales30) * 100, 2);
                    } elseif ($spend30 > 0 && $sales30 == 0) {
                        $acosL30 = 100;
                    } else {
                        $acosL30 = 0;
                    }
                    $acos = $acosL30;
                }
                
                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                
                if (isset($matchedCampaignL15) && isset($matchedCampaignL1) && $matchedCampaignL15 && $matchedCampaignL1) {
                    $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                    $spend15 = $matchedCampaignL15->cost ?? 0;
                    if ($sales15 > 0) {
                        $acosL15 = round(($spend15 / $sales15) * 100, 2);
                    } elseif ($spend15 > 0) {
                        $acosL15 = 100;
                    }
                }
                
                if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                    $sales7 = $matchedCampaignL7->sales7d ?? 0;
                    $spend7 = $matchedCampaignL7->cost ?? 0;
                    if ($sales7 > 0) {
                        $acosL7 = round(($spend7 / $sales7) * 100, 2);
                    } elseif ($spend7 > 0) {
                        $acosL7 = 100;
                    }
                }
                
                $result[] = (object) [
                    'parent' => $pm->parent,
                    'sku' => $pm->sku,
                    'is_parent' => true,
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'campaignBudgetAmount' => ($matchedCampaignL30 ? $matchedCampaignL30->campaignBudgetAmount : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignBudgetAmount : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignBudgetAmount : null) ?? 0)),
                    'campaignStatus' => ($matchedCampaignL30 ? $matchedCampaignL30->campaignStatus : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignStatus : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignStatus : null) ?? '')),
                    'pink_dil_paused_at' => ($matchedCampaignL30 ? $matchedCampaignL30->pink_dil_paused_at : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->pink_dil_paused_at : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->pink_dil_paused_at : null) ?? null)),
                    'INV' => (int)($childInvSumByParent[$pm->parent ?? ''] ?? 0),
                    'FBA_INV' => isset($fbaData[$baseSku]) ? ($fbaData[$baseSku]->quantity_available ?? 0) : 0,
                    'L30' => (int)($childL30SumByParent[$pm->parent ?? ''] ?? 0),
                    'A_L30' => (int)($childAL30SumByParent[$pm->parent ?? ''] ?? 0),
                    'l7_spend' => $l7Spend,
                    'l7_cpc' => $l7Cpc,
                    'l1_spend' => $l1Spend,
                    'l1_cpc' => $l1Cpc,
                    'avg_cpc' => $avgCpcData->get($campaignId, 0),
                    'acos' => $acos,
                    'acos_L30' => $acosL30,
                    'acos_L15' => $acosL15,
                    'acos_L7' => $acosL7,
                    'NRA' => $nra,
                    'TPFT' => $tpft,
                    'PFT' => $pft,
                    'roi' => $roi,
                    'NRL' => $nrl,
                    'hasCampaign' => $hasCampaign,
                    'GPFT' => $gpft,
                    'SPRICE' => $calculatedSprice,
                    'SGPFT' => $calculatedSgpft,
                    'Spft%' => $spft,
                    'SPFT' => $spft, // Keep both for backward compatibility
                    'SROI' => $calculatedSroi,
                    'has_custom_sprice' => $hasCustomSprice,
                    'SPRICE_STATUS' => $spriceStatus,
                ];
                
                // Update existingSkusMap to avoid duplicates
                $existingSkusMap[$sku] = true;
            }
        } else {
            // For KW/PT campaigns, use the second loop to add missing SKUs
            // For all SKUs, ensure they are in the result (even without campaigns)
            $existingSkus = array_map(function($item) {
                return strtoupper(trim($item->sku ?? ''));
            }, $result);

            foreach ($productMasters as $pm) {
                // Skip soft deleted SKUs
                if ($pm->deleted_at !== null) {
                    continue;
                }
                
                // Normalize SKU
                $normalizedSku = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku);
                $normalizedSku = preg_replace('/\s+/', ' ', $normalizedSku);
                $sku = strtoupper(trim($normalizedSku));
                
                // Skip if SKU already exists in result
                if (in_array($sku, $existingSkus)) {
                    continue;
                }

                // For KW/PT campaigns, do NOT skip parent SKUs - they may have been missed in main loop (e.g. duplicate mapKey), include them here

            // Re-check if campaign exists for this SKU (in case it was missed in main loop)
            $matchedCampaignL30 = null;
            $matchedCampaignL7 = null;
            $matchedCampaignL1 = null;
            
            if ($campaignType === 'PT') {
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.');
                });
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.');
                });
                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.');
                });
            } elseif ($campaignType === 'HL') {
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
            } else {
                // KW campaigns
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });
                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });
            }
            
            $hasCampaign = !empty($matchedCampaignL30) || !empty($matchedCampaignL7) || !empty($matchedCampaignL1);
            $campaignId = ($matchedCampaignL30 ? $matchedCampaignL30->campaign_id : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaign_id : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaign_id : null) ?? ''));
            $campaignName = ($matchedCampaignL30 ? $matchedCampaignL30->campaignName : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignName : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignName : null) ?? ''));

            // Add SKU without campaign (or with campaign if found)
            $baseSku = strtoupper(trim($pm->sku));
            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            // Get NRA, TPFT, PFT, ROI, NRL, SPRICE, SGPFT, SROI from AmazonDataView
            $nra = '';
            $tpft = null;
            $pft = null;
            $roi = null;
            $nrl = 'REQ'; // Default value
            $sprice = null;
            $sgpft = null;
            $sroi = null;
            $spriceStatus = null;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                    $tpft = $raw['TPFT'] ?? null;
                    $pft = $raw['PFT'] ?? null;
                    $roi = $raw['ROI'] ?? null;
                    $nrl = $raw['NRL'] ?? 'REQ';
                    $sprice = $raw['SPRICE'] ?? null;
                    $sgpft = $raw['SGPFT'] ?? null;
                    $sroi = $raw['SROI'] ?? null;
                    $spriceStatus = $raw['SPRICE_STATUS'] ?? null;
                }
            }
            
            // Get LP and Ship from ProductMaster
            $values = $pm->Values ?: [];
            $lp = $values['lp'] ?? 0;
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);
            
            $price = ($amazonSheet && isset($amazonSheet->price)) ? $amazonSheet->price : 0;
            
            // Calculate AD% (ad spend percentage) - use ALL campaign types (KW + PT + HL) like OverallAmazonController
            $adPercent = 0;
            $unitsL30 = ($amazonSheet && isset($amazonSheet->units_ordered_l30)) ? (int)$amazonSheet->units_ordered_l30 : 0;
            $totalRevenue = $price * $unitsL30;
            
            if ($totalRevenue > 0) {
                // Get KW campaign spend (exact SKU match, excluding PT)
                $kwSpend = 0;
                $kwCampaign = $allSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    // Exclude PT campaigns
                    if (stripos($campaignName, ' PT') !== false || stripos($campaignName, 'PT.') !== false) {
                        return false;
                    }
                    return $campaignName === $cleanSku;
                });
                if ($kwCampaign) {
                    $kwSpend = $kwCampaign->spend ?? 0;
                }
                
                // Get PT campaign spend (ends with PT or PT.)
                $ptSpend = 0;
                $ptCampaign = $allSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.' || 
                            $cleanName === $sku . 'PT' || $cleanName === $sku . 'PT.');
                });
                if ($ptCampaign) {
                    $ptSpend = $ptCampaign->spend ?? 0;
                }
                
                // Get HL campaign spend (SKU or SKU HEAD)
                $hlSpend = 0;
                if (isset($amazonHlL30)) {
                    $hlCampaign = $amazonHlL30->first(function ($item) use ($sku) {
                        $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                        $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                        $cleanName = strtoupper(trim($campaignName));
                        return ($cleanName === $sku || $cleanName === $sku . ' HEAD') && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
                    });
                    if ($hlCampaign) {
                        $hlSpend = $hlCampaign->cost ?? 0;
                    }
                }
                
                // Total AD spend = KW + PT + HL
                $totalAdSpend = $kwSpend + $ptSpend + $hlSpend;
                $adPercent = $totalAdSpend / $totalRevenue * 100;
            }
            
            // Calculate GPFT% = ((price × 0.80 - ship - lp) / price) × 100
            $gpft = $price > 0
                ? round((($price * 0.80 - $ship - $lp) / $price) * 100, 2)
                : 0;
            
            // Calculate SPRICE, SGPFT, SROI if not set
            $calculatedSprice = $sprice;
            $calculatedSgpft = $sgpft;
            $calculatedSroi = $sroi;
            $hasCustomSprice = !empty($sprice);
            
            if (empty($calculatedSprice) && $price > 0) {
                $calculatedSprice = $price;
                $hasCustomSprice = false;
                
                // Calculate SGPFT based on default price (using 0.80 for Amazon)
                $calculatedSgpft = round(
                    $price > 0 ? (($price * 0.80 - $ship - $lp) / $price) * 100 : 0,
                    2
                );
                
                // Calculate SROI = ((SPRICE * (0.80 - AD%/100) - ship - lp) / lp) * 100
                $adDecimal = $adPercent / 100;
                $calculatedSroi = round(
                    $lp > 0 ? (($price * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
                    2
                );
            } else if (!empty($calculatedSprice)) {
                $hasCustomSprice = true;
                
                // Calculate SGPFT using custom SPRICE if not already set (using 0.80 for Amazon)
                if (empty($calculatedSgpft)) {
                    $spriceFloat = floatval($calculatedSprice);
                    $calculatedSgpft = round(
                        $spriceFloat > 0 ? (($spriceFloat * 0.80 - $ship - $lp) / $spriceFloat) * 100 : 0,
                        2
                    );
                }
                
                // Calculate SROI = ((SPRICE * (0.80 - AD%/100) - ship - lp) / lp) * 100
                if (!empty($calculatedSprice)) {
                    $spriceFloat = floatval($calculatedSprice);
                    $adDecimal = $adPercent / 100;
                    $calculatedSroi = round(
                        $lp > 0 ? (($spriceFloat * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
                        2
                    );
                }
            }
            
            // Calculate SPFT% = SGPFT% - AD%
            $spft = !empty($calculatedSgpft) ? round($calculatedSgpft - $adPercent, 2) : 0;

            // Calculate spend and CPC for second loop (same as main loop)
            $l7Spend = 0;
            $l7Cpc = 0;
            $l1Spend = 0;
            $l1Cpc = 0;
            $acosL30 = 0;
            $acosL15 = 0;
            $acosL7 = 0;
            $acos = 0;
            
            if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                if ($campaignType === 'HL') {
                    $l7Spend = $matchedCampaignL7->cost ?? 0;
                    $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                        ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                        : 0;
                    $l7Cpc = $costPerClick7;
                } else {
                    $l7Spend = $matchedCampaignL7->spend ?? 0;
                    $l7Cpc = $matchedCampaignL7->costPerClick ?? 0;
                }
            }
            
            if (isset($matchedCampaignL1) && $matchedCampaignL1) {
                if ($campaignType === 'HL') {
                    $l1Spend = $matchedCampaignL1->cost ?? 0;
                    $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                        ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                        : 0;
                    $l1Cpc = $costPerClick1;
                } else {
                    $l1Spend = $matchedCampaignL1->spend ?? 0;
                    $l1Cpc = $matchedCampaignL1->costPerClick ?? 0;
                }
            }
            
            // Calculate ACOS for L30
            if (isset($matchedCampaignL30) && $matchedCampaignL30) {
                if ($campaignType === 'HL') {
                    $sales30 = $matchedCampaignL30->sales30d ?? 0;
                    $spend30 = $matchedCampaignL30->cost ?? 0;
                } else {
                    $sales30 = $matchedCampaignL30->sales30d ?? 0;
                    $spend30 = $matchedCampaignL30->spend ?? 0;
                }
                if ($spend30 > 0 && $sales30 > 0) {
                    $acosL30 = round(($spend30 / $sales30) * 100, 2);
                } elseif ($spend30 > 0 && $sales30 == 0) {
                    $acosL30 = 100;
                } else {
                    $acosL30 = 0;
                }
                $acos = $acosL30;
            }
            
            // Get matchedCampaignL15 for ACOS L15 calculation
            $matchedCampaignL15 = null;
            if ($campaignType === 'PT') {
                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.' || 
                            $cleanName === $sku . 'PT' || $cleanName === $sku . 'PT.');
                });
            } elseif ($campaignType === 'HL') {
                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });
            } else {
                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });
            }
            
            // Calculate ACOS for L15 and L7
            if (isset($matchedCampaignL15) && isset($matchedCampaignL1) && $matchedCampaignL15 && $matchedCampaignL1) {
                if ($campaignType === 'HL') {
                    $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                    $spend15 = $matchedCampaignL15->cost ?? 0;
                } else {
                    $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                    $spend15 = $matchedCampaignL15->spend ?? 0;
                }
                if ($sales15 > 0) {
                    $acosL15 = round(($spend15 / $sales15) * 100, 2);
                } elseif ($spend15 > 0) {
                    $acosL15 = 100;
                }
            }
            
            if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                if ($campaignType === 'HL') {
                    $sales7 = $matchedCampaignL7->sales7d ?? 0;
                    $spend7 = $matchedCampaignL7->cost ?? 0;
                } else {
                    $sales7 = $matchedCampaignL7->sales7d ?? 0;
                    $spend7 = $matchedCampaignL7->spend ?? 0;
                }
                if ($sales7 > 0) {
                    $acosL7 = round(($spend7 / $sales7) * 100, 2);
                } elseif ($spend7 > 0) {
                    $acosL7 = 100;
                }
            }
            
            // Get ACOS history for this row - normalize SKU for matching
            $normalizedSku = $pm->sku ? strtoupper(trim($pm->sku)) : '';
            $normalizedCampaignId = $campaignId ? trim($campaignId) : '';
            
            // Try multiple matching strategies
            $historyRecord = null;
            
            // Strategy 1: Match by campaign_id + SKU (most specific)
            if ($normalizedCampaignId && $normalizedSku) {
                $key1 = $normalizedCampaignId . '|' . $normalizedSku;
                if (isset($acosHistoryMap[$key1])) {
                    $historyRecord = $acosHistoryMap[$key1];
                }
            }
            
            // Strategy 2: Match by SKU only (fallback)
            if (!$historyRecord && $normalizedSku) {
                $key2 = '|' . $normalizedSku;
                if (isset($acosHistoryMap[$key2])) {
                    $historyRecord = $acosHistoryMap[$key2];
                }
            }
            
            // Strategy 3: Match by campaign_id only (fallback)
            if (!$historyRecord && $normalizedCampaignId) {
                $key3 = $normalizedCampaignId . '|';
                if (isset($acosHistoryMap[$key3])) {
                    $historyRecord = $acosHistoryMap[$key3];
                }
            }
            
            // Strategy 4: Loop through all records and match by SKU (case-insensitive)
            if (!$historyRecord && $normalizedSku) {
                foreach ($acosHistoryMap as $key => $record) {
                    if (isset($record->sku)) {
                        $recordSku = strtoupper(trim($record->sku));
                        if ($recordSku === $normalizedSku) {
                            $historyRecord = $record;
                            break;
                        }
                    }
                }
            }
            $targetIssues = [];
            $issueFound = '';
            $actionTaken = '';
            
            if ($historyRecord) {
                // Get target issues
                if (isset($historyRecord->target_issues) && $historyRecord->target_issues) {
                    $decoded = json_decode($historyRecord->target_issues, true);
                    $targetIssues = is_array($decoded) ? $decoded : [];
                }
                // Get issue found and action taken
                $issueFound = $historyRecord->issue_found ?? '';
                $actionTaken = $historyRecord->action_taken ?? '';
            }
            
            // For PARENT rows in missing SKUs loop: use aggregated child spend/budget/CPC so SBID rules apply
            $campaignBudgetAmountForRow = ($matchedCampaignL30 ? $matchedCampaignL30->campaignBudgetAmount : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignBudgetAmount : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignBudgetAmount : null) ?? 0));
            $avgCpcForRow = $avgCpcData->get($campaignId, 0);
            $utilizationBudgetForRow = null;
            if (stripos($sku, 'PARENT') !== false && isset($childL7SpendByParent[$pm->parent ?? ''])) {
                $p = $pm->parent ?? '';
                $l7Spend = $childL7SpendByParent[$p];
                $l1Spend = $childL1SpendByParent[$p];
                $l7Cpc = ($childL7CpcCountByParent[$p] ?? 0) > 0 ? ($childL7CpcSumByParent[$p] / $childL7CpcCountByParent[$p]) : 0;
                $l1Cpc = ($childL1CpcCountByParent[$p] ?? 0) > 0 ? ($childL1CpcSumByParent[$p] / $childL1CpcCountByParent[$p]) : 0;
                $utilizationBudgetForRow = $childBudgetByParent[$p];
                $avgCpcForRow = ($l7Cpc + $l1Cpc) / 2;
            }
            
            $result[] = (object) [
                'parent' => $pm->parent ?? '',
                'sku' => $pm->sku,
                'is_parent' => (stripos($pm->sku ?? '', 'PARENT') !== false),
                'campaign_id' => $campaignId,
                'campaignName' => $campaignName,
                'campaignBudgetAmount' => $campaignBudgetAmountForRow,
                'campaignStatus' => ($matchedCampaignL30 ? $matchedCampaignL30->campaignStatus : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignStatus : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignStatus : null) ?? '')),
                'pink_dil_paused_at' => ($matchedCampaignL30 ? $matchedCampaignL30->pink_dil_paused_at : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->pink_dil_paused_at : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->pink_dil_paused_at : null) ?? null)),
                'INV' => (stripos($sku, 'PARENT') !== false) ? (int)($childInvSumByParent[$pm->parent ?? ''] ?? 0) : (($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0),
                'FBA_INV' => isset($fbaData[$baseSku]) ? ($fbaData[$baseSku]->quantity_available ?? 0) : 0,
                'L30' => (stripos($sku, 'PARENT') !== false) ? (int)($childL30SumByParent[$pm->parent ?? ''] ?? 0) : (($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0),
                'A_L30' => (stripos($sku, 'PARENT') !== false) ? (int)($childAL30SumByParent[$pm->parent ?? ''] ?? 0) : (($amazonSheet && isset($amazonSheet->units_ordered_l30)) ? (int)$amazonSheet->units_ordered_l30 : 0),
                'l7_spend' => $l7Spend,
                'l7_cpc' => $l7Cpc,
                'l1_spend' => $l1Spend,
                'l1_cpc' => $l1Cpc,
                'avg_cpc' => $avgCpcForRow,
                'acos' => $acos,
                'acos_L30' => $acosL30,
                'acos_L15' => $acosL15,
                'acos_L7' => $acosL7,
                'NRA' => $nra,
                'TPFT' => $tpft,
                'PFT' => $pft,
                'roi' => $roi,
                'NRL' => $nrl,
                'hasCampaign' => $hasCampaign,
                'GPFT' => $gpft,
                'SPRICE' => $calculatedSprice,
                'SGPFT' => $calculatedSgpft,
                'Spft%' => $spft,
                'SPFT' => $spft, // Keep both for backward compatibility
                'SROI' => $calculatedSroi,
                'has_custom_sprice' => $hasCustomSprice,
                'SPRICE_STATUS' => $spriceStatus,
                // ACOS Action History fields
                'target_kw_issue' => $targetIssues['target_kw_issue'] ?? false,
                'target_pt_issue' => $targetIssues['target_pt_issue'] ?? false,
                'variation_issue' => $targetIssues['variation_issue'] ?? false,
                'incorrect_product_added' => $targetIssues['incorrect_product_added'] ?? false,
                'target_negative_kw_issue' => $targetIssues['target_negative_kw_issue'] ?? false,
                'target_review_issue' => $targetIssues['target_review_issue'] ?? false,
                'target_cvr_issue' => $targetIssues['target_cvr_issue'] ?? false,
                'content_check' => $targetIssues['content_check'] ?? false,
                'price_justification_check' => $targetIssues['price_justification_check'] ?? false,
                'ad_not_req' => $targetIssues['ad_not_req'] ?? false,
                'review_issue' => $targetIssues['review_issue'] ?? false,
                'issue_found' => $issueFound,
                'action_taken' => $actionTaken,
                ];
            }
        }
        
        // Add pink_dil_paused_at to second loop result (PT/KW)
        // This was already added above at line 3211, just ensuring it's there

        // Final check for HL campaigns - ensure ALL parent SKUs are present (after all processing)
        if ($campaignType === 'HL') {
            // Rebuild existing SKUs map from current result (with proper normalization)
            $finalExistingSkusMap = [];
            foreach ($result as $item) {
                if (empty($item->sku)) {
                    continue;
                }
                // Apply same normalization
                $normalizedSku = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->sku);
                $normalizedSku = preg_replace('/\s+/', ' ', $normalizedSku);
                $existingSku = strtoupper(trim($normalizedSku));
                $finalExistingSkusMap[$existingSku] = true;
            }
            
            // Get ALL parent SKUs directly from database one more time
            $allParentSkusFinal = ProductMaster::whereNull('deleted_at')
                ->where('sku', 'LIKE', 'PARENT %')
                ->get();
            
            foreach ($allParentSkusFinal as $pm) {
                // Normalize SKU
                $normalizedSku = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku);
                $normalizedSku = preg_replace('/\s+/', ' ', $normalizedSku);
                $sku = strtoupper(trim($normalizedSku));
                
                // Skip if SKU already exists in result
                if (isset($finalExistingSkusMap[$sku])) {
                    continue;
                }
                
                // This parent SKU is still missing - add it with default values (HL Final check)
                $baseSku = strtoupper(trim($pm->sku));
                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$pm->sku] ?? null;
                
                // Get NRA, TPFT, PFT, ROI, NRL, SPRICE, SGPFT, SROI from AmazonDataView
                $nra = '';
                $tpft = null;
                $pft = null;
                $roi = null;
                $nrl = 'REQ'; // Default value
                $sprice = null;
                $sgpft = null;
                $sroi = null;
                $spriceStatus = null;
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $nra = $raw['NRA'] ?? '';
                        $tpft = $raw['TPFT'] ?? null;
                        $pft = $raw['PFT'] ?? null;
                        $roi = $raw['ROI'] ?? null;
                        $nrl = $raw['NRL'] ?? 'REQ';
                        $sprice = $raw['SPRICE'] ?? null;
                        $sgpft = $raw['SGPFT'] ?? null;
                        $sroi = $raw['SROI'] ?? null;
                        $spriceStatus = $raw['SPRICE_STATUS'] ?? null;
                    }
                }
                
                // Get LP and Ship from ProductMaster
                $values = $pm->Values ?: [];
                $lp = $values['lp'] ?? 0;
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);
                
                $price = ($amazonSheet && isset($amazonSheet->price)) ? $amazonSheet->price : 0;
                
                // Calculate AD% (ad spend percentage) - use ALL campaign types (KW + PT + HL) like OverallAmazonController
                $adPercent = 0;
                $unitsL30 = ($amazonSheet && isset($amazonSheet->units_ordered_l30)) ? (int)$amazonSheet->units_ordered_l30 : 0;
                $totalRevenue = $price * $unitsL30;
                
                if ($totalRevenue > 0) {
                    // Get KW campaign spend (exact SKU match, excluding PT)
                    $kwSpend = 0;
                    $kwCampaign = $allSpCampaignReportsL30->first(function ($item) use ($sku) {
                        $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                        $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                        $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                        $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                        // Exclude PT campaigns
                        if (stripos($campaignName, ' PT') !== false || stripos($campaignName, 'PT.') !== false) {
                            return false;
                        }
                        return $campaignName === $cleanSku;
                    });
                    if ($kwCampaign) {
                        $kwSpend = $kwCampaign->spend ?? 0;
                    }
                    
                    // Get PT campaign spend (ends with PT or PT.)
                    $ptSpend = 0;
                    $ptCampaign = $allSpCampaignReportsL30->first(function ($item) use ($sku) {
                        $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                        $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                        $cleanName = strtoupper(trim($campaignName));
                        return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.' || 
                                $cleanName === $sku . 'PT' || $cleanName === $sku . 'PT.');
                    });
                    if ($ptCampaign) {
                        $ptSpend = $ptCampaign->spend ?? 0;
                    }
                    // If no KW/PT match by SKU and this is a CHILD row, try PARENT SKU for AD%
                    if ((!$kwCampaign || !$ptCampaign) && stripos($sku, 'PARENT') === false && $parent !== '' && $parent !== null) {
                        $parentNormAd = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $parent ?? ''))));
                        if (!$kwCampaign && $parentNormAd !== '') {
                            $kwCampaign = $allSpCampaignReportsL30->first(function ($item) use ($parentNormAd) {
                                $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                                $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                                $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                                if (stripos($campaignName, ' PT') !== false || stripos($campaignName, 'PT.') !== false) {
                                    return false;
                                }
                                return $campaignName === $parentNormAd;
                            });
                            if ($kwCampaign) {
                                $kwSpend = $kwCampaign->spend ?? 0;
                            }
                        }
                        if (!$ptCampaign && $parentNormAd !== '') {
                            $ptCampaign = $allSpCampaignReportsL30->first(function ($item) use ($parentNormAd) {
                                $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName ?? '');
                                $cn = preg_replace('/\s+/', ' ', $cn);
                                $clean = strtoupper(trim($cn));
                                return $clean === $parentNormAd . ' PT' || $clean === $parentNormAd . ' PT.' || $clean === $parentNormAd . 'PT' || $clean === $parentNormAd . 'PT.';
                            });
                            if ($ptCampaign) {
                                $ptSpend = $ptCampaign->spend ?? 0;
                            }
                        }
                    }
                    
                    // Get HL campaign spend (SKU or SKU HEAD)
                    $hlSpend = 0;
                    if (isset($amazonHlL30)) {
                        $hlCampaign = $amazonHlL30->first(function ($item) use ($sku) {
                            $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                            $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                            $cleanName = strtoupper(trim($campaignName));
                            return ($cleanName === $sku || $cleanName === $sku . ' HEAD') && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
                        });
                        if ($hlCampaign) {
                            $hlSpend = $hlCampaign->cost ?? 0;
                        }
                    }
                    
                    // Total AD spend = KW + PT + HL
                    $totalAdSpend = $kwSpend + $ptSpend + $hlSpend;
                    $adPercent = round($totalAdSpend / $totalRevenue * 100, 4);
                }
                
                // Calculate GPFT% = ((price × 0.80 - ship - lp) / price) × 100
                $gpft = $price > 0
                    ? round((($price * 0.80 - $ship - $lp) / $price) * 100, 2)
                    : 0;
                
                // Calculate SPRICE, SGPFT, SROI if not set
                $calculatedSprice = $sprice;
                $calculatedSgpft = $sgpft;
                $calculatedSroi = $sroi;
                $hasCustomSprice = !empty($sprice);
                
                if (empty($calculatedSprice) && $price > 0) {
                    $calculatedSprice = $price;
                    $hasCustomSprice = false;
                    
                    // Calculate SGPFT based on default price (using 0.80 for Amazon)
                    $calculatedSgpft = round(
                        $price > 0 ? (($price * 0.80 - $ship - $lp) / $price) * 100 : 0,
                        2
                    );
                    
                    // Calculate SROI = ((SPRICE * (0.80 - AD%/100) - ship - lp) / lp) * 100
                    $adDecimal = $adPercent / 100;
                    $calculatedSroi = round(
                        $lp > 0 ? (($price * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
                        2
                    );
                } else if (!empty($calculatedSprice)) {
                    $hasCustomSprice = true;
                    
                    // Calculate SGPFT using custom SPRICE if not already set (using 0.80 for Amazon)
                    if (empty($calculatedSgpft)) {
                        $spriceFloat = floatval($calculatedSprice);
                        $calculatedSgpft = round(
                            $spriceFloat > 0 ? (($spriceFloat * 0.80 - $ship - $lp) / $spriceFloat) * 100 : 0,
                            2
                        );
                    }
                    
                    // Always recalculate SROI from SPRICE and current AD% (AD% might have changed)
                    $spriceFloat = floatval($calculatedSprice);
                    $adDecimal = $adPercent / 100;
                    $calculatedSroi = round(
                        $lp > 0 ? (($spriceFloat * (0.80 - $adDecimal) - $ship - $lp) / $lp) * 100 : 0,
                        2
                    );
                }
                
                // Always recalculate SPFT = SGPFT - AD% (AD% might have changed)
                $spft = !empty($calculatedSgpft) ? round($calculatedSgpft - $adPercent, 2) : 0;
                
                // Check for campaign
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                
                $hasCampaign = !empty($matchedCampaignL30) || !empty($matchedCampaignL7) || !empty($matchedCampaignL1);
                $campaignId = ($matchedCampaignL30 ? $matchedCampaignL30->campaign_id : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaign_id : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaign_id : null) ?? ''));
                $campaignName = ($matchedCampaignL30 ? $matchedCampaignL30->campaignName : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignName : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignName : null) ?? ''));
                
                // Calculate spend and CPC
                $l7Spend = 0;
                $l7Cpc = 0;
                $l1Spend = 0;
                $l1Cpc = 0;
                $acosL30 = 0;
                $acosL15 = 0;
                $acosL7 = 0;
                $acos = 0;
                
                if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                    $l7Spend = $matchedCampaignL7->cost ?? 0;
                    $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                        ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                        : 0;
                    $l7Cpc = $costPerClick7;
                }
                
                if (isset($matchedCampaignL1) && $matchedCampaignL1) {
                    $l1Spend = $matchedCampaignL1->cost ?? 0;
                    $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                        ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                        : 0;
                    $l1Cpc = $costPerClick1;
                }
                
                if (isset($matchedCampaignL30) && $matchedCampaignL30) {
                    $sales30 = $matchedCampaignL30->sales30d ?? 0;
                    $spend30 = $matchedCampaignL30->cost ?? 0;
                    if ($spend30 > 0 && $sales30 > 0) {
                        $acosL30 = round(($spend30 / $sales30) * 100, 2);
                    } elseif ($spend30 > 0 && $sales30 == 0) {
                        $acosL30 = 100;
                    } else {
                        $acosL30 = 0;
                    }
                    $acos = $acosL30;
                }
                
                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                    $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                    $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                    $cleanName = strtoupper(trim($campaignName));
                    return ($cleanName === $sku || $cleanName === $sku . ' HEAD');
                });
                
                if (isset($matchedCampaignL15) && isset($matchedCampaignL1) && $matchedCampaignL15 && $matchedCampaignL1) {
                    $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                    $spend15 = $matchedCampaignL15->cost ?? 0;
                    if ($sales15 > 0) {
                        $acosL15 = round(($spend15 / $sales15) * 100, 2);
                    } elseif ($spend15 > 0) {
                        $acosL15 = 100;
                    }
                }
                
                if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                    $sales7 = $matchedCampaignL7->sales7d ?? 0;
                    $spend7 = $matchedCampaignL7->cost ?? 0;
                    if ($sales7 > 0) {
                        $acosL7 = round(($spend7 / $sales7) * 100, 2);
                    } elseif ($spend7 > 0) {
                        $acosL7 = 100;
                    }
                }
                
                // Get ACOS history for this row - normalize SKU for matching
                $normalizedSku = $pm->sku ? strtoupper(trim($pm->sku)) : '';
                $normalizedCampaignId = $campaignId ? trim($campaignId) : '';
                
                // Try multiple matching strategies
                $historyRecord = null;
                
                // Strategy 1: Match by campaign_id + SKU (most specific)
                if ($normalizedCampaignId && $normalizedSku) {
                    $key1 = $normalizedCampaignId . '|' . $normalizedSku;
                    if (isset($acosHistoryMap[$key1])) {
                        $historyRecord = $acosHistoryMap[$key1];
                    }
                }
                
                // Strategy 2: Match by SKU only (fallback)
                if (!$historyRecord && $normalizedSku) {
                    $key2 = '|' . $normalizedSku;
                    if (isset($acosHistoryMap[$key2])) {
                        $historyRecord = $acosHistoryMap[$key2];
                    }
                }
                
                // Strategy 3: Match by campaign_id only (fallback)
                if (!$historyRecord && $normalizedCampaignId) {
                    $key3 = $normalizedCampaignId . '|';
                    if (isset($acosHistoryMap[$key3])) {
                        $historyRecord = $acosHistoryMap[$key3];
                    }
                }
                
                // Strategy 4: Loop through all records and match by SKU (case-insensitive)
                if (!$historyRecord && $normalizedSku) {
                    foreach ($acosHistoryMap as $key => $record) {
                        if (isset($record->sku)) {
                            $recordSku = strtoupper(trim($record->sku));
                            if ($recordSku === $normalizedSku) {
                                $historyRecord = $record;
                                break;
                            }
                        }
                    }
                }
                
                $targetIssues = [];
                $issueFound = '';
                $actionTaken = '';
                
                if ($historyRecord) {
                    // Get target issues
                    if (isset($historyRecord->target_issues) && $historyRecord->target_issues) {
                        $decoded = json_decode($historyRecord->target_issues, true);
                        $targetIssues = is_array($decoded) ? $decoded : [];
                    }
                    // Get issue found and action taken
                    $issueFound = $historyRecord->issue_found ?? '';
                    $actionTaken = $historyRecord->action_taken ?? '';
                }
                
                $result[] = (object) [
                    'parent' => $pm->parent,
                    'sku' => $pm->sku,
                    'is_parent' => true,
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'campaignBudgetAmount' => ($matchedCampaignL30 ? $matchedCampaignL30->campaignBudgetAmount : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignBudgetAmount : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignBudgetAmount : null) ?? 0)),
                    'campaignStatus' => ($matchedCampaignL30 ? $matchedCampaignL30->campaignStatus : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->campaignStatus : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->campaignStatus : null) ?? '')),
                    'pink_dil_paused_at' => ($matchedCampaignL30 ? $matchedCampaignL30->pink_dil_paused_at : null) ?? (($matchedCampaignL7 ? $matchedCampaignL7->pink_dil_paused_at : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->pink_dil_paused_at : null) ?? null)),
                    'INV' => (int)($childInvSumByParent[$pm->parent ?? ''] ?? 0),
                    'FBA_INV' => isset($fbaData[$baseSku]) ? ($fbaData[$baseSku]->quantity_available ?? 0) : 0,
                    'L30' => (int)($childL30SumByParent[$pm->parent ?? ''] ?? 0),
                    'A_L30' => (int)($childAL30SumByParent[$pm->parent ?? ''] ?? 0),
                    'l7_spend' => $l7Spend,
                    'l7_cpc' => $l7Cpc,
                    'l1_spend' => $l1Spend,
                    'l1_cpc' => $l1Cpc,
                    'avg_cpc' => $avgCpcData->get($campaignId, 0),
                    'acos' => $acos,
                    'acos_L30' => $acosL30,
                    'acos_L15' => $acosL15,
                    'acos_L7' => $acosL7,
                    'NRA' => $nra,
                    'TPFT' => $tpft,
                    'PFT' => $pft,
                    'roi' => $roi,
                    'NRL' => $nrl,
                    'hasCampaign' => $hasCampaign,
                    'GPFT' => $gpft,
                    'SPRICE' => $calculatedSprice,
                    'SGPFT' => $calculatedSgpft,
                    'Spft%' => $spft,
                    'SPFT' => $spft, // Keep both for backward compatibility
                    'SROI' => $calculatedSroi,
                    'has_custom_sprice' => $hasCustomSprice,
                    'SPRICE_STATUS' => $spriceStatus,
                    // ACOS Action History fields
                    'target_kw_issue' => $targetIssues['target_kw_issue'] ?? false,
                    'target_pt_issue' => $targetIssues['target_pt_issue'] ?? false,
                    'variation_issue' => $targetIssues['variation_issue'] ?? false,
                    'incorrect_product_added' => $targetIssues['incorrect_product_added'] ?? false,
                    'target_negative_kw_issue' => $targetIssues['target_negative_kw_issue'] ?? false,
                    'target_review_issue' => $targetIssues['target_review_issue'] ?? false,
                    'target_cvr_issue' => $targetIssues['target_cvr_issue'] ?? false,
                    'content_check' => $targetIssues['content_check'] ?? false,
                    'price_justification_check' => $targetIssues['price_justification_check'] ?? false,
                    'ad_not_req' => $targetIssues['ad_not_req'] ?? false,
                    'review_issue' => $targetIssues['review_issue'] ?? false,
                    'issue_found' => $issueFound,
                    'action_taken' => $actionTaken,
                ];
            }
        }

        // For HL campaigns, add unmatched campaigns (similar to KW)
        if ($campaignType === 'HL') {
            $matchedCampaignIds = array_unique(array_filter(array_column($result, 'campaign_id')));
            
            // Get ALL HL campaigns (not filtered by SKU) to find truly unmatched ones
            $allHLCampaignsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L30')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
                
            $allHLCampaignsL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L7')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
                
            $allHLCampaignsL1 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L1')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
                
            $allHLCampaignsL15 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L15')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
            
            $allUniqueCampaigns = $allHLCampaignsL30->unique('campaign_id')
                ->merge($allHLCampaignsL7->unique('campaign_id'))
                ->merge($allHLCampaignsL1->unique('campaign_id'))
                ->unique('campaign_id'); // Add unique AFTER merge to remove duplicates across periods
            
            foreach ($allUniqueCampaigns as $campaign) {
                $campaignId = $campaign->campaign_id ?? '';
                
                // Skip if already matched with a SKU or already added
                if (empty($campaignId)) {
                    continue;
                }
                
                if (in_array($campaignId, $matchedCampaignIds)) {
                    continue;
                }

                $campaignName = $campaign->campaignName ?? '';
                if (empty($campaignName)) {
                    continue;
                }

                // Normalize campaign name
                $normalizedCampaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $campaignName);
                $normalizedCampaignName = preg_replace('/\s+/', ' ', $normalizedCampaignName);
                $cleanCampaignName = strtoupper(trim($normalizedCampaignName));

                // Check if this campaign name matches any parent SKU or parent SKU + " HEAD"
                $matchedSku = null;
                foreach ($skus as $sku) {
                    $normalizedSku = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $sku);
                    $normalizedSku = preg_replace('/\s+/', ' ', $normalizedSku);
                    $cleanSku = strtoupper(trim($normalizedSku));
                    
                    if ($cleanCampaignName === $cleanSku || $cleanCampaignName === $cleanSku . ' HEAD') {
                        $matchedSku = $sku;
                        break;
                    }
                }

                // If no SKU match found, add as unmatched campaign
                if (!$matchedSku) {
                    $matchedCampaignL30 = $allHLCampaignsL30->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });

                    $matchedCampaignL7 = $allHLCampaignsL7->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });

                    $matchedCampaignL1 = $allHLCampaignsL1->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });

                    $matchedCampaignL15 = $allHLCampaignsL15->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });

                    // Extract SKU from campaign name by removing " HEAD" suffix if present
                    $extractedSku = $cleanCampaignName;
                    if (substr($cleanCampaignName, -5) === ' HEAD') {
                        $extractedSku = substr($cleanCampaignName, 0, -5);
                    }
                    
                    // Ensure it's treated as a parent SKU for frontend filtering
                    if (stripos($extractedSku, 'PARENT') === false) {
                        $extractedSku = 'PARENT ' . $extractedSku;
                    }

                    $row = [];
                    $row['parent'] = $extractedSku;
                    $row['sku'] = $extractedSku;
                    $row['campaign_id'] = $campaignId;
                    $row['campaignName'] = $campaign->campaignName ?? '';
                    $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
                    $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
                    $row['pink_dil_paused_at'] = $matchedCampaignL30 ? $matchedCampaignL30->pink_dil_paused_at : (($matchedCampaignL7 ? $matchedCampaignL7->pink_dil_paused_at : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->pink_dil_paused_at : null) ?? null));
                    $row['INV'] = 0;
                    $row['FBA_INV'] = 0;
                    $row['L30'] = 0;
                    $row['A_L30'] = 0;
                    $row['l7_spend'] = $matchedCampaignL7->cost ?? 0;
                    $row['l7_cpc'] = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0) ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks) : 0;
                    $row['l7_clicks'] = (int)($matchedCampaignL7->clicks ?? 0);
                    $row['l7_sales'] = (float)($matchedCampaignL7->sales7d ?? 0);
                    $row['l7_purchases'] = (int)($matchedCampaignL7->unitsSoldClicks7d ?? 0);
                    $row['l1_spend'] = $matchedCampaignL1->cost ?? 0;
                    $row['l1_cpc'] = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0) ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks) : 0;
                    $row['avg_cpc'] = $avgCpcData->get($campaignId, 0);
                    $row['acos'] = 0;
                    $row['acos_L30'] = 0;
                    $row['acos_L15'] = 0;
                    $row['acos_L7'] = 0;
                    $row['NRA'] = '';
                    $row['TPFT'] = null;
                    $row['hasCampaign'] = true;

                    if (isset($matchedCampaignL30) && $matchedCampaignL30) {
                        // HL (SB): use 'sales' for L30; sales30d may not be in amazon_sb_campaign_reports
                        $sales30 = (float)($matchedCampaignL30->sales ?? $matchedCampaignL30->sales30d ?? 0);
                        $spend30 = (float)($matchedCampaignL30->cost ?? 0);
                        if ($spend30 > 0 && $sales30 > 0) {
                            $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
                        } elseif ($spend30 > 0 && $sales30 == 0) {
                            $row['acos_L30'] = 100;
                        } else {
                            $row['acos_L30'] = 0;
                        }
                        $row['acos'] = $row['acos_L30'];
                        $row['l30_spend'] = $spend30;
                        $row['l30_sales'] = $sales30;
                    }

                    if (isset($matchedCampaignL15) && isset($matchedCampaignL1) && $matchedCampaignL15 && $matchedCampaignL1) {
                        $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                        $spend15 = $matchedCampaignL15->cost ?? 0;
                        if ($sales15 > 0) {
                            $row['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
                        } elseif ($spend15 > 0) {
                            $row['acos_L15'] = 100;
                        }
                    }

                    if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                        $sales7 = (float)($matchedCampaignL7->sales ?? $matchedCampaignL7->sales7d ?? 0);
                        $spend7 = (float)($matchedCampaignL7->cost ?? 0);
                        if ($sales7 > 0) {
                            $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
                        } elseif ($spend7 > 0) {
                            $row['acos_L7'] = 100;
                        }
                    }

                    // Get ACOS history for this unmatched HL campaign
                    $normalizedSku = $extractedSku ? strtoupper(trim($extractedSku)) : '';
                    $normalizedCampaignId = $campaignId ? trim($campaignId) : '';
                    
                    // Try multiple matching strategies
                    $historyRecord = null;
                    
                    // Strategy 1: Match by campaign_id + SKU (most specific)
                    if ($normalizedCampaignId && $normalizedSku) {
                        $key1 = $normalizedCampaignId . '|' . $normalizedSku;
                        if (isset($acosHistoryMap[$key1])) {
                            $historyRecord = $acosHistoryMap[$key1];
                        }
                    }
                    
                    // Strategy 2: Match by SKU only (fallback)
                    if (!$historyRecord && $normalizedSku) {
                        $key2 = '|' . $normalizedSku;
                        if (isset($acosHistoryMap[$key2])) {
                            $historyRecord = $acosHistoryMap[$key2];
                        }
                    }
                    
                    // Strategy 3: Match by campaign_id only (fallback)
                    if (!$historyRecord && $normalizedCampaignId) {
                        $key3 = $normalizedCampaignId . '|';
                        if (isset($acosHistoryMap[$key3])) {
                            $historyRecord = $acosHistoryMap[$key3];
                        }
                    }
                    
                    // Strategy 4: Loop through all records and match by SKU (case-insensitive)
                    if (!$historyRecord && $normalizedSku) {
                        foreach ($acosHistoryMap as $key => $record) {
                            if (isset($record->sku)) {
                                $recordSku = strtoupper(trim($record->sku));
                                if ($recordSku === $normalizedSku) {
                                    $historyRecord = $record;
                                    break;
                                }
                            }
                        }
                    }
                    
                    $targetIssues = [];
                    $issueFound = '';
                    $actionTaken = '';
                    
                    if ($historyRecord) {
                        // Get target issues
                        if (isset($historyRecord->target_issues) && $historyRecord->target_issues) {
                            $decoded = json_decode($historyRecord->target_issues, true);
                            $targetIssues = is_array($decoded) ? $decoded : [];
                        }
                        // Get issue found and action taken
                        $issueFound = $historyRecord->issue_found ?? '';
                        $actionTaken = $historyRecord->action_taken ?? '';
                    }
                    
                    // Add ACOS Action History fields to row
                    $row['target_kw_issue'] = $targetIssues['target_kw_issue'] ?? false;
                    $row['target_pt_issue'] = $targetIssues['target_pt_issue'] ?? false;
                    $row['variation_issue'] = $targetIssues['variation_issue'] ?? false;
                    $row['incorrect_product_added'] = $targetIssues['incorrect_product_added'] ?? false;
                    $row['target_negative_kw_issue'] = $targetIssues['target_negative_kw_issue'] ?? false;
                    $row['target_review_issue'] = $targetIssues['target_review_issue'] ?? false;
                    $row['target_cvr_issue'] = $targetIssues['target_cvr_issue'] ?? false;
                    $row['content_check'] = $targetIssues['content_check'] ?? false;
                    $row['price_justification_check'] = $targetIssues['price_justification_check'] ?? false;
                    $row['ad_not_req'] = $targetIssues['ad_not_req'] ?? false;
                    $row['review_issue'] = $targetIssues['review_issue'] ?? false;
                    $row['issue_found'] = $issueFound;
                    $row['action_taken'] = $actionTaken;

                    $result[] = (object) $row;
                    $matchedCampaignIds[] = $campaignId;
                }
            }
        }
        
        // For KW campaigns, add unmatched campaigns (similar to ACOS control)
        if ($campaignType === 'KW') {
            $matchedCampaignIds = array_unique(array_column($result, 'campaign_id'));
            // Include L30 campaigns as well to catch all paused campaigns
            $allUniqueCampaigns = $amazonSpCampaignReportsL30->unique('campaign_id')
                ->merge($amazonSpCampaignReportsL7->unique('campaign_id'))
                ->merge($amazonSpCampaignReportsL1->unique('campaign_id'))
                ->unique('campaign_id');
            
            foreach ($allUniqueCampaigns as $campaign) {
                $campaignId = $campaign->campaign_id ?? '';
                
                // Skip if already matched with a SKU or already added
                if (empty($campaignId) || in_array($campaignId, $matchedCampaignIds)) {
                    continue;
                }

                $campaignName = strtoupper(trim($campaign->campaignName ?? ''));
                if (empty($campaignName)) {
                    continue;
                }

                // Check if this campaign name exactly matches any SKU
                $matchedSku = null;
                foreach ($skus as $sku) {
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    $cleanCampaignName = strtoupper(trim(rtrim($campaignName, '.')));
                    if ($cleanCampaignName === $cleanSku) {
                        $matchedSku = $sku;
                        break;
                    }
                }

                // If no SKU match found, add as unmatched campaign (but only if it has pink_dil_paused_at to show all paused campaigns)
                if (!$matchedSku) {
                    // First check if this campaign has pink_dil_paused_at
                    $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });

                    $checkCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });
                    $checkCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });
                    
                    $hasPinkDilPaused = ($matchedCampaignL30 && $matchedCampaignL30->pink_dil_paused_at) ||
                                       ($checkCampaignL7 && $checkCampaignL7->pink_dil_paused_at) ||
                                       ($checkCampaignL1 && $checkCampaignL1->pink_dil_paused_at);
                    
                    // Only add unmatched campaigns that are paused
                    if (!$hasPinkDilPaused) {
                        continue;
                    }
                    
                    // Now get the matched campaigns for data
                    $matchedCampaignL7 = $checkCampaignL7;
                    $matchedCampaignL1 = $checkCampaignL1;

                    $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });

                    $row = [];
                    $row['parent'] = '';
                    $row['sku'] = '';
                    $row['campaign_id'] = $campaignId;
                    $row['campaignName'] = $campaign->campaignName ?? '';
                    $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
                    $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
                    $row['pink_dil_paused_at'] = $matchedCampaignL30 ? $matchedCampaignL30->pink_dil_paused_at : (($matchedCampaignL7 ? $matchedCampaignL7->pink_dil_paused_at : null) ?? (($matchedCampaignL1 ? $matchedCampaignL1->pink_dil_paused_at : null) ?? null));
                    $row['INV'] = 0;
                    $row['FBA_INV'] = 0;
                    $row['L30'] = 0;
                    $row['A_L30'] = 0;
                    $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
                    $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
                    $row['l7_clicks'] = (int)($matchedCampaignL7->clicks ?? 0);
                    $row['l7_sales'] = (float)($matchedCampaignL7->sales7d ?? 0);
                    $row['l7_purchases'] = (int)($matchedCampaignL7->unitsSoldClicks7d ?? 0);
                    $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
                    $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;
                    $row['avg_cpc'] = $avgCpcData->get($campaignId, 0);
                    $row['acos'] = 0;
                    $row['acos_L30'] = 0;
                    $row['acos_L15'] = 0;
                    $row['acos_L7'] = 0;
                    $row['l30_spend'] = 0;
                    $row['l30_sales'] = 0;
                    $row['l30_clicks'] = 0;
                    $row['ad_cvr'] = 0;
                    $row['NRA'] = '';
                    $row['TPFT'] = null;
                    $row['hasCampaign'] = true;

                    if (isset($matchedCampaignL30) && $matchedCampaignL30) {
                        // SP (KW/PT): use sales30d and spend
                        $sales30 = (float)($matchedCampaignL30->sales30d ?? 0);
                        $spend30 = (float)($matchedCampaignL30->spend ?? 0);
                        $clicks30 = (int)($matchedCampaignL30->clicks ?? 0);
                        $purchases30 = (int)($matchedCampaignL30->unitsSoldClicks30d ?? 0);
                        $unitsSold30 = $purchases30;
                        if ($spend30 > 0 && $sales30 > 0) {
                            $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
                        } elseif ($spend30 > 0 && $sales30 == 0) {
                            $row['acos_L30'] = 100;
                        } else {
                            $row['acos_L30'] = 0;
                        }
                        $row['acos'] = $row['acos_L30'];
                        $row['l30_spend'] = $spend30;
                        $row['l30_sales'] = $sales30;
                        $row['l30_clicks'] = $clicks30;
                        $row['l30_purchases'] = $unitsSold30;
                        $row['ad_cvr'] = $clicks30 > 0 ? round(($purchases30 / $clicks30) * 100, 2) : 0;
                    }

                    if (isset($matchedCampaignL15) && isset($matchedCampaignL1) && $matchedCampaignL15 && $matchedCampaignL1) {
                        $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                        $spend15 = $matchedCampaignL15->spend ?? 0;
                        if ($sales15 > 0) {
                            $row['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
                        } elseif ($spend15 > 0) {
                            $row['acos_L15'] = 100;
                        }
                    }

                    if (isset($matchedCampaignL7) && $matchedCampaignL7) {
                        $sales7 = $matchedCampaignL7->sales7d ?? 0;
                        $spend7 = $matchedCampaignL7->spend ?? 0;
                        if ($sales7 > 0) {
                            $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
                        } elseif ($spend7 > 0) {
                            $row['acos_L7'] = 100;
                        }
                    }

                    // Get ACOS history for this unmatched KW campaign
                    $normalizedSku = $row['sku'] ? strtoupper(trim($row['sku'])) : '';
                    $normalizedCampaignId = $campaignId ? trim($campaignId) : '';
                    
                    // Try multiple matching strategies
                    $historyRecord = null;
                    
                    // Strategy 1: Match by campaign_id + SKU (most specific)
                    if ($normalizedCampaignId && $normalizedSku) {
                        $key1 = $normalizedCampaignId . '|' . $normalizedSku;
                        if (isset($acosHistoryMap[$key1])) {
                            $historyRecord = $acosHistoryMap[$key1];
                        }
                    }
                    
                    // Strategy 2: Match by SKU only (fallback)
                    if (!$historyRecord && $normalizedSku) {
                        $key2 = '|' . $normalizedSku;
                        if (isset($acosHistoryMap[$key2])) {
                            $historyRecord = $acosHistoryMap[$key2];
                        }
                    }
                    
                    // Strategy 3: Match by campaign_id only (fallback)
                    if (!$historyRecord && $normalizedCampaignId) {
                        $key3 = $normalizedCampaignId . '|';
                        if (isset($acosHistoryMap[$key3])) {
                            $historyRecord = $acosHistoryMap[$key3];
                        }
                    }
                    
                    // Strategy 4: Loop through all records and match by SKU (case-insensitive)
                    if (!$historyRecord && $normalizedSku) {
                        foreach ($acosHistoryMap as $key => $record) {
                            if (isset($record->sku)) {
                                $recordSku = strtoupper(trim($record->sku));
                                if ($recordSku === $normalizedSku) {
                                    $historyRecord = $record;
                                    break;
                                }
                            }
                        }
                    }
                    
                    $targetIssues = [];
                    $issueFound = '';
                    $actionTaken = '';
                    
                    if ($historyRecord) {
                        // Get target issues
                        if (isset($historyRecord->target_issues) && $historyRecord->target_issues) {
                            $decoded = json_decode($historyRecord->target_issues, true);
                            $targetIssues = is_array($decoded) ? $decoded : [];
                        }
                        // Get issue found and action taken
                        $issueFound = $historyRecord->issue_found ?? '';
                        $actionTaken = $historyRecord->action_taken ?? '';
                    }
                    
                    // Add ACOS Action History fields to row
                    $row['target_kw_issue'] = $targetIssues['target_kw_issue'] ?? false;
                    $row['target_pt_issue'] = $targetIssues['target_pt_issue'] ?? false;
                    $row['variation_issue'] = $targetIssues['variation_issue'] ?? false;
                    $row['incorrect_product_added'] = $targetIssues['incorrect_product_added'] ?? false;
                    $row['target_negative_kw_issue'] = $targetIssues['target_negative_kw_issue'] ?? false;
                    $row['target_review_issue'] = $targetIssues['target_review_issue'] ?? false;
                    $row['target_cvr_issue'] = $targetIssues['target_cvr_issue'] ?? false;
                    $row['content_check'] = $targetIssues['content_check'] ?? false;
                    $row['price_justification_check'] = $targetIssues['price_justification_check'] ?? false;
                    $row['ad_not_req'] = $targetIssues['ad_not_req'] ?? false;
                    $row['review_issue'] = $targetIssues['review_issue'] ?? false;
                    $row['issue_found'] = $issueFound;
                    $row['action_taken'] = $actionTaken;

                    $result[] = (object) $row;
                    $matchedCampaignIds[] = $campaignId;
                }
            }
        }

        // For PT campaigns, apply unique SKU filter (same as getAmzUnderUtilizedBgtPt)
        if ($campaignType === 'PT') {
            $result = collect($result)->unique('sku')->values()->all();
            // PT: fix parent SKU price again after unique (rows added in "missing SKUs" loop have no price set)
            foreach ($result as $item) {
                $skuStr = $item->sku ?? '';
                if (stripos($skuStr, 'PARENT') === false) {
                    continue;
                }
                $currentPrice = (float)($item->price ?? 0);
                if ($currentPrice > 0) {
                    continue;
                }
                $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $skuStr))));
                $normSku = rtrim($normSku, '.');
                $normParent = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->parent ?? ''))));
                $normParent = rtrim($normParent, '.');
                $item->price = $avgPriceByParent[$normSku] ?? $avgPriceByParent[$normParent] ?? $avgPriceByParent[$item->parent ?? ''] ?? $avgPriceByParent[rtrim($item->parent ?? '', '.')] ?? 0;
                if ($item->price <= 0 && !empty($avgPriceByParentCanonical)) {
                    $canonicalSku = preg_replace('/\s+/', '', $normSku);
                    $canonicalParent = preg_replace('/\s+/', '', $normParent);
                    $item->price = $avgPriceByParentCanonical[$canonicalSku] ?? $avgPriceByParentCanonical[$canonicalParent] ?? 0;
                }
                if ($item->price <= 0) {
                    $pmForParent = $productMasters->first(function ($p) use ($skuStr) {
                        $n = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $p->sku ?? ''))));
                        $itemSkuNorm = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $skuStr))));
                        return $n === $itemSkuNorm;
                    });
                    if ($pmForParent) {
                        $vals = $pmForParent->Values;
                        if (is_string($vals)) {
                            $vals = json_decode($vals, true) ?: [];
                        } elseif (is_object($vals)) {
                            $vals = (array) $vals;
                        } else {
                            $vals = is_array($vals) ? $vals : [];
                        }
                        if (isset($vals['msrp']) && (float)$vals['msrp'] > 0) {
                            $item->price = (float)$vals['msrp'];
                        } elseif (isset($vals['map']) && (float)$vals['map'] > 0) {
                            $item->price = (float)$vals['map'];
                        }
                    }
                }
                $item->price = (float)($item->price ?? 0);
            }
        }

        // Final pass: ACOS = 0 when Spend L30 and Sales L30 are both 0 (covers all rows including unmatched campaigns)
        // Parent rows: re-apply DIRECT campaign L7/L1 from DB so 7 UB% / 1 UB% always match database (no child-sum)
        foreach ($result as $item) {
            $rowSpend = (float)($item->l30_spend ?? 0);
            $rowSales = (float)($item->l30_sales ?? 0);
            if ($rowSpend <= 0 && $rowSales <= 0) {
                $item->acos_L30 = 0;
                $item->acos = 0;
            }
            $skuStr = $item->sku ?? '';
            if (stripos($skuStr, 'PARENT') !== false && ($campaignType === 'KW' || $campaignType === 'PT')) {
                $skuNorm = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $skuStr))));
                $skuNormNoDot = rtrim($skuNorm, '.');
                // KW: normalize campaign name with rtrim('.') so "PARENT DS 01." in report matches sku "PARENT DS 01"
                $matchL7 = $amazonSpCampaignReportsL7->first(function ($r) use ($skuNorm, $skuNormNoDot, $campaignType) {
                    $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $r->campaignName ?? '');
                    $cn = preg_replace('/\s+/', ' ', $cn);
                    $clean = strtoupper(trim($cn));
                    $cleanNoDot = rtrim($clean, '.');
                    if ($campaignType === 'PT') {
                        return $clean === $skuNorm . ' PT' || $clean === $skuNorm . ' PT.' || $clean === $skuNorm . 'PT' || $clean === $skuNorm . 'PT.'
                            || $clean === $skuNormNoDot . ' PT' || $clean === $skuNormNoDot . ' PT.' || $clean === $skuNormNoDot . 'PT' || $clean === $skuNormNoDot . 'PT.';
                    }
                    return $clean === $skuNorm || $clean === $skuNormNoDot || $cleanNoDot === $skuNorm || $cleanNoDot === $skuNormNoDot;
                });
                $matchL1 = $amazonSpCampaignReportsL1->first(function ($r) use ($skuNorm, $skuNormNoDot, $campaignType) {
                    $cn = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $r->campaignName ?? '');
                    $cn = preg_replace('/\s+/', ' ', $cn);
                    $clean = strtoupper(trim($cn));
                    $cleanNoDot = rtrim($clean, '.');
                    if ($campaignType === 'PT') {
                        return $clean === $skuNorm . ' PT' || $clean === $skuNorm . ' PT.' || $clean === $skuNorm . 'PT' || $clean === $skuNorm . 'PT.'
                            || $clean === $skuNormNoDot . ' PT' || $clean === $skuNormNoDot . ' PT.' || $clean === $skuNormNoDot . 'PT' || $clean === $skuNormNoDot . 'PT.';
                    }
                    return $clean === $skuNorm || $clean === $skuNormNoDot || $cleanNoDot === $skuNorm || $cleanNoDot === $skuNormNoDot;
                });
                $l7SpendVal = $matchL7 ? (float)($matchL7->spend ?? $matchL7->cost ?? 0) : 0;
                if ($l7SpendVal <= 0 && $matchL7) {
                    $c7 = (int)($matchL7->clicks ?? 0);
                    $cpc7 = (float)($matchL7->costPerClick ?? 0);
                    if ($c7 > 0 && $cpc7 > 0) {
                        $l7SpendVal = round($c7 * $cpc7, 2);
                    }
                }
                $item->l7_spend = $l7SpendVal;
                $item->l1_spend = $matchL1 ? (float)($matchL1->spend ?? $matchL1->cost ?? 0) : 0;
                $item->utilization_budget = (float)($item->campaignBudgetAmount ?? 0);
            }
        }

        // Calculate and save SBID for yesterday's actual date records (not L1)
        // This is saved for tracking: to compare calculated SBID with what was actually updated on Amazon
        // When cron runs and new data comes, page will refresh, so we need to save SBID to database
        try {
            $this->calculateAndSaveSBID($result, $campaignType);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            FacadesLog::error('Error saving SBID: ' . $e->getMessage());
        }

        $totalPurchasesAll = 0;
        foreach ($result as $item) {
            $totalPurchasesAll += (int)($item->unitsSoldClicks30d ?? 0);
        }

        return response()->json([
            'message' => 'fetched successfully',
            'data' => $result,
            'total_l30_spend' => round($totalSpendAll, 2),
            'total_l30_sales' => round($totalSalesAll, 2),
            'total_l30_clicks' => (int)$totalClicksAll,
            'total_l30_orders' => (int)$totalOrdersAll,
            'total_l30_purchases' => (int)$totalPurchasesAll,
            'total_acos' => round($totalACOSAll, 2),
            'total_sku_count' => $totalSkuCount,
            'status' => 200,
        ]);
    }

    /**
     * Calculate SBID based on utilization type and save to database for yesterday's actual date records
     * This is saved for tracking: to compare calculated SBID with what was actually updated on Amazon
     * When cron runs and new data comes, page will refresh, so we need to save SBID to database
     * We save to yesterday's report date because that's the date for which SBID is being calculated
     * Optimized to use batch updates to avoid timeout
     */
    private function calculateAndSaveSBID($result, $campaignType)
    {
        // Save to yesterday's date because we're calculating SBID for yesterday's report data
        // Example: If today is Jan 15, cron downloaded Jan 14 report, we save SBID to Jan 14 records
        // Tomorrow (Jan 16) when checking, last_sbid will be in Jan 14 records
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Prepare batch updates
        $spUpdates = []; // For KW and PT
        $sbUpdates = []; // For HL
        
        foreach ($result as $row) {
            // Skip if no campaign_id
            if (empty($row->campaign_id)) {
                continue;
            }

            $l1Cpc = floatval($row->l1_cpc ?? 0);
            $l7Cpc = floatval($row->l7_cpc ?? 0);
            $avgCpc = floatval($row->avg_cpc ?? 0);
            $budget = floatval($row->campaignBudgetAmount ?? 0);
            $l7Spend = floatval($row->l7_spend ?? 0);
            $l1Spend = floatval($row->l1_spend ?? 0);
            $price = floatval($row->price ?? 0);

            // Calculate UB7 and UB1
            $ub7 = 0;
            $ub1 = 0;
            if ($budget > 0) {
                $ub7 = ($l7Spend / ($budget * 7)) * 100;
                $ub1 = ($l1Spend / $budget) * 100;
            }

            // Determine utilization type
            $utilizationType = 'all';
            if ($ub7 > 99 && $ub1 > 99) {
                $utilizationType = 'over';
            } elseif ($ub7 < 66 && $ub1 < 66) {
                $utilizationType = 'under';
            } elseif ($ub7 >= 66 && $ub7 <= 99 && $ub1 >= 66 && $ub1 <= 99) {
                $utilizationType = 'correctly';
            }

            // Calculate SBID
            $sbid = 0;
            
            // Special case: If UB7 and UB1 = 0%, use price-based default
            if ($ub7 == 0 && $ub1 == 0) {
                if ($price < 50) {
                    $sbid = 0.50;
                } elseif ($price >= 50 && $price < 100) {
                    $sbid = 1.00;
                } elseif ($price >= 100 && $price < 200) {
                    $sbid = 1.50;
                } else {
                    $sbid = 2.00;
                }
            } elseif ($utilizationType === 'over') {
                // Over-utilized: Priority L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
                if ($l1Cpc > 0) {
                    $sbid = floor($l1Cpc * 0.90 * 100) / 100;
                } elseif ($l7Cpc > 0) {
                    $sbid = floor($l7Cpc * 0.90 * 100) / 100;
                } elseif ($avgCpc > 0) {
                    $sbid = floor($avgCpc * 0.90 * 100) / 100;
                } else {
                    $sbid = 1.00;
                }
            } elseif ($utilizationType === 'under') {
                // Under-utilized: Priority L1 CPC → L7 CPC → AVG CPC → 1.00, then increase by 10%
                if ($l1Cpc > 0) {
                    $sbid = floor($l1Cpc * 1.10 * 100) / 100;
                } elseif ($l7Cpc > 0) {
                    $sbid = floor($l7Cpc * 1.10 * 100) / 100;
                } elseif ($avgCpc > 0) {
                    $sbid = floor($avgCpc * 1.10 * 100) / 100;
                } else {
                    $sbid = 1.00;
                }
            } else {
                // Correctly-utilized or all: no SBID change needed
                $sbid = 0;
            }

            // Apply price-based caps
            if ($price < 10 && $sbid > 0.10) {
                $sbid = 0.10;
            } elseif ($price >= 10 && $price < 20 && $sbid > 0.20) {
                $sbid = 0.20;
            }

            // Only save if SBID > 0
            if ($sbid > 0) {
                $sbidValue = (string)$sbid;
                
                if ($campaignType === 'HL') {
                    // Store for batch update
                    $sbUpdates[$row->campaign_id] = $sbidValue;
                } else {
                    // Store for batch update
                    $spUpdates[$row->campaign_id] = $sbidValue;
                }
            }
        }

        // Perform efficient bulk updates using WHERE IN
        // Update only yesterday's actual date records (not L1) for tracking purposes
        if (!empty($spUpdates) && ($campaignType === 'KW' || $campaignType === 'PT')) {
            // Update in batches of 50 to avoid query size limits
            $chunks = array_chunk($spUpdates, 50, true);
            foreach ($chunks as $chunk) {
                $campaignIds = array_keys($chunk);
                
                // Build CASE statement for bulk update
                $cases = [];
                $bindings = [];
                foreach ($chunk as $campaignId => $sbidValue) {
                    $cases[] = "WHEN ? THEN ?";
                    $bindings[] = $campaignId;
                    $bindings[] = $sbidValue;
                }
                
                $caseSql = implode(' ', $cases);
                $placeholders = str_repeat('?,', count($campaignIds) - 1) . '?';
                
                // Single query to update all records - only for yesterday's date (Y-m-d format)
                // Save to last_sbid column for tracking purposes
                // Always update with new calculated SBID value (removed NULL check to allow recalculation)
                DB::statement("
                    UPDATE amazon_sp_campaign_reports 
                    SET last_sbid = CASE campaign_id {$caseSql} END
                    WHERE campaign_id IN ({$placeholders})
                    AND report_date_range = ?
                    AND ad_type = 'SPONSORED_PRODUCTS'
                ", array_merge($bindings, $campaignIds, [$yesterday]));
            }
        }

        if (!empty($sbUpdates) && $campaignType === 'HL') {
            // Update in batches of 50 to avoid query size limits
            $chunks = array_chunk($sbUpdates, 50, true);
            foreach ($chunks as $chunk) {
                $campaignIds = array_keys($chunk);
                
                // Build CASE statement for bulk update
                $cases = [];
                $bindings = [];
                foreach ($chunk as $campaignId => $sbidValue) {
                    $cases[] = "WHEN ? THEN ?";
                    $bindings[] = $campaignId;
                    $bindings[] = $sbidValue;
                }
                
                $caseSql = implode(' ', $cases);
                $placeholders = str_repeat('?,', count($campaignIds) - 1) . '?';
                
                // Single query to update all records - only for yesterday's date (Y-m-d format)
                // Save to last_sbid column for tracking purposes
                // Always update with new calculated SBID value (removed NULL check to allow recalculation)
                DB::statement("
                    UPDATE amazon_sb_campaign_reports 
                    SET last_sbid = CASE campaign_id {$caseSql} END
                    WHERE campaign_id IN ({$placeholders})
                    AND report_date_range = ?
                    AND ad_type = 'SPONSORED_BRANDS'
                ", array_merge($bindings, $campaignIds, [$yesterday]));
            }
        }
    }

    /**
     * Save SBID M to database
     */
    public function saveAmazonSbidM(Request $request)
    {
        try {
            $campaignId = $request->input('campaign_id');
            $sbidM = $request->input('sbid_m');
            $campaignType = $request->input('campaign_type'); // KW, PT, or HL

            if (!$campaignId || !$sbidM) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Campaign ID and SBID M are required'
                ], 400);
            }

            $sbidM = floatval($sbidM);
            if ($sbidM <= 0) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M must be greater than 0'
                ], 400);
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));

            if ($campaignType === 'HL') {
                // Update SB campaigns - try yesterday first, then L1 as fallback
                // First try yesterday's date
                $updated = DB::table('amazon_sb_campaign_reports')
                    ->where('campaign_id', $campaignId)
                    ->where('report_date_range', $yesterday)
                    ->where('ad_type', 'SPONSORED_BRANDS')
                    ->update([
                        'sbid_m' => (string)$sbidM
                    ]);
                
                // If no record found for yesterday, try L1
                if ($updated === 0) {
                    $updated = DB::table('amazon_sb_campaign_reports')
                        ->where('campaign_id', $campaignId)
                        ->where('report_date_range', 'L1')
                        ->where('ad_type', 'SPONSORED_BRANDS')
                        ->update([
                            'sbid_m' => (string)$sbidM
                        ]);
                }
            } else {
                // Update SP campaigns (KW and PT) - try yesterday first, then L1 as fallback
                // First try yesterday's date
                $updated = DB::table('amazon_sp_campaign_reports')
                    ->where('campaign_id', $campaignId)
                    ->where('report_date_range', $yesterday)
                    ->where('ad_type', 'SPONSORED_PRODUCTS')
                    ->update([
                        'sbid_m' => (string)$sbidM
                    ]);
                
                // If no record found for yesterday, try L1
                if ($updated === 0) {
                    $updated = DB::table('amazon_sp_campaign_reports')
                        ->where('campaign_id', $campaignId)
                        ->where('report_date_range', 'L1')
                        ->where('ad_type', 'SPONSORED_PRODUCTS')
                        ->update([
                            'sbid_m' => (string)$sbidM
                        ]);
                }
            }

            if ($updated > 0) {
                return response()->json([
                    'status' => 200,
                    'message' => 'SBID M saved successfully',
                    'sbid_m' => $sbidM
                ]);
            } else {
                // Log for debugging
                FacadesLog::error('SBID M save failed', [
                    'campaign_id' => $campaignId,
                    'campaign_type' => $campaignType,
                    'yesterday' => $yesterday,
                    'sbid_m' => $sbidM
                ]);
                
                return response()->json([
                    'status' => 404,
                    'message' => 'Campaign not found. Please ensure the campaign exists for yesterday\'s date or L1.'
                ], 404);
            }
        } catch (\Exception $e) {
            FacadesLog::error('Error saving SBID M: ' . $e->getMessage(), [
                'campaign_id' => $request->input('campaign_id'),
                'campaign_type' => $request->input('campaign_type'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Error saving SBID M: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve SBID M and update SBID in campaign reports
     */
    public function approveAmazonSbid(Request $request)
    {
        try {
            $campaignId = $request->input('campaign_id');
            $sbidM = $request->input('sbid_m');
            $campaignType = $request->input('campaign_type'); // KW, PT, or HL

            if (!$campaignId || !$sbidM) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Campaign ID and SBID M are required'
                ], 400);
            }

            $sbidM = floatval($sbidM);
            if ($sbidM <= 0) {
                return response()->json([
                    'status' => 400,
                    'message' => 'SBID M must be greater than 0'
                ], 400);
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            // Check all possible date ranges where campaign might exist
            // Include yesterday, L1, L7, and also check if campaign exists in any date range
            $dateRanges = [$yesterday, 'L1', 'L7'];
            $updated = 0;

            if ($campaignType === 'HL') {
                // First, try to find the campaign in any of the common date ranges
                $campaignExists = DB::table('amazon_sb_campaign_reports')
                    ->where('campaign_id', $campaignId)
                    ->whereIn('report_date_range', $dateRanges)
                    ->where('ad_type', 'SPONSORED_BRANDS')
                    ->exists();
                
                if ($campaignExists) {
                    // Update all matching records in the date ranges
                    $updated = DB::table('amazon_sb_campaign_reports')
                        ->where('campaign_id', $campaignId)
                        ->whereIn('report_date_range', $dateRanges)
                        ->where('ad_type', 'SPONSORED_BRANDS')
                        ->update([
                            'sbid' => (string)$sbidM,
                            'sbid_m' => (string)$sbidM
                        ]);
                } else {
                    // If not found in common ranges, try to find in any date range
                    $updated = DB::table('amazon_sb_campaign_reports')
                        ->where('campaign_id', $campaignId)
                        ->where('ad_type', 'SPONSORED_BRANDS')
                        ->where('campaignStatus', '!=', 'ARCHIVED')
                        ->update([
                            'sbid' => (string)$sbidM,
                            'sbid_m' => (string)$sbidM
                        ]);
                }
            } else {
                // First, try to find the campaign in any of the common date ranges
                $campaignExists = DB::table('amazon_sp_campaign_reports')
                    ->where('campaign_id', $campaignId)
                    ->whereIn('report_date_range', $dateRanges)
                    ->where('ad_type', 'SPONSORED_PRODUCTS')
                    ->exists();
                
                if ($campaignExists) {
                    // Update all matching records in the date ranges
                    $updated = DB::table('amazon_sp_campaign_reports')
                        ->where('campaign_id', $campaignId)
                        ->whereIn('report_date_range', $dateRanges)
                        ->where('ad_type', 'SPONSORED_PRODUCTS')
                        ->update([
                            'sbid' => (string)$sbidM,
                            'sbid_m' => (string)$sbidM
                        ]);
                } else {
                    // If not found in common ranges, try to find in any date range
                    $updated = DB::table('amazon_sp_campaign_reports')
                        ->where('campaign_id', $campaignId)
                        ->where('ad_type', 'SPONSORED_PRODUCTS')
                        ->where('campaignStatus', '!=', 'ARCHIVED')
                        ->update([
                            'sbid' => (string)$sbidM,
                            'sbid_m' => (string)$sbidM
                        ]);
                }
            }

            if ($updated > 0) {
                // Update bids on Amazon Ads site using API
                try {
                    $apiUpdateResult = $this->updateCampaignBidsOnAmazon($campaignId, $sbidM, $campaignType);
                    
                    return response()->json([
                        'status' => 200,
                        'message' => 'SBID approved and updated successfully on Amazon',
                        'sbid' => $sbidM,
                        'api_result' => $apiUpdateResult
                    ]);
                } catch (\Exception $e) {
                    // Database update succeeded but API update failed
                    FacadesLog::error('SBID approved in DB but API update failed: ' . $e->getMessage(), [
                        'campaign_id' => $campaignId,
                        'campaign_type' => $campaignType,
                        'sbid_m' => $sbidM
                    ]);
                    
                    return response()->json([
                        'status' => 200,
                        'message' => 'SBID approved in database, but Amazon API update failed: ' . $e->getMessage(),
                        'sbid' => $sbidM,
                        'warning' => true
                    ]);
                }
            } else {
                // Log for debugging
                FacadesLog::error('SBID approve failed', [
                    'campaign_id' => $campaignId,
                    'campaign_type' => $campaignType,
                    'yesterday' => $yesterday,
                    'sbid_m' => $sbidM
                ]);
                
                return response()->json([
                    'status' => 404,
                    'message' => 'Campaign not found. Please ensure the campaign exists for yesterday\'s date or L1.'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error approving SBID: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update campaign bids on Amazon Ads site using API
     */
    private function updateCampaignBidsOnAmazon($campaignId, $newBid, $campaignType)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $allKeywords = [];

        // Get ad groups for the campaign
        if ($campaignType === 'HL') {
            // For SB campaigns (HL), use different method
            $adGroups = $this->getSbAdGroupsByCampaigns([$campaignId]);
        } else {
            // For SP campaigns (KW and PT)
            $adGroups = $this->getAdGroupsByCampaigns([$campaignId]);
        }
        
        if (empty($adGroups)) {
            throw new \Exception('No ad groups found for campaign');
        }

        // Get keywords for each ad group
        foreach ($adGroups as $adGroup) {
            if ($campaignType === 'HL') {
                // For SB campaigns (HL), use different method
                $keywords = $this->getSbKeywordsByAdGroup($adGroup['adGroupId']);
            } else {
                // For SP campaigns (KW and PT)
                $keywords = $this->getKeywordsByAdGroup($adGroup['adGroupId']);
            }
            
            foreach ($keywords as $kw) {
                if ($campaignType === 'HL') {
                    // For SB campaigns (HL)
                    $allKeywords[] = [
                        'keywordId' => $kw['keywordId'],
                        'campaignId' => $campaignId,
                        'adGroupId' => $adGroup['adGroupId'],
                        'bid' => $newBid,
                        'state' => $kw['state'] ?? 'enabled'
                    ];
                } else {
                    // For SP campaigns (KW and PT)
                    $allKeywords[] = [
                        'keywordId' => $kw['keywordId'],
                        'bid' => $newBid,
                    ];
                }
            }
        }

        if (empty($allKeywords)) {
            throw new \Exception('No keywords found to update');
        }

        // Remove duplicates
        $allKeywords = collect($allKeywords)
            ->unique('keywordId')
            ->values()
            ->toArray();

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $results = [];

        try {
            if ($campaignType === 'HL') {
                // Use SB keywords endpoint for HL campaigns
                $url = 'https://advertising-api.amazon.com/sb/keywords';
                $chunks = array_chunk($allKeywords, 100);
                foreach ($chunks as $chunk) {
                    $response = $client->put($url, [
                        'headers' => [
                            'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Amazon-Advertising-API-Scope' => $this->profileId,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => $chunk,
                        'timeout' => 60,
                        'connect_timeout' => 30,
                    ]);

                    $results[] = json_decode($response->getBody(), true);
                }
            } else {
                // Use SP keywords endpoint for KW and PT campaigns
                $url = 'https://advertising-api.amazon.com/sp/keywords';
                $chunks = array_chunk($allKeywords, 100);
                foreach ($chunks as $chunk) {
                    $response = $client->put($url, [
                        'headers' => [
                            'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Amazon-Advertising-API-Scope' => $this->profileId,
                            'Content-Type' => 'application/vnd.spKeyword.v3+json',
                            'Accept' => 'application/vnd.spKeyword.v3+json',
                        ],
                        'json' => [
                            'keywords' => $chunk
                        ],
                        'timeout' => 60,
                        'connect_timeout' => 30,
                    ]);

                    $results[] = json_decode($response->getBody(), true);
                }
            }

            return $results;
        } catch (\Exception $e) {
            throw new \Exception('Amazon API error: ' . $e->getMessage());
        }
    }

    /**
     * Save ACOS Action History
     */
    public function saveAcosActionHistory(Request $request)
    {
        try {
            $campaignId = $request->input('campaign_id');
            $sku = $request->input('sku');
            $issueFound = $request->input('issue_found', '');
            $actionTaken = $request->input('action_taken', '');
            $targetIssues = $request->input('target_issues', '{}');
            $campaignType = $request->input('campaign_type', 'KW');

            if (!$campaignId && !$sku) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Campaign ID or SKU is required'
                ], 400);
            }

            // Save to database
            DB::table('amazon_acos_action_history')->insert([
                'campaign_id' => $campaignId,
                'sku' => $sku,
                'issue_found' => $issueFound,
                'action_taken' => $actionTaken,
                'target_issues' => $targetIssues,
                'campaign_type' => $campaignType,
                'user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'ACOS action history saved successfully'
            ]);
        } catch (\Exception $e) {
            FacadesLog::error('Error saving ACOS action history: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error saving history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ACOS Action History
     */
    public function getAcosActionHistory(Request $request)
    {
        try {
            $campaignId = $request->input('campaign_id');
            $sku = $request->input('sku');
            $campaignType = $request->input('campaign_type', 'KW');

            $query = DB::table('amazon_acos_action_history')
                ->where('campaign_type', $campaignType)
                ->orderBy('created_at', 'desc');

            if ($campaignId) {
                $query->where('campaign_id', $campaignId);
            }
            if ($sku) {
                // Case-insensitive SKU matching
                $query->where(DB::raw('UPPER(sku)'), strtoupper(trim($sku)));
            }

            $history = $query->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'campaign_id' => $item->campaign_id,
                    'sku' => $item->sku,
                    'issue_found' => $item->issue_found,
                    'action_taken' => $item->action_taken,
                    'target_issues' => $item->target_issues,
                    'created_at' => $item->created_at,
                    'user_id' => $item->user_id
                ];
            });

            return response()->json([
                'status' => 200,
                'history' => $history
            ]);
        } catch (\Exception $e) {
            FacadesLog::error('Error getting ACOS action history: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error getting history: ' . $e->getMessage()
            ], 500);
        }
    }


}
