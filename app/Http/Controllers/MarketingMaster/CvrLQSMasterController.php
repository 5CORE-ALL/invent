<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\CvrLqs;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\AmazonDatasheet; // Add this at the top with other use statements
use App\Models\MarketplacePercentage;
use App\Models\AmazonDataView;
use App\Models\ListingLqs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CvrLQSMasterController extends Controller
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

        return view('marketing-masters.cvrLQS-master', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

        public function getViewCvrData(Request $request)
        {
            // 1. Fetch all ProductMaster rows (base)
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            // 2. Fetch AmazonDatasheet and ShopifySku for those SKUs
            $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            // 3. Fetch API data (Google Sheet)
            // $response = $this->apiController->fetchDataFromAmazonGoogleSheet();
            // $apiDataArr = ($response->getStatusCode() === 200) ? ($response->getData()->data ?? []) : [];
            // // Index API data by SKU (case-insensitive)
            // $apiDataBySku = [];
            // foreach ($apiDataArr as $item) {
            //     $sku = isset($item->{'(Child) sku'}) ? strtoupper(trim($item->{'(Child) sku'})) : null;
            //     if ($sku)
            //         $apiDataBySku[$sku] = $item;
            // }

            // 4. JungleScout Data (by parent)
            $parents = $productMasters->pluck('parent')->filter()->unique()->map('strtoupper')->values()->all();
            // JungleScout Data
            $jungleScoutData = JungleScoutProductData::whereIn('parent', $parents)
                ->get()
                ->groupBy(function ($item) {
                    return strtoupper(trim($item->parent));
                })
                ->map(function ($group) {
                    $validPrices = $group->filter(function ($item) {
                        $data = is_array($item->data) ? $item->data : [];
                        $price = $data['price'] ?? null;
                        return is_numeric($price) && $price > 0;
                    })->pluck('data.price');

                    return [
                        'scout_parent' => $group->first()->parent,
                        'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                        'product_count' => $group->count(),
                        'all_data' => $group->map(function ($item) {
                            $data = is_array($item->data) ? $item->data : [];
                            if (isset($data['price'])) {
                                $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                            }
                            return $data;
                        })->toArray()
                    ];
                });

            // 5. NR values
            $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            // 6. Marketplace percentage
            $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
                return MarketplacePercentage::where('marketplace', 'Amazon')->value('percentage') ?? 100;
            });
            $percentage = $percentage / 100;

            // 7. Fetch all listing_lqs actions by SKU
            $lqsActions = CvrLqs::all()->keyBy(function($item) {
                return strtoupper($item->sku);
            });

            // $lqsActions = ListingLqs::all()->keyBy(function ($item) {
            //     return strtoupper($item->sku);
            // });

            // 8. Build final data
            $result = [];
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = $pm->parent;
                $apiItem = $apiDataBySku[$sku] ?? null;
                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$pm->sku] ?? null;

                // Merge API data into base row if exists
                $row = [];
                $row['Parent'] = $parent;
                $row['(Child) sku'] = $pm->sku;

                // Merge API fields if available
                if ($apiItem) {
                    foreach ($apiItem as $k => $v) {
                        $row[$k] = $v;
                    }
                }

                // Add AmazonDatasheet fields if available
                if ($amazonSheet) {
                    $row['A_L30'] = $row['A_L30'] ?? $amazonSheet->units_ordered_l30;
                    $row['Sess30'] = $row['Sess30'] ?? $amazonSheet->sessions_l30;
                    $row['price'] = $row['price'] ?? $amazonSheet->price;
                    $row['sessions_l60'] = $row['sessions_l60'] ?? $amazonSheet->sessions_l60;
                    $row['units_ordered_l60'] = $row['units_ordered_l60'] ?? $amazonSheet->units_ordered_l60;
                }

                // Add Shopify fields if available
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;

                // LP and Ship from ProductMaster
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $lp = 0;
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

                // Formulas
                $price = isset($row['price']) ? floatval($row['price']) : 0;
                $units_ordered_l30 = isset($row['A_L30']) ? floatval($row['A_L30']) : 0;
                $row['Total_pft'] = round((($price * $percentage) - $lp - $ship) * $units_ordered_l30, 2);
                $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);
                $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
                $row['ROI_percentage'] = round($lp > 0 ? ((($price * $percentage) - $lp - $ship) / $lp) * 100 : 0, 2);
                $row['T_COGS'] = round($lp * $units_ordered_l30, 2);

                // JungleScout
                $parentKey = strtoupper($parent);
                if (!empty($parentKey) && $jungleScoutData->has($parentKey)) {
                    $row['scout_data'] = $jungleScoutData[$parentKey];
                }

                $jungleSkuData = JungleScoutProductData::where('sku', $pm->sku)->latest()->first();

                if ($jungleSkuData && isset($jungleSkuData->data['listing_quality_score'])) {
                    $row['lqs_jungle'] = floatval($jungleSkuData->data['listing_quality_score']);
                } else {
                    $row['lqs_jungle'] = null;
                }

                // Percentage, LP, Ship
                $row['percentage'] = $percentage;
                $row['LP_productmaster'] = $lp;
                $row['Ship_productmaster'] = $ship;

                // NR value
                // $row['NR'] = false;
                $row['NR'] = 'REQ';
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }

                    if (is_array($raw)) {
                        // $row['NR'] = filter_var($raw['NR'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $row['NR'] = isset($raw['NR']) && in_array($raw['NR'], ['REQ', 'NR']) ? $raw['NR'] : 'REQ';
                    } else {
                        $decoded = json_decode($raw, true);
                        // $row['NR'] = filter_var($decoded['NR'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $row['NR'] = isset($raw['NR']) && in_array($raw['NR'], ['REQ', 'NR']) ? $raw['NR'] : 'REQ';
                    }
                }

                // Image path (from Shopify or ProductMaster)
                $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

                // Add action from listing_lqs if exists
                $row['action'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['action'])) {
                    $row['action'] = $lqsActions[$sku]->value['action'];
                }

                $row['listing_quality_score'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['listing_quality_score'])) {
                    $row['listing_quality_score'] = $lqsActions[$sku]->value['listing_quality_score'];
                }

                $row['listing_quality_score_c'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['listing_quality_score_c'])) {
                    $row['listing_quality_score_c'] = $lqsActions[$sku]->value['listing_quality_score_c'];
                }

                $row['link'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['link'])) {
                    $row['link'] = $lqsActions[$sku]->value['link'];
                }

                $row['status'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['status'])) {
                    $row['status'] = $lqsActions[$sku]->value['status'];
                }

                // Skip parent and inv <= 0 (align with getPendingCount)
                if (stripos($pm->sku, 'PARENT') !== false) {
                    continue;
                }
                if (floatval($shopify->inv ?? 0) <= 0) {
                    continue;
                }

                $result[] = (object) $row;
            }

            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $result,
                'status' => 200,
            ]);
        }

    // public function saveAction(Request $request)
    // {
    //     $request->validate([
    //         'sku' => 'required|string',
    //         'action' => 'nullable|string',
    //         'listing_quality_score' => 'nullable|numeric',
    //         'listing_quality_score_c' => 'nullable|numeric',
    //         'link' => 'nullable|string',
    //         'status' => 'nullable|string',
    //     ]);

    //     $sku = $request->input('sku');
    //     $action = $request->input('action');
    //     $lqs = $request->input('listing_quality_score');
    //     $lqsc = $request->input('listing_quality_score_c');
    //     $link = $request->input('link');
    //     $status = $request->input('status');

    //     // Find or create the record
    //     $record = CvrLqs::firstOrNew(['sku' => $sku]);
    //     $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
    //     // $value = $record->value ?? [];
    //     // $value['action'] = $action;
    //     // $value['listing_quality_score'] = $lqs;
    //     // $value['listing_quality_score_c'] = $lqsc;
    //     // $value['link'] = $link;

    //     if (!is_null($action)) $value['action'] = $action;
    //     if (!is_null($lqs)) $value['listing_quality_score'] = $lqs;
    //     if (!is_null($lqsc)) $value['listing_quality_score_c'] = $lqsc;
    //     if (!is_null($link)) $value['link'] = $link;
    //     if (!is_null($status)) $value['status'] = $status; 
    //     dd($record, $value);


    //     $record->value = $value;
    //     $record->save();

    //     return response()->json(['success' => true, 'data' => $record]);
    // }

    public function saveAction(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'action' => 'nullable|string',
            'listing_quality_score' => 'nullable|numeric',
            'listing_quality_score_c' => 'nullable|numeric',
            'link' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        // Normalize SKU (remove extra spaces, unify case)
        $sku = strtoupper(trim(preg_replace('/\s+/', ' ', $request->input('sku'))));

        $action = $request->input('action');
        $lqs = $request->input('listing_quality_score');
        $lqsc = $request->input('listing_quality_score_c');
        $link = $request->input('link');
        $status = $request->input('status');

        // Find the record strictly by normalized SKU
        $record = CvrLqs::where('sku', $sku)->first();

        if (!$record) {
            $record = new CvrLqs();
            $record->sku = $sku;
            $value = [];
        } else {
            $value = is_array($record->value) ? $record->value : (json_decode($record->value, true) ?? []);
        }

        if (!is_null($action)) $value['action'] = $action;
        if (!is_null($lqs)) $value['listing_quality_score'] = $lqs;
        if (!is_null($lqsc)) $value['listing_quality_score_c'] = $lqsc;
        if (!is_null($link)) $value['link'] = $link;
        if (!is_null($status)) $value['status'] = $status;

        $record->value = $value;
        $record->save();

        return response()->json(['success' => true, 'data' => $record]);
    }



    // public function importCVRData(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|file|mimes:csv,txt',
    //     ]);

    //     $file = fopen($request->file('csv_file'), 'r');
    //     $header = fgetcsv($file); // Read header

    //     $header = array_map('trim', $header);

    //     $skuIndex = array_search('SKU', $header);
    //     $lqsIndex = array_search('LQS', $header);
    //     $lqscIndex = array_search('LQSC', $header);
    //     $linkIndex = array_search('C LINK', $header);

    //     if ($skuIndex === false || $lqsIndex === false || $lqscIndex === false || $linkIndex === false) {
    //         return redirect()->back()->with('error', 'Required columns not found in the CSV.');
    //     }

    //     $inserted = 0;
    //     $updated = 0;

    //     while (($row = fgetcsv($file)) !== false) {
    //         $sku = trim($row[$skuIndex]);

    //         // Build JSON data
    //         $jsonData = [
    //             'action' => 'test',
    //             'listing_quality_score' => trim($row[$lqsIndex]) ?: null,
    //             'listing_quality_score_c' => trim($row[$lqscIndex]) ?: null,
    //             'link' => trim($row[$linkIndex]) ?: null,
    //         ];

    //         // Try to find existing record
    //         $record = CvrLqs::whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($sku)])->first();
    //         dd($record);

    //         if ($record) {
    //             $record->update(['data' => $jsonData]);
    //             $updated++;
    //         } else {
    //             CvrLqs::create([
    //                 'sku' => $sku,
    //                 'data' => $jsonData
    //             ]);
    //             $inserted++;
    //         }
    //     }

    //     fclose($file);

    //     return redirect()->back()->with('success', "{$updated} rows updated, {$inserted} rows inserted.");
    // }


    // public function importCVRData(Request $request)
    // {
    //     if (!$request->hasFile('file')) {
    //         return response()->json(['error' => 'No file uploaded'], 400);
    //     }

    //     $file = $request->file('file');
    //     $data = array_map('str_getcsv', file($file));

    //     $header = array_map('trim', $data[0]);
    //     unset($data[0]);

    //     foreach ($data as $row) {
    //         if (count($row) < 4) continue; // Skip if any field is missing

    //         $sku = trim($row[0]);
    //         $lqs = isset($row[1]) ? trim((string)$row[1]) : '';
    //         $lqsc = isset($row[2]) ? trim((string)$row[2]) : '';
    //         $link = isset($row[3]) ? trim($row[3]) : '';

    //         // Value for CvrLqs (includes link)
    //         $cvrValue = [
    //             'action' => 'null',
    //             'listing_quality_score' => $lqs,
    //             'listing_quality_score_c' => $lqsc,
    //             'link' => $link,
    //         ];

    //         // Update or insert in CvrLqs
    //         $record = CvrLqs::where('sku', $sku)->first();
    //         if ($record) {
    //             CvrLqs::where('sku', $sku)->update([
    //                 'value' => json_encode($cvrValue),
    //                 'updated_at' => now()
    //             ]);
    //         } else {
    //             CvrLqs::updateOrCreate([
    //                 'sku' => $sku,
    //                 'value' => json_encode($cvrValue),
    //                 'created_at' => now(),
    //                 'updated_at' => now()
    //             ]);
    //         }

    //         $records = ListingLqs::where('sku', $sku)->first();
    //         if ($record) {
    //             ListingLqs::where('sku', $sku)->update([
    //                 'value' => json_encode($cvrValue),
    //                 'updated_at' => now()
    //             ]);
    //         } else {
    //             ListingLqs::updateOrCreate([
    //                 'sku' => $sku,
    //                 'value' => json_encode($cvrValue),
    //                 'created_at' => now(),
    //                 'updated_at' => now()
    //             ]);
    //         }


    //         // Value for ListingLqs (without link)
    //         // $listingValue = [
    //         //     'action' => 'action test',
    //         //     'listing_quality_score' => $lqs,
    //         //     'listing_quality_score_c' => $lqsc,
    //         // ];

    //         // // Update or insert in ListingLqs
    //         // $listingRecord = ListingLqs::where('sku', $sku)->first();
    //         // if ($listingRecord) {
    //         //     ListingLqs::where('sku', $sku)->update([
    //         //         'value' => json_encode($listingValue),
    //         //         'updated_at' => now()
    //         //     ]);
    //         // } else {
    //         //     ListingLqs::insert([
    //         //         'sku' => $sku,
    //         //         'value' => json_encode($listingValue),
    //         //         'created_at' => now(),
    //         //         'updated_at' => now()
    //         //     ]);
    //         // }
    //     }

    //     return response()->json(['success' => true]);
    // }


    public function importCVRData(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');
        $data = array_map('str_getcsv', file($file));

        $header = array_map('trim', $data[0]);
        unset($data[0]);

        foreach ($data as $row) {
            if (count($row) < 4) continue; 

            $sku  = trim($row[0]);
            $lqs  = isset($row[1]) ? trim((string)$row[1]) : '';
            $lqsc = isset($row[2]) ? trim((string)$row[2]) : '';
            $link = isset($row[3]) ? trim($row[3]) : '';

            $cvrValue = [
                'action'                  => 'null',
                'listing_quality_score'   => $lqs,
                'listing_quality_score_c' => $lqsc,
                'link'                    => $link,
            ];

            CvrLqs::updateOrCreate(
                ['sku' => $sku],
                [
                    'value'      => $cvrValue, 
                    'updated_at' => now()
                ]
            );

            ListingLqs::updateOrCreate(
                ['sku' => $sku],
                [
                    'value'      => $cvrValue, 
                    'updated_at' => now()
                ]
            );
        }

        return response()->json(['success' => true]);
    }


    public function getPendingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $statusData = CvrLqs::whereIn('sku', $skus)->get()->keyBy('sku');

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
            // 'NR'  => $nrCount,
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ];
    }




}