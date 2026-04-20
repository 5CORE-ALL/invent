<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\InstructionsItemPkg;
use App\Models\JungleScoutProductData;
use App\Models\MfrgProgress;
use App\Models\ShopifySku;
use App\Models\Supplier;
use App\Models\ToOrderAnalysis;
use App\Models\ToOrderReview;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToOrderAnalysisController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    //only for testing purpose
    public function test()
    {
                
        try {
            // Step 1: Get Product Master Sheet
            $response = $this->apiController->fetchDataFromProductMasterGoogleSheet();
            if ($response->getStatusCode() !== 200) {
                return response()->json(['message' => 'Failed to fetch Product Master data'], $response->getStatusCode());
            }

            $data = $response->getData();

            // Step 2: Get Product Master DB
            $productListData = DB::table('product_master')->orderBy('id')->get();

            // Step 3: Get Supplier Info
            $skus = collect($data->data ?? [])
                ->filter(fn($item) => !empty($item->{'SKU'}) && stripos($item->{'SKU'}, 'PARENT') === false)
                ->pluck('SKU')->unique()->toArray();

            $supplierData = \App\Models\Supplier::whereIn('sku', $skus)->get()->keyBy('sku');

            // Step 4: Filter and build result
            $processedData = [];

            foreach ($data->data ?? [] as $item) {
                $sheetSku = strtoupper(trim($item->{'SKU'} ?? ''));
                $sheetParent = strtoupper(trim($item->Parent ?? ''));

                if (empty($sheetSku) || stripos($sheetSku, 'PARENT') !== false) {
                    continue;
                }

                $prodData = $productListData->firstWhere('sku', $sheetSku);
                if ($prodData) {
                    $item->Parent = $prodData->parent ?? $item->Parent;
                    $item->SKU = $prodData->sku ?? $item->SKU;
                }

                $item->Supplier = $supplierData[$sheetSku]->supplier_name ?? '';

                $processedData[] = (object)[
                    'Parent' => $item->Parent ?? '',
                    'SKU' => $item->SKU ?? '',
                    'Approved QTY' => $item->{'Approved QTY'} ?? '',
                    'Supplier' => $item->Supplier ?? '',
                ];
            }

            return response()->json([
                'message' => 'Filtered data fetched successfully',
                'data' => $processedData,
                'status' => 200,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
       
    }

    public function index(Request $request)
    {
        try {
            $search = strtolower($request->get('search', ''));
            $stageFilter = strtolower($request->get('stage', ''));
            $showNR = $request->get('showNR', '0') === '1';
            $showLATER = $request->get('showLATER', '0') === '1';

            // Fetch base data
            $toOrderRecords = DB::table('to_order_analysis')->get()->keyBy('sku');
            $productData = DB::table('product_master')->get()->keyBy(fn($item) => strtoupper(trim($item->sku)));
            $forecastData = DB::table('forecast_analysis')->get()->keyBy(fn($row) => strtoupper(trim($row->sku)));

            // ✅ Shopify image support
            $shopifySkus = ShopifySku::all()->keyBy(fn($item) => strtoupper(trim($item->sku)));

            $skusForReviews = $toOrderRecords->keys()->map(fn($s) => strtoupper(trim($s)))->unique()->filter()->values()->all();
            $ratingReviewsMap = $this->getRatingReviewsBySku($skusForReviews);

            // MSL: same as forecast page – from movement_analysis (Total/Total month)*4, fallback to forecast_analysis.s_msl
            $movementMap = DB::table('movement_analysis')->get()->keyBy(fn($item) => strtoupper(trim($item->sku ?? '')));

            $processedData = [];

            foreach ($toOrderRecords as $sku => $toOrder) {
                $sheetSku = strtoupper(trim($sku));
                if (empty($sheetSku)) continue;

                $product = $productData->get($sheetSku);
                $forecast = $forecastData->get($sheetSku);
                
                // Skip if SKU has NR or LATER in forecast_analysis table
                $nrValue = strtoupper(trim($forecast->nr ?? ''));
                if ($forecast && ($nrValue === 'NR' || $nrValue === 'LATER')) {
                    continue; // Skip this SKU - don't show in to-order-analysis
                }
                
                $parent = $toOrder->parent ?? $product->parent ?? '';
                $supplierName = '';

                $parentList = explode(',', $parent);
                foreach ($parentList as $singleParent) {
                    $singleParent = trim($singleParent);
                    $supplierRecord = DB::table('suppliers')
                        ->whereRaw("FIND_IN_SET(?, REPLACE(REPLACE(parent, ' ', ''), '\n', ''))", [str_replace(' ', '', $singleParent)])
                        ->first();

                    if ($supplierRecord) {
                        $supplierName = $supplierRecord->name;
                        break;
                    }
                }

                $cbm = 0;
                $imagePath = null;

                $lp = 0;
                if (!empty($product?->Values)) {
                    $valuesArray = json_decode($product->Values, true);
                    if (is_array($valuesArray)) {
                        $cbm = (float)($valuesArray['cbm'] ?? 0);
                        $imagePath = $valuesArray['image_path'] ?? null;
                        $lp = (float)($valuesArray['lp'] ?? 0);
                    }
                }

                // ✅ Image resolution
                $shopifyImage = $shopifySkus[$sheetSku]->image_src ?? null;
                $finalImage = $shopifyImage ?: $imagePath;

                $approvedQty = (int)($toOrder->approved_qty ?? 0);

                $mslValue = $this->computeMslForSku($sheetSku, $movementMap, $forecast);
                $sMsl = $forecast ? (int)($forecast->s_msl ?? 0) : 0;
                $lpMsl = ($mslValue > 0 && $lp > 0) ? round($mslValue * $lp / 4, 2) : null;

                $rr = $ratingReviewsMap[$sheetSku] ?? ['rating' => null, 'reviews' => null];
                $processedData[] = (object)[
                    'Parent'          => $parent,
                    'SKU'             => $sheetSku,
                    'Approved QTY'    => $approvedQty,
                    'Date of Appr'    => $toOrder->date_apprvl ?? '',
                    'Clink'           => ($forecast ? ($forecast->clink ?? '') : ''),
                    // Use stored supplier; only fallback to parent lookup when never set (null). Empty = user chose "Select" so keep blank.
                    'Supplier'        => $toOrder->supplier_name !== null ? (string) $toOrder->supplier_name : ($supplierName ?? ''),
                    'msl'             => $mslValue,
                    's_msl'           => $sMsl,
                    'lp_msl'          => $lpMsl,
                    'rating'          => $rr['rating'],
                    'reviews'         => $rr['reviews'],
                    'RFQ Form Link'   => $toOrder->rfq_form_link ?? '',
                    'sheet_link'      => $toOrder->sheet_link ?? '',
                    'Rfq Report Link' => $toOrder->rfq_report_link ?? '',
                    'stage'           => ($forecast ? ($forecast->stage ?? '') : ''),
                    'nrl'             => $toOrder->nrl ?? '',
                    'Adv date'        => $toOrder->advance_date ?? '',
                    'order_qty'       => $toOrder->order_qty ?? '',
                    'is_parent'       => stripos($sheetSku, 'PARENT') !== false,
                    'cbm'             => $cbm,
                    'total_cbm'       => $cbm * $approvedQty,
                    'Image'           => $finalImage,
                ];
            }

            // ✅ Apply stage + search filter BEFORE pagination
            $filteredChildren = collect($processedData)->filter(function ($item) use ($search, $stageFilter) {
                $matchSearch = $search === '' || str_contains(strtolower($item->SKU . $item->Supplier . $item->Parent), $search);
                $matchStage = $stageFilter === '' || strtolower($item->stage ?? '') === $stageFilter;

                return !$item->is_parent &&
                    ((int)$item->{'Approved QTY'} > 0) &&
                    strtolower($item->stage ?? '') !== 'mip' &&
                    $matchSearch &&
                    $matchStage;
            })->values();

            // ✅ Pagination logic
            $page = $request->get('page', 1);
            $perPage = 25;
            $offset = ($page - 1) * $perPage;
            $paginatedChildren = $filteredChildren->slice($offset, $perPage)->values();

            $finalRows = collect();

            foreach ($paginatedChildren as $child) {
                $parent = $child->Parent;

                if (!$finalRows->contains(fn($item) => $item->is_parent && $item->Parent === $parent)) {
                    $parentRow = collect($processedData)->first(function ($item) use ($parent) {
                        return $item->is_parent && $item->Parent === $parent;
                    });

                    if ($parentRow) {
                        $totalApprovedQty = $filteredChildren->filter(fn($childItem) => $childItem->Parent === $parent)
                            ->sum(fn($item) => (int)$item->{'Approved QTY'});
                        $parentRow->{'Approved QTY'} = $totalApprovedQty;
                        $finalRows->push($parentRow);
                    }
                }

                $finalRows->push($child);
            }

            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $finalRows->values(),
                $filteredChildren->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $groupedData = collect($processedData)->filter(fn($row) => !$row->is_parent)->groupBy('Parent');
            $processedArray = collect($processedData)->filter()->map(function ($item) {
                return (array) $item;
            });

            $filtered = $processedArray->filter(function ($item) {
                return isset($item['Approved QTY']) && floatval($item['Approved QTY']) > 0;
            });

            $groupedDataBySupplier = $filtered->groupBy('Supplier')->map(function ($group) {
                return $group->keyBy('SKU');
            });

            $groupedSupplierJson = $groupedDataBySupplier->toJson();

            $totalCBM = $filteredChildren->sum('total_cbm');
            // Single canonical supplier source: Supplier model (same as MIP / Ready-to-Ship)
            $suppliers = Supplier::where('type', 'Supplier')->orderBy('name')->pluck('name');

            $viewData = [
                'data' => $paginator,
                'groupedDataJson' => $groupedData,
                'totalCBM' => $totalCBM,
                'allProcessedData' => $processedData,
                'groupedSupplierJson' => $groupedSupplierJson,
                'suppliers' => $suppliers,
            ];

            return $request->ajax()
                ? view('product-master.partials.to_order_table', $viewData)
                : view('product-master.to_order_analysis', $viewData);

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Something went wrong!', 'error' => $e->getMessage()], 500);
        }
    }

    public function toOrderAnalysisNew(){
        $allSuppliers = Supplier::where('type', 'Supplier')->orderBy('name')->pluck('name')->unique()->values()->toArray();
        return view('purchase-master.to-order-analysis', compact('allSuppliers'));
    }

    public function getToOrderAnalysis()
    {
        try {
            // Get showNR and showLATER from request if available (for API calls)
            $showNR = request()->get('showNR', '0') === '1';
            $showLATER = request()->get('showLATER', '0') === '1';
            
            // Same forecast row set as the Forecast Analysis page (read-only build — no derived-stage DB writes).
            $snapshotRows = app(ForecastAnalysisController::class)->getForecastAnalysisSnapshotRows();
            $yellowForecastBySku = [];
            foreach ($snapshotRows as $faRow) {
                if (!$this->forecastRowMatchesYellowApprReqQueue($faRow)) {
                    continue;
                }
                $norm = strtoupper(trim((string) ($faRow->SKU ?? '')));
                if ($norm !== '') {
                    $yellowForecastBySku[$norm] = $faRow;
                }
            }
            $allSkus = array_keys($yellowForecastBySku);
            sort($allSkus);

            // orderBy id asc: last row per normalized SKU wins
            $toOrderByNorm = [];
            foreach (DB::table('to_order_analysis')->whereNull('deleted_at')->orderBy('id', 'asc')->get() as $row) {
                $norm = strtoupper(trim((string) ($row->sku ?? '')));
                if ($norm !== '') {
                    $toOrderByNorm[$norm] = $row;
                }
            }

            $productData = DB::table('product_master')->get()->keyBy(fn($item) => strtoupper(trim($item->sku)));
            $instructionsPkgByProductId = InstructionsItemPkg::query()
                ->whereIn('product_master_id', $productData->pluck('id')->filter()->unique()->values())
                ->get()
                ->keyBy('product_master_id');
            $forecastData = DB::table('forecast_analysis')->get()->keyBy(fn($row) => strtoupper(trim($row->sku)));
            $amazonDataMap = DB::table('amazon_data_view')
                ->select('sku', 'value')
                ->get()
                ->keyBy(fn($item) => strtoupper(trim($item->sku)));

            $shopifySkus = ShopifySku::all()->keyBy(fn($item) => strtoupper(trim($item->sku)));
            $allReviews = \App\Models\ToOrderReview::all()->keyBy(fn($r) => strtoupper(trim($r->sku)) . '|' . strtoupper(trim($r->parent)));

            $skusForReviews = $allSkus;
            $ratingReviewsMap = $this->getRatingReviewsBySku($skusForReviews);

            // MSL: same as forecast page – from movement_analysis (Total/Total month)*4, fallback to forecast_analysis.s_msl
            $movementMap = DB::table('movement_analysis')->get()->keyBy(fn($item) => strtoupper(trim($item->sku ?? '')));

            $processedData = [];

            foreach ($allSkus as $sheetSku) {
                if ($sheetSku === '') {
                    continue;
                }

                $faItem = $yellowForecastBySku[$sheetSku] ?? null;
                if ($faItem === null) {
                    continue;
                }

                $toOrder = $toOrderByNorm[$sheetSku] ?? null;
                $forecast = $forecastData->get($sheetSku);
                if ($toOrder === null) {
                    $toOrder = $this->virtualToOrderRowFromForecast($sheetSku, $forecast);
                }

                $product = $productData->get($sheetSku);
                $amazonData = $amazonDataMap->get($sheetSku);
                $amazonValue = [];
                if ($amazonData) {
                    $amazonValue = is_array($amazonData->value)
                        ? $amazonData->value
                        : (json_decode($amazonData->value ?? '{}', true) ?: []);
                }
                
                // Skip if SKU has NR or LATER in forecast_analysis table (unless explicitly shown)
                $nrValue = strtoupper(trim((string) ($forecast?->nr ?? '')));
                if ($forecast) {
                    if ($nrValue === 'NR' && !$showNR) {
                        continue; // Skip NR SKU
                    }
                    if ($nrValue === 'LATER' && !$showLATER) {
                        continue; // Skip LATER SKU
                    }
                }
                
                $parent = trim((string) ($faItem->Parent ?? $toOrder->parent ?? $product?->parent ?? ''));
                $supplierName = '';

                $parentList = explode(',', $parent);
                foreach ($parentList as $singleParent) {
                    $singleParent = trim($singleParent);
                    $supplierRecord = DB::table('suppliers')
                        ->whereRaw("FIND_IN_SET(?, REPLACE(REPLACE(parent, ' ', ''), '\n', ''))", [str_replace(' ', '', $singleParent)])
                        ->first();

                    if ($supplierRecord) {
                        $supplierName = $supplierRecord->name;
                        break;
                    }
                }

                $cbm = 0;
                $imagePath = null;

                $lp = 0;
                $ctnInstructions = '';
                $packingInstructions = '';
                $packingCdrPath = '';
                if (!empty($product?->Values)) {
                    $valuesArray = json_decode($product->Values, true);
                    if (is_array($valuesArray)) {
                        $cbm = (float)($valuesArray['cbm'] ?? 0);
                        $imagePath = $valuesArray['image_path'] ?? null;
                        $lp = (float)($valuesArray['lp'] ?? 0);
                        $ctnInstructions = isset($valuesArray['ctn_instructions']) ? (string) $valuesArray['ctn_instructions'] : '';
                        $packingInstructions = isset($valuesArray['packing_instructions']) ? trim((string) $valuesArray['packing_instructions']) : '';
                        $packingCdrPath = isset($valuesArray['packing_cdr_path']) ? trim((string) $valuesArray['packing_cdr_path']) : '';
                    }
                }

                $shopifyImage = $shopifySkus->get($sheetSku)?->image_src ?? null;
                $finalImage = $shopifyImage ?: $imagePath;

                $approvedQty = (int)($toOrder->approved_qty ?? 0);

                $reviewKey = strtoupper(trim($sheetSku)) . '|' . strtoupper(trim($parent));
                $review = $allReviews->get($reviewKey);
                $rr = $ratingReviewsMap[$sheetSku] ?? ['rating' => null, 'reviews' => null];
                $mslValue = $this->computeMslForSku($sheetSku, $movementMap, $forecast);
                $sMsl = $forecast ? (int)($forecast->s_msl ?? 0) : 0;
                $lpMsl = ($mslValue > 0 && $lp > 0) ? round($mslValue * $lp / 4, 2) : null;

                $instructionsItemPkg = '';
                if ($product && $product->id) {
                    $pkgRow = $instructionsPkgByProductId->get($product->id);
                    $instructionsItemPkg = $pkgRow && $pkgRow->instructions !== null ? (string) $pkgRow->instructions : '';
                }

                // Monthly data for MONTH VIEW modal (Jan–Dec, same as forecast)
                $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                $monthData = array_fill_keys($monthNames, 0);
                if ($movementMap->has($sheetSku)) {
                    $months = json_decode($movementMap->get($sheetSku)->months ?? '{}', true);
                    if (is_array($months)) {
                        foreach ($monthNames as $month) {
                            $monthData[$month] = isset($months[$month]) && is_numeric($months[$month]) ? (int)$months[$month] : 0;
                        }
                    }
                }

                $processedData[] = array_merge([
                    'id'              => $toOrder->id,
                    'product_master_id' => $product->id ?? null,
                    'Parent'          => $parent,
                    'SKU'             => $sheetSku,
                    'approved_qty'    => $approvedQty,
                    'ctn_instructions' => mb_substr($ctnInstructions, 0, 100),
                    'packing_instructions' => $packingInstructions,
                    'packing_cdr_path' => $packingCdrPath,
                    'instructions_item_pkg' => $instructionsItemPkg,
                    'Date of Appr'    => $toOrder->date_apprvl ?? '',
                    'Clink'           => ($forecast ? ($forecast->clink ?? '') : ''),
                    // Use stored supplier; only fallback to parent lookup when never set (null). Empty = user chose "Select" so keep blank.
                    'Supplier'        => $toOrder->supplier_name !== null ? (string) $toOrder->supplier_name : ($supplierName ?? ''),
                    'msl'             => $mslValue,
                    's_msl'           => $sMsl,
                    'lp_msl'          => $lpMsl,
                    'rating'          => $rr['rating'],
                    'reviews'         => $rr['reviews'],
                    'RFQ Form Link'   => $toOrder->rfq_form_link ?? '',
                    'sheet_link'      => $toOrder->sheet_link ?? '',
                    'Rfq Report Link' => $toOrder->rfq_report_link ?? '',
                    'stage'           => strtolower(trim((string) ($faItem->stage ?? ($forecast?->stage ?? '')))),
                    'nr'              => ($forecast ? ($forecast->nr ?? '') : ''),
                    'nrl'             => $toOrder->nrl ?? '',
                    'Adv date'        => $toOrder->advance_date ?? '',
                    'order_qty'       => $toOrder->order_qty ?? '',
                    'is_parent'       => stripos($sheetSku, 'PARENT') !== false,
                    'cbm'             => $cbm,
                    'total_cbm'       => $cbm * $approvedQty,
                    'Image'           => $finalImage,
                    'buyer_link'      => $amazonValue['buyer_link'] ?? '',
                    'seller_link'     => $amazonValue['seller_link'] ?? '',

                    'Reviews'         => $review ? (string) ($review->reviews_note ?? '') : '',
                    'has_review'      => $review ? true : false,
                    'positive_review' => $review->positive_review ?? null,
                    'negative_review' => $review->negative_review ?? null,
                    'improvement'     => $review->improvement ?? null,
                    'date_updated'    => $review->date_updated ?? null,
                ], $monthData);
            }

            return response()->json([
                "data" => $processedData
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rating and reviews from Jungle Scout by SKU (same source as Amazon tabulator).
     *
     * @param array<int, string> $skus Uppercase SKUs
     * @return array<string, array{rating: float|null, reviews: int|null}>
     */
    private function getRatingReviewsBySku(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }
        $jungleScout = JungleScoutProductData::whereNotNull('sku')
            ->whereRaw('UPPER(TRIM(sku)) IN (' . implode(',', array_fill(0, count($skus), '?')) . ')', $skus)
            ->get();
        $bySku = $jungleScout->groupBy(fn($item) => strtoupper(trim($item->sku)));
        $result = [];
        foreach ($skus as $sku) {
            $group = $bySku->get($sku);
            $rating = null;
            $reviews = null;
            if ($group) {
                foreach ($group as $item) {
                    $data = is_array($item->data) ? $item->data : (array) json_decode($item->data, true);
                    if (isset($data['rating']) && (float) $data['rating'] > 0) {
                        $rating = (float) $data['rating'];
                        $reviews = isset($data['reviews']) ? (int) $data['reviews'] : null;
                        break;
                    }
                }
            }
            $result[$sku] = ['rating' => $rating, 'reviews' => $reviews];
        }
        return $result;
    }

    /**
     * Compute MSL for a SKU the same way as forecast page: from movement_analysis (Total/Total month)*4,
     * fallback to forecast_analysis.s_msl when no movement data.
     */
    private function computeMslForSku(string $sheetSku, $movementMap, $forecast): int
    {
        if ($movementMap->has($sheetSku)) {
            $movement = $movementMap->get($sheetSku);
            $months = json_decode($movement->months ?? '{}', true);
            $months = is_array($months) ? $months : [];
            $monthNames = ['Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'];
            $totalMonthCount = 0;
            $totalSum = 0;
            foreach ($monthNames as $month) {
                $value = isset($months[$month]) && is_numeric($months[$month]) ? (int) $months[$month] : 0;
                if ($value !== 0) {
                    $totalMonthCount++;
                }
                $totalSum += $value;
            }
            if ($totalMonthCount > 0) {
                $msl = ($totalSum / $totalMonthCount) * 4;
                return (int) round($msl);
            }
        }
        $sMsl = $forecast ? (int) ($forecast->s_msl ?? 0) : 0;
        return $sMsl;
    }

    private function forecastNullOrDashQtyForYellow(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $raw = trim((string) $value);
        if ($raw === '' || $raw === '-' || $raw === '—') {
            return true;
        }
        $n = (float) $raw;

        return !is_finite($n) || $n === 0.0;
    }

    /**
     * Mirror forecastAnalysis.blade.php ajaxResponse map for one row (fields used by apprReqYellowRowVisible).
     */
    private function forecastBuildClientRowForYellowCheck(object $item): \stdClass
    {
        $sku = (string) ($item->SKU ?? '');
        $total = (float) ($item->{'Total'} ?? 0);
        $totalMonth = (float) ($item->{'Total month'} ?? 0);
        $inv = (float) ($item->INV ?? 0);
        $transit = (float) ($item->{'Transit'} ?? $item->transit ?? 0);
        $orderGiven = (float) ($item->order_given ?? $item->{'Order Given'} ?? 0);
        $r2s = (float) ($item->readyToShipQty ?? 0);

        // JS: parseFloat(item.msl) || (totalMonth > 0 ? (total / totalMonth) * 4 : 0) — 0 is falsy, use movement MSL
        $mslFromProp = isset($item->msl) && $item->msl !== '' && is_numeric($item->msl) ? (float) $item->msl : null;
        $msl = ($mslFromProp !== null && abs($mslFromProp) > 1e-9)
            ? $mslFromProp
            : ($totalMonth > 0 ? ($total / $totalMonth) * 4 : 0.0);

        $sMslVal = (float) ($item->{'s_msl'} ?? $item->{'s-msl'} ?? 0);
        $effectiveMslForToOrder = max($msl, $sMslVal);

        $itemStage = strtolower(trim((string) ($item->stage ?? '')));

        $effectiveOrderGiven = $itemStage === 'mip' ? $orderGiven : 0.0;
        $effectiveR2s = $itemStage === 'r2s' ? $r2s : 0.0;
        $effectiveTransit = $transit;

        $toOrder = (float) round($effectiveMslForToOrder - $inv - $effectiveTransit - $effectiveOrderGiven - $effectiveR2s);

        $twoOrderQty = $itemStage === 'to_order_analysis'
            ? (float) ($item->two_order_qty ?? $item->{'MOQ'} ?? $item->{'Approved QTY'} ?? 0)
            : 0.0;

        $originalOrderGiven = (float) ($item->order_given ?? $item->{'Order Given'} ?? 0);
        $originalReadyToShipQty = (float) ($item->readyToShipQty ?? 0);
        $originalTransit = (float) ($item->transit ?? $item->{'Transit'} ?? 0);

        $finalOrderGiven = $itemStage === 'mip' ? $originalOrderGiven : 0.0;
        $finalReadyToShipQty = $itemStage === 'r2s' ? $originalReadyToShipQty : 0.0;
        $finalTransit = $originalTransit;

        $ip = $item->is_parent ?? false;
        $isParent = $ip === true || $ip === 'true' || $ip === 1 || $ip === '1' || stripos($sku, 'PARENT') !== false;

        $row = new \stdClass();
        $row->to_order = $toOrder;
        $row->two_order_qty = $twoOrderQty;
        $row->transit = $finalTransit;
        $row->order_given = $finalOrderGiven;
        $row->readyToShipQty = $finalReadyToShipQty;
        $row->nr = (string) ($item->nr ?? '');
        $row->is_parent = $isParent;

        return $row;
    }

    /** Same rules as forecastAnalysis.blade.js apprReqYellowRowVisible + pipeline helpers. */
    private function forecastRowMatchesYellowApprReqQueue(object $item): bool
    {
        $row = $this->forecastBuildClientRowForYellowCheck($item);
        if ($row->is_parent) {
            return false;
        }

        $twoOrdVal = (float) $row->to_order;
        if (!is_finite($twoOrdVal) || $twoOrdVal < 0) {
            return false;
        }

        if (!$this->forecastNullOrDashQtyForYellow($row->transit)
            || !$this->forecastNullOrDashQtyForYellow($row->readyToShipQty)
            || !$this->forecastNullOrDashQtyForYellow($row->order_given)
            || !$this->forecastNullOrDashQtyForYellow($row->two_order_qty)) {
            return false;
        }

        $nr = strtoupper(trim($row->nr));

        return $nr !== 'NR' && $nr !== 'LATER';
    }

    /**
     * Minimal to_order_analysis-shaped row when there is no DB row yet (still used for yellow-cohort SKUs).
     */
    private function virtualToOrderRowFromForecast(string $sheetSku, $forecast): object
    {
        return (object) [
            'id' => null,
            'sku' => $sheetSku,
            'parent' => $forecast?->parent ?? null,
            'approved_qty' => (int) ($forecast?->approved_qty ?? 0),
            'date_apprvl' => $forecast?->date_apprvl ?? null,
            'rfq_form_link' => $forecast?->rfq_form_link ?? null,
            'sheet_link' => null,
            'rfq_report_link' => $forecast?->rfq_report ?? null,
            'supplier_name' => null,
            'nrl' => null,
            'advance_date' => null,
            'order_qty' => null,
        ];
    }

    public function updateLink(Request $request)
    {
        $sku = trim(strtoupper((string) $request->input('sku', '')));
        $column = $request->input('column');
        $value = $request->input('value');

        if (empty($sku)) {
            return response()->json(['success' => false, 'message' => 'SKU is required']);
        }

        if (!in_array($column, ['approved_qty','Date of Appr', 'RFQ Form Link', 'Rfq Report Link', 'sheet_link', 'Stage', 'nrl', 'Supplier', 'order_qty', 'Adv date', 'Clink', 'Reviews'])) {
            return response()->json(['success' => false, 'message' => 'Invalid column']);
        }

        if ($column === 'Reviews') {
            $parent = trim((string) $request->input('parent', ''));
            $note = $value === null ? '' : (string) $value;

            try {
                ToOrderReview::updateOrCreate(
                    [
                        'parent' => $parent,
                        'sku' => $sku,
                    ],
                    [
                        'reviews_note' => $note,
                        'date_updated' => now()->format('Y-m-d'),
                    ]
                );

                return response()->json(['success' => true]);
            } catch (\Throwable $e) {
                Log::error('ToOrderAnalysis updateLink Reviews failed', ['sku' => $sku, 'error' => $e->getMessage()]);

                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        // When user chooses "-- Select --" for Supplier, ensure we store empty string (not null) so refresh shows blank
        if ($column === 'Supplier') {
            $value = trim((string) $value);
        }

        if ($column === 'Clink') {
            $value = trim((string) $value);
            try {
                $skuWhere = ['TRIM(UPPER(sku)) = ?', [$sku]];
                if (DB::table('forecast_analysis')->whereRaw(...$skuWhere)->exists()) {
                    DB::table('forecast_analysis')->whereRaw(...$skuWhere)->update(['clink' => $value, 'updated_at' => now()]);
                } else {
                    DB::table('forecast_analysis')->insert([
                        'sku' => $sku,
                        'clink' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return response()->json(['success' => true]);
            } catch (\Throwable $e) {
                Log::error('ToOrderAnalysis updateLink Clink failed', ['sku' => $sku, 'error' => $e->getMessage()]);

                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        $updateColumn = match ($column) {
            'Date of Appr'    => 'date_apprvl',
            'RFQ Form Link'   => 'rfq_form_link',
            'Rfq Report Link' => 'rfq_report_link',
            'Stage'           => 'stage',
            'nrl'             => 'nrl',
            'sheet_link'      => 'sheet_link',
            'Supplier'        => 'supplier_name',
            'Adv date'        => 'advance_date',
            'order_qty'       => 'order_qty',
            'approved_qty'    => 'approved_qty',
        };

        try {
            // For Supplier, use DB::table so empty string is stored exactly (not converted to null by Eloquent)
            if ($column === 'Supplier') {
                $updated = DB::table('to_order_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$sku])
                    ->update(['supplier_name' => $value, 'updated_at' => now()]);
            } else {
                // Update ALL rows with this SKU (handles duplicate SKU rows so refresh shows correct value)
                $updated = ToOrderAnalysis::whereRaw('TRIM(UPPER(sku)) = ?', [$sku])->update([$updateColumn => $value]);
            }

            if ($updated === 0 && $column !== 'Supplier') {
                // No row exists for this SKU, create one (avoid creating if any duplicate already exists)
                ToOrderAnalysis::create([
                    'sku' => $sku,
                    $updateColumn => $value
                ]);
            }

            // When MOQ (approved_qty) is updated on to-order page, sync to forecast_analysis so forecast page shows same value
            if ($column === 'approved_qty') {
                $valueNum = is_numeric($value) ? (int) $value : null;
                DB::table('forecast_analysis')
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$sku])
                    ->update(['approved_qty' => $valueNum, 'updated_at' => now()]);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('ToOrderAnalysis updateLink failed', ['sku' => $sku, 'column' => $column, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk update supplier for selected SKUs (by checkbox).
     */
    public function bulkUpdateSupplier(Request $request)
    {
        $skus = $request->input('skus', []);
        $supplierName = trim((string) $request->input('supplier_name', ''));

        if (empty($skus) || !is_array($skus)) {
            return response()->json(['success' => false, 'message' => 'No rows selected'], 400);
        }

        if ($supplierName === '') {
            return response()->json(['success' => false, 'message' => 'Please select a supplier'], 400);
        }

        $skus = array_map(fn($s) => trim(strtoupper((string) $s)), $skus);
        $skus = array_filter($skus);

        if (empty($skus)) {
            return response()->json(['success' => false, 'message' => 'No valid SKUs'], 400);
        }

        try {
            $placeholders = implode(',', array_fill(0, count($skus), '?'));
            $updated = ToOrderAnalysis::whereRaw('TRIM(UPPER(sku)) IN (' . $placeholders . ')', $skus)
                ->update(['supplier_name' => $supplierName]);

            return response()->json([
                'success' => true,
                'message' => $updated . ' record(s) updated successfully',
                'updated' => $updated,
            ]);
        } catch (\Throwable $e) {
            Log::error('ToOrderAnalysis bulkUpdateSupplier failed', ['skus' => $skus, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMFRG(Request $request)
    {
        try {
            $record = MfrgProgress::where('parent', $request->parent)
                ->where('sku', $request->sku)
                ->first();

            if ($record) {
                $record->qty = $request->order_qty;
                $record->supplier = $request->supplier;
                $record->adv_date = $request->adv_date;
                $record->ready_to_ship = 'No';
                $record->save();
            } else {
                // Create new record
                MfrgProgress::create([
                    'parent' => $request->parent,
                    'sku' => $request->sku,
                    'qty' => $request->order_qty,
                    'supplier' => $request->supplier,
                    'adv_date' => $request->adv_date,
                    'ready_to_ship' => 'No',
                ]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    public function storeToOrderReview(Request $request)
    {
        $data = [
            'supplier' => $request->input('supplier'),
            'positive_review' => $request->input('positive_review'),
            'negative_review' => $request->input('negative_review'),
            'improvement' => $request->input('improvement'),
            'date_updated' => $request->input('date_updated'),
        ];
        if ($request->has('reviews_note')) {
            $data['reviews_note'] = $request->input('reviews_note');
        }

        ToOrderReview::updateOrCreate(
            [
                'parent' => $request->input('parent'),
                'sku' => $request->input('sku'),
            ],
            $data
        );

        return response()->json(['success' => true]);
    }

    public function deleteToOrderAnalysis(Request $request)
    {
        try {
            $ids = $request->input('ids', []);

            if (!empty($ids)) {
                $user = auth()->check() ? auth()->user()->name : 'System';

                ToOrderAnalysis::whereIn('id', $ids)->update([
                    'auth_user' => $user,
                    'deleted_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Records soft-deleted successfully by ' . $user,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No IDs provided',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage(),
            ], 500);
        }
    }

}
