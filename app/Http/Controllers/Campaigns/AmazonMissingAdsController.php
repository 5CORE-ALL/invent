<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ADVMastersData;
use App\Models\FbaManualData;
use App\Models\FbaMonthlySale;
use App\Models\FbaTable;
use App\Models\ShopifySku;
use GPBMetadata\Google\Api\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as FacadesLog;

class AmazonMissingAdsController extends Controller
{
    public function index()
    {
        return view('campaign.amazon-missing-ads');
    }

    public function getAmzonAdvSaveMissingData(Request $request)
    {
        return ADVMastersData::getAmzonAdvSaveMissingDataProceed($request);
    }

    public function getAmazonMissingAdsData(Request $request)
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

        $nrListingValues = AmazonListingStatus::whereIn('sku', $skus)->pluck('value', 'sku');
        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // Create uppercase SKU array for efficient matching (needed for query)
        $skuUpperArray = array_map('strtoupper', $skus);

        // Optimize: Get campaigns that might match any of our SKUs (instead of ALL campaigns)
        // This is much more efficient than loading all campaigns
        $allCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->where(function($q) use ($skuUpperArray) {
                foreach ($skuUpperArray as $skuUpper) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $skuUpper . '%');
                }
            })
            ->get();

        // Filter campaigns in memory - more efficient approach
        // Since query already filtered by SKU, we just need to separate KW and PT campaigns
        $amazonKwCampaigns = $allCampaigns->filter(function ($campaign) {
            $campaignName = strtoupper($campaign->campaignName);
            // Exclude PT and FBA campaigns - KW campaigns should NOT have PT or FBA
            // Check both with space and without space before PT
            return !str_contains($campaignName, ' PT') && 
                   !str_contains($campaignName, ' PT.') &&
                   !str_contains($campaignName, 'PT') && 
                   !str_contains($campaignName, 'PT.') &&
                   !str_contains($campaignName, 'FBA') && 
                   !str_contains($campaignName, 'FBA.');
        });

        $amazonPtCampaigns = $allCampaigns->filter(function ($campaign) {
            $campaignName = strtoupper($campaign->campaignName);
            // Must contain PT but NOT FBA (regular PT campaigns, not FBA PT)
            // Check both with space and without space before PT
            $hasPt = str_contains($campaignName, ' PT') || str_contains($campaignName, ' PT.') ||
                     str_contains($campaignName, 'PT') || str_contains($campaignName, 'PT.');
            $hasFba = str_contains($campaignName, 'FBA') || str_contains($campaignName, 'FBA.');
            // Must have PT and NOT have FBA
            return $hasPt && !$hasFba;
        });

        $result = [];

        foreach ($productMasters as $pm) {
            // Normalize SKU first - normalize spaces (including non-breaking spaces) and convert to uppercase
            // Replace non-breaking spaces (UTF-8 c2a0) and other unicode spaces with regular spaces
            $normalizedSku = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku);
            $sku = preg_replace('/\s+/', ' ', strtoupper(trim($normalizedSku)));
            $skuUpper = strtoupper($pm->sku); // Keep original uppercase for lookups
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$skuUpper] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedKwCampaign = $amazonKwCampaigns->first(function ($item) use ($sku) {
                // Normalize spaces: replace non-breaking spaces and multiple spaces with single space
                $campaignNameRaw = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                $campaignName = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($campaignNameRaw, '.'))));
                $cleanSku = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($sku, '.'))));
                
                // Exact match only - campaign name must exactly equal SKU (excluding PT campaigns)
                return $campaignName === $cleanSku;
            });

            $matchedPtCampaign = $amazonPtCampaigns->first(function ($item) use ($sku) {
                // Normalize spaces: replace non-breaking spaces and multiple spaces with single space
                $campaignNameRaw = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($campaignNameRaw)));
                $cleanSku = preg_replace('/\s+/', ' ', strtoupper(trim($sku)));

                // Exact match: SKU + ' PT' or SKU + ' PT.' (with space)
                // Also check: SKU + 'PT' or SKU + 'PT.' (without space)
                $expected1 = $cleanSku . ' PT';
                $expected2 = $cleanSku . ' PT.';
                $expected3 = $cleanSku . 'PT';
                $expected4 = $cleanSku . 'PT.';
                
                return ($cleanName === $expected1 || $cleanName === $expected2 || 
                        $cleanName === $expected3 || $cleanName === $expected4);
            });

            $row = [
                'parent' => $parent,
                'sku' => $pm->sku,
                'INV' => $shopify->inv ?? 0,
                'L30' => $shopify->quantity ?? 0,
                'A_L30' => $amazonSheet->units_ordered_l30 ?? 0,
                'kw_campaign_name' => $matchedKwCampaign ? $matchedKwCampaign->campaignName : '',
                'pt_campaign_name' => $matchedPtCampaign ? $matchedPtCampaign->campaignName : '',
                'campaignStatus' => $matchedKwCampaign ? $matchedKwCampaign->campaignStatus : '',
                'NRL' => '',
                'NRA' => '',
                'FBA' => '',
            ];

            if (isset($nrListingValues[$pm->sku])) {
                $rawListing = $nrListingValues[$pm->sku];
                if (!is_array($rawListing)) {
                    $rawListing = json_decode($rawListing, true);
                }
                if (is_array($rawListing)) {
                    $row['NRL'] = $rawListing['nr_req'] ?? null;
                }
            }

            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
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

    public function fbaMissingAdsView()
    {
        return view('campaign.amazon-fba-ads.amazon-fba-missing-ads');
    }


    public function getAmazonFbaMissingAdsData()
    {
        // Get all FBA records
        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->orderBy('seller_sku', 'asc')
            ->get();

        // Extract seller SKUs for campaigns matching
        $sellerSkus = $fbaData->pluck('seller_sku')->unique()->toArray();

        // Get base SKUs (without FBA) for other data
        $baseSkus = $fbaData->map(function ($item) {
            $sku = $item->seller_sku;
            $base = preg_replace('/\s*FBA\s*/i', '', $sku);
            return strtoupper(trim($base));
        })->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $baseSkus)
            ->get()
            ->keyBy(function ($item) {
                return trim(strtoupper($item->sku));
            });

        $fbaMonthlySales = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->get()
            ->keyBy(function ($item) {
                return strtoupper(trim($item->seller_sku));
            });

        // Use seller_sku (with FBA) for manual data lookup
        $nrValues = FbaManualData::whereIn('sku', $sellerSkus)->pluck('data', 'sku');

        // Match campaigns using seller_sku (with FBA)
        $amazonKwCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonPtCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                ->orWhere('campaignName', 'LIKE', '%FBA PT.%')
                ->orWhere('campaignName', 'LIKE', '%FBAPT%')
                ->orWhere('campaignName', 'LIKE', '%FBAPT.%');
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];

        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));
            
            // Get base SKU (without FBA)
            $baseSku = preg_replace('/\s*FBA\s*/i', '', $sellerSku);
            $baseSkuUpper = strtoupper(trim($baseSku));

            $shopify = $shopifyData[$baseSkuUpper] ?? null;
            $monthlySales = $fbaMonthlySales->get($sellerSkuUpper);

            // Match campaigns using seller_sku (with FBA already in it)
            // For KW: exact match with seller SKU (excluding PT)
            $matchedKwCampaign = $amazonKwCampaigns->first(function ($item) use ($sellerSkuUpper) {
                // Normalize spaces: replace multiple spaces with single space
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($item->campaignName, '.'))));
                $cleanSku = preg_replace('/\s+/', ' ', $sellerSkuUpper);
                
                // Exact match: campaign name must exactly equal seller SKU
                return $cleanName === $cleanSku;
            });

            // For PT: exact match with seller SKU + ' PT' or seller SKU + ' PT.'
            // Also check without space: seller SKU + 'PT' or seller SKU + 'PT.'
            $matchedPtCampaign = $amazonPtCampaigns->first(function ($item) use ($sellerSkuUpper) {
                // Normalize spaces: replace multiple spaces with single space
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($item->campaignName, '.'))));
                $cleanSku = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($sellerSkuUpper, '.'))));
                
                // Exact match: SKU + ' PT' or SKU + 'PT' (with and without space)
                $expected1 = $cleanSku . ' PT';
                $expected2 = $cleanSku . 'PT';
                
                return ($cleanName === $expected1 || $cleanName === $expected2);
            });

            $row = [
                'parent' => '',
                'sku' => $sellerSku,
                'INV' => $fba->quantity_available ?? 0,
                'A_L30' => $monthlySales ? ($monthlySales->l30_units ?? 0) : 0,
                'L30' => $shopify->quantity ?? 0,
                'kw_campaign_name' => $matchedKwCampaign->campaignName ?? '',
                'pt_campaign_name' => $matchedPtCampaign->campaignName ?? '',
                'campaignStatus' => $matchedKwCampaign->campaignStatus ?? ($matchedPtCampaign->campaignStatus ?? ''),
                'NRL' => '',
                'NRA' => '',
                'FBA' => '',
            ];

            // Check using seller_sku (with FBA)
            if (isset($nrValues[$sellerSku]) || isset($nrValues[$sellerSkuUpper])) {
                $raw = $nrValues[$sellerSku] ?? $nrValues[$sellerSkuUpper];
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
}
