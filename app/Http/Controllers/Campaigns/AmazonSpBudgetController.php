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
use AWS\CRT\Log;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
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
                    'refresh_token' => env('AMAZON_ADS_REFRESH_TOKEN'),
                    'client_id' => env('AMAZON_ADS_CLIENT_ID'),
                    'client_secret' => env('AMAZON_ADS_CLIENT_SECRET'),
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
                'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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
                'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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
                'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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
                
                if ($record) {
                    $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
                    return response()->json([
                        'over_utilized' => $value['over_utilized'] ?? 0,
                        'under_utilized' => $totalCount, // Return total campaigns to match table
                        'correctly_utilized' => $value['correctly_utilized'] ?? 0,
                        'status' => 200,
                    ]);
                } else {
                    // If no record, still return the total count
                    return response()->json([
                        'over_utilized' => 0,
                        'under_utilized' => $totalCount, // Return total campaigns to match table
                        'correctly_utilized' => 0,
                        'status' => 200,
                    ]);
                }
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
                
                // Always return the table count, regardless of stored data
                if ($record) {
                    $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
                    return response()->json([
                        'over_utilized' => $value['over_utilized'] ?? 0,
                        'under_utilized' => $totalCount, // Return total campaigns to match table (26)
                        'correctly_utilized' => $value['correctly_utilized'] ?? 0,
                        'status' => 200,
                    ]);
                } else {
                    // If no record, still return the total count from table
                    return response()->json([
                        'over_utilized' => 0,
                        'under_utilized' => $totalCount, // Return total campaigns to match table (26)
                        'correctly_utilized' => 0,
                        'status' => 200,
                    ]);
                }
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
        
        if ($record) {
            $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
            return response()->json([
                'over_utilized' => ($campaignType === 'KW') ? $overCountFromTable : ($value['over_utilized'] ?? 0), // Use table count for KW
                'under_utilized' => ($campaignType === 'PT') ? $underCountFromTable : ($value['under_utilized'] ?? 0), // Use table count for PT
                'correctly_utilized' => $value['correctly_utilized'] ?? 0,
                'status' => 200,
            ]);
        }

        // If no data for today, calculate from current data
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Get NRA values to filter out NRA campaigns (same as in getAmzUnderUtilizedBgtKw)
        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

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
                      ->orWhere('campaignName', 'LIKE', '% PT.');
                });
                $amazonSpCampaignReportsL1->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                      ->orWhere('campaignName', 'LIKE', '% PT.');
                });
            } else {
                $amazonSpCampaignReportsL7->where('campaignName', 'NOT LIKE', '%PT')
                                          ->where('campaignName', 'NOT LIKE', '%PT.');
                $amazonSpCampaignReportsL1->where('campaignName', 'NOT LIKE', '%PT')
                                          ->where('campaignName', 'NOT LIKE', '%PT.');
            }

            $amazonSpCampaignReportsL7 = $amazonSpCampaignReportsL7->get();
            $amazonSpCampaignReportsL1 = $amazonSpCampaignReportsL1->get();
        }

        $overUtilizedCount = 0;
        $underUtilizedCount = 0;
        $correctlyUtilizedCount = 0;
        
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

            // Categorize based on utilization
            if ($ub7 > 90 && $ub1 > 90) {
                $overUtilizedCount++;
            } elseif ($ub7 < 70) {
                $underUtilizedCount++;
            } elseif ($ub7 >= 70 && $ub7 <= 90) {
                $correctlyUtilizedCount++;
            }
        }

        // If this is an "under-utilized" page, return total campaigns count to match table
        if ($pageType === 'under') {
            $totalCount = $overUtilizedCount + $underUtilizedCount + $correctlyUtilizedCount;
            return response()->json([
                'over_utilized' => $overUtilizedCount,
                'under_utilized' => $totalCount, // Return total campaigns to match table
                'correctly_utilized' => $correctlyUtilizedCount,
                'status' => 200,
            ]);
        }
        
        return response()->json([
            'over_utilized' => $overUtilizedCount,
            'under_utilized' => $underUtilizedCount,
            'correctly_utilized' => $correctlyUtilizedCount,
            'status' => 200,
        ]);
        } catch (\Exception $e) {
            FacadesLog::error('Error in getAmazonUtilizationCounts: ' . $e->getMessage());
            return response()->json([
                'over_utilized' => 0,
                'under_utilized' => 0,
                'correctly_utilized' => 0,
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
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
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
        
        $data = $data->map(function ($item) use ($campaignType) {
                $value = is_array($item->value) ? $item->value : json_decode($item->value, true);
                
                // Handle both old format (AMAZON_UTILIZATION_YYYY-MM-DD) and new format (AMAZON_UTILIZATION_TYPE_YYYY-MM-DD)
                $date = str_replace('AMAZON_UTILIZATION_' . $campaignType . '_', '', $item->sku);
                $date = str_replace('AMAZON_UTILIZATION_', '', $date);
                
                return [
                    'date' => $date,
                    'over_utilized' => $value['over_utilized'] ?? 0,
                    'under_utilized' => $value['under_utilized'] ?? 0,
                    'correctly_utilized' => $value['correctly_utilized'] ?? 0,
                ];
            })
            ->reverse()
            ->values();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $data,
            'status' => 200,
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
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L15')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
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
            if ($sales30 > 0) {
                $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
            } elseif ($spend30 > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            // ACOS L15
            if ($sales15 > 0) {
                $row['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
            } elseif ($spend15 > 0) {
                $row['acos_L15'] = 100;
            } else {
                $row['acos_L15'] = 0;
            }

            // ACOS L7
            if ($sales7 > 0) {
                $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
            } elseif ($spend7 > 0) {
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
            if ($sales30 > 0) {
                $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
            } elseif ($spend30 > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            // ACOS L15
            if ($sales15 > 0) {
                $row['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
            } elseif ($spend15 > 0) {
                $row['acos_L15'] = 100;
            } else {
                $row['acos_L15'] = 0;
            }

            // ACOS L7
            if ($sales7 > 0) {
                $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
            } elseif ($spend7 > 0) {
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

        $amazonDataView = AmazonDataView::where('sku', $sku)->first();

        $jsonData = $amazonDataView && $amazonDataView->value ? $amazonDataView->value : [];

        $jsonData[$field] = $value;

        $amazonDataView = AmazonDataView::updateOrCreate(
            ['sku' => $sku],
            ['value' => $jsonData]
        );

        return response()->json([
            'status' => 200,
            'message' => "...",
            'updated_json' => $jsonData
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

    public function getAmazonUtilizedAdsData(Request $request)
    {
        $campaignType = $request->get('type', 'KW'); // KW, PT, or HL
        
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

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
                $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L30')
                    ->where('campaignName', 'NOT LIKE', '%PT')
                    ->where('campaignName', 'NOT LIKE', '%PT.')
                    ->where('campaignName', 'NOT LIKE', '%FBA')
                    ->where('campaignName', 'NOT LIKE', '%FBA.')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
            } else {
                $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L30')
                    ->where(function ($q) use ($skus) {
                        foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    });
                
                if ($campaignType === 'PT') {
                    $amazonSpCampaignReportsL30->where(function($q) {
                        $q->where('campaignName', 'LIKE', '% PT')
                          ->orWhere('campaignName', 'LIKE', '% PT.');
                    });
                } else {
                    $amazonSpCampaignReportsL30->where('campaignName', 'NOT LIKE', '%PT')
                                              ->where('campaignName', 'NOT LIKE', '%PT.');
                }
                
                $amazonSpCampaignReportsL30 = $amazonSpCampaignReportsL30->where('campaignStatus', '!=', 'ARCHIVED')->get();
            }
        }

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
                $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L15')
                    ->where('campaignName', 'NOT LIKE', '%PT')
                    ->where('campaignName', 'NOT LIKE', '%PT.')
                    ->where('campaignName', 'NOT LIKE', '%FBA')
                    ->where('campaignName', 'NOT LIKE', '%FBA.')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
                
                $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L7')
                    ->where('campaignName', 'NOT LIKE', '%PT')
                    ->where('campaignName', 'NOT LIKE', '%PT.')
                    ->where('campaignName', 'NOT LIKE', '%FBA')
                    ->where('campaignName', 'NOT LIKE', '%FBA.')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
                
                $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L1')
                    ->where('campaignName', 'NOT LIKE', '%PT')
                    ->where('campaignName', 'NOT LIKE', '%PT.')
                    ->where('campaignName', 'NOT LIKE', '%FBA')
                    ->where('campaignName', 'NOT LIKE', '%FBA.')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
            } else {
                // For PT campaigns, keep the existing SKU filter logic
                $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L15')
                    ->where(function ($q) use ($skus) {
                        foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    })
                    ->where(function($q) {
                        $q->where('campaignName', 'LIKE', '% PT')
                          ->orWhere('campaignName', 'LIKE', '% PT.');
                    })
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
                
                $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L7')
                    ->where(function ($q) use ($skus) {
                        foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    })
                    ->where(function($q) {
                        $q->where('campaignName', 'LIKE', '% PT')
                          ->orWhere('campaignName', 'LIKE', '% PT.');
                    })
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
                
                $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('report_date_range', 'L1')
                    ->where(function ($q) use ($skus) {
                        foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                    })
                    ->where(function($q) {
                        $q->where('campaignName', 'LIKE', '% PT')
                          ->orWhere('campaignName', 'LIKE', '% PT.');
                    })
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get();
            }
        }

        $result = [];
        $campaignMap = [];
        $processedSkus = []; // For PT campaigns to ensure unique SKUs

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;

            // For PT campaigns, apply unique SKU filter (same as getAmzUnderUtilizedBgtPt)
            if ($campaignType === 'PT' && in_array($sku, $processedSkus)) {
                continue;
            }

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            if ($campaignType === 'PT') {
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.');
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.');
                });
            } elseif ($campaignType === 'HL') {
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });
            } else {
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
            }

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            
            if (empty($campaignId) || empty($campaignName)) {
                continue;
            }

            // Check NRA filter and get TPFT
            $nra = '';
            $tpft = null;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                    $tpft = $raw['TPFT'] ?? null;
                }
            }

            if ($nra === 'NRA') {
                continue;
            }

            // For PT campaigns, mark SKU as processed (unique filter)
            if ($campaignType === 'PT') {
                $processedSkus[] = $sku;
            }

            if (!isset($campaignMap[$campaignId])) {
                $baseSku = strtoupper(trim($pm->sku));
                $campaignMap[$campaignId] = [
                    'parent' => $parent,
                    'sku' => $pm->sku,
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'campaignBudgetAmount' => $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0),
                    'campaignStatus' => $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? ''),
                    'INV' => ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0,
                    'FBA_INV' => isset($fbaData[$baseSku]) ? ($fbaData[$baseSku]->quantity_available ?? 0) : 0,
                    'L30' => ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0,
                    'A_L30' => ($amazonSheet && isset($amazonSheet->units_ordered_l30)) ? (int)$amazonSheet->units_ordered_l30 : 0,
                    'l7_spend' => 0,
                    'l7_cpc' => 0,
                    'l1_spend' => 0,
                    'l1_cpc' => 0,
                    'acos' => 0,
                    'acos_L30' => 0,
                    'acos_L15' => 0,
                    'acos_L7' => 0,
                    'NRA' => $nra,
                    'TPFT' => $tpft,
                ];
            }

            if ($matchedCampaignL7) {
                if ($campaignType === 'HL') {
                    $campaignMap[$campaignId]['l7_spend'] = $matchedCampaignL7->cost ?? 0;
                    $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                        ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                        : 0;
                    $campaignMap[$campaignId]['l7_cpc'] = $costPerClick7;
                } else {
                    $campaignMap[$campaignId]['l7_spend'] = $matchedCampaignL7->spend ?? 0;
                    $campaignMap[$campaignId]['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
                }
            }

            if ($matchedCampaignL1) {
                if ($campaignType === 'HL') {
                    $campaignMap[$campaignId]['l1_spend'] = $matchedCampaignL1->cost ?? 0;
                    $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                        ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                        : 0;
                    $campaignMap[$campaignId]['l1_cpc'] = $costPerClick1;
                } else {
                    $campaignMap[$campaignId]['l1_spend'] = $matchedCampaignL1->spend ?? 0;
                    $campaignMap[$campaignId]['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;
                }
            }

            if ($campaignType === 'PT') {
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.');
                });

                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.');
                });
            } elseif ($campaignType === 'HL') {
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });

                $matchedCampaignL15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });
            } else {
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
            }

            if ($matchedCampaignL30) {
                if ($campaignType === 'HL') {
                    $sales30 = $matchedCampaignL30->sales30d ?? 0;
                    $spend30 = $matchedCampaignL30->cost ?? 0;
                } else {
                    $sales30 = $matchedCampaignL30->sales30d ?? 0;
                    $spend30 = $matchedCampaignL30->spend ?? 0;
                }
                if ($sales30 > 0) {
                    $campaignMap[$campaignId]['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
                } elseif ($spend30 > 0) {
                    $campaignMap[$campaignId]['acos_L30'] = 100;
                }
                $campaignMap[$campaignId]['acos'] = $campaignMap[$campaignId]['acos_L30'];
            }

            if ($matchedCampaignL15 && $matchedCampaignL1) {
                if ($campaignType === 'HL') {
                    $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                    $spend15 = $matchedCampaignL15->cost ?? 0;
                } else {
                    $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                    $spend15 = $matchedCampaignL15->spend ?? 0;
                }
                if ($sales15 > 0) {
                    $campaignMap[$campaignId]['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
                } elseif ($spend15 > 0) {
                    $campaignMap[$campaignId]['acos_L15'] = 100;
                }
            }

            if ($matchedCampaignL7) {
                if ($campaignType === 'HL') {
                    $sales7 = $matchedCampaignL7->sales7d ?? 0;
                    $spend7 = $matchedCampaignL7->cost ?? 0;
                } else {
                    $sales7 = $matchedCampaignL7->sales7d ?? 0;
                    $spend7 = $matchedCampaignL7->spend ?? 0;
                }
                if ($sales7 > 0) {
                    $campaignMap[$campaignId]['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
                } elseif ($spend7 > 0) {
                    $campaignMap[$campaignId]['acos_L7'] = 100;
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
                $allL30Campaigns->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                      ->orWhere('campaignName', 'LIKE', '% PT.');
                });
            } else {
                $allL30Campaigns->where('campaignName', 'NOT LIKE', '%PT')
                               ->where('campaignName', 'NOT LIKE', '%PT.');
            }
            
            $allL30Campaigns = $allL30Campaigns->where('campaignStatus', '!=', 'ARCHIVED')->get();
        }

        $totalSpendAll = 0;
        $totalSalesAll = 0;

        foreach ($allL30Campaigns as $campaign) {
            if ($campaignType === 'HL') {
                $spend = $campaign->cost ?? 0;
            } else {
                $spend = $campaign->spend ?? 0;
            }
            $sales = $campaign->sales30d ?? 0;
            $totalSpendAll += $spend;
            $totalSalesAll += $sales;
        }

        $totalACOSAll = $totalSalesAll > 0 ? ($totalSpendAll / $totalSalesAll) * 100 : 0;

        foreach ($campaignMap as $campaignId => $row) {
            $result[] = (object) $row;
        }

        // For KW campaigns, add unmatched campaigns (similar to ACOS control)
        if ($campaignType === 'KW') {
            $matchedCampaignIds = array_unique(array_column($result, 'campaign_id'));
            $allUniqueCampaigns = $amazonSpCampaignReportsL7->unique('campaign_id')->merge($amazonSpCampaignReportsL1->unique('campaign_id'));
            
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

                // If no SKU match found, add as unmatched campaign
                if (!$matchedSku) {
                    $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });

                    $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });

                    $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($campaignId) {
                        return ($item->campaign_id ?? '') === $campaignId;
                    });

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
                    $row['INV'] = 0;
                    $row['FBA_INV'] = 0;
                    $row['L30'] = 0;
                    $row['A_L30'] = 0;
                    $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
                    $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
                    $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
                    $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;
                    $row['acos'] = 0;
                    $row['acos_L30'] = 0;
                    $row['acos_L15'] = 0;
                    $row['acos_L7'] = 0;
                    $row['NRA'] = '';
                    $row['TPFT'] = null;

                    if ($matchedCampaignL30) {
                        $sales30 = $matchedCampaignL30->sales30d ?? 0;
                        $spend30 = $matchedCampaignL30->spend ?? 0;
                        if ($sales30 > 0) {
                            $row['acos_L30'] = round(($spend30 / $sales30) * 100, 2);
                        } elseif ($spend30 > 0) {
                            $row['acos_L30'] = 100;
                        }
                        $row['acos'] = $row['acos_L30'];
                    }

                    if ($matchedCampaignL15 && $matchedCampaignL1) {
                        $sales15 = ($matchedCampaignL15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
                        $spend15 = $matchedCampaignL15->spend ?? 0;
                        if ($sales15 > 0) {
                            $row['acos_L15'] = round(($spend15 / $sales15) * 100, 2);
                        } elseif ($spend15 > 0) {
                            $row['acos_L15'] = 100;
                        }
                    }

                    if ($matchedCampaignL7) {
                        $sales7 = $matchedCampaignL7->sales7d ?? 0;
                        $spend7 = $matchedCampaignL7->spend ?? 0;
                        if ($sales7 > 0) {
                            $row['acos_L7'] = round(($spend7 / $sales7) * 100, 2);
                        } elseif ($spend7 > 0) {
                            $row['acos_L7'] = 100;
                        }
                    }

                    $result[] = (object) $row;
                    $matchedCampaignIds[] = $campaignId;
                }
            }
        }

        // For PT campaigns, apply unique SKU filter (same as getAmzUnderUtilizedBgtPt)
        if ($campaignType === 'PT') {
            $result = collect($result)->unique('sku')->values()->all();
        }

        return response()->json([
            'message' => 'fetched successfully',
            'data' => $result,
            'total_l30_spend' => round($totalSpendAll, 2),
            'total_l30_sales' => round($totalSalesAll, 2),
            'total_acos' => round($totalACOSAll, 2),
            'status' => 200,
        ]);
    }


}
