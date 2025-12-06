<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\AmazonDataView;
use App\Models\MetaAllAd;
use App\Models\ShopifyFacebookCampaign;
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

        // Fetch all Shopify Facebook campaign sales data grouped by campaign_id and date_range
        $shopifySales = ShopifyFacebookCampaign::whereNotNull('campaign_id')
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
                'ad_type' => $ad->ad_type ?? '',
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

    public function updateAdType(Request $request)
    {
        $request->validate([
            'campaign_name' => 'required|string',
            'ad_type' => 'nullable|string|in:' . implode(',', MetaAllAd::$adTypes),
        ]);

        $metaAd = MetaAllAd::where('campaign_name', $request->campaign_name)->first();

        if (!$metaAd) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $metaAd->ad_type = $request->ad_type ?: null;
        $metaAd->save();

        return response()->json([
            'success' => true,
            'message' => 'Ad Type updated successfully',
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
                
                MetaAllAd::updateOrCreate(
                    ['campaign_name' => $campaignName],
                    [
                        'campaign_id' => $campaignId,
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

    // AD Type specific views and data methods
    private function getMetaAdsDataByType($adType = null)
    {
        $query = MetaAllAd::orderBy('campaign_name', 'asc');
        
        if ($adType) {
            $query->where('ad_type', $adType);
        }
        
        $metaAds = $query->get();

        // Fetch all Shopify Facebook campaign sales data grouped by campaign_id and date_range
        $shopifySales = ShopifyFacebookCampaign::whereNotNull('campaign_id')
            ->select('campaign_id', 'date_range', 'sales', 'orders')
            ->get()
            ->groupBy('campaign_id');

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
                'ad_type' => $ad->ad_type ?? '',
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

    // Facebook Ad Type Methods
    public function metaFacebookSingleImage()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Facebook Single Image')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.singleImage', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Facebook Single Image']);
    }

    public function metaFacebookSingleImageData()
    {
        $data = $this->getMetaAdsDataByType('Facebook Single Image');
        return response()->json(['message' => 'Facebook Single Image Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaFacebookSingleVideo()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Facebook Single Video')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.singleVideo', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Facebook Single Video']);
    }

    public function metaFacebookSingleVideoData()
    {
        $data = $this->getMetaAdsDataByType('Facebook Single Video');
        return response()->json(['message' => 'Facebook Single Video Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaFacebookCarousal()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Facebook Carousal')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.carousal', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Facebook Carousal']);
    }

    public function metaFacebookCarousalData()
    {
        $data = $this->getMetaAdsDataByType('Facebook Carousal');
        return response()->json(['message' => 'Facebook Carousal Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaFacebookExistingPost()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Facebook Existing Post')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.existingPost', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Facebook Existing Post']);
    }

    public function metaFacebookExistingPostData()
    {
        $data = $this->getMetaAdsDataByType('Facebook Existing Post');
        return response()->json(['message' => 'Facebook Existing Post Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaFacebookCatalogueAd()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Facebook Catalogue Ad')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.facebook.catalogueAd', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Facebook Catalogue Ad']);
    }

    public function metaFacebookCatalogueAdData()
    {
        $data = $this->getMetaAdsDataByType('Facebook Catalogue Ad');
        return response()->json(['message' => 'Facebook Catalogue Ad Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    // Instagram Ad Type Methods
    public function metaInstagramSingleImage()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Instagram Single Image')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.singleImage', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Instagram Single Image']);
    }

    public function metaInstagramSingleImageData()
    {
        $data = $this->getMetaAdsDataByType('Instagram Single Image');
        return response()->json(['message' => 'Instagram Single Image Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaInstagramSingleVideo()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Instagram Single Video')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.singleVideo', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Instagram Single Video']);
    }

    public function metaInstagramSingleVideoData()
    {
        $data = $this->getMetaAdsDataByType('Instagram Single Video');
        return response()->json(['message' => 'Instagram Single Video Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaInstagramCarousal()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Instagram Carousal')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.carousal', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Instagram Carousal']);
    }

    public function metaInstagramCarousalData()
    {
        $data = $this->getMetaAdsDataByType('Instagram Carousal');
        return response()->json(['message' => 'Instagram Carousal Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaInstagramExistingPost()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Instagram Existing Post')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.existingPost', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Instagram Existing Post']);
    }

    public function metaInstagramExistingPostData()
    {
        $data = $this->getMetaAdsDataByType('Instagram Existing Post');
        return response()->json(['message' => 'Instagram Existing Post Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaInstagramCatalogueAd()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Instagram Catalogue Ad')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.instagram.catalogueAd', ['latestUpdatedAt' => $formattedDate, 'adType' => 'Instagram Catalogue Ad']);
    }

    public function metaInstagramCatalogueAdData()
    {
        $data = $this->getMetaAdsDataByType('Instagram Catalogue Ad');
        return response()->json(['message' => 'Instagram Catalogue Ad Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }
}


