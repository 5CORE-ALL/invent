<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\InstructionsItemPkg;
use App\Models\InstructionsCartonDesign;
use App\Models\JungleScoutProductData;
use App\Models\MfrgProgress;
use App\Models\ShopifySku;
use App\Models\Supplier;
use App\Models\ToOrderAnalysis;
use App\Models\ToOrderPreChecklist;
use App\Models\ToOrderReview;
use App\Models\AmazonSkuCompetitor;
use App\Services\LinkedSkuGroupService;
use App\Services\PurchasePageExecService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

            // Do not keyBy sku — duplicate SKUs must each appear and update row-wise by id
            $toOrderRecords = ToOrderAnalysis::query()->orderBy('id')->get();
            $productData = DB::table('product_master')->get()->keyBy(fn($item) => strtoupper(trim($item->sku)));
            $forecastData = DB::table('forecast_analysis')->get()->keyBy(fn($row) => strtoupper(trim($row->sku)));

            // ✅ Shopify image support
            $shopifySkus = ShopifySku::all()->keyBy(fn($item) => strtoupper(trim($item->sku)));

            $skusForReviews = $toOrderRecords->pluck('sku')->map(fn($s) => strtoupper(trim((string) $s)))->unique()->filter()->values()->all();
            $ratingReviewsMap = $this->getRatingReviewsBySku($skusForReviews);

            // MSL: from movement_analysis (Total/Total month)*4
            $movementMap = DB::table('movement_analysis')->get()->keyBy(fn($item) => strtoupper(trim($item->sku ?? '')));

            $processedData = [];

            foreach ($toOrderRecords as $toOrder) {
                $sheetSku = strtoupper(trim((string) ($toOrder->sku ?? '')));
                if (empty($sheetSku)) {
                    continue;
                }

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
                $lpMsl = ($mslValue > 0 && $lp > 0) ? round($mslValue * $lp / 4, 2) : null;

                $rr = $ratingReviewsMap[$sheetSku] ?? ['rating' => null, 'reviews' => null];
                $processedData[] = (object)[
                    'id'              => $toOrder->id,
                    'Parent'          => $parent,
                    'SKU'             => $sheetSku,
                    'Approved QTY'    => $approvedQty,
                    'Date of Appr'    => $toOrder->date_apprvl ?? '',
                    'Clink'           => ($forecast ? ($forecast->clink ?? '') : ''),
                    // Use stored supplier; only fallback to parent lookup when never set (null). Empty = user chose "Select" so keep blank.
                    'Supplier'        => $toOrder->supplier_name !== null ? (string) $toOrder->supplier_name : ($supplierName ?? ''),
                    'msl'             => $mslValue,
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
                return $group->keyBy(fn ($item) => (string) ($item['id'] ?? ''));
            });

            $groupedSupplierJson = $groupedDataBySupplier->toJson();

            $totalCBM = $filteredChildren->sum('total_cbm');
            // Same name catalog as /supplier.list (no type filter) and /supplier.list.json
            $suppliers = Supplier::distinctNamesForListPage();

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
        $allSuppliers = Supplier::distinctNamesForListPage()->all();
        $allCategories = DB::table('categories')
            ->whereNull('deleted_at')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
        // Category id+name list for the "Add Supplier" modal (mirrors the supplier list page).
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);
        $execService = app(PurchasePageExecService::class);

        return view('purchase-master.to-order-analysis', [
            'allSuppliers' => $allSuppliers,
            'allCategories' => $allCategories,
            'categories' => $categories,
            'execOptions' => $execService->getOptions(),
            'pageExec' => $execService->getAssignment('to_order') ?? '',
            'execCanEdit' => PurchasePageExecService::userCanEdit(),
        ]);
    }

    /**
     * Map of normalized supplier name => category name, derived from the suppliers
     * table joined to the categories table. Used to attach a Category to each
     * To Order Analysis row based on its supplier.
     *
     * @return array<string, string>
     */
    private function supplierCategoryMap(): array
    {
        $categoryById = DB::table('categories')->pluck('name', 'id');
        $map = [];
        foreach (DB::table('suppliers')->select('name', 'category_id')->get() as $sup) {
            $name = strtoupper(trim((string) ($sup->name ?? '')));
            if ($name === '' || empty($sup->category_id)) {
                continue;
            }
            $catName = $categoryById[$sup->category_id] ?? null;
            if ($catName && !isset($map[$name])) {
                $map[$name] = (string) $catName;
            }
        }
        return $map;
    }

    /**
     * @return array<string, int> normalized category name => supplier count
     */
    private function categorySupplierCountMap(): array
    {
        $map = [];
        foreach (DB::table('categories')->select('id', 'name')->get() as $cat) {
            $name = strtoupper(trim((string) ($cat->name ?? '')));
            if ($name === '') {
                continue;
            }
            $map[$name] = (int) DB::table('suppliers')
                ->whereRaw('FIND_IN_SET(?, category_id)', [$cat->id])
                ->count();
        }

        return $map;
    }

    private function attachCategorySupplierCounts(array &$rows, array $countMap): void
    {
        foreach ($rows as &$row) {
            $cat = strtoupper(trim((string) ($row['Category'] ?? '')));
            $row['category_supplier_count'] = $cat !== '' ? ($countMap[$cat] ?? 0) : null;
        }
        unset($row);
    }

    public function getToOrderAnalysis()
    {
        try {
            // Get showNR and showLATER from request if available (for API calls)
            $showNR = request()->get('showNR', '0') === '1';
            $showLATER = request()->get('showLATER', '0') === '1';
            
            // Same forecast row set as the Forecast Analysis page (read-only build — no derived-stage DB writes).
            // Include a SKU when ANY of these is true:
            //   - stage = 'appr_req'           → row tagged for approval
            //   - stage = 'to_order_analysis'  → row already moved to the 2-Order stage (matches the
            //                                    "Stage = 2Order" rows shown on /approval.required)
            //   - the Appr Req rule passes     → to_order >= 0 AND MOQ > 0 (matches the Forecast
            //                                    "Appr Req" column and /approval.required filter)
            $snapshotRows = app(ForecastAnalysisController::class)->getForecastAnalysisSnapshotRows();
            $yellowForecastBySku = [];
            foreach ($snapshotRows as $faRow) {
                $stage = strtolower(trim((string) ($faRow->stage ?? '')));
                $explicitStage = ($stage === 'appr_req' || $stage === 'to_order_analysis');
                if (!$explicitStage && !$this->forecastRowMatchesYellowApprReqQueue($faRow)) {
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
            $instructionsCartonDesignByProductId = InstructionsCartonDesign::query()
                ->whereIn('product_master_id', $productData->pluck('id')->filter()->unique()->values())
                ->get()
                ->keyBy('product_master_id');
            $forecastData = DB::table('forecast_analysis')->get()->keyBy(fn($row) => strtoupper(trim($row->sku)));
            $amazonDataMap = DB::table('amazon_data_view')
                ->select('sku', 'value')
                ->get()
                ->keyBy(fn($item) => strtoupper(trim($item->sku)));

            $shopifySkus = ShopifySku::all()->keyBy(fn($item) => strtoupper(trim($item->sku)));
            $lmpLookups = AmazonSkuCompetitor::buildGroupedLookup('amazon');
            $lmpDetailsLookup = $lmpLookups['details'];
            $lmpLowestLookup = $lmpLookups['lowest'];
            $allReviews = \App\Models\ToOrderReview::all()->keyBy(fn($r) => strtoupper(trim($r->sku)) . '|' . strtoupper(trim($r->parent)));

            $skusForReviews = $allSkus;
            $ratingReviewsMap = $this->getRatingReviewsBySku($skusForReviews);

            // MSL: from movement_analysis (Total/Total month)*4
            $movementMap = DB::table('movement_analysis')->get()->keyBy(fn($item) => strtoupper(trim($item->sku ?? '')));
            $qcIssuesBySku = $this->buildQcPackingIssuesBySku($allSkus);

            // RFQ forms linked to each SKU (from /rfq-form/list "Linked SKU" data)
            $rfqFormsBySku = [];
            foreach (\App\Models\RfqForm::select('name', 'slug', 'linked_skus')->whereNotNull('linked_skus')->get() as $rfqForm) {
                $linked = $rfqForm->linked_skus;
                if (is_string($linked)) {
                    $linked = json_decode($linked, true) ?: [];
                }
                if (!is_array($linked)) {
                    continue;
                }
                foreach ($linked as $linkedSku) {
                    $norm = strtoupper(trim((string) $linkedSku));
                    if ($norm === '') {
                        continue;
                    }
                    $rfqFormsBySku[$norm][] = [
                        'name' => $rfqForm->name,
                        'slug' => $rfqForm->slug,
                    ];
                }
            }

            $processedData = [];
            $execService = app(PurchasePageExecService::class);
            $pageExec = $execService->getAssignment('to_order') ?? '';
            $supplierCategoryMap = $this->supplierCategoryMap();
            $categorySupplierCountMap = $this->categorySupplierCountMap();

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
                $cp = 0;
                if (!empty($product?->Values)) {
                    $valuesArray = json_decode($product->Values, true);
                    if (is_array($valuesArray)) {
                        $cbm = (float)($valuesArray['cbm'] ?? 0);
                        $imagePath = $valuesArray['image_path'] ?? null;
                        $lp = (float)($valuesArray['lp'] ?? 0);
                        $cp = (float)($valuesArray['cp'] ?? 0);
                        $ctnInstructions = isset($valuesArray['ctn_instructions']) ? (string) $valuesArray['ctn_instructions'] : '';
                        $packingInstructions = isset($valuesArray['packing_instructions']) ? trim((string) $valuesArray['packing_instructions']) : '';
                        $packingCdrPath = isset($valuesArray['packing_cdr_path']) ? trim((string) $valuesArray['packing_cdr_path']) : '';
                    }
                }
                if ($cp <= 0 && $faItem) {
                    $cp = (float) ($faItem->CP ?? 0);
                }

                $shopifyImage = $shopifySkus->get($sheetSku)?->image_src ?? null;
                $finalImage = $shopifyImage ?: $imagePath;

                $approvedQty = (int)($toOrder->approved_qty ?? 0);

                $reviewKey = strtoupper(trim($sheetSku)) . '|' . strtoupper(trim($parent));
                $review = $allReviews->get($reviewKey);
                $rr = $ratingReviewsMap[$sheetSku] ?? ['rating' => null, 'reviews' => null];
                $mslValue = $this->computeMslForSku($sheetSku, $movementMap, $forecast);
                $lpMsl = ($mslValue > 0 && $lp > 0) ? round($mslValue * $lp / 4, 2) : null;

                $instructionsItemPkg = '';
                if ($product && $product->id) {
                    $pkgRow = $instructionsPkgByProductId->get($product->id);
                    $instructionsItemPkg = $pkgRow && $pkgRow->instructions !== null ? (string) $pkgRow->instructions : '';
                }

                $instructionsCartonDesign = '';
                if ($product && $product->id) {
                    $cartonDesignRow = $instructionsCartonDesignByProductId->get($product->id);
                    $instructionsCartonDesign = $cartonDesignRow && $cartonDesignRow->instructions !== null
                        ? (string) $cartonDesignRow->instructions
                        : '';
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
                    'CP'              => $cp,
                    'ctn_instructions' => mb_substr($ctnInstructions, 0, 100),
                    'packing_instructions' => $packingInstructions,
                    'packing_cdr_path' => $packingCdrPath,
                    'instructions_item_pkg' => $instructionsItemPkg,
                    'instructions_carton_design' => $instructionsCartonDesign,
                    'Date of Appr'    => $toOrder->date_apprvl ?? '',
                    'Clink'           => ($forecast ? ($forecast->clink ?? '') : ''),
                    // Use stored supplier; only fallback to parent lookup when never set (null). Empty = user chose "Select" so keep blank.
                    'Supplier'        => $toOrder->supplier_name !== null ? (string) $toOrder->supplier_name : ($supplierName ?? ''),
                    'Category'        => $supplierCategoryMap[strtoupper(trim((string) ($toOrder->supplier_name !== null ? $toOrder->supplier_name : ($supplierName ?? ''))))] ?? '',
                    'Exec'            => isset($toOrder->exec) && $toOrder->exec !== null ? (string) $toOrder->exec : '',
                    'msl'             => $mslValue,
                    'lp_msl'          => $lpMsl,
                    'rating'          => $rr['rating'],
                    'reviews'         => $rr['reviews'],
                    'RFQ Form Link'   => $toOrder->rfq_form_link ?? '',
                    'rfq_linked_forms' => $rfqFormsBySku[$sheetSku] ?? [],
                    'sheet_link'      => $toOrder->sheet_link ?? '',
                    'Rfq Report Link' => $toOrder->rfq_report_link ?? '',
                    'stage'           => strtolower(trim((string) ($faItem->stage ?? ($forecast?->stage ?? '')))),
                    'nr'              => ($forecast ? ($forecast->nr ?? '') : ''),
                    'nrl'             => $toOrder->nrl ?? '',
                    'issues'          => $qcIssuesBySku[$sheetSku] ?? '',
                    'Adv date'        => $toOrder->advance_date ?? '',
                    'order_qty'       => $toOrder->order_qty ?? '',
                    'is_parent'       => stripos($sheetSku, 'PARENT') !== false,
                    'cbm'             => $cbm,
                    'total_cbm'       => $cbm * $approvedQty,
                    'Image'           => $finalImage,
                    'buyer_link'      => $amazonValue['buyer_link'] ?? '',
                    'seller_link'     => $amazonValue['seller_link'] ?? '',

                    'Reviews'         => $review ? (string) ($review->reviews_note ?? '') : '',
                    'has_review'      => $review && (
                        trim((string) ($review->positive_review ?? '')) !== ''
                        || trim((string) ($review->negative_review ?? '')) !== ''
                        || trim((string) ($review->improvement ?? '')) !== ''
                    ),
                    'positive_review' => $review->positive_review ?? null,
                    'negative_review' => $review->negative_review ?? null,
                    'improvement'     => $review->improvement ?? null,
                    'date_updated'    => $review->date_updated ?? null,
                ], $monthData, $this->lmpFieldsForSku($sheetSku, $lmpDetailsLookup, $lmpLowestLookup));
            }

            $this->attachToOrderPreChecklists($processedData);
            $this->attachCategorySupplierCounts($processedData, $categorySupplierCountMap);

            return response()->json([
                'data' => $processedData,
                'exec_options' => $execService->getOptions(),
                'page_exec' => $pageExec,
                'exec_can_edit' => PurchasePageExecService::userCanEdit(),
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
     * LMP fields from amazon_sku_competitors (same source as /amazon-tabulator-view).
     *
     * @return array{lmp_price: float|null, lmp_link: string|null, lmp_entries_total: int}
     */
    private function lmpFieldsForSku(string $sku, $lmpDetailsLookup, $lmpLowestLookup): array
    {
        $skuLookupKey = AmazonSkuCompetitor::normalizeSkuKey($sku);
        $lmpEntries = $lmpDetailsLookup->get($skuLookupKey);
        if (! $lmpEntries instanceof \Illuminate\Support\Collection) {
            $lmpEntries = collect();
        }

        $lowestLmp = $lmpLowestLookup->get($skuLookupKey);

        return [
            'lmp_price' => ($lowestLmp && isset($lowestLmp->price) && is_numeric($lowestLmp->price))
                ? (float) $lowestLmp->price
                : null,
            'lmp_link' => $lowestLmp->product_link ?? null,
            'lmp_entries_total' => $lmpEntries->count(),
        ];
    }

    /**
     * Compute MSL for a SKU the same way as forecast page: from movement_analysis (Total/Total month)*4.
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
        return 0;
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

        $effectiveMslForToOrder = $msl;

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

    /**
     * Appr Req rule — mirrors getEffectiveApprReqValue() in forecastAnalysis.blade.php and
     * approvalRequired.blade.php. Includes a SKU when it would display a numeric value in
     * the Forecast Analysis "Appr Req" column:
     *   - non-parent
     *   - AND stage NOT IN ('mip','r2s','transit','all_good')  (already in pipeline → exclude)
     *   - AND (appr_req_qty > 0  OR  (to_order >= 0 AND MOQ > 0))
     * Pipeline qty and NR status are not excluded here; use the page's dropdowns to narrow.
     */
    private function forecastRowMatchesYellowApprReqQueue(object $item): bool
    {
        $row = $this->forecastBuildClientRowForYellowCheck($item);
        if ($row->is_parent) {
            return false;
        }

        // Rows already moved into a downstream pipeline stage no longer need approval —
        // exclude them so bulk-stage updates from this page don't have the row reappear after refresh.
        $stage = strtolower(trim((string) ($item->stage ?? '')));
        if (in_array($stage, ['mip', 'r2s', 'transit', 'all_good'], true)) {
            return false;
        }

        // Explicit appr_req_qty (set when stage='appr_req')
        $explicitApprReq = (float) ($item->appr_req_qty ?? 0);
        if (is_finite($explicitApprReq) && $explicitApprReq > 0) {
            return true;
        }

        // MOQ fallback: to_order >= 0 and a positive MOQ to display
        $twoOrdVal = (float) $row->to_order;
        if (!is_finite($twoOrdVal) || $twoOrdVal < 0) {
            return false;
        }
        $moqVal = (float) ($item->MOQ ?? $item->{'Approved QTY'} ?? 0);

        return is_finite($moqVal) && $moqVal > 0;
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
            'exec' => null,
            'nrl' => null,
            'issues' => null,
            'advance_date' => null,
            'order_qty' => null,
        ];
    }

    public function updateLink(Request $request)
    {
        $sku = trim(strtoupper((string) $request->input('sku', '')));
        $column = $request->input('column');
        $value = $request->input('value');
        $rowId = (int) $request->input('row_id', 0);

        if (empty($sku)) {
            return response()->json(['success' => false, 'message' => 'SKU is required']);
        }

        if (!in_array($column, ['approved_qty','Date of Appr', 'RFQ Form Link', 'Rfq Report Link', 'sheet_link', 'Stage', 'nrl', 'Supplier', 'order_qty', 'Adv date', 'Clink', 'Reviews', 'Exec'])) {
            return response()->json(['success' => false, 'message' => 'Invalid column']);
        }

        // DOA (Date of Approval) may only be edited by the president account.
        if ($column === 'Date of Appr' && strtolower((string) (auth()->user()->email ?? '')) !== 'president@5core.com') {
            return response()->json(['success' => false, 'message' => 'You are not allowed to edit DOA'], 403);
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
                /** @var LinkedSkuGroupService $linkedSkuGroupService */
                $linkedSkuGroupService = app(LinkedSkuGroupService::class);
                $linkedSkus = is_array($request->input('linked_skus')) ? $request->input('linked_skus') : [];
                $affected = $linkedSkuGroupService->propagateClink(
                    trim((string) $request->input('sku', $sku)),
                    $value,
                    $linkedSkus
                );

                return response()->json([
                    'success' => true,
                    'affected' => $affected,
                ]);
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
            'Exec'            => 'exec',
        };

        try {
            // Try indexed lookup first (sku column has an index; $sku is already UPPER+TRIM normalized).
            // Fall back to the TRIM/UPPER whereRaw only when the indexed lookup finds nothing,
            // which handles rows stored with non-normalized casing.
            $buildQuery = function () use ($sku, $rowId) {
                $q = ToOrderAnalysis::query()->where('sku', $sku);
                if ($rowId > 0) {
                    $q->where('id', $rowId);
                }
                return $q;
            };
            $buildQuerySlow = function () use ($sku, $rowId) {
                $q = ToOrderAnalysis::query()->whereRaw('TRIM(UPPER(sku)) = ?', [$sku]);
                if ($rowId > 0) {
                    $q->where('id', $rowId);
                }
                return $q;
            };
            $toOrderQuery = $buildQuery();

            // Supplier: allow empty string; scope by row_id when sent so duplicate SKUs update the correct row only
            if ($column === 'Supplier') {
                $updated = $toOrderQuery->update(['supplier_name' => $value]);
                if ($updated === 0) {
                    // Try slow fallback (non-normalized SKU casing in DB)
                    $updated = $buildQuerySlow()->update(['supplier_name' => $value]);
                }
                if ($updated === 0 && $rowId > 0) {
                    return response()->json(['success' => false, 'message' => 'Row not found for this SKU'], 422);
                }
                if ($updated === 0 && $rowId === 0) {
                    $parent = trim((string) $request->input('parent', ''));
                    ToOrderAnalysis::create([
                        'sku' => $sku,
                        'parent' => $parent !== '' ? $parent : null,
                        'supplier_name' => $value,
                    ]);
                }
            } else {
                $updated = $toOrderQuery->update([$updateColumn => $value]);

                if ($updated === 0) {
                    // Try slow fallback before deciding to create
                    $updated = $buildQuerySlow()->update([$updateColumn => $value]);
                }

                if ($updated === 0) {
                    if ($rowId > 0) {
                        return response()->json(['success' => false, 'message' => 'Row not found for this SKU'], 422);
                    }
                    ToOrderAnalysis::create([
                        'sku' => $sku,
                        $updateColumn => $value,
                    ]);
                }
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
            $updatedRows = 0;
            $createdRows = 0;
            foreach ($skus as $skuOne) {
                $n = (int) ToOrderAnalysis::query()
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuOne])
                    ->update(['supplier_name' => $supplierName]);
                $updatedRows += $n;
                if ($n === 0) {
                    ToOrderAnalysis::create([
                        'sku' => $skuOne,
                        'supplier_name' => $supplierName,
                    ]);
                    $createdRows++;
                }
            }

            $msg = $updatedRows . ' row(s) updated';
            if ($createdRows > 0) {
                $msg .= ', ' . $createdRows . ' row(s) created';
            }

            return response()->json([
                'success' => true,
                'message' => $msg . '.',
                'updated' => $updatedRows,
                'created' => $createdRows,
            ]);
        } catch (\Throwable $e) {
            Log::error('ToOrderAnalysis bulkUpdateSupplier failed', ['skus' => $skus, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return all suppliers that belong to a given category name (JSON).
     * Used by the "view suppliers in this category" magnifier on the
     * To Order Analysis Supplier column.
     */
    public function suppliersByCategory(Request $request)
    {
        $categoryName = trim((string) $request->input('category', ''));
        if ($categoryName === '') {
            return response()->json(['success' => true, 'category' => '', 'suppliers' => []]);
        }

        $category = \App\Models\Category::whereRaw('TRIM(LOWER(name)) = ?', [strtolower($categoryName)])->first();
        if (!$category) {
            return response()->json(['success' => true, 'category' => $categoryName, 'suppliers' => []]);
        }

        // category_id is stored as a comma-separated list of ids on the supplier row.
        $suppliers = DB::table('suppliers')
            ->whereRaw('FIND_IN_SET(?, REPLACE(category_id, " ", ""))', [$category->id])
            ->orderBy('name')
            ->get(['name', 'company', 'phone', 'email', 'whatsapp', 'city', 'approval_status']);

        return response()->json([
            'success' => true,
            'category' => $categoryName,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Import supplier assignments from an Excel/CSV file.
     * Expected columns (header row, case-insensitive): "SKU", "Supplier" and an
     * optional "Category". For each row, sets supplier_name on the matching
     * to_order_analysis record (creating the record when the SKU does not exist).
     * When a Category is given (and matches an existing category by name), the
     * supplier's category is updated so the derived Category column reflects it.
     */
    public function importSupplier(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getPathName());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            Log::error('ToOrderAnalysis importSupplier read failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not read the file: ' . $e->getMessage()], 422);
        }

        if (empty($rows)) {
            return response()->json(['success' => false, 'message' => 'The file is empty'], 422);
        }

        // Locate the SKU, Supplier and (optional) Category columns from the header row.
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);
        $skuIdx = null;
        $supplierIdx = null;
        $categoryIdx = null;
        foreach ($header as $idx => $name) {
            if ($skuIdx === null && $name === 'sku') {
                $skuIdx = $idx;
            }
            if ($supplierIdx === null && in_array($name, ['supplier', 'supplier name', 'supplier_name'], true)) {
                $supplierIdx = $idx;
            }
            if ($categoryIdx === null && in_array($name, ['category', 'category name', 'category_name'], true)) {
                $categoryIdx = $idx;
            }
        }

        if ($skuIdx === null || $supplierIdx === null) {
            return response()->json([
                'success' => false,
                'message' => 'File must contain "SKU" and "Supplier" column headers.',
            ], 422);
        }

        // Existing categories keyed by lowercase name for fast lookup (category is optional).
        $categoryIdByName = [];
        foreach (DB::table('categories')->select('id', 'name')->get() as $cat) {
            $key = strtolower(trim((string) ($cat->name ?? '')));
            if ($key !== '' && !isset($categoryIdByName[$key])) {
                $categoryIdByName[$key] = $cat->id;
            }
        }

        $updated = 0;
        $created = 0;
        $skipped = 0;
        $categoryUpdated = 0;
        $categoryUnmatched = [];

        try {
            foreach (array_slice($rows, 1) as $row) {
                $sku = trim(strtoupper((string) ($row[$skuIdx] ?? '')));
                $supplier = trim((string) ($row[$supplierIdx] ?? ''));
                $category = $categoryIdx !== null ? trim((string) ($row[$categoryIdx] ?? '')) : '';

                if ($sku === '') {
                    $skipped++;
                    continue;
                }

                $n = (int) ToOrderAnalysis::query()
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$sku])
                    ->update(['supplier_name' => $supplier]);

                if ($n > 0) {
                    $updated += $n;
                } else {
                    ToOrderAnalysis::create([
                        'sku' => $sku,
                        'supplier_name' => $supplier,
                    ]);
                    $created++;
                }

                // Apply category to the supplier record so the derived Category column updates.
                if ($category !== '' && $supplier !== '') {
                    $catId = $categoryIdByName[strtolower($category)] ?? null;
                    if ($catId !== null) {
                        $cn = (int) DB::table('suppliers')
                            ->whereRaw('TRIM(UPPER(name)) = ?', [strtoupper($supplier)])
                            ->update(['category_id' => $catId]);
                        if ($cn > 0) {
                            $categoryUpdated += $cn;
                        }
                    } elseif (!in_array($category, $categoryUnmatched, true)) {
                        $categoryUnmatched[] = $category;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('ToOrderAnalysis importSupplier failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $msg = "Import complete: {$updated} updated, {$created} created";
        if ($categoryUpdated > 0) {
            $msg .= ", {$categoryUpdated} supplier category link(s) updated";
        }
        if ($skipped > 0) {
            $msg .= ", {$skipped} skipped (no SKU)";
        }
        if (!empty($categoryUnmatched)) {
            $msg .= '. Unknown categories ignored: ' . implode(', ', array_slice($categoryUnmatched, 0, 10));
        }

        return response()->json([
            'success' => true,
            'message' => $msg . '.',
            'updated' => $updated,
            'created' => $created,
            'skipped' => $skipped,
            'category_updated' => $categoryUpdated,
        ]);
    }

    /**
     * Download a sample CSV template for the supplier import.
     */
    public function importSupplierSample()
    {
        $rows = [
            ['SKU', 'Supplier', 'Category'],
            ['ABC-123', 'Acme Co', 'Electronics'],
            ['XYZ-9', 'Globex', 'Drum Stool'],
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, 'supplier-import-sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function bulkUpdateExec(Request $request)
    {
        $skus = $request->input('skus', []);
        $execName = trim((string) $request->input('exec_name', ''));

        if (empty($skus) || !is_array($skus)) {
            return response()->json(['success' => false, 'message' => 'No rows selected'], 400);
        }

        $allowed = ['', 'Atin', 'Jack', 'Nitish', 'Ajay', 'Candy', 'Sruti'];
        if (!in_array($execName, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid executive name'], 400);
        }

        $skus = array_map(fn($s) => trim(strtoupper((string) $s)), $skus);
        $skus = array_filter($skus);

        if (empty($skus)) {
            return response()->json(['success' => false, 'message' => 'No valid SKUs'], 400);
        }

        try {
            $updated = 0;
            $created = 0;
            foreach ($skus as $skuOne) {
                $n = (int) ToOrderAnalysis::query()
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuOne])
                    ->update(['exec' => $execName !== '' ? $execName : null]);
                $updated += $n;
                if ($n === 0) {
                    ToOrderAnalysis::create([
                        'sku' => $skuOne,
                        'exec' => $execName !== '' ? $execName : null,
                    ]);
                    $created++;
                }
            }

            $msg = $updated . ' row(s) updated';
            if ($created > 0) $msg .= ', ' . $created . ' row(s) created';

            return response()->json([
                'success' => true,
                'message' => $msg . '.',
                'updated' => $updated,
                'created' => $created,
            ]);
        } catch (\Throwable $e) {
            Log::error('ToOrderAnalysis bulkUpdateExec failed', ['skus' => $skus, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMFRG(Request $request)
    {
        try {
            $sku      = trim((string) $request->input('sku', ''));
            $parent   = trim((string) $request->input('parent', ''));
            $qty      = (int) ($request->input('order_qty') ?? 0);
            $supplier = trim((string) ($request->input('supplier') ?? ''));
            $advDate  = $request->input('adv_date') ?: null;

            if ($sku === '') {
                return response()->json(['success' => false, 'message' => 'SKU is required'], 400);
            }

            // Match by normalized SKU only — mirrors the /update-forecast-data stage='mip' branch
            // in ForecastAnalysisController so the two writes touch the SAME row (no duplicates).
            // Use DB::table to bypass the SoftDeletes global scope and include soft-deleted rows;
            // if found, we restore them by clearing deleted_at instead of creating a duplicate.
            $skuLower = strtolower($sku);
            $existing = DB::table('mfrg_progress')
                ->whereRaw('TRIM(LOWER(sku)) = ?', [$skuLower])
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->orderByRaw("CASE WHEN ready_to_ship = 'No' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->first();

            $now = now();

            if ($existing) {
                $update = [
                    'ready_to_ship' => 'No',
                    'deleted_at'    => null,
                    'updated_at'    => $now,
                ];
                if ($qty > 0) {
                    $update['qty'] = $qty;
                }
                if ($parent !== '') {
                    $update['parent'] = $parent;
                }
                if ($supplier !== '') {
                    $update['supplier'] = $supplier;
                }
                if ($advDate !== null && $advDate !== '') {
                    $update['adv_date'] = $advDate;
                }
                DB::table('mfrg_progress')->where('id', $existing->id)->update($update);
            } else {
                DB::table('mfrg_progress')->insert([
                    'sku'           => $sku,
                    'parent'        => $parent !== '' ? $parent : null,
                    'qty'           => $qty > 0 ? $qty : null,
                    'supplier'      => $supplier !== '' ? $supplier : null,
                    'adv_date'      => $advDate,
                    'ready_to_ship' => 'No',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('ToOrderAnalysis storeMFRG failed', [
                'sku'    => $request->input('sku'),
                'parent' => $request->input('parent'),
                'error'  => $e->getMessage(),
            ]);
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
                'parent' => trim((string) $request->input('parent', '')),
                'sku' => trim((string) $request->input('sku', '')),
            ],
            $data
        );

        return response()->json(['success' => true]);
    }

    /**
     * Active QC & Packing issues keyed by normalized SKU (from qc_and_packing_issues).
     *
     * @param array<int, string> $skus
     * @return array<string, string>
     */
    /**
     * Return QC & Packing issues (and their history) for a single SKU as JSON.
     * Powers the "Improvement Required" modal on the To Order Analysis page.
     * Source data is the same as /customer-care/qc-and-packing.
     */
    public function qcIssuesForSku(Request $request)
    {
        $sku = strtoupper(trim((string) $request->input('sku', '')));
        if ($sku === '') {
            return response()->json(['success' => true, 'sku' => '', 'issues' => [], 'history' => []]);
        }

        $cols = ['id', 'sku', 'what_happened', 'issue', 'issue_remark', 'c_action_1', 'c_action_1_remark', 'created_by', 'created_at'];

        $issues = DB::table('qc_and_packing_issues')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$sku])
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->orderByDesc('id')
            ->get($cols);

        $history = DB::table('qc_and_packing_issue_histories')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$sku])
            ->orderByDesc('id')
            ->get(['id', 'sku', 'event_type', 'revision_no', 'what_happened', 'issue', 'issue_remark', 'c_action_1', 'c_action_1_remark', 'created_by', 'logged_at', 'created_at']);

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'issues' => $issues,
            'history' => $history,
        ]);
    }

    private function buildQcPackingIssuesBySku(array $skus): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            fn ($s) => strtoupper(trim((string) $s)),
            $skus
        ))));

        if ($normalized === []) {
            return [];
        }

        $wanted = array_fill_keys($normalized, true);
        $bySku = [];

        $rows = DB::table('qc_and_packing_issues')
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->orderByDesc('id')
            ->get(['sku', 'what_happened', 'issue', 'issue_remark']);

        foreach ($rows as $row) {
            $skuKey = strtoupper(trim((string) ($row->sku ?? '')));
            if ($skuKey === '' || ! isset($wanted[$skuKey])) {
                continue;
            }
            $line = $this->formatQcPackingIssueLine($row);
            if ($line === '') {
                continue;
            }
            $bySku[$skuKey] = isset($bySku[$skuKey])
                ? $bySku[$skuKey] . "\n" . $line
                : $line;
        }

        return $bySku;
    }

    private function formatQcPackingIssueLine(object $row): string
    {
        $issue = trim((string) ($row->issue ?? ''));
        $remark = trim((string) ($row->issue_remark ?? ''));
        $whatHappened = trim((string) ($row->what_happened ?? ''));

        if ($issue !== '' && $remark !== '') {
            return $issue . ': ' . $remark;
        }
        if ($issue !== '') {
            return $issue;
        }
        if ($remark !== '') {
            return $remark;
        }

        return $whatHappened;
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

    public function getPreOrderChecklist(Request $request)
    {
        $sku = strtoupper(trim((string) $request->input('sku', '')));
        $rowId = (int) $request->input('to_order_analysis_id', 0);

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        $record = ToOrderPreChecklist::query()
            ->where('sku', $sku)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'to_order_analysis_id' => $rowId > 0 ? $rowId : ($record?->to_order_analysis_id),
                'sku' => $sku,
                'items' => ToOrderPreChecklist::mergeWithDefaults($record?->items),
                'status' => $record?->status,
                'escalation_note' => $record?->escalation_note,
            ],
        ]);
    }

    public function savePreOrderChecklist(Request $request)
    {
        $validated = $request->validate([
            'to_order_analysis_id' => 'nullable|integer|min:1',
            'sku' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|string|max:120',
            'items.*.label' => 'required|string|max:500',
            'items.*.checked' => 'boolean',
            'action' => 'required|in:clear_to_load,escalate',
            'escalation_note' => 'nullable|string|max:2000',
        ]);

        $sku = strtoupper(trim($validated['sku']));
        $items = $this->normalizeToOrderChecklistItems($validated['items']);
        $action = $validated['action'];
        $userName = $request->user()->name ?? 'Unknown';

        if ($action === 'clear_to_load' && ! ToOrderPreChecklist::allItemsChecked($items)) {
            return response()->json([
                'success' => false,
                'message' => 'All checklist points must be met before Clear to load.',
            ], 422);
        }

        if ($action === 'escalate' && ToOrderPreChecklist::allItemsChecked($items)) {
            return response()->json([
                'success' => false,
                'message' => 'All points are met — use Clear to load instead.',
            ], 422);
        }

        $rowId = (int) ($validated['to_order_analysis_id'] ?? 0);
        if ($rowId <= 0) {
            $rowId = (int) ToOrderAnalysis::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                ->value('id');
        }

        $record = ToOrderPreChecklist::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'to_order_analysis_id' => $rowId > 0 ? $rowId : null,
                'items' => $items,
                'status' => $action === 'clear_to_load' ? 'clear_to_load' : 'escalated',
                'escalation_note' => $action === 'escalate' ? ($validated['escalation_note'] ?? null) : null,
                'updated_by' => $userName,
                'escalated_by' => $action === 'escalate' ? $userName : null,
                'escalated_at' => $action === 'escalate' ? now() : null,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $this->formatToOrderChecklistPayload($record),
        ]);
    }

    public function bulkSavePreOrderChecklist(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.to_order_analysis_id' => 'nullable|integer|min:1',
            'rows.*.sku' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|string|max:120',
            'items.*.label' => 'required|string|max:500',
            'items.*.checked' => 'boolean',
            'action' => 'required|in:clear_to_load,escalate',
            'escalation_note' => 'nullable|string|max:2000',
        ]);

        $items = $this->normalizeToOrderChecklistItems($validated['items']);
        $action = $validated['action'];
        $userName = $request->user()->name ?? 'Unknown';

        if ($action === 'clear_to_load' && ! ToOrderPreChecklist::allItemsChecked($items)) {
            return response()->json([
                'success' => false,
                'message' => 'All checklist points must be met before Clear to load.',
            ], 422);
        }

        if ($action === 'escalate' && ToOrderPreChecklist::allItemsChecked($items)) {
            return response()->json([
                'success' => false,
                'message' => 'All points are met — use Clear to load instead.',
            ], 422);
        }

        $results = [];
        foreach ($validated['rows'] as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }
            $rowId = (int) ($row['to_order_analysis_id'] ?? 0);
            if ($rowId <= 0) {
                $rowId = (int) ToOrderAnalysis::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                    ->value('id');
            }

            $record = ToOrderPreChecklist::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'to_order_analysis_id' => $rowId > 0 ? $rowId : null,
                    'items' => $items,
                    'status' => $action === 'clear_to_load' ? 'clear_to_load' : 'escalated',
                    'escalation_note' => $action === 'escalate' ? ($validated['escalation_note'] ?? null) : null,
                    'updated_by' => $userName,
                    'escalated_by' => $action === 'escalate' ? $userName : null,
                    'escalated_at' => $action === 'escalate' ? now() : null,
                ]
            );

            $results[] = array_merge(
                ['sku' => $sku, 'to_order_analysis_id' => $record->to_order_analysis_id],
                $this->formatToOrderChecklistPayload($record)
            );
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    private function normalizeToOrderChecklistItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $id = Str::slug((string) ($item['id'] ?? ''), '_');
            if ($id === '') {
                $id = Str::slug((string) ($item['label'] ?? ''), '_');
            }
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'label' => trim((string) ($item['label'] ?? $id)),
                'checked' => (bool) ($item['checked'] ?? false),
            ];
        }

        return $out;
    }

    private function formatToOrderChecklistPayload(ToOrderPreChecklist $record): array
    {
        $items = ToOrderPreChecklist::mergeWithDefaults($record->items);
        $met = count(array_filter($items, fn ($i) => ! empty($i['checked'])));

        return [
            'items' => $items,
            'status' => $record->status,
            'escalation_note' => $record->escalation_note,
            'met_count' => $met,
            'total_count' => count($items),
        ];
    }

    private function attachToOrderPreChecklists(array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row['SKU'] ?? '')));
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
        $skus = array_values(array_unique($skus));
        if ($skus === []) {
            return;
        }

        $map = ToOrderPreChecklist::query()
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(fn ($r) => strtoupper(trim((string) $r->sku)));

        foreach ($rows as &$row) {
            $sku = strtoupper(trim((string) ($row['SKU'] ?? '')));
            $rec = $map->get($sku);
            $items = ToOrderPreChecklist::mergeWithDefaults($rec?->items);
            $met = count(array_filter($items, fn ($i) => ! empty($i['checked'])));
            $row['pre_order_checklist_items'] = $items;
            $row['pre_order_checklist_status'] = $rec?->status;
            $row['pre_order_checklist_escalation_note'] = $rec?->escalation_note;
            $row['pre_order_checklist_met_count'] = $met;
            $row['pre_order_checklist_total_count'] = count($items);
        }
        unset($row);
    }

}
