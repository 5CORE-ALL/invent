<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\FbaTable;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AmzUnderUtilizedBgtController extends Controller
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

        $url = 'https://advertising-api.amazon.com/sp/adGroups/list';
        $payload = [
            'campaignIdFilter' => ['include' => $campaignIds],
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

        $payload = [
            'campaignIdFilter' => [
                'include' => is_array($campaignId) ? $campaignId : [$campaignId],
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

        $accessToken = $this->getAccessToken();
        $client = new Client();
        $allKeywords = [];

        foreach ($campaignIds as $index => $campaignId) {
            $newBid = floatval($newBids[$index] ?? 0);

            AmazonSpCampaignReport::where('campaign_id', $campaignId)
                ->where('ad_type', 'SPONSORED_PRODUCTS')
                ->whereIn('report_date_range', ['L7', 'L1'])
                ->update(['apprUnderSbid' => "approved"]);

            $adGroups = $this->getAdGroupsByCampaigns([$campaignId]);
            if (empty($adGroups)) continue;

            foreach (array_chunk($adGroups, 10) as $adGroupChunk) {
                foreach ($adGroupChunk as $adGroup) {
                    $keywords = $this->getKeywordsByAdGroup($adGroup['adGroupId']);
                    foreach ($keywords as $kw) {
                        $allKeywords[] = [
                            'keywordId' => $kw['keywordId'],
                            'bid' => $newBid,
                        ];
                    }
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

        $results = [];
        $url = 'https://advertising-api.amazon.com/sp/keywords';

        try {
            foreach (array_chunk($allKeywords, 100) as $chunk) {
                $response = $client->put($url, [
                    'headers' => [
                        'Amazon-Advertising-API-ClientId' => env('AMAZON_ADS_CLIENT_ID'),
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Amazon-Advertising-API-Scope' => $this->profileId,
                        'Content-Type' => 'application/vnd.spKeyword.v3+json',
                        'Accept' => 'application/vnd.spKeyword.v3+json',
                    ],
                    'json' => ['keywords' => $chunk],
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
                $allTargets[] = [
                    'bid' => $newBid,
                    'targetId' => $adTarget['targetId'],
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

    public function amzUnderUtilizedBgtKw(){
        return view('campaign.amz-under-utilized-bgt-kw');
    }

    function getAmzUnderUtilizedBgtKw()
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

        // For PARENT rows: avg price = average of child SKUs' prices (from amazon datasheet, or ProductMaster Values as fallback)
        $childPricesByParent = [];
        foreach ($productMasters as $pmChild) {
            $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pmChild->sku ?? '');
            $norm = preg_replace('/\s+/', ' ', $norm);
            $skuUpper = strtoupper(trim($norm));
            if (stripos($skuUpper, 'PARENT') !== false) {
                continue;
            }
            $p = $pmChild->parent ?? '';
            if ($p === '') {
                continue;
            }
            $amazonSheetChild = $amazonDatasheetsBySku[$skuUpper] ?? null;
            $childPrice = ($amazonSheetChild && isset($amazonSheetChild->price) && (float)$amazonSheetChild->price > 0)
                ? (float)$amazonSheetChild->price
                : null;
            if ($childPrice === null) {
                $values = $pmChild->Values ?? null;
                if (is_string($values)) {
                    $values = json_decode($values, true) ?: [];
                } elseif (is_object($values)) {
                    $values = (array) $values;
                } else {
                    $values = is_array($values) ? $values : [];
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
        $avgPriceByParentCanonical = [];
        foreach ($childPricesByParent as $pk => $prices) {
            $avg = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
            $avgPriceByParent[$pk] = $avg;
            $canonical = preg_replace('/\s+/', '', $pk);
            if ($canonical !== '' && $avg > 0) {
                $avgPriceByParentCanonical[$canonical] = $avg;
            }
        }

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
            $row['crnt_bid'] = $matchedCampaignL7->currentUnderSpBidPrice ?? ($matchedCampaignL1->currentUnderSpBidPrice ?? '');
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;

            // Price: from AmazonDatasheet or ProductMaster Values; for PARENT use avg of children
            $price = ($amazonSheet && isset($amazonSheet->price) && (float)$amazonSheet->price > 0) ? (float)$amazonSheet->price : 0;
            if (($price === 0 || $price === null) && stripos($pm->sku ?? '', 'PARENT') !== false && !empty($avgPriceByParent)) {
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
                    $values = $pm->Values ?? null;
                    if (is_string($values)) {
                        $values = json_decode($values, true) ?: [];
                    } elseif (is_object($values)) {
                        $values = (array) $values;
                    } else {
                        $values = is_array($values) ? $values : [];
                    }
                    $price = (isset($values['msrp']) && (float)$values['msrp'] > 0) ? (float)$values['msrp'] : (isset($values['map']) && (float)$values['map'] > 0 ? (float)$values['map'] : 0);
                }
            }
            $row['price'] = (float) ($price ?? 0);

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

            if($row['NRA'] !== 'NRA' && $row['campaignName'] !== ''){
                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }


    public function amzUnderUtilizedBgtPt()
    {   
        return view('campaign.amz-under-utilized-bgt-pt');
    }

    function getAmzUnderUtilizedBgtPt()
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
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReports15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L15')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        // For PARENT rows: avg price = average of child SKUs' prices (from amazon datasheet, or ProductMaster Values as fallback)
        $childPricesByParent = [];
        foreach ($productMasters as $pmChild) {
            $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pmChild->sku ?? '');
            $norm = preg_replace('/\s+/', ' ', $norm);
            $skuUpper = strtoupper(trim($norm));
            if (stripos($skuUpper, 'PARENT') !== false) {
                continue;
            }
            $p = $pmChild->parent ?? '';
            if ($p === '') {
                continue;
            }
            $amazonSheetChild = $amazonDatasheetsBySku[$skuUpper] ?? null;
            $childPrice = ($amazonSheetChild && isset($amazonSheetChild->price) && (float)$amazonSheetChild->price > 0)
                ? (float)$amazonSheetChild->price
                : null;
            if ($childPrice === null) {
                $values = $pmChild->Values ?? null;
                if (is_string($values)) {
                    $values = json_decode($values, true) ?: [];
                } elseif (is_object($values)) {
                    $values = (array) $values;
                } else {
                    $values = is_array($values) ? $values : [];
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
        $avgPriceByParentCanonical = [];
        foreach ($childPricesByParent as $pk => $prices) {
            $avg = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
            $avgPriceByParent[$pk] = $avg;
            $canonical = preg_replace('/\s+/', '', $pk);
            if ($canonical !== '' && $avg > 0) {
                $avgPriceByParentCanonical[$canonical] = $avg;
            }
        }

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaign30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return (
                    ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.')
                );
            });

            $matchedCampaign15 = $amazonSpCampaignReports15->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return (
                    ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.')
                );
            });

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return (
                    ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.')
                );
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                return (
                    ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.')
                );
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
            $row['crnt_bid'] = $matchedCampaignL7->currentUnderSpBidPrice ?? ($matchedCampaignL1->currentUnderSpBidPrice ?? '');
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;

            // Price: from AmazonDatasheet or ProductMaster Values; for PARENT use avg of children
            $price = ($amazonSheet && isset($amazonSheet->price) && (float)$amazonSheet->price > 0) ? (float)$amazonSheet->price : 0;
            if (($price === 0 || $price === null) && stripos($pm->sku ?? '', 'PARENT') !== false && !empty($avgPriceByParent)) {
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
                    $values = $pm->Values ?? null;
                    if (is_string($values)) {
                        $values = json_decode($values, true) ?: [];
                    } elseif (is_object($values)) {
                        $values = (array) $values;
                    } else {
                        $values = is_array($values) ? $values : [];
                    }
                    $price = (isset($values['msrp']) && (float)$values['msrp'] > 0) ? (float)$values['msrp'] : (isset($values['map']) && (float)$values['map'] > 0 ? (float)$values['map'] : 0);
                }
            }
            $row['price'] = (float) ($price ?? 0);

            $sales30 = $matchedCampaign30->sales30d ?? 0;
            $spend30 = $matchedCampaign30->spend ?? 0;
            $sales15 = ($matchedCampaign15->sales14d ?? 0) + ($matchedCampaignL1->sales1d ?? 0);
            $spend15 = $matchedCampaign15->spend ?? 0;
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

            
            $row['clicks_L30'] = $matchedCampaign30->clicks ?? 0;
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

            // Calculate UB7 for filtering (same as frontend filter at line 781)
            $budget = $row['campaignBudgetAmount'] ?? 0;
            $l7_spend = $row['l7_spend'] ?? 0;
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            
            // Only include campaigns where UB7 < 70 (under-utilized) - same as frontend filter
            if($row['NRA'] !== 'NRA' && $row['campaignName'] !== '' && $ub7 < 70){
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
                'currentUnderSpBidPrice' => $validated['crnt_bid'],
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
