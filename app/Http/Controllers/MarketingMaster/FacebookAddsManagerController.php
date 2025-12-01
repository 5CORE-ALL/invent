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
            $units_sold_l30 = 0; // TODO: Add units sold data when available
            
            // Get L7 values
            $spend_l7 = $ad->spent_l7 ?? 0;
            $clicks_l7 = $ad->clicks_l7 ?? 0;
            $units_sold_l7 = 0; // TODO: Add units sold data when available
            
            // L60 data (currently using L30 values)
            $spend_l60 = $ad->spent_l30 ?? 0;
            $clicks_l60 = $ad->clicks_l30 ?? 0;
            $units_sold_l60 = 0; // TODO: Add units sold data when available
            
            // Fetch sales data from Shopify Facebook campaigns by matching campaign_id
            $sales_l30 = 0;
            $sales_l60 = 0;
            $sales_l7 = 0;
            
            if ($ad->campaign_id && isset($shopifySales[$ad->campaign_id])) {
                $campaignSales = $shopifySales[$ad->campaign_id];
                
                foreach ($campaignSales as $sale) {
                    if ($sale->date_range === '30_days') {
                        $sales_l30 = $sale->sales ?? 0;
                    } elseif ($sale->date_range === '60_days') {
                        $sales_l60 = $sale->sales ?? 0;
                    } elseif ($sale->date_range === '7_days') {
                        $sales_l7 = $sale->sales ?? 0;
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
            'ad_type' => 'nullable|string',
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

    public function syncMetaAdsFromGoogleSheets()
    {
        try {
            // Get Apps Script web app URLs from environment
            $l30Url = env('META_ADS_L30_SCRIPT_URL');
            $l7Url = env('META_ADS_L7_SCRIPT_URL');
            
            if (!$l30Url || !$l7Url) {
                throw new \Exception('Apps Script URLs not configured. Please set META_ADS_L30_SCRIPT_URL and META_ADS_L7_SCRIPT_URL in .env');
            }
            
            // Sync L30 data
            $l30Count = $this->syncL30Data($l30Url);
            
            // Sync L7 data
            $l7Count = $this->syncL7Data($l7Url);
            
            return response()->json([
                'success' => true,
                'message' => 'Data synced successfully',
                'l30_synced' => $l30Count,
                'l7_synced' => $l7Count,
            ]);
        } catch (\Exception $e) {
            Log::error('Google Sheets Sync Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error syncing data: ' . $e->getMessage()
            ], 500);
        }
    }

    private function syncL30Data($csvUrl)
    {
        // Fetch CSV data from Google Sheets
        $response = @file_get_contents($csvUrl);
        
        if ($response === false) {
            throw new \Exception('Failed to fetch L30 data from Google Sheets. Make sure the sheet is shared as "Anyone with the link can view"');
        }
        
        // Parse CSV
        $lines = explode("\n", $response);
        
        if (empty($lines)) {
            throw new \Exception('Empty CSV data from L30 sheet');
        }
        
        // Find header row
        $header = null;
        $dataStartIndex = 0;
        
        foreach ($lines as $index => $line) {
            if (trim($line) === '') continue;
            
            $row = str_getcsv($line);
            $rowStr = strtolower(implode(',', $row));
            
            if (strpos($rowStr, 'campaign name') !== false && strpos($rowStr, 'campaign id') !== false) {
                $header = $row;
                $dataStartIndex = $index + 1;
                break;
            }
        }
        
        if (!$header) {
            throw new \Exception('Header row not found in L30 CSV. Expected columns: Campaign name, Campaign ID, etc.');
        }
        
        $processed = 0;
        
        for ($i = $dataStartIndex; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') continue;
            
            $row = str_getcsv($line);
            
            // Skip if row doesn't match header length
            if (count($row) < count($header)) continue;
            
            $rowData = array_combine($header, $row);
            if ($rowData === false) continue;
            
            $campaignName = trim($rowData['Campaign name'] ?? '');
            $campaignId = trim($rowData['Campaign ID'] ?? '');
            $campaignDelivery = strtolower(trim($rowData['Campaign delivery'] ?? 'inactive'));
            
            // Skip invalid rows
            if (!$campaignName || !$campaignId || $campaignId === '-' || strlen($campaignId) < 5) {
                continue;
            }
            
            $bgt = $this->parseNumericValue($rowData['Ad set budget'] ?? '0');
            $impL30 = $this->parseNumericValue($rowData['Impressions'] ?? '0');
            $spentL30 = $this->parseNumericValue($rowData['Amount spent (USD)'] ?? '0');
            $clicksL30 = $this->parseNumericValue($rowData['Link clicks'] ?? '0');
            
            MetaAllAd::updateOrCreate(
                ['campaign_name' => $campaignName],
                [
                    'campaign_id' => $campaignId,
                    'campaign_delivery' => $campaignDelivery,
                    'bgt' => $bgt,
                    'imp_l30' => $impL30,
                    'spent_l30' => $spentL30,
                    'clicks_l30' => $clicksL30,
                ]
            );
            $processed++;
        }
        
        return $processed;
    }

    private function syncL7Data($csvUrl)
    {
        // Fetch CSV data from Google Sheets
        $response = @file_get_contents($csvUrl);
        
        if ($response === false) {
            throw new \Exception('Failed to fetch L7 data from Google Sheets. Make sure the sheet is shared as "Anyone with the link can view"');
        }
        
        // Parse CSV
        $lines = explode("\n", $response);
        
        if (empty($lines)) {
            throw new \Exception('Empty CSV data from L7 sheet');
        }
        
        // Find header row
        $header = null;
        $dataStartIndex = 0;
        
        foreach ($lines as $index => $line) {
            if (trim($line) === '') continue;
            
            $row = str_getcsv($line);
            $rowStr = strtolower(implode(',', $row));
            
            if (strpos($rowStr, 'campaign name') !== false) {
                $header = $row;
                $dataStartIndex = $index + 1;
                break;
            }
        }
        
        if (!$header) {
            throw new \Exception('Header row not found in L7 CSV. Expected columns: Campaign name, Impressions, etc.');
        }
        
        $processed = 0;
        
        for ($i = $dataStartIndex; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') continue;
            
            $row = str_getcsv($line);
            
            // Skip if row doesn't match header length
            if (count($row) < count($header)) continue;
            
            $rowData = array_combine($header, $row);
            if ($rowData === false) continue;
            
            $campaignName = trim($rowData['Campaign name'] ?? '');
            
            // Skip invalid rows
            if (!$campaignName) {
                continue;
            }
            
            $impL7 = $this->parseNumericValue($rowData['Impressions'] ?? '0');
            $spentL7 = $this->parseNumericValue($rowData['Amount spent (USD)'] ?? '0');
            $clicksL7 = $this->parseNumericValue($rowData['Link clicks'] ?? '0');
            
            // Only update if campaign exists
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
        
        return $processed;
    }

    private function parseNumericValue($value)
    {
        if ($value === '' || $value === null) {
            return 0;
        }

        // Remove currency symbols and other non-numeric characters except comma, dot, and minus
        $numeric = preg_replace('/[^\d\.\-\,]/', '', $value);
        
        // Handle European format (comma as decimal separator)
        if (strpos($numeric, ',') !== false && strpos($numeric, '.') === false) {
            $numeric = str_replace(',', '.', str_replace('.', '', $numeric));
        } else {
            // Handle US format (comma as thousand separator)
            $numeric = str_replace(',', '', $numeric);
        }

        if (is_numeric($numeric)) {
            return $numeric + 0;
        }

        return 0;
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
                    } elseif ($sale->date_range === '60_days') {
                        $sales_l60 = $sale->sales ?? 0;
                    } elseif ($sale->date_range === '7_days') {
                        $sales_l7 = $sale->sales ?? 0;
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

    public function metaSingleImage()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Single Image')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.singleImage', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaSingleImageData()
    {
        $data = $this->getMetaAdsDataByType('Single Image');
        return response()->json(['message' => 'Single Image Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaSingleVideo()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Single Video')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.singleVideo', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaSingleVideoData()
    {
        $data = $this->getMetaAdsDataByType('Single Video');
        return response()->json(['message' => 'Single Video Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaCarousal()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Carousal')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.carousal', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaCarousalData()
    {
        $data = $this->getMetaAdsDataByType('Carousal');
        return response()->json(['message' => 'Carousal Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaExistingPost()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Existing Post')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.existingPost', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaExistingPostData()
    {
        $data = $this->getMetaAdsDataByType('Existing Post');
        return response()->json(['message' => 'Existing Post Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }

    public function metaCatalogueAd()
    {
        $latestUpdatedAt = MetaAllAd::where('ad_type', 'Catalogue Ad')->latest('updated_at')->first();
        $formattedDate = $latestUpdatedAt ? $latestUpdatedAt->updated_at->format('d F, Y. h:i:s A') : null;
        return view('marketing-masters.meta_ads_manager.catalogueAd', ['latestUpdatedAt' => $formattedDate]);
    }

    public function metaCatalogueAdData()
    {
        $data = $this->getMetaAdsDataByType('Catalogue Ad');
        return response()->json(['message' => 'Catalogue Ad Ads data fetched successfully', 'data' => $data, 'status' => 200]);
    }
}

