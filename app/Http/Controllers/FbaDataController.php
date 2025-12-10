<?php

namespace App\Http\Controllers;

use App\Models\AmazonSpCampaignReport;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\FbaTable;
use App\Models\FbaPrice;
use App\Models\AmazonDatasheet;
use App\Models\FbaReportsMaster;
use App\Models\FbaMonthlySale;
use App\Models\FbaManualData;
use App\Models\FbaOrder;
use App\Models\FbaShipCalculation;
use App\Models\FbaMetricsHistory;
use App\Models\FbaSkuDailyData;
use App\Services\ColorService;
use App\Services\FbaManualDataService;
use App\Services\LmpaDataService;
use App\Services\AmazonSpApiService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Sum;

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

      $amazonDatasheet = AmazonDatasheet::all()->keyBy(function ($item) {
         return strtoupper(trim($item->sku));
      });

      // Fetch latest FBA shipment status for each SKU based on shipment_name date
      $fbaShipments = \App\Models\FbaShipment::select('sku', 'shipment_status', 'shipment_id', 'shipment_name', 'updated_at', 'quantity_shipped')
         ->get()
         ->groupBy(function($item) {
            return strtoupper(trim($item->sku));
         })
         ->map(function($shipments) {
            // Sort by date extracted from shipment_name (format: "FBA STA (MM/DD/YYYY HH:MM)-XXX")
            return $shipments->sortByDesc(function($shipment) {
               // Extract date from shipment_name
               if (preg_match('/\((\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})\)/', $shipment->shipment_name, $matches)) {
                  try {
                     return \Carbon\Carbon::createFromFormat('m/d/Y H:i', $matches[1]);
                  } catch (\Exception $e) {
                     return \Carbon\Carbon::parse($shipment->updated_at);
                  }
               }
               return \Carbon\Carbon::parse($shipment->updated_at);
            })->first(); // Get the shipment with latest date from shipment_name
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

      return compact('productData', 'shopifyData', 'fbaData', 'fbaPriceData', 'fbaReportsData', 'matchedSkus', 'unmatchedSkus', 'fbaMonthlySales', 'fbaManualData', 'fbaDispatchDates', 'fbaShipCalculations', 'amazonDatasheet', 'fbaShipments', 'amazonSpCampaignReportsL60', 'amazonSpCampaignReportsL30', 'amazonSpCampaignReportsL15', 'amazonSpCampaignReportsL7', 'amazonSpCampaignReportsL1');
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
            'FBA_Quantity' => $fba->quantity_available ?? 0,
            'Shopify_INV' => $shopifyInfo ? ($shopifyInfo->inv ?? 0) : 0,
            'Shopify_OV_L30' => $shopifyInfo ? ($shopifyInfo->quantity ?? 0) : 0,
            'l30_units' => $monthlySales ? ($monthlySales->l30_units ?? 0) : 0,
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
      $amazonDatasheet = $data['amazonDatasheet'];
      $fbaShipments = $data['fbaShipments'];
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
         // Find KW campaign for this SKU - prioritize FBA campaigns
         $kwCampaign = $amazonSpCampaignReportsL30KW->filter(function ($campaign) use ($sku) {
            return stripos($campaign->campaignName, $sku) !== false;
         })->sortByDesc(function ($campaign) {
            return stripos($campaign->campaignName, 'FBA') !== false ? 1 : 0;
         })->first();
         $adsKWDataBySku[$sku] = $kwCampaign;

         // Find PT campaign for this SKU - prioritize FBA PT campaigns
         $ptCampaign = $amazonSpCampaignReportsL30PT->filter(function ($campaign) use ($sku) {
            return stripos($campaign->campaignName, $sku) !== false;
         })->sortByDesc(function ($campaign) {
            return stripos($campaign->campaignName, 'FBA') !== false ? 1 : 0;
         })->first();
         $adsPTDataBySku[$sku] = $ptCampaign;
      }

      // Calculate overall average price: sum(price) * sum(l30) / sum(l30) = sum(price)
      $totalPrice = 0;
      $totalL30 = 0;
      foreach ($fbaData as $sku => $fba) {
         $fbaPriceInfo = $fbaPriceData->get($sku);
         $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
         $monthlySales = $fbaMonthlySales->get($sku);
         $l30Units = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
         $totalPrice += $PRICE;
         $totalL30 += $l30Units;
      }
      $overallAvgPrice = $totalL30 > 0 ? $totalPrice * $totalL30 / $totalL30 : 0;

      // Prepare table data with repeated parent name for all child SKUs
      $tableData = $fbaData->map(function ($fba, $sku) use ($fbaPriceData, $fbaReportsData, $shopifyData, $productData, $fbaMonthlySales, $fbaManualData, $fbaDispatchDates, $fbaShipCalculations, $amazonDatasheet, $fbaShipments, $adsKWDataBySku, $adsPTDataBySku, $overallAvgPrice) {
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

         // Calculate total spend for Total_Spend_L30 field
         $totalSpend = $kwSpend + $ptSpend;

         // Calculate price_l30 (FBA_Price * l30_units)
         $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
         $l30Units = $monthlySales ? ($monthlySales->l30_units ?? 0) : 0;
         $priceL30 = $PRICE * $l30Units;

         // Calculate total_spend_sum (KW + PT spend)
         $totalSpendSum = $kwSpend + $ptSpend;

         // Calculate TCOS percentage (total_spend_sum / price_l30)
         // If no ad spend, TCOS = 0%
         // If ad spend exists but no sales, TCOS = 100%
         // Otherwise, calculate normally
         if ($totalSpendSum == 0) {
            $tcosPercentage = 0;
         } elseif ($totalSpendSum > 0 && $priceL30 == 0) {
            $tcosPercentage = 100;
         } else {
            $tcosPercentage = round(($totalSpendSum / $priceL30) * 100, 2);
         }

         $lmpaData = $this->lmpaDataService->getLmpaData($sku);

         // Debug logging for ET 6FT BLU LMP data
         if (stripos($sku, 'ET 6FT BLU') !== false) {
            Log::info("ET 6FT BLU LMP Data Check", [
               'sku' => $sku,
               'lmpaData' => $lmpaData
            ]);
         }

         $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
         
         // Use same data sources as analytics for PFT/ROI/SPFT/SROI calculations
         $LP_FOR_PFT = $product ? floatval($product->Values['lp'] ?? 0) : 0; // Match analytics: direct from product
         $FBA_SHIP_FOR_PFT = $fbaReportsInfo ? floatval($fbaReportsInfo->fulfillment_fee ?? 0) : 0; // Match analytics: simple fulfillment fee
         
         // Keep enhanced calculations for other metrics (GPFT, GROI, etc.)
         $LP = \App\Services\CustomLpMappingService::getLpValue($sku, $product);
         $FBA_SHIP = $this->fbaManualDataService->calculateFbaShipCalculation(
            $fba->seller_sku,
            $manual ? ($manual->data['fba_fee_manual'] ?? 0) : 0,
            $manual ? ($manual->data['send_cost'] ?? 0) : 0
         );

         // ✅ Validate s_price from database - prevent 0 values from being used
         $S_PRICE = $manual ? floatval($manual->data['s_price'] ?? 0) : 0;
         if ($S_PRICE < 0) {
            $S_PRICE = 0; // Sanitize negative values
         }

         $commissionPercentage = $manual ? floatval($manual->data['commission_percentage'] ?? 0) : 0;
         // --- Calculate all profit & ROI metrics (same as analytics) ---
         
         // PFT and ROI calculations matching analytics exactly (using same LP and FBA_SHIP sources)
         $pft = ($PRICE > 0) ? (($PRICE * 0.7) - $LP_FOR_PFT - $FBA_SHIP_FOR_PFT) / $PRICE : 0;
         $roi = ($LP_FOR_PFT > 0) ? (($PRICE * 0.7) - $LP_FOR_PFT - $FBA_SHIP_FOR_PFT) / $LP_FOR_PFT : 0;
         
         // SPFT and SROI calculations matching analytics exactly (using same LP and FBA_SHIP sources)
         $spft = ($S_PRICE > 0) ? (($S_PRICE * 0.7) - $LP_FOR_PFT - $FBA_SHIP_FOR_PFT) / $S_PRICE : 0;
         $sroi = ($LP_FOR_PFT > 0) ? (($S_PRICE * 0.7) - $LP_FOR_PFT - $FBA_SHIP_FOR_PFT) / $LP_FOR_PFT : 0;

         $cvr = ($monthlySales ? ($monthlySales->l30_units ?? 0) : 0) / ($fbaReportsInfo ? ($fbaReportsInfo->current_month_views ?: 1) : 1) * 100;

         // Keep GPFT and GROI calculations with commission for backward compatibility
         $gpft = 0;
         if ($PRICE > 0) {
            $gpft = ($PRICE * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $PRICE;
         }

         $groi = 0;
         if ($LP > 0) {
            $groi = ($PRICE * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $LP;
         }

         // Keep SGPFT and SGROI calculations with commission for backward compatibility
         $sgpft = ($S_PRICE > 0) ? ($S_PRICE * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $S_PRICE : 0;
         $sgroi = ($LP > 0 && $S_PRICE > 0) ? ($S_PRICE * (1 - ($commissionPercentage  / 100 + 0.05)) - $LP - $FBA_SHIP)  / $LP : 0;

         $avgprice = $overallAvgPrice;


           $topgpft = 0;
         if ($PRICE > 0) {
            $topgpft = ($PRICE * (1 - ($commissionPercentage / 100 + 0.05)) - $LP - $FBA_SHIP) / $PRICE;
         }

         $gpftPercentage = round($gpft * 100);
         $sgpftPercentage = round($sgpft * 100);
         $groiPercentage = round($groi * 100);
         $pftPercentage = round($pft * 100, 2); // Match analytics: round to 2 decimal places
         $roiPercentage = round($roi * 100, 2); // Match analytics: round to 2 decimal places
         $spftPercentage = round($spft * 100, 2); // Match analytics: round to 2 decimal places
         $sroiPercentage = round($sroi * 100, 2); // Match analytics: round to 2 decimal places
         $sgroiPercentage = round($sgroi * 100);

         $pft_amt = $l30Units * $PRICE * $gpft;

         $sales_amt = $l30Units * $PRICE;

         $lp_amt = $LP * $l30Units;

         // Calculate Amazon L30 data from Amazon Datasheet
         $amazonData = $amazonDatasheet->get($sku);
         $amzL30 = $amazonData ? ($amazonData->units_ordered_l30 ?? 0) : 0;

         // Use separate dimension fields if available, otherwise split combined dimensions
         $length = $manual ? ($manual->data['length'] ?? '') : '';
         $width = $manual ? ($manual->data['width'] ?? '') : '';
         $height = $manual ? ($manual->data['height'] ?? '') : '';

         // If any separate fields are missing, try to fill from combined dimensions
         if (empty($length) || empty($width) || empty($height)) {
            $dimensions = $manual ? ($manual->data['dimensions'] ?? '') : '';
            $dimensionsParts = explode('x', str_replace(' ', '', $dimensions));
            if (empty($length)) $length = $dimensionsParts[0] ?? '';
            if (empty($width)) $width = $dimensionsParts[1] ?? '';
            if (empty($height)) $height = $dimensionsParts[2] ?? '';
         }

         return [
            'Parent' => $product ? ($product->parent ?? '') : '',
            'SKU' => $sku,
            'FBA_SKU' => $fba->seller_sku,
            'FBA_Price' => $fbaPriceInfo ? round(($fbaPriceInfo->price ?? 0), 2) : 0,
            'l30_units' => $monthlySales ? ($monthlySales->l30_units ?? 0) : 0,
            'AMZ_L30' => $amzL30,
            'Shopify_OV_L30' => $shopifyInfo ? ($shopifyInfo->quantity ?? 0) : 0,
            'Shopify_INV' => $shopifyInfo ? ($shopifyInfo->inv ?? 0) : 0,
            'l60_units' => $monthlySales ? ($monthlySales->l60_units ?? 0) : 0,
            'FBA_Quantity' => $fba->quantity_available,
            'FBA_Shipment_Status' => $fbaShipments->get(strtoupper(trim($fba->seller_sku))) ? $fbaShipments->get(strtoupper(trim($fba->seller_sku)))->shipment_status : '',
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
            'S_Price' => $S_PRICE > 0 ? round($S_PRICE, 2) : '', // ✅ Show empty if 0 to prevent confusion
            'SPRICE_STATUS' => $manual ? ($manual->data['SPRICE_STATUS'] ?? null) : null, // Status: 'pushed', 'applied', 'error'
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
            'Ratings' => $manual ? ($manual->data['ratings'] ?? 0) : 0,
            'Total_quantity_sent' => $manual ? ($manual->data['total_quantity_sent'] ?? 0) : 0,
            'Done' => $manual ? ($manual->data['done'] ?? false) : false,
            'Warehouse_INV_Reduction' => $manual ? ($manual->data['warehouse_inv_reduction'] ?? false) : false,
            'Shipping_Amount' => $manual ? ($manual->data['shipping_amount'] ?? 0) : 0,
            'Inbound_Quantity' => $manual ? ($manual->data['inbound_quantity'] ?? 0) : 0,
            'FBA_Send' => $manual ? ($manual->data['fba_send'] ?? false) : false,
            'Approval' => $manual ? ($manual->data['approval'] ?? false) : false,
            'Profit_is_ok' => $manual ? ($manual->data['profit_is_ok'] ?? false) : false,
            'Length' => $length,
            'Width' => $width,
            'Height' => $height,
            'Shipment_Track_Status' => $manual ? ($manual->data['shipment_track_status'] ?? '') : '',
            'MSL' => (
                ($monthlySales ? ($monthlySales->jan ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->feb ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->mar ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->apr ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->may ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->jun ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->jul ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->aug ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->sep ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->oct ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->nov ?? 0) : 0) +
                ($monthlySales ? ($monthlySales->dec ?? 0) : 0)
            ) - ($fba->quantity_available ?? 0) - ($fbaShipments->get(strtoupper(trim($fba->seller_sku)))->quantity_shipped ?? 0),
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

            'Total_Spend_L30' => $totalSpend,

            // TCOS calculation (total_spend_sum / price_l30 * 100)
            'TCOS_Percentage' => $tcosPercentage,

            // TPFT calculation (GPFT% - TCOS%)
            'TPFT' => round($gpftPercentage - $tcosPercentage, 2),
            'SPFT' => round($spftPercentage - $tcosPercentage, 2),
            'ROI' => round($roiPercentage - $tcosPercentage, 2),
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
            // Total sales for the 12 months - used in view (T sales)
            'T_sales' => (
               ($monthlySales ? ($monthlySales->jan ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->feb ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->mar ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->apr ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->may ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->jun ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->jul ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->aug ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->sep ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->oct ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->nov ?? 0) : 0) +
               ($monthlySales ? ($monthlySales->dec ?? 0) : 0)
            ),
            'PFT_AMT' => round($pft_amt, 2),
            'SALES_AMT' => round($sales_amt, 2),
            'LP_AMT' => round($lp_amt, 2),



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
            'Ratings' => '',
            'Total_Spend_L30' => $children->sum('Total_Spend_L30'),
            'TCOS_Percentage' => '',
            'Done' => false,
            'Warehouse_INV_Reduction' => false,
            'FBA_Send' => false,
            'Approval' => false,
            'Profit_is_ok' => false,
            'Shipping_Amount' => round($children->sum(fn($item) => is_numeric($item['Shipping_Amount']) ? $item['Shipping_Amount'] : 0), 2),
            'Inbound_Quantity' => round($children->sum(fn($item) => is_numeric($item['Inbound_Quantity']) ? $item['Inbound_Quantity'] : 0), 2),
            'Length' => '',
            'Width' => '',
            'Height' => '',
            'MSL' => $children->sum('MSL'),
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
            'PFT_AMT' => round($children->sum('PFT_AMT'), 2),
            'SALES_AMT' => round($children->sum('SALES_AMT'), 2),
            'LP_AMT' => round($children->sum('LP_AMT'), 2),
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

      // Validate ratings field
      if ($field === 'ratings') {
         $numValue = floatval($value);
         if ($numValue < 0 || $numValue > 5) {
            return response()->json([
               'success' => false,
               'error' => 'Ratings must be between 0 and 5'
            ], 400);
         }
         $value = $numValue; // Ensure it's stored as a number
      }

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

      // Special handling for dimension fields - reconstruct combined dimensions
      if (in_array($field, ['length', 'width', 'height'])) {
         $length = $data['length'] ?? '';
         $width = $data['width'] ?? '';
         $height = $data['height'] ?? '';
         $data['dimensions'] = trim($length . 'x' . $width . 'x' . $height, 'x');
      }

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


   public function exportFbaManualData(Request $request)
   {
      $selectedColumns = [];
      if ($request->has('columns')) {
         $columnsJson = $request->input('columns');
         $selectedColumns = json_decode($columnsJson, true) ?: [];
      }
      return $this->fbaManualDataService->exportToCSV($selectedColumns);
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

   /**
    * Push FBA price to Amazon with automatic verification
    * 
    * IMPORTANT FIX: The AmazonSpApiService now includes automatic verification
    * to ensure prices are actually updated on Amazon. It will:
    * 1. Update the price on Amazon
    * 2. Wait 1 second and verify the price was applied
    * 3. If verification fails, retry with fresh token (up to 2 attempts)
    * 4. Return error only if price truly wasn't applied after verification
    * 
    * This fixes the issue where Amazon API returns success but doesn't actually
    * apply the price on the first attempt (often due to token refresh timing).
    */
   public function pushFbaPrice(Request $request)
   {
      $sku = strtoupper(trim($request->input('sku')));
      $price = $request->input('price');

      // Validate SKU
      if (empty($sku)) {
         $this->saveSpriceStatus($sku, 'error');
         return response()->json([
            'errors' => [['code' => 'InvalidInput', 'message' => 'SKU is required.']]
         ], 400);
      }

      // Validate price
      if (!is_numeric($price) || $price <= 0) {
         $this->saveSpriceStatus($sku, 'error');
         return response()->json([
            'errors' => [['code' => 'InvalidInput', 'message' => 'Price must be a valid number greater than 0.']]
         ], 400);
      }
      $price = round((float)$price, 2); // Ensure price is a float and rounded

      // Additional price range validation
      if ($price < 0.01 || $price > 999999.99) {
         $this->saveSpriceStatus($sku, 'error');
         return response()->json([
            'errors' => [['code' => 'InvalidInput', 'message' => 'Price must be between $0.01 and $999,999.99.']]
         ], 400);
      }

      try {
         $service = new AmazonSpApiService();
         $result = $service->updateAmazonPriceUS($sku, $price);

         if (isset($result['errors']) && !empty($result['errors'])) {
            $this->saveSpriceStatus($sku, 'error');
            return response()->json($result);
         }

         $this->saveSpriceStatus($sku, 'pushed');
         return response()->json($result);
      } catch (\Exception $e) {
         $this->saveSpriceStatus($sku, 'error');
         Log::error('Exception in pushFbaPrice', [
            'sku' => $sku,
            'price' => $price,
            'error' => $e->getMessage()
         ]);
         return response()->json([
            'errors' => [['message' => $e->getMessage()]]
         ], 500);
      }
   }

   private function saveSpriceStatus($sku, $status)
   {
      try {
         $manual = FbaManualData::where('sku', strtoupper(trim($sku)))->first();
         
         if (!$manual) {
            $manual = new FbaManualData();
            $manual->sku = strtoupper(trim($sku));
            $manual->data = [];
         }
         
         $data = $manual->data ?? [];
         $data['SPRICE_STATUS'] = $status;
         $data['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
         
         $manual->data = $data;
         $manual->save();
         
         Log::info('FBA SPRICE status saved', ['sku' => $sku, 'status' => $status]);
      } catch (\Exception $e) {
         Log::error('Failed to save FBA SPRICE status', [
            'sku' => $sku,
            'status' => $status,
            'error' => $e->getMessage()
         ]);
      }
   }

   public function updateSpriceStatus(Request $request)
   {
      $sku = strtoupper(trim($request->input('sku')));
      $status = $request->input('status');
      
      if (!in_array($status, ['pushed', 'applied', 'error'])) {
         return response()->json([
            'success' => false,
            'message' => 'Invalid status. Must be: pushed, applied, or error.'
         ], 400);
      }
      
      $this->saveSpriceStatus($sku, $status);
      
      return response()->json([
         'success' => true,
         'message' => 'Status updated successfully',
         'status' => $status
      ]);
   }

   public function getMetricsHistory(Request $request)
   {
      $days = $request->input('days', 30); // Default to last 30 days
      $sku = $request->input('sku'); // Optional SKU filter
      
      // Ensure minimum 7 days if pulling from today
      $minDays = 7;
      if ($days < $minDays) {
         $days = $minDays;
      }
      
      $startDate = Carbon::today()->subDays($days - 1); // -1 to include today
      $endDate = Carbon::today();
      
      $chartData = [];
      $dataByDate = []; // Store data by date for filling gaps
      
      try {
         // Try to use the new table for JSON format data
         $query = FbaSkuDailyData::where('record_date', '>=', $startDate)
            ->where('record_date', '<=', $endDate)
            ->orderBy('record_date', 'asc');
         
         // If SKU is provided, return data for specific SKU
         if ($sku) {
            $metricsData = $query->where('sku', strtoupper(trim($sku)))->get();
            
            foreach ($metricsData as $record) {
               $data = $record->daily_data;
               $dateKey = Carbon::parse($record->record_date)->format('Y-m-d');
               $dataByDate[$dateKey] = [
                  'date' => $dateKey,
                  'date_formatted' => Carbon::parse($record->record_date)->format('M d'),
                  'price' => round($data['price'] ?? 0, 2),
                  'views' => $data['views'] ?? 0,
                  'cvr_percent' => round($data['cvr_percent'] ?? 0, 2),
                  'tacos_percent' => round($data['tacos_percent'] ?? 0, 2),
               ];
            }
         } else {
            // Aggregate data for all SKUs
            $metricsData = $query->get()->groupBy('record_date');
            
            foreach ($metricsData as $date => $records) {
               $dateKey = Carbon::parse($date)->format('Y-m-d');
               
               // Calculate weighted average price (same as summary badge: price * l30_units / sum l30_units)
               $totalWeightedPrice = 0;
               $totalL30 = 0;
               foreach ($records as $record) {
                  $price = floatval($record->daily_data['price'] ?? 0);
                  $l30Units = floatval($record->daily_data['l30_units'] ?? 0);
                  $totalWeightedPrice += $price * $l30Units;
                  $totalL30 += $l30Units;
               }
               $avgPrice = $totalL30 > 0 ? ($totalWeightedPrice / $totalL30) : 0;
               
               $dataByDate[$dateKey] = [
                  'date' => $dateKey,
                  'date_formatted' => Carbon::parse($date)->format('M d'),
                  'avg_price' => round($avgPrice, 2),
                  'total_views' => $records->sum(function($r) { return $r->daily_data['views'] ?? 0; }),
                  'avg_cvr_percent' => round($records->avg(function($r) { return $r->daily_data['cvr_percent'] ?? 0; }), 2),
                  'avg_tacos_percent' => round($records->avg(function($r) { return $r->daily_data['tacos_percent'] ?? 0; }), 2),
               ];
            }
         }
         
         // If no data found in new table, try fallback to old table
         if (empty($dataByDate)) {
            throw new \Exception('No data in new table, trying fallback');
         }
         
      } catch (\Exception $e) {
         // Fallback to old table and calculate CVR
         Log::info('Using fallback to FbaMetricsHistory table: ' . $e->getMessage());
         
         $query = FbaMetricsHistory::where('record_date', '>=', $startDate)
            ->where('record_date', '<=', $endDate)
            ->orderBy('record_date', 'asc');
         
         if ($sku) {
            $metricsData = $query->where('sku', strtoupper(trim($sku)))->get();
            
            if ($metricsData->isEmpty()) {
               Log::info('No metrics history data found for SKU: ' . $sku);
            } else {
               // Get monthly sales for CVR calculation
               $monthlySales = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                  ->get()
                  ->keyBy(function ($item) {
                     $sku = $item->seller_sku;
                     $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                     return strtoupper(trim($base));
                  });
               
               $normalizedSku = strtoupper(trim($sku));
               $monthlySale = $monthlySales->get($normalizedSku);
               $l30Units = $monthlySale ? ($monthlySale->l30_units ?? 0) : 0;
               
               foreach ($metricsData as $record) {
                  $views = $record->views ?? 0;
                  
                  // Calculate CVR from stored views and monthly sales
                  // Note: This is an approximation since we're using monthly l30_units with daily views
                  $cvr = 0;
                  if ($views > 0 && $l30Units > 0) {
                     // Estimate daily CVR: distribute monthly units across views proportionally
                     // This gives an approximate CVR percentage
                     $cvr = ($l30Units / $views) * 100;
                  }
                  
                  $dateKey = Carbon::parse($record->record_date)->format('Y-m-d');
                  $dataByDate[$dateKey] = [
                     'date' => $dateKey,
                     'date_formatted' => Carbon::parse($record->record_date)->format('M d'),
                     'price' => round($record->price ?? 0, 2),
                     'views' => $views,
                     'cvr_percent' => round($cvr, 2),
                     'tacos_percent' => round($record->tacos ?? 0, 2),
                  ];
               }
            }
         } else {
            // Aggregate data for all SKUs from old table
            $metricsData = $query->get()->groupBy('record_date');
            
            // Get monthly sales for L30 data (for weighted average)
            $monthlySales = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
               ->get()
               ->keyBy(function ($item) {
                  $sku = $item->seller_sku;
                  $base = preg_replace('/\s*FBA\s*/i', '', $sku);
                  return strtoupper(trim($base));
               });
            
            foreach ($metricsData as $date => $records) {
               $dateKey = Carbon::parse($date)->format('Y-m-d');
               
               // Calculate weighted average price (same as summary badge: price * l30_units / sum l30_units)
               $totalWeightedPrice = 0;
               $totalL30 = 0;
               foreach ($records as $record) {
                  $price = floatval($record->price ?? 0);
                  $normalizedSku = strtoupper(trim($record->sku));
                  $monthlySale = $monthlySales->get($normalizedSku);
                  $l30Units = $monthlySale ? floatval($monthlySale->l30_units ?? 0) : 0;
                  $totalWeightedPrice += $price * $l30Units;
                  $totalL30 += $l30Units;
               }
               $avgPrice = $totalL30 > 0 ? ($totalWeightedPrice / $totalL30) : 0;
               
               $dataByDate[$dateKey] = [
                  'date' => $dateKey,
                  'date_formatted' => Carbon::parse($date)->format('M d'),
                  'avg_price' => round($avgPrice, 2),
                  'total_views' => $records->sum('views'),
                  'avg_cvr_percent' => 0, // Can't calculate CVR from aggregated data easily
                  'avg_tacos_percent' => round($records->avg('tacos'), 2),
               ];
            }
         }
      }

      // Fill in missing dates with zero values to ensure at least 7 days
      $currentDate = Carbon::parse($startDate);
      $today = Carbon::today();
      
      while ($currentDate->lte($today)) {
         $dateKey = $currentDate->format('Y-m-d');
         
         if (!isset($dataByDate[$dateKey])) {
            // Fill missing date with zero values
            if ($sku) {
               $dataByDate[$dateKey] = [
                  'date' => $dateKey,
                  'date_formatted' => $currentDate->format('M d'),
                  'price' => 0,
                  'views' => 0,
                  'cvr_percent' => 0,
                  'tacos_percent' => 0,
               ];
            } else {
               $dataByDate[$dateKey] = [
                  'date' => $dateKey,
                  'date_formatted' => $currentDate->format('M d'),
                  'avg_price' => 0,
                  'total_views' => 0,
                  'avg_cvr_percent' => 0,
                  'avg_tacos_percent' => 0,
               ];
            }
         }
         
         $currentDate->addDay();
      }
      
      // Sort by date and convert to array
      ksort($dataByDate);
      $chartData = array_values($dataByDate);

      return response()->json($chartData);
   }

   public function getFbaDispatchColumnVisibility()
   {
      $userId = auth()->id();
      if (!$userId) {
         return response()->json([]);
      }

      $cacheKey = 'fba_dispatch_columns_' . $userId;
      return response()->json(cache($cacheKey, []));
   }

   public function setFbaDispatchColumnVisibility(Request $request)
   {
      $userId = auth()->id();
      if (!$userId) {
         return response()->json(['success' => false]);
      }

      $visibility = $request->input('visibility', []);
      $cacheKey = 'fba_dispatch_columns_' . $userId;
      
      cache([$cacheKey => $visibility], 60 * 24 * 30); // Cache for 30 days

      return response()->json(['success' => true]);
   }
}
