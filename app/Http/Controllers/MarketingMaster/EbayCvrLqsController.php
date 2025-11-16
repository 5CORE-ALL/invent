<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CvrLqs;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\AmazonDatasheet; // Add this at the top with other use statements
use App\Models\MarketplacePercentage;
use App\Models\AmazonDataView;
use App\Models\EbayCvrLqs;
use App\Models\EbayDataView;
use App\Models\EbayGeneralReport;
use App\Models\EbayMetric;
use App\Models\ListingLqs;
use Illuminate\Support\Facades\Cache;

class EbayCvrLqsController extends Controller
{
     protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function cvrLQSMaster(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('marketing-masters.ebay-cvr-lqs', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }


    public function getViewEbayCvrData(Request $request)
    {
        // 1. Fetch ProductMasters
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // 2. Fetch EbayMetric + ShopifySku
        $ebayMetricsBySku = EbayMetric::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // 3. NR values
        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // Build mapping arrays
        $itemIdToSku = $ebayMetricsBySku->pluck('sku', 'item_id')->toArray();
        $campaignIdToSku = $ebayMetricsBySku->pluck('sku', 'campaign_id')->toArray();

        // Fetch General Reports (listing_id → clicks etc.)
        $generalReports = EbayGeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->whereIn('report_range', ['L30'])
            ->get();

        // Build clicks by SKU
        $adMetricsBySku = [];
        foreach ($generalReports as $report) {
            $sku = $itemIdToSku[$report->listing_id] ?? null;
            if (!$sku) continue;

            $adMetricsBySku[$sku]['L30']['Clk'] =
                ($adMetricsBySku[$sku]['L30']['Clk'] ?? 0) + (int) $report->clicks;
        }

        // Fetch extra clicks (same as in getViewEbayData)
        $extraClicksData = EbayGeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->where('report_range', 'L30')
            ->pluck('clicks', 'listing_id')
            ->toArray();

        // 4. Marketplace %
        $percentage = Cache::remember('ebay_marketplace_percentage', now()->addDays(30), function () {
            return MarketplacePercentage::where('marketplace', 'EBay')->value('percentage') ?? 100;
        }) / 100;

        // 5. LQS actions
        $lqsActions = EbayCvrLqs::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // 6. Build final data
        $result = [];
        foreach ($productMasters as $pm) {
            if (stripos($pm->sku, 'PARENT ') === 0) continue;

            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $ebayMetric = $ebayMetricsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            $row['Parent'] = $parent;
            $row['(Child) sku'] = $pm->sku;

            if ($ebayMetric) {
                $row['E_L30'] = $ebayMetric->ebay_l30 ?? 0;
                $row['E_L60'] = $ebayMetric->ebay_l60 ?? 0;
                $row['price'] = $ebayMetric->ebay_price ?? 0;
                $row['views'] = $ebayMetric->views ?? 0;
                $row['ebay_item_id'] = $ebayMetric->item_id ?? null;
            }

            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;

            if ($row['INV'] <= 0) continue;

            // Add PmtClkL30
            $row['PmtClkL30'] = $adMetricsBySku[$sku]['L30']['Clk'] ?? 0;
            if ($ebayMetric && isset($extraClicksData[$ebayMetric->item_id])) {
                $row['PmtClkL30'] += (int) $extraClicksData[$ebayMetric->item_id];
            }

            // LP/Ship
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) $lp = floatval($pm->lp);
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $price = isset($row['price']) ? floatval($row['price']) : 0;
            $units_ordered_l30 = isset($row['E_L30']) ? floatval($row['E_L30']) : 0;
            $row['Total_pft'] = round((($price * $percentage) - $lp - $ship) * $units_ordered_l30, 2);
            $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
            $row['ROI_percentage'] = round($lp > 0 ? ((($price * $percentage) - $lp - $ship) / $lp) * 100 : 0, 2);
            $row['T_COGS'] = round($lp * $units_ordered_l30, 2);

            $row['percentage'] = $percentage;
            $row['LP_productmaster'] = $lp;
            $row['Ship_productmaster'] = $ship;

            // NR
            $row['NR'] = 'REQ';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) {
                    $row['NR'] = isset($raw['NR']) && in_array($raw['NR'], ['REQ', 'NR']) ? $raw['NR'] : 'REQ';
                }
            }

            $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

            // Actions
            $row['action'] = $lqsActions[$sku]->value['action'] ?? '';
            $row['listing_quality_score'] = $lqsActions[$sku]->value['listing_quality_score'] ?? '';
            $row['listing_quality_score_c'] = $lqsActions[$sku]->value['listing_quality_score_c'] ?? '';
            $row['link'] = $lqsActions[$sku]->value['link'] ?? '';
            $row['comp'] = $lqsActions[$sku]->value['comp'] ?? '';
            $row['status'] = $lqsActions[$sku]->value['status'] ?? '';

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'eBay CVR Data fetched successfully',
            'data' => $result,
            'status' => 200,
        ]);
    }



    public function saveEbayAction(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'action' => 'nullable|string',
            // 'listing_quality_score' => 'nullable|numeric',
            'comp' => 'nullable|numeric',
            'link' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        // Normalize SKU (remove extra spaces, unify case)
        $sku = strtoupper(trim(preg_replace('/\s+/', ' ', $request->input('sku'))));

        $action = $request->input('action');
        // $lqs = $request->input('listing_quality_score');
        $comp = $request->input('comp');
        $link = $request->input('link');
        $status = $request->input('status');

        // Find the record strictly by normalized SKU
        $record = EbayCvrLqs::where('sku', $sku)->first();

        if (!$record) {
            $record = new EbayCvrLqs();
            $record->sku = $sku;
            $value = [];
        } else {
            $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
        }

        if (!is_null($action)) $value['action'] = $action;
        // if (!is_null($lqs)) $value['listing_quality_score'] = $lqs;
        if (!is_null($comp)) $value['comp'] = $comp;
        if (!is_null($link)) $value['link'] = $link;
        if (!is_null($status)) $value['status'] = $status;

        $record->value = $value;
        $record->save();

        return response()->json(['success' => true, 'data' => $record]);
    }


    // public function importEbayCVRData(Request $request)
    // {
    //     if (!$request->hasFile('file')) {
    //         return response()->json(['error' => 'No file uploaded'], 400);
    //     }

    //     $file = $request->file('file');
    //     $data = array_map('str_getcsv', file($file));

    //     $header = array_map('trim', $data[0]);
    //     unset($data[0]);

    //     foreach ($data as $row) {
    //         if (count($row) < 3) continue; 

    //         $sku  = trim($row[0]);
    //         // $lqs  = isset($row[1]) ? trim((string)$row[1]) : '';
    //         $comp = isset($row[1]) ? trim($row[1]) : '';
    //         $link = isset($row[2]) ? trim($row[2]) : '';

    //         $cvrValue = [
    //             // 'action'                  => 'null',
    //             // 'listing_quality_score'   => $lqs,
    //             'comp'                    => $comp,
    //             'link'                    => $link,
    //         ];

    //         EbayCvrLqs::updateOrCreate(
    //             ['sku' => $sku],
    //             [
    //                 'value'      => $cvrValue, 
    //                 'updated_at' => now()
    //             ]
    //         );

    //         // ListingLqs::updateOrCreate(
    //         //     ['sku' => $sku],
    //         //     [
    //         //         'value'      => $cvrValue, 
    //         //         'updated_at' => now()
    //         //     ]
    //         // );
    //     }

    //     return response()->json(['success' => true]);
    // }

    public function importEbayCVRData(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');
        $data = array_map('str_getcsv', file($file));

        $header = array_map('trim', $data[0]);
        unset($data[0]);

        foreach ($data as $row) {
            if (count($row) < 3) continue;

            $sku  = trim($row[0]);
            $comp = isset($row[1]) ? trim($row[1]) : '';
            $link = isset($row[2]) ? trim($row[2]) : '';

            // Find existing record
            $record = EbayCvrLqs::where('sku', $sku)->first();

            if ($record) {
                // Decode old value JSON
                $oldValue = is_array($record->value) 
                    ? $record->value 
                    : (json_decode($record->value, true) ?? []);

                // Merge new values into existing JSON
                $newValue = array_merge($oldValue, [
                    'comp' => $comp,
                    'link' => $link,
                ]);

                $record->value = $newValue;
                $record->updated_at = now();
                $record->save();
            } else {
                // New record
                $cvrValue = [
                    'comp' => $comp,
                    'link' => $link,
                ];

                EbayCvrLqs::create([
                    'sku'        => $sku,
                    'value'      => $cvrValue,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    public function getPendingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = EbayCvrLqs::whereIn('sku', $skus)->get()->keyBy('sku');

        $reqCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? (floatval($inv) > 0 ? 'REQ' : 'NR');
            $rowStatus = $status['status'] ?? null;
            
            if ($nrReq === 'REQ') {
                $reqCount++;
            }

            // if ($nrReq !== 'NR') {
                if ($rowStatus === 'Processed') {
                    $listedCount++;
                } elseif ($rowStatus === 'Pending' || empty($rowStatus)) {
                    $pendingCount++;
                }
            // }
           
        }

        return [
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ];
    }
    
}
