<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ADVMastersData;
use App\Models\ShopifySku;
use Illuminate\Http\Request;

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
        // Increase memory and execution time limits
        ini_set('memory_limit', '1024M');
        set_time_limit(300); // 5 minutes max
        
        // Only select necessary columns to reduce memory
        $productMasters = ProductMaster::select(['id', 'sku', 'parent'])
            ->orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Get amazon datasheet data - fetch all columns to avoid column name issues
        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        $shopifyData = ShopifySku::mapByProductSkus($skus);

        $nrListingValues = AmazonListingStatus::whereIn('sku', $skus)->pluck('value', 'sku');
        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // OPTIMIZATION: Fetch all campaigns in ONE query instead of chunking with LIKE
        // This is much faster than multiple LIKE queries
        $allCampaigns = AmazonSpCampaignReport::select(['id', 'campaignName', 'campaignStatus', 'ad_type'])
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        // Helper function to normalize campaign names and SKUs
        $normalizeString = function($str) {
            // Replace non-breaking spaces and other unicode spaces with regular spaces
            $normalized = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $str);
            // Remove trailing dots, normalize spaces, uppercase
            return preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($normalized, '.'))));
        };

        // OPTIMIZATION: Pre-process campaigns into lookup maps for O(1) access
        // Create maps: normalized SKU => campaign object
        $kwCampaignMap = []; // For KW campaigns (exact SKU match)
        $ptCampaignMap = []; // For PT campaigns (SKU + PT match)

        foreach ($allCampaigns as $campaign) {
            $campaignNameUpper = strtoupper($campaign->campaignName);
            
            // Skip FBA campaigns
            if (str_contains($campaignNameUpper, 'FBA') || str_contains($campaignNameUpper, 'FBA.')) {
                continue;
            }

            $normalizedCampaignName = $normalizeString($campaign->campaignName);
            
            // Check if it's a PT campaign
            $hasPt = str_contains($campaignNameUpper, ' PT') || str_contains($campaignNameUpper, ' PT.') ||
                     str_contains($campaignNameUpper, 'PT') || str_contains($campaignNameUpper, 'PT.');

            if ($hasPt) {
                // PT campaign: extract base SKU by removing PT suffix
                // Handle: "SKU PT", "SKU PT.", "SKUPT", "SKUPT."
                $baseSku = preg_replace('/\s*PT\.?\s*$/i', '', $normalizedCampaignName);
                // Also try without space before PT
                if ($baseSku === $normalizedCampaignName) {
                    $baseSku = preg_replace('/PT\.?\s*$/i', '', $normalizedCampaignName);
                }
                if ($baseSku && $baseSku !== $normalizedCampaignName) {
                    $ptCampaignMap[$baseSku] = $campaign;
                }
            } else {
                // KW campaign: use normalized name as key (exact match)
                $kwCampaignMap[$normalizedCampaignName] = $campaign;
            }
        }

        // Clear memory
        unset($allCampaigns);

        $result = [];

        foreach ($productMasters as $pm) {
            // Normalize SKU
            $normalizedSku = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku);
            $sku = preg_replace('/\s+/', ' ', strtoupper(trim($normalizedSku)));
            $skuUpper = strtoupper($pm->sku); // Keep original uppercase for lookups
            $parent = $pm->parent;

            $amazonSheet = $amazonDatasheetsBySku[$skuUpper] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            // OPTIMIZATION: O(1) lookup instead of O(n) first() call
            $normalizedSkuForLookup = $normalizeString($pm->sku);
            $matchedKwCampaign = $kwCampaignMap[$normalizedSkuForLookup] ?? null;
            $matchedPtCampaign = $ptCampaignMap[$normalizedSkuForLookup] ?? null;

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
}
