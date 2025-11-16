<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\FbaTable;
use App\Models\FbaPrice;
use App\Models\FbaReportsMaster;
use App\Models\FbaMonthlySale;
use App\Models\FbaManualData;
use App\Models\FbaOrder;
use App\Services\ColorService;
use App\Services\FbaManualDataService;
use App\Services\LmpaDataService;
use Symfony\Polyfill\Intl\Idn\Resources\unidata\DisallowedRanges;

class FbaDataController extends Controller
{
   protected $fbaManualDataService;
   protected $colorService;
   protected $lmpaDataService;

   public function __construct(FbaManualDataService $fbaManualDataService, ColorService $colorService, LmpaDataService $lmpaDataService)
   {
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

      $matchedSkus = $fbaData->keys()->toArray();
      $unmatchedSkus = array_diff($skus, $matchedSkus);

      return compact('productData', 'shopifyData', 'fbaData', 'fbaPriceData', 'fbaReportsData', 'matchedSkus', 'unmatchedSkus', 'fbaMonthlySales', 'fbaManualData', 'fbaDispatchDates');
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

   public function fbaAdsPt(){
      $data = $this->getFbaData();
      return view('fba.fba_ads_pt', $data);
   }





   public function fbaDataJson()
   {
      $data = $this->getFbaData();

      $fbaData = $data['fbaData'];
      $fbaPriceData = $data['fbaPriceData'];
      $fbaReportsData = $data['fbaReportsData'];
      $shopifyData = $data['shopifyData'];
      $fbaMonthlySales = $data['fbaMonthlySales'];
      $fbaManualData = $data['fbaManualData'];
      $fbaDispatchDates = $data['fbaDispatchDates'];
      $productData = $data['productData']->keyBy(function ($p) {
         return strtoupper(trim($p->sku));
      });

      // Prepare table data with repeated parent name for all child SKUs
      $tableData = $fbaData->map(function ($fba, $sku) use ($fbaPriceData, $fbaReportsData, $shopifyData, $productData, $fbaMonthlySales, $fbaManualData, $fbaDispatchDates) {
         $fbaPriceInfo = $fbaPriceData->get($sku);
         $fbaReportsInfo = $fbaReportsData->get($sku);
         $shopifyInfo = $shopifyData->get($sku);
         $product = $productData->get($sku);
         // dd($product->Values['lp']);
         $monthlySales = $fbaMonthlySales->get($sku);
         $manual = $fbaManualData->get(strtoupper(trim($fba->seller_sku)));
         $dispatchDate = $fbaDispatchDates->get($sku);

         $lmpaData = $this->lmpaDataService->getLmpaData($sku);

         $PRICE = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
         $LP = $product ? floatval($product->Values['lp'] ?? 0) : 0;
         $FBA_SHIP = $fbaReportsInfo ? floatval($fbaReportsInfo->fulfillment_fee ?? 0) : 0;
         $S_PRICE = $manual ? floatval($manual->data['s_price'] ?? 0) : 0;

         // --- Calculate all profit & ROI metrics ---
         $pft = ($PRICE > 0) ? (($PRICE * 0.7) - $LP - $FBA_SHIP) / $PRICE : 0;
         $roi = ($LP > 0) ? (($PRICE * 0.7) - $LP - $FBA_SHIP) / $LP : 0;
         $spft = ($S_PRICE > 0) ? (($S_PRICE * 0.7) - $LP - $FBA_SHIP) / $S_PRICE : 0;
         $sroi = ($LP > 0) ? (($S_PRICE * 0.7) - $LP - $FBA_SHIP) / $LP : 0;

         $pftPercentage = round($pft * 100, 2);
         $roiPercentage = round($roi * 100, 2);
         $spftPercentage = round($spft * 100, 2);
         $sroiPercentage = round($sroi * 100, 2);
         $cvr = ($monthlySales ? ($monthlySales->l30_units ?? 0) : 0) / ($fbaReportsInfo ? ($fbaReportsInfo->current_month_views ?: 1) : 1) * 100;

         return [
            'Parent' => $product ? ($product->parent ?? '') : '',
            'SKU' => $sku,
            'FBA_SKU' => $fba->seller_sku,
            'FBA_Price' => $fbaPriceInfo ? round(($fbaPriceInfo->price ?? 0), 2) : 0,
            'l30_units' => $monthlySales ? ($monthlySales->l30_units ?? 0) : 0,
            'Shopify_OV_L30' => $shopifyInfo ? ($shopifyInfo->quantity ?? 0) : 0,
            'Shopify_inv' => $shopifyInfo ? ($shopifyInfo->inv ?? 0) : 0,
            'l60_units' => $monthlySales ? ($monthlySales->l60_units ?? 0) : 0,
            'FBA_Quantity' => $fba->quantity_available,
            'Dil' => ($shopifyInfo ? ($shopifyInfo->quantity ?? 0) : 0) / max($shopifyInfo ? ($shopifyInfo->inv ?? 0) : 1, 1) * 100,
            'FBA_Dil' => ($monthlySales ? ($monthlySales->l30_units ?? 0) : 0) / ($fba->quantity_available ?: 1) * 100,
            'Current_Month_Views' => $fbaReportsInfo ? ($fbaReportsInfo->current_month_views ?? 0) : 0,
            'FBA_CVR' => $this->colorService->getCvrHtml($cvr),
            'Listed' => $manual ? ($manual->data['listed'] ?? false) : false,
            'Live' => $manual ? ($manual->data['live'] ?? false) : false,
            'Pft%' => $pftPercentage,
            'Pft%_HTML' => $this->colorService->getValueHtml($pftPercentage),
            'ROI%' => $this->colorService->getRoiHtmlForView($roiPercentage),
            'S_Price' => round($S_PRICE, 2),
            'SPft%' => $this->colorService->getValueHtml($spftPercentage),
            'SROI%' => $this->colorService->getRoiHtmlForView($sroiPercentage),
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
               $manual ? ($manual->data['send_cost'] ?? 0) : 0,
               $manual ? ($manual->data['in_charges'] ?? 0) : 0
            ),
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

   public function updateFbaManualData(Request $request)
   {
      $sku = strtoupper(trim($request->input('sku')));
      $field = $request->input('field');
      $value = $request->input('value');

      $manual = FbaManualData::where('sku', $sku)->first();

      if (!$manual) {
         $manual = new FbaManualData();
         $manual->sku = $sku;
         $manual->data = [];
      }

      $data = $manual->data ?? [];
      $data[$field] = $value;
      $manual->data = $data;
      $manual->save();

      return response()->json(['success' => true]);
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
}
