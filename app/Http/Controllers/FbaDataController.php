<?php

namespace App\Http\Controllers;

use App\Models\AmazonSpCampaignReport;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\FbaTable;
use App\Models\FbaPrice;
use App\Models\FbaReportsMaster;
use App\Models\FbaMonthlySale;
use App\Models\FbaManualData;
use App\Models\FbaOrder;
use App\Models\FbaShipCalculation;
use App\Services\ColorService;
use App\Services\FbaManualDataService;
use App\Services\LmpaDataService;
use App\Services\AmazonSpApiService;
use Illuminate\Support\Facades\Log;


class FbaDataController extends Controller
{
   protected $fbaManualDataService;
   protected $colorService;
   protected $lmpaDataService;

   public function __construct(
      FbaManualDataService $fbaManualDataService,
      ColorService $colorService,
      LmpaDataService $lmpaDataService
   ) {
      $this->fbaManualDataService = $fbaManualDataService;
      $this->colorService = $colorService;
      $this->lmpaDataService = $lmpaDataService;
   }

   private function getFbaData()
   {
      $productData = ProductMaster::whereNull('deleted_at')
         ->orderBy('id', 'asc')
         ->get();

      $skus = $productData
         ->pluck('sku')
         ->filter(function ($sku) {
            return stripos($sku, 'PARENT') === false;
         })
         ->unique()
         ->toArray();

      $shopifyData = ShopifySku::whereIn('sku', $skus)
         ->get()
         ->keyBy(function ($item) {
            return trim(strtoupper($item->sku));
         });

      $skus = array_map(function ($sku) {
         return strtoupper(trim($sku));
      }, $skus);

      $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
         ->get()
         ->keyBy(function ($item) {
            $sku = $item->seller_sku;
            $base = preg_replace('/\s*FBA\s*/i', '', $sku);
            return strtoupper(trim($base));
         });

      $fbaPriceData = FbaPrice::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
         ->get()
         ->keyBy(function ($item) {
            $sku = $item->seller_sku;
            $base = preg_replace('/\s*FBA\s*/i', '', $sku);
            return strtoupper(trim($base));
         });

      $fbaReportsData = FbaReportsMaster::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
         ->get()
         ->keyBy(function ($item) {
            $sku = $item->seller_sku;
            $base = preg_replace('/\s*FBA\s*/i', '', $sku);
            return strtoupper(trim($base));
         });

      $fbaMonthlySales = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
         ->get()
         ->keyBy(function ($item) {
            $sku = $item->seller_sku;
            $base = preg_replace('/\s*FBA\s*/i', '', $sku);
            return strtoupper(trim($base));
         });

      $fbaManualData = FbaManualData::all()->keyBy(function ($item) {
         return strtoupper(trim($item->sku));
      });

      $fbaDispatchDates = FbaOrder::all()->keyBy('sku');

      $fbaShipCalculations = FbaShipCalculation::all()->keyBy(function ($item) {
         return strtoupper(trim($item->sku));
      });


      $amazonSpCampaignReportsL60 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L60')
         ->where(function ($q) use ($skus) {
            foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
         })
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA%')
               ->orWhere('campaignName', 'LIKE', '%fba%')
               ->orWhere('campaignName', 'LIKE', '%FBA.%')
               ->orWhere('campaignName', 'LIKE', '%fba.%');
         })
         ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L30')
         ->where(function ($q) use ($skus) {
            foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
         })
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA%')
               ->orWhere('campaignName', 'LIKE', '%fba%')
               ->orWhere('campaignName', 'LIKE', '%FBA.%')
               ->orWhere('campaignName', 'LIKE', '%fba.%');
         })
         ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L15')
         ->where(function ($q) use ($skus) {
            foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
         })
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA%')
               ->orWhere('campaignName', 'LIKE', '%fba%')
               ->orWhere('campaignName', 'LIKE', '%FBA.%')
               ->orWhere('campaignName', 'LIKE', '%fba.%');
         })
         ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L7')
         ->where(function ($q) use ($skus) {
            foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
         })
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA%')
               ->orWhere('campaignName', 'LIKE', '%fba%')
               ->orWhere('campaignName', 'LIKE', '%FBA.%')
               ->orWhere('campaignName', 'LIKE', '%fba.%');
         })
         ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L1')
         ->where(function ($q) use ($skus) {
            foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
         })
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA%')
               ->orWhere('campaignName', 'LIKE', '%fba%')
               ->orWhere('campaignName', 'LIKE', '%FBA.%')
               ->orWhere('campaignName', 'LIKE', '%fba.%');
         })
         ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      $matchedSkus = $fbaData->keys()->toArray();
      $unmatchedSkus = array_diff($skus, $matchedSkus);

      return compact('productData', 'shopifyData', 'fbaData', 'fbaPriceData', 'fbaReportsData', 'matchedSkus', 'unmatchedSkus', 'fbaMonthlySales', 'fbaManualData', 'fbaDispatchDates', 'fbaShipCalculations', 'amazonSpCampaignReportsL60', 'amazonSpCampaignReportsL30', 'amazonSpCampaignReportsL15', 'amazonSpCampaignReportsL7', 'amazonSpCampaignReportsL1');
   }

   public function fbaPageView()
   {
      $data = $this->getFbaData();
      return view('fba.fba_views_data', $data);
   }

   public function fbaDispatchPageView()
   {
      $data = $this->getFbaData();
      return view('fba.fba_dispatch_data', $data);
   }


   public function fbaadskw()
   {
      $data = $this->getFbaData();
      return view('fba.fba_ads_kw', $data);
   }

   public function fbaAdsPt()
   {
      $data = $this->getFbaData();
      return view('fba.fba_ads_pt', $data);
   }


   public function fbaAdsDataJson()
   {
      $data = $this->getFbaData();

      $fbaData = $data['fbaData'];
      $shopifyData = $data['shopifyData'];
      $fbaMonthlySales = $data['fbaMonthlySales'];

      $productData = $data['productData']->keyBy(function ($p) {
         return strtoupper(trim($p->sku));
      });

      // Get all ads campaign reports
      $amazonSpCampaignReportsL60 = $data['amazonSpCampaignReportsL60'];
      $amazonSpCampaignReportsL30 = $data['amazonSpCampaignReportsL30'];
      $amazonSpCampaignReportsL15 = $data['amazonSpCampaignReportsL15'];
      $amazonSpCampaignReportsL7 = $data['amazonSpCampaignReportsL7'];
      $amazonSpCampaignReportsL1 = $data['amazonSpCampaignReportsL1'];

      // Create a map of SKU to ads data
      $adsDataBySku = [];

      foreach ($fbaData as $sku => $fba) {
         $adsDataBySku[$sku] = [
            'L60' => $amazonSpCampaignReportsL60->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
            'L30' => $amazonSpCampaignReportsL30->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
            'L15' => $amazonSpCampaignReportsL15->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
            'L7' => $amazonSpCampaignReportsL7->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
            'L1' => $amazonSpCampaignReportsL1->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
         ];
      }

      // Filter to only include SKUs that have ads data

      $tableData = $fbaData->filter(function ($fba, $sku) use ($adsDataBySku) {
         $ads = $adsDataBySku[$sku] ?? null;
         return $ads && ($ads['L60'] || $ads['L30'] || $ads['L15'] || $ads['L7'] || $ads['L1']);
      })->map(function ($fba, $sku) use ($shopifyData, $productData, $fbaMonthlySales, $adsDataBySku) {
         $shopifyInfo = $shopifyData->get($sku);
         $product = $productData->get($sku);
         $monthlySales = $fbaMonthlySales->get($sku);

         $ads = $adsDataBySku[$sku] ?? null;


         // Calculate ads percentage
         $adsL30 = $ads['L30'] ?? null;
         $adsL60 = $ads['L60'] ?? null;
         $adsL15 = $ads['L15'] ?? null;
         $adsL7 = $ads['L7'] ?? null;
         $adsL1 = $ads['L1'] ?? null;

         // Get campaign name from ads data
         $campaignName = '';
         if ($adsL30) {
            $campaignName = $adsL30->campaignName ?? '';
         } elseif ($adsL60) {
            $campaignName = $adsL60->campaignName ?? '';
         } elseif ($adsL15) {
            $campaignName = $adsL15->campaignName ?? '';
         } elseif ($adsL7) {
            $campaignName = $adsL7->campaignName ?? '';
         } elseif ($adsL1) {
            $campaignName = $adsL1->campaignName ?? '';
         }

         return [
            'Parent' => $product ? ($product->parent ?? '') : '',
            'SKU' => $sku,
            'Campaign_Name' => $campaignName,
            'FBA_Quantity' => $fba->quantity_available,
            'Shopify_INV' => $shopifyInfo ? ($shopifyInfo->inv ?? 0) : 0,
            'Shopify_OV_L30' => $shopifyInfo ? ($shopifyInfo->quantity ?? 0) : 0,
            'Dil' => ($shopifyInfo ? ($shopifyInfo->quantity ?? 0) : 0) / max($shopifyInfo ? ($shopifyInfo->inv ?? 0) : 1, 1) * 100,
            'l30_units' => $monthlySales ? ($monthlySales->l30_units ?? 0) : 0,
            'FBA_Dil' => ($monthlySales ? ($monthlySales->l30_units ?? 0) : 0) / ($fba->quantity_available ?: 1) * 100,
            'Ads_L30_Impressions' => $adsL30 ? ($adsL30->impressions ?? 0) : 0,
            'Ads_L60_Impressions' => $adsL60 ? ($adsL60->impressions ?? 0) : 0,
            'Ads_L15_Impressions' => $adsL15 ? ($adsL15->impressions ?? 0) : 0,
            'Ads_L7_Impressions' => $adsL7 ? ($adsL7->impressions ?? 0) : 0,
            'Ads_L30_Clicks' => $adsL30 ? ($adsL30->clicks ?? 0) : 0,
            'Ads_L60_Clicks' => $adsL60 ? ($adsL60->clicks ?? 0) : 0,
            'Ads_L15_Clicks' => $adsL15 ? ($adsL15->clicks ?? 0) : 0,
            'Ads_L7_Clicks' => $adsL7 ? ($adsL7->clicks ?? 0) : 0,
            'Ads_L30_Spend' => $adsL30 ? round(($adsL30->cost ?? 0), 2) : 0,
            'Ads_L60_Spend' => $adsL60 ? round(($adsL60->cost ?? 0), 2) : 0,
            'Ads_L15_Spend' => $adsL15 ? round(($adsL15->cost ?? 0), 2) : 0,
            'Ads_L7_Spend' => $adsL7 ? round(($adsL7->cost ?? 0), 2) : 0,
            'Ads_L30_Sales' => $adsL30 ? round(($adsL30->sales14d ?? 0), 2) : 0,
            'Ads_L60_Sales' => $adsL60 ? round(($adsL60->sales14d ?? 0), 2) : 0,
            'Ads_L15_Sales' => $adsL15 ? round(($adsL15->sales14d ?? 0), 2) : 0,
            'Ads_L7_Sales' => $adsL7 ? round(($adsL7->sales14d ?? 0), 2) : 0,
            'Ads_L30_Orders' => $adsL30 ? ($adsL30->purchases14d ?? 0) : 0,
            'Ads_L60_Orders' => $adsL60 ? ($adsL60->purchases14d ?? 0) : 0,
            'Ads_L15_Orders' => $adsL15 ? ($adsL15->purchases14d ?? 0) : 0,
            'Ads_L7_Orders' => $adsL7 ? ($adsL7->purchases14d ?? 0) : 0,
            'Ads_L30_ACOS' => $adsL30 && ($adsL30->sales14d ?? 0) > 0 ? round((($adsL30->cost ?? 0) / ($adsL30->sales14d ?? 1)) * 100, 2) : 0,
            'Ads_L60_ACOS' => $adsL60 && ($adsL60->sales14d ?? 0) > 0 ? round((($adsL60->cost ?? 0) / ($adsL60->sales14d ?? 1)) * 100, 2) : 0,
            'Ads_L15_ACOS' => $adsL15 && ($adsL15->sales14d ?? 0) > 0 ? round((($adsL15->cost ?? 0) / ($adsL15->sales14d ?? 1)) * 100, 2) : 0,
            'Ads_L7_ACOS' => $adsL7 && ($adsL7->sales14d ?? 0) > 0 ? round((($adsL7->cost ?? 0) / ($adsL7->sales14d ?? 1)) * 100, 2) : 0,
            'Ads_L30_CPC' => $adsL30 && ($adsL30->clicks ?? 0) > 0 ? round(($adsL30->cost ?? 0) / ($adsL30->clicks ?? 1), 2) : 0,
            'Ads_L60_CPC' => $adsL60 && ($adsL60->clicks ?? 0) > 0 ? round(($adsL60->cost ?? 0) / ($adsL60->clicks ?? 1), 2) : 0,
            'Ads_L15_CPC' => $adsL15 && ($adsL15->clicks ?? 0) > 0 ? round(($adsL15->cost ?? 0) / ($adsL15->clicks ?? 1), 2) : 0,
            'Ads_L7_CPC' => $adsL7 && ($adsL7->clicks ?? 0) > 0 ? round(($adsL7->cost ?? 0) / ($adsL7->clicks ?? 1), 2) : 0,
            'Ads_L30_CVR' => $adsL30 && ($adsL30->clicks ?? 0) > 0 ? round((($adsL30->purchases14d ?? 0) / ($adsL30->clicks ?? 1)) * 100, 2) : 0,
            'Ads_L60_CVR' => $adsL60 && ($adsL60->clicks ?? 0) > 0 ? round((($adsL60->purchases14d ?? 0) / ($adsL60->clicks ?? 1)) * 100, 2) : 0,
            'Ads_L15_CVR' => $adsL15 && ($adsL15->clicks ?? 0) > 0 ? round((($adsL15->purchases14d ?? 0) / ($adsL15->clicks ?? 1)) * 100, 2) : 0,
            'Ads_L7_CVR' => $adsL7 && ($adsL7->clicks ?? 0) > 0 ? round((($adsL7->purchases14d ?? 0) / ($adsL7->clicks ?? 1)) * 100, 2) : 0,
            'TPFT' => 0, // Placeholder, since profit calculations removed
         ];
      })->values();

      Log::info('FBA Ads Data JSON - Total records: ' . $tableData->count());
      Log::info('FBA Ads Data JSON - Sample SKUs: ' . $tableData->take(5)->pluck('SKU')->implode(', '));

      return response()->json($tableData);
   }

   public function fbaAdsPtDataJson()
   {
      $data = $this->getFbaData();

      $fbaData = $data['fbaData'];
      $shopifyData = $data['shopifyData'];
      $fbaMonthlySales = $data['fbaMonthlySales'];

      $productData = $data['productData']->keyBy(function ($p) {
         return strtoupper(trim($p->sku));
      });


      // Get all ads campaign reports for PT (Product Targeting)
      $amazonSpCampaignReportsL60 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L60')
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA PT%')
               ->orWhere('campaignName', 'LIKE', '%fba pt%')
               ->orWhere('campaignName', 'LIKE', '%FBA.PT%')
               ->orWhere('campaignName', 'LIKE', '%fba.pt%');
         })
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L30')
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA PT%')
               ->orWhere('campaignName', 'LIKE', '%fba pt%')
               ->orWhere('campaignName', 'LIKE', '%FBA.PT%')
               ->orWhere('campaignName', 'LIKE', '%fba.pt%');
         })
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      $amazonSpCampaignReportsL15 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L15')
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA PT%')
               ->orWhere('campaignName', 'LIKE', '%fba pt%')
               ->orWhere('campaignName', 'LIKE', '%FBA.PT%')
               ->orWhere('campaignName', 'LIKE', '%fba.pt%');
         })
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L7')
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA PT%')
               ->orWhere('campaignName', 'LIKE', '%fba pt%')
               ->orWhere('campaignName', 'LIKE', '%FBA.PT%')
               ->orWhere('campaignName', 'LIKE', '%fba.pt%');
         })
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L1')
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%FBA PT%')
               ->orWhere('campaignName', 'LIKE', '%fba pt%')
               ->orWhere('campaignName', 'LIKE', '%FBA.PT%')
               ->orWhere('campaignName', 'LIKE', '%fba.pt%');
         })
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      // Create a map of SKU to ads data
      $adsDataBySku = [];

      foreach ($fbaData as $sku => $fba) {
         $adsDataBySku[$sku] = [
            'L60' => $amazonSpCampaignReportsL60->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
            'L30' => $amazonSpCampaignReportsL30->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
            'L15' => $amazonSpCampaignReportsL15->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
            'L7' => $amazonSpCampaignReportsL7->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
            'L1' => $amazonSpCampaignReportsL1->first(function ($report) use ($sku) {
               return stripos($report->campaignName, $sku) !== false;
            }),
         ];
      }

      // Filter to only include SKUs that have PT ads data
      $tableData = $fbaData->filter(function ($fba, $sku) use ($adsDataBySku) {
         $ads = $adsDataBySku[$sku] ?? null;
         return $ads && ($ads['L60'] || $ads['L30'] || $ads['L15'] || $ads['L7'] || $ads['L1']);
      })->map(function ($fba, $sku) use ($shopifyData, $productData, $fbaMonthlySales, $adsDataBySku) {
         $shopifyInfo = $shopifyData->get($sku);
         $product = $productData->get($sku);
         $monthlySales = $fbaMonthlySales->get($sku);


         $ads = $adsDataBySku[$sku] ?? null;

         // Extract ads metrics
         $adsL30 = $ads['L30'] ?? null;
         $adsL60 = $ads['L60'] ?? null;
         $adsL15 = $ads['L15'] ?? null;
         $adsL7 = $ads['L7'] ?? null;
         $adsL1 = $ads['L1'] ?? null;

         // Get campaign name from ads data
         $campaignName = '';
         if ($adsL30) {
            $campaignName = $adsL30->campaignName ?? '';
         } elseif ($adsL60) {
            $campaignName = $adsL60->campaignName ?? '';
         } elseif ($adsL15) {
            $campaignName = $adsL15->campaignName ?? '';
         } elseif ($adsL7) {
            $campaignName = $adsL7->campaignName ?? '';
         } elseif ($adsL1) {
            $campaignName = $adsL1->campaignName ?? '';
         }

         return [
            'Parent' => $product ? ($product->parent ?? '') : '',
            'SKU' => $sku,
            'Campaign_Name' => $campaignName,

            // Ads Data L30
            'Ads_L30_Impressions' => $adsL30 ? ($adsL30->impressions ?? 0) : 0,
            'Ads_L30_Clicks' => $adsL30 ? ($adsL30->clicks ?? 0) : 0,
            'Ads_L30_Spend' => $adsL30 ? round(($adsL30->cost ?? 0), 2) : 0,
            'Ads_L30_Sales' => $adsL30 ? round(($adsL30->sales14d ?? 0), 2) : 0,
            'Ads_L30_Orders' => $adsL30 ? ($adsL30->purchases14d ?? 0) : 0,
            'Ads_L30_ACOS' => $adsL30 && ($adsL30->sales14d ?? 0) > 0 ? round((($adsL30->cost ?? 0) / ($adsL30->sales14d ?? 1)) * 100, 2) : 0,
            'Ads_L30_CTR' => $adsL30 && ($adsL30->impressions ?? 0) > 0 ? round((($adsL30->clicks ?? 0) / ($adsL30->impressions ?? 1)) * 100, 2) : 0,
            'Ads_L30_CVR' => $adsL30 && ($adsL30->clicks ?? 0) > 0 ? round((($adsL30->purchases14d ?? 0) / ($adsL30->clicks ?? 1)) * 100, 2) : 0,

            // Ads Data L60
            'Ads_L60_Impressions' => $adsL60 ? ($adsL60->impressions ?? 0) : 0,
            'Ads_L60_Clicks' => $adsL60 ? ($adsL60->clicks ?? 0) : 0,
            'Ads_L60_Spend' => $adsL60 ? round(($adsL60->cost ?? 0), 2) : 0,
            'Ads_L60_Sales' => $adsL60 ? round(($adsL60->sales14d ?? 0), 2) : 0,
            'Ads_L60_Orders' => $adsL60 ? ($adsL60->purchases14d ?? 0) : 0,
            'Ads_L60_ACOS' => $adsL60 && ($adsL60->sales14d ?? 0) > 0 ? round((($adsL60->cost ?? 0) / ($adsL60->sales14d ?? 1)) * 100, 2) : 0,
            'Ads_L60_CTR' => $adsL60 && ($adsL60->impressions ?? 0) > 0 ? round((($adsL60->clicks ?? 0) / ($adsL60->impressions ?? 1)) * 100, 2) : 0,
            'Ads_L60_CVR' => $adsL60 && ($adsL60->clicks ?? 0) > 0 ? round((($adsL60->purchases14d ?? 0) / ($adsL60->clicks ?? 1)) * 100, 2) : 0,

            // Ads Data L15
            'Ads_L15_Impressions' => $adsL15 ? ($adsL15->impressions ?? 0) : 0,
            'Ads_L15_Clicks' => $adsL15 ? ($adsL15->clicks ?? 0) : 0,
            'Ads_L15_Spend' => $adsL15 ? round(($adsL15->cost ?? 0), 2) : 0,
            'Ads_L15_Sales' => $adsL15 ? round(($adsL15->sales14d ?? 0), 2) : 0,
            'Ads_L15_Orders' => $adsL15 ? ($adsL15->purchases14d ?? 0) : 0,
            'Ads_L15_ACOS' => $adsL15 && ($adsL15->sales14d ?? 0) > 0 ? round((($adsL15->cost ?? 0) / ($adsL15->sales14d ?? 1)) * 100, 2) : 0,
            'Ads_L15_CTR' => $adsL15 && ($adsL15->impressions ?? 0) > 0 ? round((($adsL15->clicks ?? 0) / ($adsL15->impressions ?? 1)) * 100, 2) : 0,
            'Ads_L15_CVR' => $adsL15 && ($adsL15->clicks ?? 0) > 0 ? round((($adsL15->purchases14d ?? 0) / ($adsL15->clicks ?? 1)) * 100, 2) : 0,

            // Ads Data L7
            'Ads_L7_Impressions' => $adsL7 ? ($adsL7->impressions ?? 0) : 0,
            'Ads_L7_Clicks' => $adsL7 ? ($adsL7->clicks ?? 0) : 0,
            'Ads_L7_Spend' => $adsL7 ? round(($adsL7->cost ?? 0), 2) : 0,
            'Ads_L7_Sales' => $adsL7 ? round(($adsL7->sales14d ?? 0), 2) : 0,
            'Ads_L7_Orders' => $adsL7 ? ($adsL7->purchases14d ?? 0) : 0,
            'Ads_L7_ACOS' => $adsL7 && ($adsL7->sales14d ?? 0) > 0 ? round((($adsL7->cost ?? 0) / ($adsL7->sales14d ?? 1)) * 100, 2) : 0,
            'Ads_L7_CTR' => $adsL7 && ($adsL7->impressions ?? 0) > 0 ? round((($adsL7->clicks ?? 0) / ($adsL7->impressions ?? 1)) * 100, 2) : 0,
            'Ads_L7_CVR' => $adsL7 && ($adsL7->clicks ?? 0) > 0 ? round((($adsL7->purchases14d ?? 0) / ($adsL7->clicks ?? 1)) * 100, 2) : 0,

            // Ads Data L1
            'Ads_L1_Impressions' => $adsL1 ? ($adsL1->impressions ?? 0) : 0,
            'Ads_L1_Clicks' => $adsL1 ? ($adsL1->clicks ?? 0) : 0,
            'Ads_L1_Spend' => $adsL1 ? round(($adsL1->cost ?? 0), 2) : 0,
            'Ads_L1_Sales' => $adsL1 ? round(($adsL1->sales14d ?? 0), 2) : 0,
            'Ads_L1_Orders' => $adsL1 ? ($adsL1->purchases14d ?? 0) : 0,
            'Ads_L1_ACOS' => $adsL1 && ($adsL1->sales14d ?? 0) > 0 ? round((($adsL1->cost ?? 0) / ($adsL1->sales14d ?? 1)) * 100, 2) : 0,
            'Ads_L1_CPC' => $adsL1 && ($adsL1->clicks ?? 0) > 0 ? round(($adsL1->cost ?? 0) / ($adsL1->clicks ?? 1), 2) : 0,
            'Ads_L1_CVR' => $adsL1 && ($adsL1->clicks ?? 0) > 0 ? round((($adsL1->purchases14d ?? 0) / ($adsL1->clicks ?? 1)) * 100, 2) : 0,

         ];
      })->values();

      return response()->json($tableData);
   }

   public function fbaDataJson(Request $request)
   {
      $data = $this->getFbaData();

      $fbaData = $data['fbaData'];
      $fbaPriceData = $data['fbaPriceData'];
      $fbaReportsData = $data['fbaReportsData'];
      $shopifyData = $data['shopifyData'];
      $fbaMonthlySales = $data['fbaMonthlySales'];
      $fbaManualData = $data['fbaManualData'];
      $fbaDispatchDates = $data['fbaDispatchDates'];
      $fbaShipCalculations = $data['fbaShipCalculations'];
      $productData = $data['productData']->keyBy(function ($p) {
         return strtoupper(trim($p->sku));
      });


      // Fetch KW (Keyword) ads data - campaigns NOT ending with 'pt'
      $skus = $fbaData->keys()->toArray();
      $amazonSpCampaignReportsL30KW = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L30')
         ->where(function ($q) use ($skus) {
            foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
         })
         ->where(function ($q) {
            $q->where('campaignName', 'NOT LIKE', '%.pt')
               ->where('campaignName', 'NOT LIKE', '% pt')
               ->where('campaignName', 'NOT LIKE', '%pt%');
         })
         ->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'")
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      // Fetch PT (Product Targeting) ads data - campaigns ending with 'pt'
      $amazonSpCampaignReportsL30PT = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
         ->where('report_date_range', 'L30')
         ->where(function ($q) use ($skus) {
            foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
         })
         ->where(function ($q) {
            $q->where('campaignName', 'LIKE', '%.pt')
               ->orWhere('campaignName', 'LIKE', '% pt')
               ->orWhereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) LIKE '% pt'");
         })
         ->where('campaignStatus', '!=', 'ARCHIVED')
         ->get();

      // Create maps for KW and PT ads data by SKU
      $adsKWDataBySku = [];
      $adsPTDataBySku = [];

      foreach ($fbaData as $sku => $fba) {
         // Find KW campaign for this SKU
         $kwCampaign = $amazonSpCampaignReportsL30KW->first(function ($campaign) use ($sku) {
            return stripos($campaign->campaignName, $sku) !== false;
         });
         $adsKWDataBySku[$sku] = $kwCampaign;

         // Find PT campaign for this SKU
         $ptCampaign = $amazonSpCampaignReportsL30PT->first(function ($campaign) use ($sku) {
            return stripos($campaign->campaignName, $sku) !== false;
         });
         $adsPTDataBySku[$sku] = $ptCampaign;
      }

      // Prepare table data with repeated parent name for all child SKUs
      $tableData = $fbaData->map(function ($fba, $sku) use ($fbaPriceData, $fbaReportsData, $shopifyData, $productData, $fbaMonthlySales, $fbaManualData, $fbaDispatchDates, $fbaShipCalculations, $adsKWDataBySku, $adsPTDataBySku) {
         $fbaPriceInfo = $fbaPriceData->get($sku);
         $fbaReportsInfo = $fbaReportsData->get($sku);
         $shopifyInfo = $shopifyData->get($sku);
         $product = $productData->get($sku);
         $monthlySales = $fbaMonthlySales->get($sku);
         $manual = $fbaManualData->get(strtoupper(trim($fba->seller_sku)));
         $dispatchDate = $fbaDispatchDates->get($sku);
         $shipCalc = $fbaShipCalculations->get($sku);

         // Get KW and PT ads data
         $adsKW = $adsKWDataBySku[$sku] ?? null;
         $adsPT = $adsPTDataBySku[$sku] ?? null;

         // Calculate combined ads metrics (KW + PT)
         $kwSpend = $adsKW ? floatval($adsKW->cost ?? 0) : 0;
         $ptSpend = $adsPT ? floatval($adsPT->cost ?? 0) : 0;
         $kwSales = $adsKW ? floatval($adsKW->sales14d ?? 0) : 0;
         $ptSales = $adsPT ? floatval($adsPT->sales14d ?? 0) : 0;

         // Ads Percentage = (KW Spend + PT Spend) / (KW Sales + PT Sales)
         $totalSpend = $kwSpend + $ptSpend;
         $totalSales = $kwSales + $ptSales;
         $adsPercentage = $totalSales > 0 ? (($totalSpend) * 100) : 0;

         $lmpaData = $this->lmpaDataService->getLmpaData($sku);

         $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
         $LP = \App\Services\CustomLpMappingService::getLpValue($sku, $product);
         $FBA_SHIP = $this->fbaManualDataService->calculateFbaShipCalculation(
            $fba->seller_sku,
            $manual ? ($manual->data['fba_fee_manual'] ?? 0) : 0,
            $manual ? ($manual->data['send_cost'] ?? 0) : 0
         );

         $S_PRICE = $manual ? floatval($manual->data['s_price'] ?? 0) : 0;

         $commissionPercentage = $manual ? floatval($manual->data['commission_percentage'] ?? 0) : 0;
         // --- Calculate all profit & ROI metrics ---

         $sgpft = ($S_PRICE > 0) ? ($S_PRICE * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $S_PRICE : 0;

         $pft = ($PRICE > 0) ? (($PRICE * 0.66) - $LP - $FBA_SHIP) / $PRICE : 0;



         // $spft =  ($S_PRICE > 0) ? (($S_PRICE * ((1 - ($commissionPercentage  / 100 + 0.05)) - $LP - $FBA_SHIP)) - $adsPercentage) / $S_PRICE : 0;
         $sroi = ($LP > 0 && $S_PRICE > 0) ? ($S_PRICE * (1 - ($commissionPercentage  / 100 + 0.05)) - $LP - $FBA_SHIP - $adsPercentage)  / $LP : 0;
         $sgroi = ($LP > 0 && $S_PRICE > 0) ? ($S_PRICE * (1 - ($commissionPercentage  / 100 + 0.05)) - $LP - $FBA_SHIP)  / $LP : 0;



         $cvr = ($monthlySales ? ($monthlySales->l30_units ?? 0) : 0) / ($fbaReportsInfo ? ($fbaReportsInfo->current_month_views ?: 1) : 1) * 100;

         // Calculate GPFT%



         $roi = 0;
         if ($LP > 0) {
            $roi = ($PRICE * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $LP;
         }

         $spft = 0;
         if ($S_PRICE > 0) {
            $spft = ($S_PRICE * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $S_PRICE;
         }


         $gpft = 0;
         if ($PRICE > 0) {
            $gpft = ($PRICE * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $PRICE;
         }

         $groi = 0;
         if ($LP > 0) {
            $groi = ($PRICE * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $LP;
         }


         $gpftPercentage = round($gpft * 100);
         $sgpftPercentage = round($sgpft * 100);
         $groiPercentage = round($groi * 100);
         $pftPercentage = round($pft * 100);
         $roiPercentage = round($roi * 100);
         $spftPercentage = round($spft * 100);
         $sroiPercentage = round($sroi * 100);
         $sgroiPercentage = round($sgroi * 100);

         return [
            'Parent' => $product ? ($product->parent ?? '') : '',
            'SKU' => $sku,
            'FBA_SKU' => $fba->seller_sku,
            'FBA_Price' => $fbaPriceInfo ? round(($fbaPriceInfo->price ?? 0), 2) : 0,
            'l30_units' => $monthlySales ? ($monthlySales->l30_units ?? 0) : 0,
            'Shopify_OV_L30' => $shopifyInfo ? ($shopifyInfo->quantity ?? 0) : 0,
            'Shopify_INV' => $shopifyInfo ? ($shopifyInfo->inv ?? 0) : 0,
            'l60_units' => $monthlySales ? ($monthlySales->l60_units ?? 0) : 0,
            'FBA_Quantity' => $fba->quantity_available,
            'Dil' => ($shopifyInfo ? ($shopifyInfo->quantity ?? 0) : 0) / max($shopifyInfo ? ($shopifyInfo->inv ?? 0) : 1, 1) * 100,
            'FBA_Dil' => ($monthlySales ? ($monthlySales->l30_units ?? 0) : 0) / ($fba->quantity_available ?: 1) * 100,
            'Current_Month_Views' => $fbaReportsInfo ? ($fbaReportsInfo->current_month_views ?? 0) : 0,
            'FBA_CVR' => $this->colorService->getCvrHtml($cvr),
            'Listed' => $manual ? ($manual->data['listed'] ?? false) : false,
            'Live' => $manual ? ($manual->data['live'] ?? false) : false,
            'Pft%' => $this->colorService->getValueHtml($pftPercentage),
            'ROI%' => $this->colorService->getRoiHtmlForView($roiPercentage),
            'GPFT%' => $this->colorService->getValueHtml($gpftPercentage),
            'SGPFT%' => $this->colorService->getValueHtml($sgpftPercentage),
            'GROI%' => $this->colorService->getRoiHtmlForView($groiPercentage),
            'S_Price' => round($S_PRICE, 2),
            'SPft%' => $this->colorService->getValueHtml($spftPercentage),
            'SROI%' => $this->colorService->getRoiHtmlForView($sroiPercentage),
            'SGROI%' => $this->colorService->getRoiHtmlForView($sgroiPercentage),
            'lmp_1' => $lmpaData['lowest_price'],
            'lmp_data' => $lmpaData['data'],
            'ACTION_ACTION' => $manual ? ($manual->data['action_action'] ?? '') : '',
            'REV_COUNT' => $manual ? ($manual->data['rev_count'] ?? '') : '',
            'RATING' => $manual ? ($manual->data['rating'] ?? '') : '',
            'LP' => round($LP, 2),
            'Fulfillment_Fee' => $fbaReportsInfo ? round(($fbaReportsInfo->fulfillment_fee ?? 0), 2) : 0,
            'FBA_Fee_Manual' => $manual ? ($manual->data['fba_fee_manual'] ?? 0) : 0,
            'ASIN' => $fba->asin,
            'Barcode' => $manual ? ($manual->data['barcode'] ?? '') : '',
            'Dispatch_Date' => $dispatchDate ? $dispatchDate->dispatch_date : ($manual ? ($manual->data['dispatch_date'] ?? '') : ''),
            'Weight' => $manual ? ($manual->data['weight'] ?? 0) : 0,
            'WH_ACT' => $manual ? ($manual->data['wh_act'] ?? '') : '',
            'UPC_Codes' => $manual ? ($manual->data['upc_codes'] ?? '') : '',
            'Issues_at_WH' => $manual ? ($manual->data['issues_at_wh'] ?? '') : '',
            'Issues_Remarks_Update' => $manual ? ($manual->data['issues_remarks_update'] ?? '') : '',
            'Sent_By' => $manual ? ($manual->data['sent_by'] ?? '') : '',
            'Quantity_in_each_box' => $manual ? ($manual->data['quantity_in_each_box'] ?? 0) : 0,
            'Send_Cost' => $manual ? ($manual->data['send_cost'] ?? 0) : 0,
            'IN_Charges' => $manual ? ($manual->data['in_charges'] ?? 0) : 0,
            'Commission_Percentage' => $manual ? ($manual->data['commission_percentage'] ?? 0) : 0,
            'Total_quantity_sent' => $manual ? ($manual->data['total_quantity_sent'] ?? 0) : 0,
            'Done' => $manual ? ($manual->data['done'] ?? false) : false,
            'Warehouse_INV_Reduction' => $manual ? ($manual->data['warehouse_inv_reduction'] ?? false) : false,
            'Shipping_Amount' => $manual ? ($manual->data['shipping_amount'] ?? 0) : 0,
            'Inbound_Quantity' => $manual ? ($manual->data['inbound_quantity'] ?? 0) : 0,
            'FBA_Send' => $manual ? ($manual->data['fba_send'] ?? false) : false,
            'Approval' => $manual ? ($manual->data['approval'] ?? false) : false,
            'Profit_is_ok' => $manual ? ($manual->data['profit_is_ok'] ?? false) : false,
            'Dimensions' => $manual ? ($manual->data['dimensions'] ?? '') : '',
            'MSL' => $manual ? ($manual->data['msl'] ?? '') : '',
            'SEND' => $manual ? ($manual->data['send'] ?? '') : '',
            'Correct_Cost' => $manual ? ($manual->data['correct_cost'] ?? false) : false,
            'Zero_Stock' => $manual ? ($manual->data['zero_stock'] ?? false) : false,
            '0-to-90-days' => $manual ? ($manual->data['0-to-90-days'] ?? '') : '',
            '91-to-180-days' => $manual ? ($manual->data['91-to-180-days'] ?? '') : '',
            '181-to-270-days' => $manual ? ($manual->data['181-to-270-days'] ?? '') : '',
            '271-to-365-days' => $manual ? ($manual->data['271-to-365-days'] ?? '') : '',
            '365-plus-days' => $manual ? ($manual->data['365-plus-days'] ?? '') : '',
            'FBA_Ship_Calculation' => $this->fbaManualDataService->calculateFbaShipCalculation(
               $fba->seller_sku,
               $manual ? ($manual->data['fba_fee_manual'] ?? 0) : 0,
               $manual ? ($manual->data['send_cost'] ?? 0) : 0,
               $manual ? ($manual->data['in_charges'] ?? 0) : 0
            ),

            'Ads_Percentage' => ($monthlySales && ($monthlySales->l30_units ?? 0) > 0) ? $adsPercentage / ($monthlySales->l30_units ?? 1) : 0,

            // TPFT calculation (Commission % - Ads %)
            'TPFT' => round($gpftPercentage - $adsPercentage, 2),
            'SPFT' => round($spftPercentage - $adsPercentage),
            'ROI' => round($roiPercentage - $adsPercentage, 2),
            'Jan' => $monthlySales ? ($monthlySales->jan ?? 0) : 0,
            'Feb' => $monthlySales ? ($monthlySales->feb ?? 0) : 0,
            'Mar' => $monthlySales ? ($monthlySales->mar ?? 0) : 0,
            'Apr' => $monthlySales ? ($monthlySales->apr ?? 0) : 0,
            'May' => $monthlySales ? ($monthlySales->may ?? 0) : 0,
            'Jun' => $monthlySales ? ($monthlySales->jun ?? 0) : 0,
            'Jul' => $monthlySales ? ($monthlySales->jul ?? 0) : 0,
            'Aug' => $monthlySales ? ($monthlySales->aug ?? 0) : 0,
            'Sep' => $monthlySales ? ($monthlySales->sep ?? 0) : 0,
            'Oct' => $monthlySales ? ($monthlySales->oct ?? 0) : 0,
            'Nov' => $monthlySales ? ($monthlySales->nov ?? 0) : 0,
            'Dec' => $monthlySales ? ($monthlySales->dec ?? 0) : 0,



         ];
      })->values();

      // Group by Parent and process
      $grouped = collect($tableData)->groupBy('Parent');

      $finalData = $grouped->flatMap(function ($rows, $parentKey) {
         $children = $rows->filter(fn($item) => !isset($item['is_parent']) || !$item['is_parent']);

         if ($children->isEmpty()) {
            return $rows;
         }

         // Create parent row
         $parentRow = [
            'Parent' => $parentKey,
            'SKU' => $parentKey,
            'FBA_SKU' => '',
            'FBA_Price' => '',
            'l30_units' => $children->sum('l30_units'),
            'l60_units' => $children->sum('l60_units'),
            'FBA_Quantity' => $children->sum('FBA_Quantity'),

            'Dil' => round($children->sum('Dil'), 2),
            'FBA_Dil' => round($children->sum('FBA_Dil'), 2),
            'Current_Month_Views' => $children->sum('Current_Month_Views'),
            'FBA_CVR' => '',
            'Listed' => false,
            'Live' => false,
            'Fulfillment_Fee' => round($children->sum('Fulfillment_Fee'), 2),
            'FBA_Fee_Manual' => '',
            'ASIN' => '',
            'Shopify_INV' => $children->sum('Shopify_INV'),
            'Barcode' => '',
            'Dispatch_Date' => '',
            'Weight' => round($children->sum(fn($item) => is_numeric($item['Weight']) ? $item['Weight'] : 0), 2),
            'WH_ACT' => '',
            'UPC_Codes' => '',
            'Issues_at_WH' => '',
            'Issues_Remarks_Update' => '',
            'Sent_By' => '',
            'Quantity_in_each_box' => round($children->sum(fn($item) => is_numeric($item['Quantity_in_each_box']) ? $item['Quantity_in_each_box'] : 0), 2),
            'Total_quantity_sent' => round($children->sum(fn($item) => is_numeric($item['Total_quantity_sent']) ? $item['Total_quantity_sent'] : 0), 2),
            'Send_Cost' => round($children->sum(fn($item) => is_numeric($item['Send_Cost']) ? $item['Send_Cost'] : 0), 2),
            'IN_Charges' => round($children->sum(fn($item) => is_numeric($item['IN_Charges']) ? $item['IN_Charges'] : 0), 2),
            'Commission_Percentage' => '',
            'Ads_Percentage' => '',
            'Done' => false,
            'Warehouse_INV_Reduction' => false,
            'FBA_Send' => false,
            'Approval' => false,
            'Profit_is_ok' => false,
            'Shipping_Amount' => round($children->sum(fn($item) => is_numeric($item['Shipping_Amount']) ? $item['Shipping_Amount'] : 0), 2),
            'Inbound_Quantity' => round($children->sum(fn($item) => is_numeric($item['Inbound_Quantity']) ? $item['Inbound_Quantity'] : 0), 2),
            'Dimensions' => '',
            'MSL' => '',
            'SEND' => '',
            'Correct_Cost' => false,
            'Zero_Stock' => false,
            '0-to-90-days' => '',
            '91-to-180-days' => '',
            '181-to-270-days' => '',
            '271-to-365-days' => '',
            '365-plus-days' => '',
            'Jan' => $children->sum('Jan'),
            'Feb' => $children->sum('Feb'),
            'Mar' => $children->sum('Mar'),
            'Apr' => $children->sum('Apr'),
            'May' => $children->sum('May'),
            'Jun' => $children->sum('Jun'),
            'Jul' => $children->sum('Jul'),
            'Aug' => $children->sum('Aug'),
            'Sep' => $children->sum('Sep'),
            'Oct' => $children->sum('Oct'),
            'Nov' => $children->sum('Nov'),
            'Dec' => $children->sum('Dec'),
            'is_parent' => true,
            'Pft%' => '',
            'ROI%' => '',
            'GPFT%' => '',
            'S_Price' => '',
            'SPft%' => '',
            'SROI%' => '',
            'lmp_1' => '',
            'lmp_data' => [],
            'ACTION_ACTION' => '',
            'REV_COUNT' => '',
            'RATING' => '',
            'LP' => '',
            'FBA_Ship_Calculation' => '',
         ];

         // Return children first, then parent
         return $children->push($parentRow);
      })->values();

      // Handle server-side sorting
      $sort = $request->get('sort');
      if ($sort && is_array($sort)) {
         $finalData = collect($finalData);
         foreach ($sort as $s) {
            $field = $s['field'];
            $dir = $s['dir']; // 'asc' or 'desc'
            if ($field === 'FBA_CVR') {
               $finalData = $finalData->sort(function ($a, $b) use ($dir, $field) {
                  $aVal = $this->extractCvrValue($a[$field] ?? '');
                  $bVal = $this->extractCvrValue($b[$field] ?? '');
                  if ($dir === 'asc') {
                     return $aVal <=> $bVal;
                  } else {
                     return $bVal <=> $aVal;
                  }
               });
            } else {
               $finalData = $finalData->sortBy($field, SORT_REGULAR, $dir === 'desc');
            }
         }
         $finalData = $finalData->values();
      }

      return response()->json($finalData);
   }

   public function getFbaMonthlySales($sku)
   {
      $baseSku = strtoupper(trim($sku));

      $sales = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
         ->get()
         ->filter(function ($item) use ($baseSku) {
            $sku = $item->seller_sku;
            $base = preg_replace('/\s*FBA\s*/i', '', $sku);
            return strtoupper(trim($base)) === $baseSku;
         })
         ->first();

      if (!$sales) {
         return response()->json(['error' => 'No data found'], 404);
      }

      $monthlyData = [
         'Jan' => $sales->jan ?? 0,
         'Feb' => $sales->feb ?? 0,
         'Mar' => $sales->mar ?? 0,
         'Apr' => $sales->apr ?? 0,
         'May' => $sales->may ?? 0,
         'Jun' => $sales->jun ?? 0,
         'Jul' => $sales->jul ?? 0,
         'Aug' => $sales->aug ?? 0,
         'Sep' => $sales->sep ?? 0,
         'Oct' => $sales->oct ?? 0,
         'Nov' => $sales->nov ?? 0,
         'Dec' => $sales->dec ?? 0,
      ];

      return response()->json([
         'sku' => $sku,
         'monthly_sales' => $monthlyData,
         'total_units' => $sales->total_units ?? 0,
         'avg_price' => $sales->avg_price ?? 0,
      ]);
   }

   public function updateFbaSkuManualData(Request $request)
   {
      $sku = strtoupper(trim($request->input('sku')));
      $field = $request->input('field');
      $value = $request->input('value') ?: 0;
      $fulfillmentFee = floatval($request->input('fulfillment_fee') ?? 0);


      // Row find or create
      $manual = FbaManualData::where('sku', $sku)->first();

      if (!$manual) {
         $manual = new FbaManualData();
         $manual->sku = $sku;
         $manual->data = [];
      }

      // Update JSON fields
      $data = $manual->data ?? [];
      $data[$field] = $value;

      // Extract only 3 fields
      $FBA_FEE_MANUAL = floatval($data['fba_fee_manual'] ?? 0);
      $SEND_COST = floatval($data['send_cost'] ?? 0);

      // Calculate FBA_SHIP (Fulfillment_Fee + FBA_Fee_Manual + Send_Cost)
      $FBA_SHIP = $fulfillmentFee + $FBA_FEE_MANUAL + $SEND_COST;
      $data['fba_ship'] = $FBA_SHIP;

      $manual->data = $data;
      $manual->save();

      // Return only FBA_SHIP + updated field
      return response()->json([
         'success' => true,
         'updatedRow' => [
            'FBA_SHIP' => $FBA_SHIP,
            strtoupper($field) => $value,
         ]
      ]);
   }





   public function getFbaListedLiveAndViewsData()
   {
      // --- Fetch Product Master SKUs ---
      $productMasters = ProductMaster::whereNull('deleted_at')->get();

      $normalizeSku = function ($sku) {
         $sku = strtoupper(trim($sku));
         // Remove trailing "FBA" (with or without spaces)
         return preg_replace('/\s*FBA\s*$/i', '', $sku);
      };

      // Collect normalized SKUs from Product Master
      $productSkus = $productMasters->pluck('sku')
         ->map(fn($s) => strtoupper(trim($s)))
         ->unique()
         ->toArray();

      // --- Fetch FBA-related tables (no whereIn, since they contain FBA) ---
      $fbaManualData = FbaManualData::all();
      $fbaReports = FbaReportsMaster::all();
      $fbaTables = FbaTable::all();

      // Re-key each table by normalized SKU (without FBA)
      $manualBySku = $fbaManualData->keyBy(fn($s) => $normalizeSku($s->sku ?? ''));
      $reportsBySku = $fbaReports->keyBy(fn($s) => $normalizeSku($s->seller_sku ?? ''));
      $inventoryBySku = $fbaTables->keyBy(fn($s) => $normalizeSku($s->seller_sku ?? ''));

      // --- Initialize counters ---
      $listedCount = 0;
      $liveCount = 0;
      $zeroViewCount = 0;

      $listedSkus = [];
      $liveSkus = [];
      $zeroViewSkus = [];

      foreach ($productMasters as $item) {
         $sku = strtoupper(trim($item->sku));
         $normalizedSku = $normalizeSku($sku);

         // Skip parent SKUs
         if (stripos($sku, 'PARENT') !== false) continue;

         // --- Get inventory ---
         $inv = floatval($inventoryBySku[$normalizedSku]->quantity_available ?? 0);

         // --- Get FBA Manual Data ---
         $manualData = $manualBySku[$normalizedSku]->data ?? null;
         if (is_string($manualData)) {
            $manualData = json_decode($manualData, true);
         }

         $listed = filter_var($manualData['listed'] ?? false, FILTER_VALIDATE_BOOLEAN);
         $live = filter_var($manualData['live'] ?? false, FILTER_VALIDATE_BOOLEAN);

         // --- Count Listed ---
         if ($listed === true) {
            $listedCount++;
            $listedSkus[] = $sku;
         }

         // --- Count Live ---
         if ($live === true) {
            $liveCount++;
            $liveSkus[] = $sku;
         }

         // --- Get Views ---
         $views = (int) ($reportsBySku[$normalizedSku]->current_month_views ?? 0);

         // --- Zero Views ---
         if ($inv > 0 && $views === 0) {
            $zeroViewCount++;
            $zeroViewSkus[] = $sku;
         }
      }

      // --- Calculate Live Pending ---
      $livePending = max($listedCount - $liveCount, 0);

      // --- Return Final Counts ---
      return [
         'live_pending' => $livePending,
         'zero_view' => $zeroViewCount,
      ];
   }


   public function exportFbaManualData()
   {
      return $this->fbaManualDataService->exportToCSV();
   }


   public function importFbaManualData(Request $request)
   {
      $request->validate(['file' => 'required|mimes:csv,txt']);
      $result = $this->fbaManualDataService->importFromCSV($request->file('file'));

      return response()->json([
         'success' => $result['success'],
         'message' => $result['success']
            ? "{$result['imported']} records imported successfully!"
            : $result['message']
      ]);
   }


   public function downloadSampleTemplate()
   {
      return $this->fbaManualDataService->downloadSampleTemplate();
   }


   public function getFbaColumnVisibility()
   {
      return response()->json(\App\Services\ColumnVisibilityService::getFbaColumnVisibility());
   }

   public function setFbaColumnVisibility(Request $request)
   {
      $visibility = $request->input('visibility', []);
      \App\Services\ColumnVisibilityService::setFbaColumnVisibility($visibility);
      return response()->json(['success' => true]);
   }

   private function extractCvrValue($html)
   {
      // Remove HTML tags and % symbol, then parse as float
      $str = strip_tags($html);
      $str = str_replace('%', '', $str);
      return floatval($str);
   }

   public function pushFbaPrice(Request $request)
   {
      $sku = $request->input('sku');
      $price = $request->input('price');

      $service = new AmazonSpApiService();
      $result = $service->updateAmazonPriceUS($sku, $price);

      return response()->json($result);
   }
}
