<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaManualData;
use App\Models\FbaMonthlySale;
use App\Models\FbaTable;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;

class AmazonFbaAcosController extends Controller
{
    public function amazonFbaAcosKwView(){
        return view('campaign.amazon-fba-ads.amazon-fba-acos-kw');
    }

    public function amazonFbaAcosKwControlData(){

        // Get all FBA records
        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->orderBy('seller_sku', 'asc')
            ->get();

        // Extract seller SKUs for campaigns matching
        $sellerSkus = $fbaData->pluck('seller_sku')->unique()->toArray();

        // Get base SKUs (without FBA) for Shopify data
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

        $nrValues = FbaManualData::whereIn('sku', $sellerSkus)->pluck('data', 'sku');

        // Get all FBA KW campaigns (excluding PT) - without SKU filter to show unmatched campaigns too
        $allCampaignsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where('campaignName', 'LIKE', '%FBA%')
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $allCampaignsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where('campaignName', 'LIKE', '%FBA%')
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];
        $matchedCampaignIds = []; // Track which campaigns are matched with SKUs
        $addedCampaignIds = []; // Track which campaign_ids have already been added to result

        // First, process campaigns that match with FBA SKUs
        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));
            
            // Get base SKU (without FBA)
            $baseSku = preg_replace('/\s*FBA\s*/i', '', $sellerSku);
            $baseSkuUpper = strtoupper(trim($baseSku));

            $shopify = $shopifyData[$baseSkuUpper] ?? null;
            $monthlySales = $fbaMonthlySales->get($sellerSkuUpper);

            // Match campaigns that contain the seller SKU (with FBA)
            $matchedCampaignsL30 = $allCampaignsL30->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $matchedCampaignsL7 = $allCampaignsL7->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $matchedCampaignL30 = $matchedCampaignsL30->first();
            $matchedCampaignL7 = $matchedCampaignsL7->first();
            
            // Skip if no campaign matched
            if (!$matchedCampaignL30 && !$matchedCampaignL7) {
                continue;
            }
            
            // Get campaign_id and check for duplicates
            $campaignId = $matchedCampaignL30->campaign_id ?? ($matchedCampaignL7->campaign_id ?? '');
            if (empty($campaignId) || in_array($campaignId, $addedCampaignIds)) {
                continue; // Skip duplicate campaign_id or empty campaign_id
            }
            
            $allCampaignNames = $matchedCampaignsL30->pluck('campaignName')->unique()->implode(' , ');

            $row = [];
            $row['parent'] = '';
            $row['sku']    = $sellerSku;
            $row['INV']    = $fba->quantity_available ?? 0;
            $row['A_L30']    = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = null;
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $allCampaignNames ?: ($matchedCampaignL30->campaignName ?? ($matchedCampaignL7->campaignName ?? ''));
            $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? ($matchedCampaignL7->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? ($matchedCampaignL7->campaignBudgetAmount ?? 0);
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            
            $sales = $matchedCampaignL30->sales30d ?? 0;
            $spend = $matchedCampaignL30->spend ?? 0;

            if ($sales > 0) {
                $row['acos_L30'] = round(($spend / $sales) * 100, 2);
            } elseif ($spend > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            // Use seller_sku (with FBA) for manual data lookup
            if ($sellerSku && isset($nrValues[$sellerSku])) {
                $raw = $nrValues[$sellerSku];
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

            if ($row['NRA'] !== 'NRA' && $row['campaignName'] !== '' && !empty($row['campaign_id'])) {
                // Only add if this campaign_id hasn't been added yet (prevent duplicates)
                if (!in_array($row['campaign_id'], $addedCampaignIds)) {
                    $result[] = (object) $row;
                    $addedCampaignIds[] = $row['campaign_id'];
                    $matchedCampaignIds[] = $row['campaign_id'];
                }
            }
        }

        // Now add campaigns that don't match with any FBA SKU
        // Merge L7 and L30, prioritizing L7 if duplicate campaign_id exists
        $allUniqueCampaigns = collect();
        $processedIds = [];
        
        // First add L7 campaigns
        foreach ($allCampaignsL7->unique('campaign_id') as $campaign) {
            $campaignId = $campaign->campaign_id ?? '';
            if (!empty($campaignId) && !in_array($campaignId, $processedIds)) {
                $allUniqueCampaigns->push($campaign);
                $processedIds[] = $campaignId;
            }
        }
        
        // Then add L30 campaigns that are not in L7
        foreach ($allCampaignsL30->unique('campaign_id') as $campaign) {
            $campaignId = $campaign->campaign_id ?? '';
            if (!empty($campaignId) && !in_array($campaignId, $processedIds)) {
                $allUniqueCampaigns->push($campaign);
                $processedIds[] = $campaignId;
            }
        }
        
        $matchedCampaignIds = array_unique($matchedCampaignIds);

        foreach ($allUniqueCampaigns as $campaign) {
            $campaignId = $campaign->campaign_id ?? '';
            
            // Skip if already matched with a SKU or already added
            if (empty($campaignId) || in_array($campaignId, $matchedCampaignIds) || in_array($campaignId, $addedCampaignIds)) {
                continue;
            }

            $campaignName = strtoupper(trim($campaign->campaignName ?? ''));
            if (empty($campaignName)) {
                continue;
            }

            // Check if this campaign name matches any FBA seller SKU
            $matchedSku = null;
            foreach ($sellerSkus as $sellerSku) {
                $sellerSkuUpper = strtoupper(trim($sellerSku));
                $cleanCampaignName = strtoupper(trim(rtrim($campaignName, '.')));
                if (str_contains($cleanCampaignName, $sellerSkuUpper) && 
                    !str_ends_with($cleanCampaignName, ' PT') && 
                    !str_ends_with($cleanCampaignName, ' PT.')) {
                    $matchedSku = $sellerSku;
                    break;
                }
            }

            // If no SKU match found, add as unmatched campaign
            if (!$matchedSku) {
                $matchedCampaignL30 = $allCampaignsL30->first(function ($item) use ($campaignId) {
                    return ($item->campaign_id ?? '') === $campaignId;
                });

                $matchedCampaignL7 = $allCampaignsL7->first(function ($item) use ($campaignId) {
                    return ($item->campaign_id ?? '') === $campaignId;
                });

                $row = [];
                $row['parent'] = '';
                $row['sku'] = '';
                $row['INV'] = 0;
                $row['A_L30'] = 0;
                $row['L30'] = 0;
                $row['fba'] = null;
                $row['campaign_id'] = $campaignId;
                $row['campaignName'] = $campaign->campaignName ?? '';
                $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? ($matchedCampaignL7->campaignStatus ?? '');
                $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? ($matchedCampaignL7->campaignBudgetAmount ?? 0);
                $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
                
                $sales = $matchedCampaignL30->sales30d ?? 0;
                $spend = $matchedCampaignL30->spend ?? 0;

                if ($sales > 0) {
                    $row['acos_L30'] = round(($spend / $sales) * 100, 2);
                } elseif ($spend > 0) {
                    $row['acos_L30'] = 100;
                } else {
                    $row['acos_L30'] = 0;
                }

                $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
                $row['NRL'] = '';
                $row['NRA'] = '';
                $row['FBA'] = '';
                $row['TPFT'] = null;

                // Add unmatched campaign (no NRA filter for unmatched campaigns)
                if ($row['campaignName'] !== '' && !in_array($row['campaign_id'], $addedCampaignIds)) {
                    $result[] = (object) $row;
                    $addedCampaignIds[] = $row['campaign_id'];
                }
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function amazonFbaAcosPtView(){
        return view('campaign.amazon-fba-ads.amazon-fba-acos-pt');
    }
    
    public function amazonFbaAcosPtControlData(){

        // Get all FBA records
        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->orderBy('seller_sku', 'asc')
            ->get();

        // Extract seller SKUs for campaigns matching
        $sellerSkus = $fbaData->pluck('seller_sku')->unique()->toArray();

        // Get base SKUs (without FBA) for Shopify data
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

        $nrValues = FbaManualData::whereIn('sku', $sellerSkus)->pluck('data', 'sku');

        // Get all FBA PT campaigns - without SKU filter to show unmatched campaigns too
        $allCampaignsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $allCampaignsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];
        $matchedCampaignIds = []; // Track which campaigns are matched with SKUs
        $addedCampaignIds = []; // Track which campaign_ids have already been added to result

        // First, process campaigns that match with FBA SKUs
        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));
            
            // Get base SKU (without FBA)
            $baseSku = preg_replace('/\s*FBA\s*/i', '', $sellerSku);
            $baseSkuUpper = strtoupper(trim($baseSku));

            $shopify = $shopifyData[$baseSkuUpper] ?? null;
            $monthlySales = $fbaMonthlySales->get($sellerSkuUpper);

            // Match campaigns that contain the seller SKU (with FBA)
            $matchedCampaignsL30 = $allCampaignsL30->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                return str_contains($cleanName, $sellerSkuUpper);
            });

            $matchedCampaignsL7 = $allCampaignsL7->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                return str_contains($cleanName, $sellerSkuUpper);
            });

            $matchedCampaignL30 = $matchedCampaignsL30->first();
            $matchedCampaignL7 = $matchedCampaignsL7->first();
            
            // Skip if no campaign matched
            if (!$matchedCampaignL30 && !$matchedCampaignL7) {
                continue;
            }
            
            // Get campaign_id and check for duplicates
            $campaignId = $matchedCampaignL30->campaign_id ?? ($matchedCampaignL7->campaign_id ?? '');
            if (empty($campaignId) || in_array($campaignId, $addedCampaignIds)) {
                continue; // Skip duplicate campaign_id or empty campaign_id
            }
            
            $allCampaignNames = $matchedCampaignsL30->pluck('campaignName')->unique()->implode(', ');

            $row = [];
            $row['parent'] = '';
            $row['sku']    = $sellerSku;
            $row['INV']    = $fba->quantity_available ?? 0;
            $row['A_L30']    = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = null;
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $allCampaignNames ?: ($matchedCampaignL30->campaignName ?? ($matchedCampaignL7->campaignName ?? ''));
            $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? ($matchedCampaignL7->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? ($matchedCampaignL7->campaignBudgetAmount ?? 0);
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            
            $sales = $matchedCampaignL30->sales30d ?? 0;
            $spend = $matchedCampaignL30->spend ?? 0;

            if ($sales > 0) {
                $row['acos_L30'] = round(($spend / $sales) * 100, 2);
            } elseif ($spend > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            // Use seller_sku (with FBA) for manual data lookup
            if ($sellerSku && isset($nrValues[$sellerSku])) {
                $raw = $nrValues[$sellerSku];
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

            if ($row['NRA'] !== 'NRA' && $row['campaignName'] !== '' && !empty($row['campaign_id'])) {
                // Only add if this campaign_id hasn't been added yet (prevent duplicates)
                if (!in_array($row['campaign_id'], $addedCampaignIds)) {
                    $result[] = (object) $row;
                    $addedCampaignIds[] = $row['campaign_id'];
                    $matchedCampaignIds[] = $row['campaign_id'];
                }
            }
        }

        // Now add campaigns that don't match with any FBA SKU
        $allUniqueCampaigns = $allCampaignsL7->unique('campaign_id')->merge($allCampaignsL30->unique('campaign_id'));
        $matchedCampaignIds = array_unique($matchedCampaignIds);

        foreach ($allUniqueCampaigns as $campaign) {
            $campaignId = $campaign->campaign_id ?? '';
            
            // Skip if already matched with a SKU or already added
            if (empty($campaignId) || in_array($campaignId, $matchedCampaignIds) || in_array($campaignId, $addedCampaignIds)) {
                continue;
            }

            $campaignName = strtoupper(trim($campaign->campaignName ?? ''));
            if (empty($campaignName)) {
                continue;
            }

            // Check if this campaign name matches any FBA seller SKU
            $matchedSku = null;
            foreach ($sellerSkus as $sellerSku) {
                $sellerSkuUpper = strtoupper(trim($sellerSku));
                $cleanCampaignName = strtoupper(trim(rtrim($campaignName, '.')));
                if (str_contains($cleanCampaignName, $sellerSkuUpper)) {
                    $matchedSku = $sellerSku;
                    break;
                }
            }

            // If no SKU match found, add as unmatched campaign
            if (!$matchedSku) {
                $matchedCampaignL30 = $allCampaignsL30->first(function ($item) use ($campaignId) {
                    return ($item->campaign_id ?? '') === $campaignId;
                });

                $matchedCampaignL7 = $allCampaignsL7->first(function ($item) use ($campaignId) {
                    return ($item->campaign_id ?? '') === $campaignId;
                });

                $row = [];
                $row['parent'] = '';
                $row['sku'] = '';
                $row['INV'] = 0;
                $row['A_L30'] = 0;
                $row['L30'] = 0;
                $row['fba'] = null;
                $row['campaign_id'] = $campaignId;
                $row['campaignName'] = $campaign->campaignName ?? '';
                $row['campaignStatus'] = $campaign->campaignStatus ?? '';
                $row['campaignBudgetAmount'] = $campaign->campaignBudgetAmount ?? 0;
                $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
                
                $sales = $matchedCampaignL30->sales30d ?? 0;
                $spend = $matchedCampaignL30->spend ?? 0;

                if ($sales > 0) {
                    $row['acos_L30'] = round(($spend / $sales) * 100, 2);
                } elseif ($spend > 0) {
                    $row['acos_L30'] = 100;
                } else {
                    $row['acos_L30'] = 0;
                }

                $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
                $row['NRL'] = '';
                $row['NRA'] = '';
                $row['FBA'] = '';
                $row['TPFT'] = null;

                // Add unmatched campaign (no NRA filter for unmatched campaigns)
                if ($row['campaignName'] !== '' && !in_array($row['campaign_id'], $addedCampaignIds)) {
                    $result[] = (object) $row;
                    $addedCampaignIds[] = $row['campaign_id'];
                }
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }
}
