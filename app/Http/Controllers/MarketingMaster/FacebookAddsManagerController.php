<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\AmazonDataView;
use App\Models\MetaAllAd;
use App\Models\MetaAdGroup;
use App\Models\ShopifyMetaCampaign;
use Illuminate\Support\Facades\Log;
use App\Services\MetaApiService;

class FacebookAddsManagerController extends Controller
{
    public function metaAllAds() {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();

        $formattedDate = $latestUpdatedAt
            ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A')
            : null;

        return view('marketing-masters.meta_ads_manager.metaAllAds', [
            'latestUpdatedAt' => $formattedDate,
        ]);
    }

    public function metaAllAdsData()
    {
        $metaAds = MetaAllAd::orderBy('campaign_name', 'asc')->get();

        // Fetch all Shopify Meta campaign sales data grouped by campaign_id and date_range
        $shopifySales = ShopifyMetaCampaign::whereNotNull('campaign_id')
            ->select('campaign_id', 'date_range', 'sales', 'orders')
            ->get()
            ->groupBy('campaign_id');

        $data = [];
        foreach ($metaAds as $ad) {
            // Get L30 values
            $spend_l30 = $ad->spent_l30 ?? 0;
            $clicks_l30 = $ad->clicks_l30 ?? 0;
            $units_sold_l30 = 0;
            
            // Get L7 values
            $spend_l7 = $ad->spent_l7 ?? 0;
            $clicks_l7 = $ad->clicks_l7 ?? 0;
            $units_sold_l7 = 0;
            
            // L60 data (currently using L30 values)
            $spend_l60 = $ad->spent_l30 ?? 0;
            $clicks_l60 = $ad->clicks_l30 ?? 0;
            $units_sold_l60 = 0;
            
            // Fetch sales data from Shopify Facebook campaigns by matching campaign_id
            $sales_l30 = 0;
            $sales_l60 = 0;
            $sales_l7 = 0;
            
            if ($ad->campaign_id && isset($shopifySales[$ad->campaign_id])) {
                $campaignSales = $shopifySales[$ad->campaign_id];
                
                foreach ($campaignSales as $sale) {
                    if ($sale->date_range === '30_days') {
                        $sales_l30 = $sale->sales ?? 0;
                        $units_sold_l30 = $sale->orders ?? 0;
                    } elseif ($sale->date_range === '60_days') {
                        $sales_l60 = $sale->sales ?? 0;
                        $units_sold_l60 = $sale->orders ?? 0;
                    } elseif ($sale->date_range === '7_days') {
                        $sales_l7 = $sale->sales ?? 0;
                        $units_sold_l7 = $sale->orders ?? 0;
                    }
                }
            }
            
            // Calculate ACOS L30 using Amazon formula: ACOS = (Spend / Sales) * 100
            $acos_l30 = 0;
            if ($sales_l30 > 0) {
                $acos_l30 = round(($spend_l30 / $sales_l30) * 100, 2);
            } elseif ($spend_l30 > 0 && $sales_l30 == 0) {
                $acos_l30 = 100;
            }
            
            // Calculate ACOS L60
            $acos_l60 = 0;
            if ($sales_l60 > 0) {
                $acos_l60 = round(($spend_l60 / $sales_l60) * 100, 2);
            } elseif ($spend_l60 > 0 && $sales_l60 == 0) {
                $acos_l60 = 100;
            }
            
            // Calculate ACOS L7
            $acos_l7 = 0;
            if ($sales_l7 > 0) {
                $acos_l7 = round(($spend_l7 / $sales_l7) * 100, 2);
            } elseif ($spend_l7 > 0 && $sales_l7 == 0) {
                $acos_l7 = 100;
            }
            
            // Calculate CVR L30 using Amazon formula: CVR = (Units Sold / Clicks) * 100
            $cvr_l30 = null;
            if ($clicks_l30 > 0 && $units_sold_l30 > 0) {
                $cvr_l30 = number_format(($units_sold_l30 / $clicks_l30) * 100, 2);
            }
            
            // Calculate CVR L60
            $cvr_l60 = null;
            if ($clicks_l60 > 0 && $units_sold_l60 > 0) {
                $cvr_l60 = number_format(($units_sold_l60 / $clicks_l60) * 100, 2);
            }
            
            // Calculate CVR L7
            $cvr_l7 = null;
            if ($clicks_l7 > 0 && $units_sold_l7 > 0) {
                $cvr_l7 = number_format(($units_sold_l7 / $clicks_l7) * 100, 2);
            }
            
            $data[] = [
                'campaign_name' => $ad->campaign_name,
                'campaign_id' => $ad->campaign_id,
                'platform' => $ad->platform ?? 'Facebook/Instagram',
                'group_name' => $ad->group ? $ad->group->group_name : null,
                'budget' => $ad->bgt ?? 0,
                'impressions_l60' => $ad->imp_l30 ?? 0,
                'impressions_l30' => $ad->imp_l30 ?? 0,
                'impressions_l7' => $ad->imp_l7 ?? 0,
                'spend_l60' => $spend_l60,
                'spend_l30' => $spend_l30,
                'spend_l7' => $spend_l7,
                'clicks_l60' => $clicks_l60,
                'clicks_l30' => $clicks_l30,
                'clicks_l7' => $clicks_l7,
                'sales_l60' => $sales_l60,
                'sales_l30' => $sales_l30,
                'sales_l7' => $sales_l7,
                'sales_delivered_l60' => 0,
                'sales_delivered_l30' => 0,
                'sales_delivered_l7' => 0,
                'acos_l60' => $acos_l60,
                'acos_l30' => $acos_l30,
                'acos_l7' => $acos_l7,
                'cvr_l60' => $cvr_l60,
                'cvr_l30' => $cvr_l30,
                'cvr_l7' => $cvr_l7,
                'status' => strtoupper($ad->campaign_delivery)
            ];
        }

        return response()->json([
            'message' => 'Meta All Ads data fetched successfully',
            'data' => $data,
            'status' => 200,
        ]);
    }

    public function syncMetaAdsFromApi()
    {
        try {
            $metaApi = new MetaApiService();
            
            // Sync L30 data from Meta API
            $l30Count = $this->syncL30DataFromMetaApi($metaApi);
            
            // Sync L7 data from Meta API
            $l7Count = $this->syncL7DataFromMetaApi($metaApi);
            
            return response()->json([
                'success' => true,
                'message' => 'Data synced successfully from Meta API',
                'l30_synced' => $l30Count,
                'l7_synced' => $l7Count,
            ]);
        } catch (\Exception $e) {
            Log::error('Meta API Sync Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error syncing data from Meta API: ' . $e->getMessage()
            ], 500);
        }
    }

    private function syncL30DataFromMetaApi(MetaApiService $metaApi)
    {
        try {
            // Fetch L30 campaigns data from Meta API
            $campaigns = $metaApi->fetchCampaignsWithBudget('last_30d');
            
            $processed = 0;
            
            foreach ($campaigns as $campaign) {
                $campaignName = trim($campaign['name'] ?? '');
                $campaignId = trim($campaign['id'] ?? '');
                
                // Skip invalid campaigns
                if (!$campaignName || !$campaignId) {
                    continue;
                }
                
                // Map Meta API status to campaign delivery
                // Meta API statuses: ACTIVE, PAUSED, ARCHIVED, DELETED
                // Database accepts: active, inactive, not_delivering
                $status = strtolower($campaign['status'] ?? 'paused');
                $campaignDelivery = match($status) {
                    'active' => 'active',
                    'paused' => 'not_delivering',
                    'archived' => 'inactive',
                    'deleted' => 'inactive',
                    default => 'inactive',
                };
                
                // Get budget (daily or lifetime, converted from cents to dollars)
                $dailyBudget = isset($campaign['daily_budget']) ? (float) $campaign['daily_budget'] / 100 : 0;
                $lifetimeBudget = isset($campaign['lifetime_budget']) ? (float) $campaign['lifetime_budget'] / 100 : 0;
                $adSetBudget = $campaign['ad_set_budget'] ?? 0;
                $bgt = max($dailyBudget, $lifetimeBudget, $adSetBudget);
                
                // Extract insights data
                $impL30 = (int) ($campaign['impressions'] ?? 0);
                $spentL30 = (float) ($campaign['spend'] ?? 0);
                $clicksL30 = (int) ($campaign['link_clicks'] ?? 0);
                
                // Get platform information
                $platform = $campaign['platform'] ?? 'Facebook/Instagram';
                
                // Assign group based on campaign name prefix
                $groupId = MetaAllAd::assignGroupByCampaignName($campaignName);
                
                MetaAllAd::updateOrCreate(
                    ['campaign_name' => $campaignName],
                    [
                        'campaign_id' => $campaignId,
                        'group_id' => $groupId,
                        'platform' => $platform,
                        'campaign_delivery' => $campaignDelivery,
                        'bgt' => $bgt,
                        'imp_l30' => $impL30,
                        'spent_l30' => $spentL30,
                        'clicks_l30' => $clicksL30,
                    ]
                );
                $processed++;
            }
            
            Log::info('L30 Data Synced from Meta API', ['campaigns_processed' => $processed]);
            return $processed;
        } catch (\Exception $e) {
            Log::error('Meta API L30 Sync Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function syncL7DataFromMetaApi(MetaApiService $metaApi)
    {
        try {
            // Fetch L7 campaigns data from Meta API
            $campaigns = $metaApi->fetchCampaignsL7();
            
            $processed = 0;
            
            foreach ($campaigns as $campaign) {
                $campaignName = trim($campaign['name'] ?? '');
                
                // Skip invalid campaigns
                if (!$campaignName) {
                    continue;
                }
                
                // Extract insights data
                $impL7 = (int) ($campaign['impressions'] ?? 0);
                $spentL7 = (float) ($campaign['spend'] ?? 0);
                $clicksL7 = (int) ($campaign['link_clicks'] ?? 0);
                
                // Only update if campaign exists (created during L30 sync)
                $metaAd = MetaAllAd::where('campaign_name', $campaignName)->first();
                if ($metaAd) {
                    $metaAd->update([
                        'imp_l7' => $impL7,
                        'spent_l7' => $spentL7,
                        'clicks_l7' => $clicksL7,
                    ]);
                    $processed++;
                }
            }
            
            Log::info('L7 Data Synced from Meta API', ['campaigns_processed' => $processed]);
            return $processed;
        } catch (\Exception $e) {
            Log::error('Meta API L7 Sync Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function index()
    {
        return view('marketing-masters.facebook_ads_manager.index');
    }

    public function getFacebookAdsData()
    {
        $data = [
            ['id' => 1, 'campaign_name' => 'Campaign 1', 'status' => 'Active', 'budget' => 100],
            ['id' => 2, 'campaign_name' => 'Campaign 2', 'status' => 'Paused', 'budget' => 200],
        ];

        return response()->json($data);
    }

    public function facebookWebToVideo()
    {
        return view('marketing-masters.facebook_web_ads.facebook-video-to-web');
    }

    public function facebookWebToVideoData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;

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

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }


    public function FbImgCaraousalToWeb()
    {
        return view('marketing-masters.facebook_web_ads.fb-img-caraousal-to-web');
    }

    public function FbImgCaraousalToWebData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = $pm->fba ?? null;

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

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    // AD Type specific views and data methods (now showing all ads regardless of type)
    private function getMetaAdsDataByType($adType = null, $channel = null)
    {
        $query = MetaAllAd::orderBy('campaign_name', 'asc');
        
        // Removed ad_type filtering - showing all ads
        
        $metaAds = $query->get();

        // Fetch Shopify Meta campaign sales data grouped by campaign_id and date_range
        $shopifySalesQuery = ShopifyMetaCampaign::whereNotNull('campaign_id')
            ->select('campaign_id', 'date_range', 'sales', 'orders', 'referring_channel');
        
        // Filter by channel if specified (facebook, instagram)
        if ($channel) {
            $shopifySalesQuery->where('referring_channel', $channel);
        }
        
        $shopifySales = $shopifySalesQuery->get()->groupBy('campaign_id');

        $data = [];
        foreach ($metaAds as $ad) {
            $spend_l30 = $ad->spent_l30 ?? 0;
            $clicks_l30 = $ad->clicks_l30 ?? 0;
            $units_sold_l30 = 0;
            
            $spend_l7 = $ad->spent_l7 ?? 0;
            $clicks_l7 = $ad->clicks_l7 ?? 0;
            $units_sold_l7 = 0;
            
            $spend_l60 = $ad->spent_l30 ?? 0;
            $clicks_l60 = $ad->clicks_l30 ?? 0;
            $units_sold_l60 = 0;
            
            $sales_l30 = 0;
            $sales_l60 = 0;
            $sales_l7 = 0;
            
            // Match Shopify sales data by campaign_id
            // Channel filtering is already applied in the query, but we verify here for safety
            if ($ad->campaign_id && isset($shopifySales[$ad->campaign_id])) {
                $campaignSales = $shopifySales[$ad->campaign_id];
                
                foreach ($campaignSales as $sale) {
                    // Double-check channel match if channel filter is applied (safety check for data integrity)
                    if ($channel && isset($sale->referring_channel) && $sale->referring_channel !== $channel) {
                        continue;
                    }
                    
                    if ($sale->date_range === '30_days') {
                        $sales_l30 = $sale->sales ?? 0;
                        $units_sold_l30 = $sale->orders ?? 0;
                    } elseif ($sale->date_range === '60_days') {
                        $sales_l60 = $sale->sales ?? 0;
                        $units_sold_l60 = $sale->orders ?? 0;
                    } elseif ($sale->date_range === '7_days') {
                        $sales_l7 = $sale->sales ?? 0;
                        $units_sold_l7 = $sale->orders ?? 0;
                    }
                }
            }
            
            $acos_l30 = 0;
            if ($sales_l30 > 0) {
                $acos_l30 = round(($spend_l30 / $sales_l30) * 100, 2);
            } elseif ($spend_l30 > 0 && $sales_l30 == 0) {
                $acos_l30 = 100;
            }
            
            $acos_l60 = 0;
            if ($sales_l60 > 0) {
                $acos_l60 = round(($spend_l60 / $sales_l60) * 100, 2);
            } elseif ($spend_l60 > 0 && $sales_l60 == 0) {
                $acos_l60 = 100;
            }
            
            $acos_l7 = 0;
            if ($sales_l7 > 0) {
                $acos_l7 = round(($spend_l7 / $sales_l7) * 100, 2);
            } elseif ($spend_l7 > 0 && $sales_l7 == 0) {
                $acos_l7 = 100;
            }
            
            $cvr_l30 = null;
            if ($clicks_l30 > 0 && $units_sold_l30 > 0) {
                $cvr_l30 = number_format(($units_sold_l30 / $clicks_l30) * 100, 2);
            }
            
            $cvr_l60 = null;
            if ($clicks_l60 > 0 && $units_sold_l60 > 0) {
                $cvr_l60 = number_format(($units_sold_l60 / $clicks_l60) * 100, 2);
            }
            
            $cvr_l7 = null;
            if ($clicks_l7 > 0 && $units_sold_l7 > 0) {
                $cvr_l7 = number_format(($units_sold_l7 / $clicks_l7) * 100, 2);
            }
            
            $data[] = [
                'campaign_name' => $ad->campaign_name,
                'campaign_id' => $ad->campaign_id,
                'group_name' => $ad->group ? $ad->group->group_name : null,
                'budget' => $ad->bgt ?? 0,
                'impressions_l60' => $ad->imp_l30 ?? 0,
                'impressions_l30' => $ad->imp_l30 ?? 0,
                'impressions_l7' => $ad->imp_l7 ?? 0,
                'spend_l60' => $spend_l60,
                'spend_l30' => $spend_l30,
                'spend_l7' => $spend_l7,
                'clicks_l60' => $clicks_l60,
                'clicks_l30' => $clicks_l30,
                'clicks_l7' => $clicks_l7,
                'sales_l60' => $sales_l60,
                'sales_l30' => $sales_l30,
                'sales_l7' => $sales_l7,
                'sales_delivered_l60' => 0,
                'sales_delivered_l30' => 0,
                'sales_delivered_l7' => 0,
                'acos_l60' => $acos_l60,
                'acos_l30' => $acos_l30,
                'acos_l7' => $acos_l7,
                'cvr_l60' => $cvr_l60,
                'cvr_l30' => $cvr_l30,
                'cvr_l7' => $cvr_l7,
                'status' => strtoupper($ad->campaign_delivery)
            ];
        }

        return $data;
    }

    // Facebook Ad Type Methods (now showing all ads without type filtering)
    public function metaFacebookSingleImage()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.singleImage', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaFacebookSingleImageData()
    {
        $data = $this->getMetaAdsDataByType(null);
        return response()->json(['message' => 'Facebook Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaFacebookSingleVideo()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.singleVideo', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaFacebookSingleVideoData()
    {
        $data = $this->getMetaAdsDataByType(null);
        return response()->json(['message' => 'Facebook Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaFacebookCarousal()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.carousal', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaFacebookCarousalData()
    {
        $data = $this->getMetaAdsDataByType(null, 'facebook');
        // Filter out campaigns with empty groups and campaigns without "FB" in campaign name
        $data = array_filter($data, function($campaign) {
            $hasGroup = !empty($campaign['group_name']);
            $hasFB = !empty($campaign['campaign_name']) && stripos($campaign['campaign_name'], 'FB') !== false;
            return $hasGroup && $hasFB;
        });
        // Re-index array after filtering
        $data = array_values($data);
        return response()->json(['message' => 'Facebook Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaFacebookExistingPost()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.existingPost', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaFacebookExistingPostData()
    {
        $data = $this->getMetaAdsDataByType(null);
        return response()->json(['message' => 'Facebook Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaFacebookCatalogueAd()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.catalogueAd', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaFacebookCatalogueAdData()
    {
        $data = $this->getMetaAdsDataByType(null);
        return response()->json(['message' => 'Facebook Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    // Instagram Ad Type Methods (now showing all ads without type filtering)
    public function metaInstagramSingleImage()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.singleImage', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaInstagramSingleImageData()
    {
        $data = $this->getMetaAdsDataByType(null);
        return response()->json(['message' => 'Instagram Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaInstagramSingleVideo()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.singleVideo', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaInstagramSingleVideoData()
    {
        $data = $this->getMetaAdsDataByType(null);
        return response()->json(['message' => 'Instagram Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaInstagramCarousal()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.carousal', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaInstagramCarousalData()
    {
        $data = $this->getMetaAdsDataByType(null, 'instagram');
        // Filter out campaigns with empty groups and campaigns without "INSTA" in campaign name
        $data = array_filter($data, function($campaign) {
            $hasGroup = !empty($campaign['group_name']);
            $hasInsta = !empty($campaign['campaign_name']) && stripos($campaign['campaign_name'], 'INSTA') !== false;
            return $hasGroup && $hasInsta;
        });
        // Re-index array after filtering
        $data = array_values($data);
        return response()->json(['message' => 'Instagram Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaInstagramExistingPost()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.existingPost', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaInstagramExistingPostData()
    {
        $data = $this->getMetaAdsDataByType(null);
        return response()->json(['message' => 'Instagram Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaInstagramCatalogueAd()
    {
        $latestUpdatedAt = MetaAllAd::latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.catalogueAd', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaInstagramCatalogueAdData()
    {
        $data = $this->getMetaAdsDataByType(null);
        return response()->json(['message' => 'Instagram Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    /**
     * Store a new group
     */
    public function storeGroup(Request $request)
    {
        try {
            $request->validate([
                'group_name' => 'required|string|max:255|unique:meta_ad_groups,group_name'
            ]);

            $group = MetaAdGroup::create([
                'group_name' => $request->group_name
            ]);

            // Automatically assign existing campaigns that match this group name prefix
            $campaigns = MetaAllAd::whereNull('group_id')
                ->orWhere('group_id', '!=', $group->id)
                ->get();
            
            $assignedCount = 0;
            foreach ($campaigns as $campaign) {
                if (stripos($campaign->campaign_name, $group->group_name) === 0) {
                    $campaign->group_id = $group->id;
                    $campaign->save();
                    $assignedCount++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'group' => $group,
                'campaigns_assigned' => $assignedCount
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create group: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import ads data from Excel/CSV
     */
    public function importAds(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:csv,xlsx,xls|max:10240'
            ]);

            $file = $request->file('file');
            
            // Use Laravel Excel or similar package to import
            // \Maatwebsite\Excel\Facades\Excel::import(new MetaAdsImport, $file);
            
            // Placeholder logic - implement actual import logic based on your requirements
            
            return response()->json([
                'success' => true,
                'message' => 'File imported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export ads data to Excel
     */
    public function exportAds(Request $request)
    {
        try {
            // Fetch all ads
            $ads = MetaAllAd::all();

            // Use Laravel Excel or similar to export
            // return \Maatwebsite\Excel\Facades\Excel::download(new MetaAdsExport($ads), 'meta_ads_export.xlsx');
            
            // Placeholder - implement actual export logic
            $filename = 'meta_ads_export_' . date('Y-m-d') . '.xlsx';
            
            return response()->json([
                'success' => true,
                'message' => 'Export functionality needs implementation with Laravel Excel package',
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * FB GRP CAROUSAL NEW Page
     */
    public function metaFacebookCarousalNew()
    {
        return view('marketing-masters.meta_ads_manager.facebook.carousal_new');
    }

    /**
     * Get FB GRP CAROUSAL NEW Data
     */
    public function metaFacebookCarousalNewData()
    {
        try {
            $productMasters = ProductMaster::with('productGroup')
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            $data = [];
            foreach ($productMasters as $pm) {
                $sku = $pm->sku;
                $shopify = $shopifyData[$sku] ?? null;

                // Get ov_l30 from shopify_skus quantity column
                $ovL30 = $shopify ? (float) ($shopify->quantity ?? 0) : 0;
                
                // Get inventory
                $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;

                // Get group name from relationship
                $groupName = $pm->productGroup ? $pm->productGroup->group_name : '';

                // Calculate dil_percent = (l30 / inv) * 100
                $dilPercent = 0;
                if ($inv > 0) {
                    $dilPercent = (int) round(($ovL30 / $inv) * 100);
                }

                $data[] = [
                    'group' => $groupName,
                    'parent' => $pm->parent ?? '',
                    'sku' => $sku ?? '',
                    'inv' => $inv,
                    'ov_l30' => $ovL30,
                    'dil_percent' => $dilPercent
                ];
            }

            return response()->json([
                'message' => 'Product SKU data fetched successfully',
                'data' => $data,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error('Product SKU Data Error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error fetching data: ' . $e->getMessage(),
                'data' => [],
                'status' => 500,
            ], 500);
        }
    }
}


