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
use Illuminate\Support\Facades\Log;

class AmazonFbaAdsController extends Controller
{
    public function amzFbaUtilizedBgtKw()
    {
        return view('campaign.amazon-fba-ads.amz-over-utilized-bgt-kw');
    }

    public function amzFbaUtilizedBgtPt()
    {
        return view('campaign.amazon-fba-ads.amz-over-utilized-bgt-pt');
    }

    public function amzFbaUnderUtilizedBgtKw()
    {
        return view('campaign.amazon-fba-ads.amz-under-utilized-bgt-kw');
    }

    public function amzFbaUnderUtilizedBgtPt()
    {
        return view('campaign.amazon-fba-ads.amz-under-utilized-bgt-pt');
    }

    public function amzFbaCorrectlyUtilizedBgtKw()
    {
        return view('campaign.amazon-fba-ads.amz-correctly-utilized-bgt-kw');
    }

    public function amzFbaCorrectlyUtilizedBgtPt()
    {
        return view('campaign.amazon-fba-ads.amz-correctly-utilized-bgt-pt');
    }

    public function getAmazonFbaKwAdsData(){
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

        $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L15')
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

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
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

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );            
            });

            $matchedCampaign15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $row = [];
            $row['parent'] = '';
            $row['sku']    = $sellerSku;
            $row['INV']    = $fba->quantity_available ?? 0;
            $row['A_L30']    = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['campaign_id'] = $matchedCampaignL30->campaign_id ?? ($matchedCampaign15->campaign_id ?? ($matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '')));
            $row['campaignName'] = $matchedCampaignL30->campaignName ?? ($matchedCampaign15->campaignName ?? ($matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '')));
            $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? ($matchedCampaign15->campaignStatus ?? ($matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '')));
            $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? ($matchedCampaign15->campaignBudgetAmount ?? ($matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '')));
            $row['sbid'] = $matchedCampaignL30->sbid ?? ($matchedCampaign15->sbid ?? ($matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '')));
            $row['crnt_bid'] = $matchedCampaignL30->currentSpBidPrice ?? ($matchedCampaign15->currentSpBidPrice ?? ($matchedCampaignL7->currentSpBidPrice ?? ($matchedCampaignL1->currentSpBidPrice ?? '')));
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;
            
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
            $row['clicks_L15'] = $matchedCampaign15->clicks ?? 0;
            $row['clicks_L7'] = $matchedCampaignL7->clicks ?? 0;

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            // Use seller_sku (with FBA) for manual data lookup
            $sellerSku = $fba->seller_sku ?? '';
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

            if($row['campaignName'] != '') {
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

    public function getAmazonFbaPtAdsData(){
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

        $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L15')
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

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
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

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return str_contains($cleanName, $sellerSkuUpper);
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return str_contains($cleanName, $sellerSkuUpper);
            });

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return str_contains($cleanName, $sellerSkuUpper);
           });

            $matchedCampaign15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return str_contains($cleanName, $sellerSkuUpper);
            });

            $row = [];
            $row['parent'] = '';
            $row['sku']    = $sellerSku;
            $row['INV']    = $fba->quantity_available ?? 0;
            $row['A_L30']    = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['campaign_id'] = $matchedCampaignL30->campaign_id ?? ($matchedCampaign15->campaign_id ?? ($matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '')));
            $row['campaignName'] = $matchedCampaignL30->campaignName ?? ($matchedCampaign15->campaignName ?? ($matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '')));
            $row['campaignStatus'] = $matchedCampaignL30->campaignStatus ?? ($matchedCampaign15->campaignStatus ?? ($matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '')));
            $row['campaignBudgetAmount'] = $matchedCampaignL30->campaignBudgetAmount ?? ($matchedCampaign15->campaignBudgetAmount ?? ($matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '')));
            $row['sbid'] = $matchedCampaignL30->sbid ?? ($matchedCampaign15->sbid ?? ($matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '')));
            $row['crnt_bid'] = $matchedCampaignL30->currentSpBidPrice ?? ($matchedCampaign15->currentSpBidPrice ?? ($matchedCampaignL7->currentSpBidPrice ?? ($matchedCampaignL1->currentSpBidPrice ?? '')));
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;
            
            $row['acos_L30'] = ($matchedCampaignL30 && ($matchedCampaignL30->sales30d ?? 0) > 0)
                ? round(($matchedCampaignL30->spend / $matchedCampaignL30->sales30d) * 100, 2)
                : null;

            $row['acos_L15'] = ($matchedCampaign15 && ($matchedCampaign15->sales14d ?? 0) > 0)
                ? round(($matchedCampaign15->spend / $matchedCampaign15->sales14d) * 100, 2)
                : null;

            $row['acos_L7'] = ($matchedCampaignL7 && ($matchedCampaignL7->sales7d ?? 0) > 0)
                ? round(($matchedCampaignL7->spend / $matchedCampaignL7->sales7d) * 100, 2)
                : null;


            $row['clicks_L30'] = $matchedCampaignL30->clicks ?? 0;
            $row['clicks_L15'] = $matchedCampaign15->clicks ?? 0;
            $row['clicks_L7'] = $matchedCampaignL7->clicks ?? 0;

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            // Use seller_sku (with FBA) for manual data lookup
            $sellerSku = $fba->seller_sku ?? '';
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

            if($row['campaignName'] != '') {
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

    public function updateNrNRLFbaData(Request $request)
    {
        $sku   = $request->input('sku');
        $field = $request->input('field');
        $value = $request->input('value');

        $amazonDataView = FbaManualData::where('sku', $sku)->first();

        $jsonData = $amazonDataView && $amazonDataView->value ? $amazonDataView->value : [];

        $jsonData[$field] = $value;

        $amazonDataView = FbaManualData::updateOrCreate(
            ['sku' => $sku],
            ['data' => $jsonData]
        );

        return response()->json([
            'status' => 200,
            'message' => "...",
            'updated_json' => $jsonData
        ]);

    }

    // Consolidated FBA KW Utilized page
    public function amazonFbaUtilizedKwView()
    {
        return view('campaign.amazon-fba-ads.amazon-fba-utilized-kw');
    }

    public function getAmazonFbaUtilizedKwAdsData(Request $request)
    {
        $utilizationType = $request->get('type', 'over'); // over, under, correctly
        
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

        // Get all FBA KW campaigns (excluding PT)
        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L15')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
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

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            
            if (empty($campaignId) || empty($campaignName)) {
                continue;
            }

            // Check NRA filter
            $nra = '';
            $tpft = null;
            if (isset($nrValues[$sellerSku])) {
                $raw = $nrValues[$sellerSku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                    $tpft = $raw['TPFT'] ?? null;
                }
            }

            if ($nra === 'NRA') {
                continue;
            }

            $budget = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
            $l7_spend = $matchedCampaignL7->spend ?? 0;
            $l1_spend = $matchedCampaignL1->spend ?? 0;

            // Calculate UB7 and UB1
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // Don't filter by utilization type here - let frontend handle it

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $matchedCampaign15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                return (
                    str_contains($cleanName, $sellerSkuUpper)
                    && !str_ends_with($cleanName, ' PT')
                    && !str_ends_with($cleanName, ' PT.')
                );
            });

            $row = [];
            $row['parent'] = '';
            $row['sku']    = $sellerSku;
            $row['INV']    = $fba->quantity_available ?? 0;
            $row['A_L30']    = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $campaignName;
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $budget;
            $row['sbid'] = $matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '');
            $row['crnt_bid'] = $matchedCampaignL7->currentSpBidPrice ?? ($matchedCampaignL1->currentSpBidPrice ?? '');
            $row['l7_spend'] = $l7_spend;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $l1_spend;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;
            
            $sales30 = $matchedCampaignL30->sales30d ?? 0;
            $spend30 = $matchedCampaignL30->spend ?? 0;
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
            $row['acos'] = $row['acos_L30']; // Set main acos field

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
            $row['clicks_L15'] = $matchedCampaign15->clicks ?? 0;
            $row['clicks_L7'] = $matchedCampaignL7->clicks ?? 0;

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            if ($sellerSku && isset($nrValues[$sellerSku])) {
                $raw = $nrValues[$sellerSku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                }
            }
            $row['TPFT'] = $tpft;

            $row['ub7'] = round($ub7, 2);
            $row['ub1'] = round($ub1, 2);

            $result[] = (object) $row;
        }

        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
    }

    public function getAmazonFbaUtilizationCounts(Request $request)
    {
        $campaignType = $request->get('type', 'KW'); // KW or PT
        
        try {
            $today = now()->format('Y-m-d');
            $skuKey = 'AMAZON_FBA_UTILIZATION_' . $campaignType . '_' . $today;
            
            $record = AmazonDataView::where('sku', $skuKey)->first();
            
            // Check if record exists and has valid data (not blank/zero data)
            // Blank data (all counts = 0) is inserted for next date, so we should calculate directly
            $isValidRecord = false;
            if ($record) {
                $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
                // Check if any count is greater than 0 (valid data)
                $totalCount = ($value['over_utilized_7ub'] ?? 0) + 
                             ($value['under_utilized_7ub'] ?? 0) + 
                             ($value['correctly_utilized_7ub'] ?? 0) +
                             ($value['over_utilized_7ub_1ub'] ?? 0) + 
                             ($value['under_utilized_7ub_1ub'] ?? 0) + 
                             ($value['correctly_utilized_7ub_1ub'] ?? 0);
                $isValidRecord = $totalCount > 0;
            }

            // Only use stored data if it's valid (not blank/zero data)
            if ($isValidRecord && $record) {
                $value = is_array($record->value) ? $record->value : json_decode($record->value, true);
                return response()->json([
                    // 7UB only condition
                    'over_utilized_7ub' => $value['over_utilized_7ub'] ?? 0,
                    'under_utilized_7ub' => $value['under_utilized_7ub'] ?? 0,
                    'correctly_utilized_7ub' => $value['correctly_utilized_7ub'] ?? 0,
                    // 7UB + 1UB condition
                    'over_utilized_7ub_1ub' => $value['over_utilized_7ub_1ub'] ?? 0,
                    'under_utilized_7ub_1ub' => $value['under_utilized_7ub_1ub'] ?? 0,
                    'correctly_utilized_7ub_1ub' => $value['correctly_utilized_7ub_1ub'] ?? 0,
                    'status' => 200,
                ]);
            }

            // If no valid data for today (blank/zero data or no record), calculate from current data
            $overCount = 0;
            $underCount = 0;
            $correctlyCount = 0;

            if ($campaignType === 'PT') {
                // Get data for each type (PT)
                $overRequest = new Request(['type' => 'over']);
                $overResponse = $this->getAmazonFbaUtilizedPtAdsData($overRequest);
                $overData = json_decode($overResponse->getContent(), true);
                $overCount = count($overData['data'] ?? []);

                $underRequest = new Request(['type' => 'under']);
                $underResponse = $this->getAmazonFbaUtilizedPtAdsData($underRequest);
                $underData = json_decode($underResponse->getContent(), true);
                $underCount = count($underData['data'] ?? []);

                $correctlyRequest = new Request(['type' => 'correctly']);
                $correctlyResponse = $this->getAmazonFbaUtilizedPtAdsData($correctlyRequest);
                $correctlyData = json_decode($correctlyResponse->getContent(), true);
                $correctlyCount = count($correctlyData['data'] ?? []);
            } else {
                // Get data for each type (KW)
                $overRequest = new Request(['type' => 'over']);
                $overResponse = $this->getAmazonFbaUtilizedKwAdsData($overRequest);
                $overData = json_decode($overResponse->getContent(), true);
                $overCount = count($overData['data'] ?? []);

                $underRequest = new Request(['type' => 'under']);
                $underResponse = $this->getAmazonFbaUtilizedKwAdsData($underRequest);
                $underData = json_decode($underResponse->getContent(), true);
                $underCount = count($underData['data'] ?? []);

                $correctlyRequest = new Request(['type' => 'correctly']);
                $correctlyResponse = $this->getAmazonFbaUtilizedKwAdsData($correctlyRequest);
                $correctlyData = json_decode($correctlyResponse->getContent(), true);
                $correctlyCount = count($correctlyData['data'] ?? []);
            }

            // For backward compatibility, also return old format
            return response()->json([
                'over_utilized' => $overCount,
                'under_utilized' => $underCount,
                'correctly_utilized' => $correctlyCount,
                // 7UB only condition (for chart)
                'over_utilized_7ub' => $overCount,
                'under_utilized_7ub' => $underCount,
                'correctly_utilized_7ub' => $correctlyCount,
                // 7UB + 1UB condition (for chart)
                'over_utilized_7ub_1ub' => $overCount,
                'under_utilized_7ub_1ub' => $underCount,
                'correctly_utilized_7ub_1ub' => $correctlyCount,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getAmazonFbaUtilizationCounts: ' . $e->getMessage());
            return response()->json([
                'over_utilized' => 0,
                'under_utilized' => 0,
                'correctly_utilized' => 0,
                'status' => 500,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAmazonFbaUtilizationChartData(Request $request)
    {
        $campaignType = $request->get('type', 'KW'); // KW or PT
        
        // Fetch historical data from database
        $data = AmazonDataView::where('sku', 'LIKE', 'AMAZON_FBA_UTILIZATION_' . $campaignType . '_%')
            ->orderBy('sku', 'desc')
            ->limit(30)
            ->get();
        
        $condition = $request->get('condition', '7ub'); // Default to 7ub, can be '7ub-1ub'
        
        $data = $data->map(function ($item) use ($campaignType, $condition) {
                $value = is_array($item->value) ? $item->value : json_decode($item->value, true);
                
                // Extract date from SKU
                $date = str_replace('AMAZON_FBA_UTILIZATION_' . $campaignType . '_', '', $item->sku);
                
                if ($condition === '7ub') {
                    return [
                        'date' => $date,
                        'over_utilized_7ub' => $value['over_utilized_7ub'] ?? 0,
                        'under_utilized_7ub' => $value['under_utilized_7ub'] ?? 0,
                        'correctly_utilized_7ub' => $value['correctly_utilized_7ub'] ?? 0,
                    ];
                } else {
                    return [
                        'date' => $date,
                        'over_utilized_7ub_1ub' => $value['over_utilized_7ub_1ub'] ?? 0,
                        'under_utilized_7ub_1ub' => $value['under_utilized_7ub_1ub'] ?? 0,
                        'correctly_utilized_7ub_1ub' => $value['correctly_utilized_7ub_1ub'] ?? 0,
                    ];
                }
            })
            ->reverse()
            ->values();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $data,
            'status' => 200,
        ]);
    }

    // Consolidated FBA PT Utilized page
    public function amazonFbaUtilizedPtView()
    {
        return view('campaign.amazon-fba-ads.amazon-fba-utilized-pt');
    }

    public function getAmazonFbaUtilizedPtAdsData(Request $request)
    {
        $utilizationType = $request->get('type', 'over'); // over, under, correctly
        
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

        // Get all FBA PT campaigns
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
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L15')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
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
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];
        $processedSkus = []; // For PT campaigns to ensure unique SKUs

        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));
            
            // Get base SKU (without FBA)
            $baseSku = preg_replace('/\s*FBA\s*/i', '', $sellerSku);
            $baseSkuUpper = strtoupper(trim($baseSku));

            // For PT campaigns, apply unique SKU filter
            if (in_array($baseSkuUpper, $processedSkus)) {
                continue;
            }

            $shopify = $shopifyData[$baseSkuUpper] ?? null;
            $monthlySales = $fbaMonthlySales->get($sellerSkuUpper);

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sellerSkuUpper, '.')));
                $expected = $cleanSku . ' PT';
                return $cleanName === $expected || $cleanName === ($expected . '.');
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sellerSkuUpper, '.')));
                $expected = $cleanSku . ' PT';
                return $cleanName === $expected || $cleanName === ($expected . '.');
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            
            if (empty($campaignId) || empty($campaignName)) {
                continue;
            }

            // Check NRA filter
            $nra = '';
            $tpft = null;
            if (isset($nrValues[$sellerSku])) {
                $raw = $nrValues[$sellerSku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                    $tpft = $raw['TPFT'] ?? null;
                }
            }

            if ($nra === 'NRA') {
                continue;
            }

            // Mark SKU as processed (unique filter)
            $processedSkus[] = $baseSkuUpper;

            $budget = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
            $l7_spend = $matchedCampaignL7->spend ?? 0;
            $l1_spend = $matchedCampaignL1->spend ?? 0;

            // Calculate UB7 and UB1
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // Don't filter by utilization type here - let frontend handle it

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sellerSkuUpper, '.')));
                $expected = $cleanSku . ' PT';
                return $cleanName === $expected || $cleanName === ($expected . '.');
            });

            $matchedCampaign15 = $amazonSpCampaignReportsL15->first(function ($item) use ($sellerSkuUpper) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sellerSkuUpper, '.')));
                $expected = $cleanSku . ' PT';
                return $cleanName === $expected || $cleanName === ($expected . '.');
            });

            $row = [];
            $row['parent'] = '';
            $row['sku']    = $sellerSku;
            $row['INV']    = $fba->quantity_available ?? 0;
            $row['A_L30']    = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $campaignName;
            $row['campaignStatus'] = $matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? '');
            $row['campaignBudgetAmount'] = $budget;
            $row['sbid'] = $matchedCampaignL7->sbid ?? ($matchedCampaignL1->sbid ?? '');
            $row['crnt_bid'] = $matchedCampaignL7->currentSpBidPrice ?? ($matchedCampaignL1->currentSpBidPrice ?? '');
            $row['l7_spend'] = $l7_spend;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $l1_spend;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;
            
            $sales30 = $matchedCampaignL30->sales30d ?? 0;
            $spend30 = $matchedCampaignL30->spend ?? 0;
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
            $row['acos'] = $row['acos_L30']; // Set main acos field

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
            $row['clicks_L15'] = $matchedCampaign15->clicks ?? 0;
            $row['clicks_L7'] = $matchedCampaignL7->clicks ?? 0;

            $row['NRL']  = '';
            $row['NRA'] = '';
            $row['FBA'] = '';
            if ($sellerSku && isset($nrValues[$sellerSku])) {
                $raw = $nrValues[$sellerSku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRL']  = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                }
            }
            $row['TPFT'] = $tpft;

            $row['ub7'] = round($ub7, 2);
            $row['ub1'] = round($ub1, 2);

            $result[] = (object) $row;
        }

        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
    }

}
