<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSbCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class AmazonSbBudgetController extends Controller
{
    protected $profileId;

    public function __construct()
    {
        $this->profileId = env('AMAZON_ADS_PROFILE_IDS');
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

        // Normalize campaign IDs to strings (Amazon API requires strings, not numbers)
        $normalizedCampaignIds = array_filter(
            array_map(function($id) {
                $strId = trim((string)$id);
                return !empty($strId) ? $strId : null;
            }, $campaignIds),
            function($id) { return $id !== null; }
        );

        if (empty($normalizedCampaignIds)) {
            return [];
        }

        $url = 'https://advertising-api.amazon.com/sb/v4/adGroups/list';
        $payload = [
            'campaignIdFilter' => ['include' => array_values($normalizedCampaignIds)],
            'stateFilter' => ['include' => ['ENABLED']],
        ];

        $response = $client->post($url, [
            'headers' => [
                'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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

    public function getKeywordsByAdGroup($adGroupId)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $url = 'https://advertising-api.amazon.com/sb/keywords';
        
        $response = $client->get($url, [
            'headers' => [
                'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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
    public function updateAutoCampaignSbKeywordsBid(array $campaignIds, array $newBids)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        if (empty($campaignIds) || empty($newBids)) {
            return response()->json([
                'message' => 'Campaign IDs and new bids are required',
                'status' => 400
            ]);
        }

        $allKeywords = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            AmazonSbCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', 'SPONSORED_BRANDS')
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
                        'campaignId' => $campaignId,
                        'adGroupId' => $adGroup['adGroupId'],
                        'bid' => $newBid,
                        'state' => $kw['state'] ?? 'enabled'
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
        $url = 'https://advertising-api.amazon.com/sb/keywords';
        $results = [];

        try {
            $chunks = array_chunk($allKeywords, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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

            return $results;

        } catch (\Exception $e) {
            return [
                'message' => 'Error updating target keywords bid',
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

            AmazonSbCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', 'SPONSORED_BRANDS')
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
                        'campaignId' => $campaignId,
                        'adGroupId' => $adGroup['adGroupId'],
                        'bid' => $newBid,
                        'state' => $kw['state'] ?? 'enabled'
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
        $url = 'https://advertising-api.amazon.com/sb/keywords';
        $results = [];

        try {
            $chunks = array_chunk($allKeywords, 100);
            foreach ($chunks as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
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


    public function amzUtilizedBgtHl()
    {   
        return view('campaign.amz-utilized-bgt-hl');
    }

    function getAmzUtilizedBgtHl()
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

        $amazonSpCampaignReportsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

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

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

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

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? '';
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? '';
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');
            $row['sbid'] = $matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '');
            $row['crnt_bid'] = $matchedCampaignL7->currentSbBidPrice ?? ($matchedCampaignL1->currentSbBidPrice ?? '');
            $row['l7_spend'] = $matchedCampaignL7->cost ?? 0;

            $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                : 0;

            $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                : 0;

            $row['l7_cpc']   = $costPerClick7;
            $row['l1_spend'] = $matchedCampaignL1->cost ?? 0;
            $row['l1_cpc']   = $costPerClick1;

            $sales30 = $matchedCampaignL30->sales ?? 0;
            $spend30 = $matchedCampaignL30->cost ?? 0;
            $sales15 = $matchedCampaignL15->sales ?? 0;
            $spend15 = $matchedCampaignL15->cost ?? 0;
            $sales7 = $matchedCampaignL7->sales ?? 0;
            $spend7 = $matchedCampaignL7->cost ?? 0;

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
            
            if($row['campaignName'] !== '' ){
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

    public function amzUnderUtilizedBgtHl()
    {   
        return view('campaign.amz-under-utilized-bgt-hl');
    }

    function getAmzUnderUtilizedBgtHl()
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

        $amazonSpCampaignReportsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

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

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

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

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? '';
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? '';
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');
            $row['sbid'] = $matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '');
            $row['crnt_bid'] = $matchedCampaignL7->currentUnderSbBidPrice ?? ($matchedCampaignL1->currentUnderSbBidPrice ?? '');
            $row['l7_spend'] = $matchedCampaignL7->cost ?? 0;

            $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                : 0;

            $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                : 0;

            $row['l7_cpc']   = $costPerClick7;
            $row['l1_spend'] = $matchedCampaignL1->cost ?? 0;
            $row['l1_cpc']   = $costPerClick1;


            $sales30 = $matchedCampaignL30->sales ?? 0;
            $spend30 = $matchedCampaignL30->cost ?? 0;
            $sales15 = $matchedCampaignL15->sales ?? 0;
            $spend15 = $matchedCampaignL15->cost ?? 0;
            $sales7 = $matchedCampaignL7->sales ?? 0;
            $spend7 = $matchedCampaignL7->cost ?? 0;

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
            
            // Calculate UB7 for filtering (same as frontend filter at line 789)
            $budget = $row['campaignBudgetAmount'] ?? 0;
            $l7_spend = $row['l7_spend'] ?? 0;
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            
            // Only include campaigns where UB7 < 70 (under-utilized) - same as frontend filter
            if($row['campaignName'] !== '' && $ub7 < 70){
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

    public function updateAmazonSbBidPrice(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',  
            'crnt_bid' => 'required|numeric',
            '_token' => 'required|string',
        ]);

        $updated = AmazonSbCampaignReport::where('campaign_id', $validated['id'])
            ->where('ad_type', 'SPONSORED_BRANDS')
            ->whereIn('report_date_range', ['L7', 'L1'])
            ->update([
                'currentSbBidPrice' => $validated['crnt_bid'],
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

    public function updateUnderAmazonSbBidPrice(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',  
            'crnt_bid' => 'required|numeric',
            '_token' => 'required|string',
        ]);

        $updated = AmazonSbCampaignReport::where('campaign_id', $validated['id'])
            ->where('ad_type', 'SPONSORED_BRANDS')
            ->whereIn('report_date_range', ['L7', 'L1'])
            ->update([
                'currentUnderSbBidPrice' => $validated['crnt_bid'],
                'sbid' => $validated['crnt_bid'] * 1.1
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
}
