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

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
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

            $matchedCampaignsL30 = $amazonSpCampaignReportsL30->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $matchedCampaignsL7 = $amazonSpCampaignReportsL7->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $matchedCampaignL30 = $matchedCampaignsL30->first();
            $matchedCampaignL7 = $matchedCampaignsL7->first();
            $allCampaignNames = $matchedCampaignsL30->pluck('campaignName')->unique()->implode(' , ');

            $row = [];
            $row['parent'] = '';
            $row['sku']    = $sellerSku;
            $row['INV']    = $fba->quantity_available ?? 0;
            $row['A_L30']    = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = null;
            $row['campaign_id'] = $matchedCampaignL30->campaign_id ??  '';
            $row['campaignName'] = $allCampaignNames;
            $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? '';
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

            if ($row['NRA'] !== 'NRA' && $row['campaignName'] !== '') {
                $result[] = (object) $row;
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

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            })
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            })
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

            $matchedCampaignsL30 = $amazonSpCampaignReportsL30->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return str_contains($cleanName, $sellerSkuUpper);
            });

            $matchedCampaignsL7 = $amazonSpCampaignReportsL7->filter(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return str_contains($cleanName, $sellerSkuUpper);
            });

            $matchedCampaignL30 = $matchedCampaignsL30->first();
            $matchedCampaignL7 = $matchedCampaignsL7->first();
            $allCampaignNames = $matchedCampaignsL30->pluck('campaignName')->unique()->implode(', ');

            $row = [];
            $row['parent'] = '';
            $row['sku']    = $sellerSku;
            $row['INV']    = $fba->quantity_available ?? 0;
            $row['A_L30']    = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['fba']    = null;
            $row['campaign_id'] = $matchedCampaignL30->campaign_id ??  '';
            $row['campaignName'] = $allCampaignNames;
            $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? '';
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

            if ($row['NRA'] !== 'NRA' && $row['campaignName'] !== '') {
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
