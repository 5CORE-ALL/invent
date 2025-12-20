<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayDataView;
use App\Models\EbayThreeDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;

class Ebay3KeywordAdsController extends Controller
{
    public function ebay3KeywordAdsView(){
        return view('campaign.ebay-three.keyword-ads');
    }

    public function getEbay3KeywordAdsData()
    {
        $productMasters = ProductMaster::orderBy('parent')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues    = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // Get all campaigns, excluding generic ones
        $allCampaigns = Ebay3PriorityReport::where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $normalize = fn($value) => is_string($value) ? strtoupper(trim($value)) : $value;

        $periods = ['L7', 'L15', 'L30', 'L60'];
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = $normalize($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [
                'parent' => $parent,
                'sku'    => $pm->sku,
                'INV'    => $shopify->inv ?? 0,
                'L30'    => $shopify->quantity ?? 0,
                'NR'     => ''
            ];

            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) $row['NR'] = $raw['NR'] ?? null;
            }

            $matchedCampaigns = $allCampaigns->filter(function ($c) use ($sku, $normalize, $pm) {
                if (!$c->campaign_name) {
                    return false;
                }
                
                $campaignName = $normalize($c->campaign_name);
                
                // Exact match first (same as utilized page)
                if ($campaignName === $sku) {
                    return true;
                }
                
                // For PARENT campaigns, also try matching without "PARENT " prefix
                // (in case campaign is named "10 WF PP" not "PARENT 10 WF PP")
                if (str_starts_with($pm->sku, 'PARENT ')) {
                    $skuWithoutParent = str_replace('PARENT ', '', $pm->sku);
                    $skuWithoutParentNormalized = $normalize($skuWithoutParent);
                    if ($campaignName === $skuWithoutParentNormalized) {
                        return true;
                    }
                }
                
                return false;
            });


            if ($matchedCampaigns->isEmpty()) continue;

            // Group by campaign_id to get unique campaigns (same campaign can have multiple report ranges)
            $uniqueCampaigns = $matchedCampaigns->groupBy('campaign_id');
            
            // Get campaign name and budget from first campaign (budget is same for all report ranges)
            $firstCampaign = $matchedCampaigns->first();
            $row['campaignName'] = $firstCampaign->campaign_name ?? '';
            $row['campaignBudgetAmount'] = $firstCampaign->campaignBudgetAmount ?? 0;
            
            // Prioritize RUNNING status, otherwise use the first unique status
            $statuses = $matchedCampaigns->pluck('campaignStatus')->unique()->values();
            $runningStatus = $statuses->first(function ($status) {
                return strtoupper(trim($status)) === 'RUNNING';
            });
            $row['campaignStatus'] = $runningStatus ?? $statuses->first() ?? '';

            foreach ($periods as $period) {
                // Get data for this period - should only be one campaign per period
                $periodMatch = $matchedCampaigns->where('report_range', $period)->first();
                
                if ($periodMatch) {
                    $impressions = $periodMatch->cpc_impressions ?? 0;
                    $clicks = $periodMatch->cpc_clicks ?? 0;
                    $adFees = (float) str_replace('USD ', '', $periodMatch->cpc_ad_fees_payout_currency ?? '0');
                    $sales = (float) str_replace('USD ', '', $periodMatch->cpc_sale_amount_payout_currency ?? '0');
                    $unitsSold = $periodMatch->unitsSold ?? 0;
                } else {
                    $impressions = 0;
                    $clicks = 0;
                    $adFees = 0;
                    $sales = 0;
                    $unitsSold = 0;
                }

                $cpc  = $clicks > 0 ? ($adFees / $clicks) : 0;
                $acos = $sales > 0 ? ($adFees / $sales) * 100 : ($adFees > 0 ? 100 : 0);

                $row["impressions_" . strtolower($period)] = $impressions;
                $row["clicks_" . strtolower($period)]      = $clicks;
                $row["ad_sales_" . strtolower($period)]    = $sales;
                $row["ad_sold_" . strtolower($period)]     = $unitsSold;
                $row["spend_" . strtolower($period)]       = $adFees;
                $row["acos_" . strtolower($period)]        = $acos;
                $row["cpc_" . strtolower($period)]         = $cpc;
            }

            $result[] = (object) $row;
        }

        // Process campaigns that don't match ProductMaster SKUs (additional campaigns)
        // Get all campaign IDs that we've already processed
        $processedCampaignIds = [];
        foreach ($result as $row) {
            $campaignNames = explode(', ', $row->campaignName ?? '');
            foreach ($campaignNames as $name) {
                $name = trim($name);
                // Find campaign ID for this name
                $matched = $allCampaigns->first(function($c) use ($name) {
                    return trim($c->campaign_name ?? '') === $name;
                });
                if ($matched && $matched->campaign_id) {
                    $processedCampaignIds[] = $matched->campaign_id;
                }
            }
        }
        $processedCampaignIds = array_unique($processedCampaignIds);
        
        // Get all unique campaign IDs from allCampaigns
        $allCampaignIds = $allCampaigns->pluck('campaign_id')->unique()->filter()->values();
        
        // Find campaigns that haven't been processed
        foreach ($allCampaignIds as $campaignId) {
            // Skip if already processed
            if (in_array($campaignId, $processedCampaignIds)) {
                continue;
            }
            
            $campaignReports = $allCampaigns->where('campaign_id', $campaignId);
            if ($campaignReports->isEmpty()) {
                continue;
            }
            
            $firstCampaign = $campaignReports->first();
            $campaignName = strtoupper(trim($firstCampaign->campaign_name ?? ''));
            
            // Try to find matching SKU in ProductMaster for INV/L30 data
            $matchedSku = null;
            foreach ($productMasters as $pm) {
                if (strtoupper(trim($pm->sku)) === $campaignName) {
                    $matchedSku = $pm->sku;
                    break;
                }
            }
            
            $shopify = $matchedSku ? ($shopifyData[$matchedSku] ?? null) : null;
            $nrValue = '';
            if ($matchedSku && isset($nrValues[$matchedSku])) {
                $raw = $nrValues[$matchedSku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) $nrValue = $raw['NR'] ?? null;
            }
            
            $row = [
                'parent' => '',
                'sku'    => $firstCampaign->campaign_name ?? '',
                'INV'    => ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0,
                'L30'    => ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0,
                'NR'     => $nrValue,
                'campaignName' => $firstCampaign->campaign_name ?? '',
                'campaignBudgetAmount' => $campaignReports->sum('campaignBudgetAmount'),
            ];
            
            // Prioritize RUNNING status, otherwise use the first unique status
            $statuses = $campaignReports->pluck('campaignStatus')->unique()->values();
            $runningStatus = $statuses->first(function ($status) {
                return strtoupper(trim($status)) === 'RUNNING';
            });
            $row['campaignStatus'] = $runningStatus ?? $statuses->first() ?? '';
            
            foreach ($periods as $period) {
                $periodMatches = $campaignReports->where('report_range', $period);

                $impressions = $periodMatches->sum('cpc_impressions');
                $clicks      = $periodMatches->sum('cpc_clicks');
                $adFees      = $periodMatches->sum(fn($item) => (float) str_replace('USD ', '', $item->cpc_ad_fees_payout_currency ?? 0));
                $sales       = $periodMatches->sum(fn($item) => (float) str_replace('USD ', '', $item->cpc_sale_amount_payout_currency ?? 0));
                $unitsSold   = $periodMatches->sum('unitsSold');

                $cpc  = $clicks > 0 ? ($adFees / $clicks) : 0;
                $acos = $sales > 0 ? ($adFees / $sales) * 100 : ($adFees > 0 ? 100 : 0);

                $row["impressions_" . strtolower($period)] = $impressions;
                $row["clicks_" . strtolower($period)]      = $clicks;
                $row["ad_sales_" . strtolower($period)]    = $sales;
                $row["ad_sold_" . strtolower($period)]     = $unitsSold;
                $row["spend_" . strtolower($period)]       = $adFees;
                $row["acos_" . strtolower($period)]        = $acos;
                $row["cpc_" . strtolower($period)]         = $cpc;
            }

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function ebay3PriceLessThanThirtyAdsView(){
        return view('campaign.ebay-three.ebay-less-thirty-kw-ads');
    }

    public function ebay3PriceLessThanThirtyAdsData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayMetrics = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
            ->orderBy('report_range', 'asc')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = strtoupper(trim($pm->parent));
            $shopify = $shopifyData[$pm->sku] ?? null;

            // 🔹 Get NR
            $nrValue = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nrValue = $raw['NR'] ?? null;
                }
            }

            // 🔹 Match only campaigns whose name == sku (exact match)
            $matchedReports = $reports->filter(function ($item) use ($sku) {
                return stripos(trim($item->campaign_name ?? ''), $sku) === 0; // starts with
            });


            // skip if no exact match
            if ($matchedReports->isEmpty()) {
                continue;
            }

            foreach ($matchedReports as $campaign) {
                $row = [];
                $row['parent'] = $parent;
                $row['sku'] = $pm->sku;
                $row['report_range'] = $campaign->report_range;
                $row['campaign_id'] = $campaign->campaign_id ?? '';
                $row['campaignName'] = $campaign->campaign_name ?? '';
                $row['campaignBudgetAmount'] = $campaign->campaignBudgetAmount ?? 0;
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;
                $row['price'] = isset($ebayMetrics[$pm->sku]) ? ($ebayMetrics[$pm->sku]->ebay_price ?? 0) : 0;

                $adFees = (float) str_replace('USD ', '', $campaign->cpc_ad_fees_payout_currency ?? 0);
                $sales  = (float) str_replace('USD ', '', $campaign->cpc_sale_amount_payout_currency ?? 0);

                // L7 / L1 data
                $row['l7_spend'] = (float) str_replace('USD ', '', $campaign->report_range == 'L7' ? $campaign->cpc_ad_fees_payout_currency ?? 0 : 0);
                $row['l7_cpc']   = (float) str_replace('USD ', '', $campaign->report_range == 'L7' ? $campaign->cost_per_click ?? 0 : 0);
                $row['l1_spend'] = (float) str_replace('USD ', '', $campaign->report_range == 'L1' ? $campaign->cpc_ad_fees_payout_currency ?? 0 : 0);
                $row['l1_cpc']   = (float) str_replace('USD ', '', $campaign->report_range == 'L1' ? $campaign->cost_per_click ?? 0 : 0);

                // ACOS calc
                $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
                $row['acos'] = ($adFees > 0 && $sales == 0) ? 100 : round($acos, 2);

                $row['adFees'] = $adFees;
                $row['sales']  = $sales;
                $row['NR']     = $nrValue;

                // SBID logic
                if ($row['price'] < 30 && $row['price'] > 0) {
                    if ($row['price'] < 10) {
                        $row['sbid'] = 0.10;
                    } elseif ($row['price'] > 10 && $row['price'] <= 20) {
                        $row['sbid'] = 0.20;
                    } elseif ($row['price'] > 20 && $row['price'] <= 30) {
                        $row['sbid'] = 0.30;
                    }
                }

                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function ebay3MakeNewKwAdsView(){
        return view('campaign.ebay-three.make-new-kw-ads');
    }

    public function getEbay3MMakeNewKwAdsData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayCampaignReportsL7 = Ebay3PriorityReport::where('report_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL1 = Ebay3PriorityReport::where('report_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL30 = Ebay3PriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($sku) {
                return $item->campaign_name && stripos($item->campaign_name, $sku) !== false;
            });

            $matchedCampaignL1 = $ebayCampaignReportsL1->first(function ($item) use ($sku) {
                return $item->campaign_name && stripos($item->campaign_name, $sku) !== false;
            });

            $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                return $item->campaign_name && stripos($item->campaign_name, $sku) !== false;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['campaign_id'] = $matchedCampaignL7 ? ($matchedCampaignL7->campaign_id ?? '') : ($matchedCampaignL1 ? ($matchedCampaignL1->campaign_id ?? '') : '');
            $row['campaignName'] = $matchedCampaignL7 ? ($matchedCampaignL7->campaign_name ?? '') : ($matchedCampaignL1 ? ($matchedCampaignL1->campaign_name ?? '') : '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7 ? ($matchedCampaignL7->campaignBudgetAmount ?? '') : ($matchedCampaignL1 ? ($matchedCampaignL1->campaignBudgetAmount ?? '') : '');

            $adFees   = $matchedCampaignL30 ? (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0) : 0;
            $sales    = $matchedCampaignL30 ? (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0) : 0;

            $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
            
            if($adFees > 0 && $sales === 0){
                $row['acos'] = 100;
            }else{
                $row['acos'] = $acos;
            }

            $row['l7_spend'] = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0) : 0;
            $row['l7_cpc'] = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0) : 0;
            $row['l1_spend'] = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0) : 0;
            $row['l1_cpc'] = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0) : 0;

            $row['NR'] = '';
            $row['NRL'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['NRL'] = $raw['NRL'] ?? null;

                }
            }
            if ($row['campaignName'] === '' && ($row['NR'] !== 'NRA' && $row['NRL'] !== 'NRL')) {
                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }
}
