<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\AmazonSkuCompetitor;
use App\Models\Category;
use App\Models\ComparisonData;
use App\Models\ComparisonHistory;
use App\Models\EbaySkuCompetitor;
use App\Models\ForecastAnalysisHistory;
use App\Models\ProductMaster;
use App\Models\RfqForm;
use App\Models\ShopifySku;
use App\Models\Supplier;
use App\Services\ComparisonSheetService;
use App\Services\ComparisonSheetStorage;
use App\Services\ComparisonSkuLinkService;
use App\Services\LinkedSkuGroupService;
use App\Services\ShippingSlabRateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComparisonController extends Controller
{
    public function __construct(
        private ComparisonSheetService $sheetService,
        private ComparisonSheetStorage $sheetStorage,
        private ShippingSlabRateService $shippingSlabRateService,
        private ComparisonSkuLinkService $skuLinkService,
        private LinkedSkuGroupService $linkedSkuGroupService
    ) {
    }
    public function index()
    {
        return view('purchase-master.comparison.index');
    }

    public function getData(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $size = min(200, max(1, (int) $request->query('size', 50)));
            $skuFilter = trim((string) $request->query('sku', ''));
            $parentFilter = trim((string) $request->query('parent', ''));
            $skuList = array_values(array_unique(array_filter(array_map(
                fn ($value) => trim((string) $value),
                is_array($request->query('skus')) ? $request->query('skus') : explode(',', (string) $request->query('skus', ''))
            ))));

            $baseQuery = ProductMaster::query()
                ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'");

            if ($skuList !== []) {
                $baseQuery->whereIn('sku', $skuList);
            } else {
                if ($skuFilter !== '') {
                    $baseQuery->whereRaw('LOWER(TRIM(sku)) LIKE ?', ['%' . strtolower($skuFilter) . '%']);
                }

                if ($parentFilter !== '') {
                    $baseQuery->whereRaw('LOWER(TRIM(parent)) LIKE ?', ['%' . strtolower($parentFilter) . '%']);
                }
            }

            $total = (clone $baseQuery)->count();

            $productsQuery = (clone $baseQuery)
                ->with(['productCategory:id,category_name'])
                ->orderBy('parent')
                ->orderBy('sku');

            $products = $skuList !== []
                ? $productsQuery->get(['id', 'parent', 'sku', 'category_id', 'Values', 'main_image', 'image1'])
                : $productsQuery->forPage($page, $size)->get(['id', 'parent', 'sku', 'category_id', 'Values', 'main_image', 'image1']);

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'last_page' => max(1, (int) ceil($total / $size)),
                    'total' => $total,
                ]);
            }

            $skus = $products->pluck('sku')->filter()->values()->all();
            $shopifyBySku = ShopifySku::mapByProductSkus($skus);

            $forecastBySku = [];
            foreach (array_chunk($skus, 200) as $skuChunk) {
                foreach (DB::table('forecast_analysis')->select('sku', 'clink', 'updated_at')->whereIn('sku', $skuChunk)->get() as $row) {
                    $key = strtoupper(trim((string) $row->sku));
                    if ($key !== '') {
                        $forecastBySku[$key] = $row;
                    }
                }
            }

            $lmpLookups = AmazonSkuCompetitor::buildGroupedLookup('amazon');
            $lmpDetailsLookup = $lmpLookups['details'];
            $lmpLowestLookup = $lmpLookups['lowest'];
            $ebayLmpLookups = EbaySkuCompetitor::buildGroupedLookup('ebay');
            $ebayLmpLowestLookup = $ebayLmpLookups['lowest'];

            $historySummary = $this->buildHistorySummaryMap($skus);

            $sheetBySku = ComparisonData::query()
                ->whereIn('sku', $skus)
                ->get(['sku', 'sheet_data', 'updated_at'])
                ->keyBy(fn ($row) => strtoupper(trim((string) $row->sku)));

            $this->linkedSkuGroupService->prepareForSkus($skus);

            $supplierCountBySheetSku = [];

            $data = $products->map(function ($product) use ($shopifyBySku, $forecastBySku, $lmpDetailsLookup, $lmpLowestLookup, $ebayLmpLowestLookup, $historySummary, $sheetBySku, &$supplierCountBySheetSku) {
                $shopify = $shopifyBySku->get($product->sku);
                $image = $this->resolveProductImage($product, $shopify);

                $skuKey = strtoupper(trim((string) $product->sku));
                $forecast = $forecastBySku[$skuKey] ?? null;

                $lmpEntries = $lmpDetailsLookup->get($skuKey);
                if (!$lmpEntries instanceof \Illuminate\Support\Collection) {
                    $lmpEntries = collect();
                }
                $lowestLmp = $lmpLowestLookup->get($skuKey);
                $lmpPriceAmazon = ($lowestLmp && isset($lowestLmp->price) && is_numeric($lowestLmp->price))
                    ? (float) $lowestLmp->price
                    : null;

                $ebayLowest = $ebayLmpLowestLookup->get($skuKey);
                if (! $ebayLowest) {
                    foreach (EbaySkuCompetitor::resolveLookupKeys((string) $product->sku) as $lookupKey) {
                        $ebayLowest = $ebayLmpLowestLookup->get($lookupKey);
                        if ($ebayLowest) {
                            break;
                        }
                    }
                }
                $lmpPriceEbay = ($ebayLowest && isset($ebayLowest->total_price) && is_numeric($ebayLowest->total_price))
                    ? (float) $ebayLowest->total_price
                    : null;

                $history = $historySummary[$skuKey] ?? [
                    'history_count' => 0,
                    'latest_history_at' => null,
                    'latest_history_by' => null,
                    'latest_change' => null,
                ];

                $productCategory = trim((string) ($product->productCategory?->category_name ?? ''));

                $linkedSkus = $this->linkedSkusForProduct((string) $product->sku);
                $skuGroup = $linkedSkus;
                $sharedSheet = $this->resolveSharedSheetFromDbMap($skuGroup, $sheetBySku);
                $sheetSku = (string) ($sharedSheet['sheet_sku'] ?? $product->sku);
                $hasSheetData = (bool) ($sharedSheet['has_sheet_data'] ?? false);
                $sharedClink = $this->resolveSharedClinkFromForecastMap($skuGroup, $forecastBySku);
                $clink = (string) ($sharedClink['clink'] ?? '');
                $clinkSku = (string) ($sharedClink['clink_sku'] ?? $product->sku);
                $clinkIsSheet = $this->sheetStorage->isGoogleSheetUrl($clink);

                $sheetCells = [];
                $supplierCount = 0;
                if ($hasSheetData) {
                    $sheetKey = strtoupper(trim($sheetSku));
                    if (! array_key_exists($sheetKey, $supplierCountBySheetSku)) {
                        $sheetRow = $sheetBySku->get($sheetKey);
                        $sheetCells = $sheetRow?->sheet_data['cells'] ?? [];
                        $supplierCountBySheetSku[$sheetKey] = $this->countSupplierColumns(is_array($sheetCells) ? $sheetCells : []);
                    }
                    $supplierCount = $supplierCountBySheetSku[$sheetKey];
                }

                return [
                    'id' => $product->id,
                    'image' => $image,
                    'parent' => $product->parent,
                    'sku' => $product->sku,
                    'category_id' => $product->category_id,
                    'category' => $productCategory,
                    'linked_skus' => $linkedSkus,
                    'sheet_sku' => $sheetSku,
                    'clink' => $clink,
                    'clink_sku' => $clinkSku !== (string) $product->sku ? $clinkSku : null,
                    'clink_is_sheet' => $clinkIsSheet,
                    'lmp_price' => $lmpPriceAmazon,
                    'lmp_price_amazon' => $lmpPriceAmazon,
                    'lmp_price_ebay' => $lmpPriceEbay,
                    'lmp_link' => $lowestLmp?->product_link ?? null,
                    'lmp_entries_total' => $lmpEntries->count(),
                    'history_count' => $history['history_count'],
                    'latest_history_at' => $history['latest_history_at'],
                    'latest_history_by' => $history['latest_history_by'],
                    'latest_change' => $history['latest_change'],
                    'has_sheet_data' => $hasSheetData,
                    'sheet_supplier_count' => $supplierCount,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'last_page' => max(1, (int) ceil($total / $size)),
                'total' => $total,
            ]);
        } catch (\Throwable $e) {
            Log::error('Comparison getData failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load comparison data.',
            ], 500);
        }
    }

    public function getSheet(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 400);
        }

        $linkedSkus = $this->parseLinkedSkusQuery($request->query('linked_skus'));
        if ($linkedSkus === []) {
            $product = ProductMaster::query()
                ->with(['productCategory:id,category_name'])
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
                ->first(['sku', 'parent', 'category_id']);
            if ($product) {
                $linkedSkuContext = $this->buildLinkedSkuContext();
                $this->linkedSkuGroupService->reset();
                $linkedSkus = $this->linkedSkusForProduct(
                    (string) $product->sku,
                    (string) ($product->parent ?? ''),
                    trim((string) ($product->productCategory?->category_name ?? '')),
                    $linkedSkuContext
                );
            }
        }

        $this->linkedSkuGroupService->reset();
        $skuGroup = $this->normalizeSkuGroup($sku, $linkedSkus);
        $sharedSheet = $this->resolveSharedSheetSku($skuGroup);
        $sheetSku = (string) ($sharedSheet['sheet_sku'] ?? $sku);

        $sharedClink = $this->linkedSkuGroupService->resolveSharedClink($skuGroup);
        $clink = (string) ($sharedClink['clink'] ?? $this->clinkForSku($sku));
        $record = ComparisonData::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sheetSku)])->first();
        $filePayload = $this->sheetStorage->load($sheetSku);

        if (! $record && is_array($filePayload) && ! empty($filePayload['cells'])) {
            $record = ComparisonData::updateOrCreate(
                ['sku' => $sheetSku],
                [
                    'parent' => $filePayload['parent'] ?? null,
                    'sheet_data' => $this->sheetStorage->sheetDataForDatabase(
                        ComparisonData::normalizeCells($filePayload['cells']),
                        $this->sheetStorage->formatsFromPayload($filePayload)
                    ),
                    'google_sheet_url' => $filePayload['google_sheet_url'] ?? $this->clinkForSku($sheetSku),
                    'google_sheet_tab' => $filePayload['google_sheet_tab'] ?? 'Sheet1',
                    'updated_by' => $filePayload['updated_by'] ?? null,
                ]
            );
        }

        $cells = $this->sheetStorage->cellsForSku($sheetSku)
            ?? $record?->sheet_data['cells']
            ?? ComparisonData::defaultSheetCells();
        $cells = $this->sheetService->ensureLeadColumns($cells);
        $cells = $this->sheetService->moveLowestPriceSupplierAfterSpec($cells);
        $formats = $this->sheetStorage->formatsFromPayload(is_array($filePayload) ? $filePayload : null);
        if ($formats === ComparisonData::defaultSheetFormats() && is_array($record?->sheet_data)) {
            $formats = ComparisonData::normalizeFormats($record->sheet_data['formats'] ?? []);
        }
        $autoFormats = $this->sheetService->computeAutoFormats($cells);
        $sheetUrl = $record?->google_sheet_url
            ?: ($filePayload['google_sheet_url'] ?? null)
            ?: ($this->sheetStorage->isGoogleSheetUrl($this->clinkForSku($sheetSku)) ? $this->clinkForSku($sheetSku) : null);
        $hasSheetData = (bool) ($sharedSheet['has_sheet_data'] ?? false)
            || $this->sheetHasContent($cells)
            || $this->sheetStorage->cellsForSku($sheetSku) !== null;

        $parent = trim((string) $request->query('parent', ''));
        if ($parent === '') {
            $parent = (string) (ProductMaster::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
                ->value('parent') ?? '');
        }

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'sheet_sku' => $sheetSku,
            'linked_skus' => $skuGroup,
            'parent' => $parent !== '' ? $parent : $record?->parent,
            'cells' => ComparisonData::normalizeCells($cells),
            'formats' => $formats,
            'auto_formats' => $autoFormats,
            'clink' => $clink,
            'clink_sku' => ($sharedClink['clink_sku'] ?? null) !== $sku ? ($sharedClink['clink_sku'] ?? null) : null,
            'clink_is_sheet' => $this->sheetStorage->isGoogleSheetUrl($clink),
            'google_sheet_url' => $sheetUrl,
            'google_sheet_tab' => $record?->google_sheet_tab ?? 'Sheet1',
            'has_sheet_data' => $hasSheetData,
            'sheet_file' => $this->sheetStorage->pathForSku($sheetSku),
            'updated_by' => $record?->updated_by,
            'updated_at' => $record?->updated_at?->format('m-d-Y H:i'),
        ]);
    }

    public function saveSheet(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'parent' => 'nullable|string',
            'linked_skus' => 'nullable|array',
            'linked_skus.*' => 'string',
            'bulk_edit_skus' => 'nullable|array',
            'bulk_edit_skus.*' => 'string',
            'cells' => 'required|array',
            'cells.*' => 'array',
            'formats' => 'nullable|array',
            'formats.cells' => 'nullable|array',
            'formats.rows' => 'nullable|array',
            'formats.cols' => 'nullable|array',
            'google_sheet_url' => 'nullable|string|max:2000',
            'google_sheet_tab' => 'nullable|string|max:120',
        ]);

        $sku = trim($validated['sku']);
        $parent = trim((string) ($validated['parent'] ?? ''));
        $linkedSkus = is_array($validated['linked_skus'] ?? null) ? $validated['linked_skus'] : [];
        $bulkEditSkus = is_array($validated['bulk_edit_skus'] ?? null) ? $validated['bulk_edit_skus'] : [];
        $cells = ComparisonData::normalizeCells($validated['cells']);
        $cells = $this->sheetService->ensureLeadColumns($cells);
        $cells = $this->sheetService->moveLowestPriceSupplierAfterSpec($cells);
        $formats = ComparisonData::normalizeFormats($validated['formats'] ?? []);
        $autoFormats = $this->sheetService->computeAutoFormats($cells);
        $user = Auth::user()?->name ?? 'N/A';
        $clink = $this->clinkForSku($sku);
        $url = trim((string) ($validated['google_sheet_url'] ?? ''))
            ?: ($this->sheetStorage->isGoogleSheetUrl($clink) ? $clink : null);
        $tab = trim((string) ($validated['google_sheet_tab'] ?? '')) ?: 'Sheet1';

        $record = ComparisonData::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])->first();
        $oldCells = $record?->sheet_data['cells'] ?? [];

        $this->linkedSkuGroupService->reset();
        $this->persistSheetForLinkedGroup($sku, $linkedSkus, $parent, $cells, $url, $tab, $user, $formats, $bulkEditSkus);

        ComparisonHistory::logChange(
            $sku,
            $parent,
            'sheet_data',
            count($oldCells) . ' rows',
            count($cells) . ' rows',
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Comparison sheet saved.',
            'cells' => $cells,
            'formats' => $formats,
            'auto_formats' => $autoFormats,
        ]);
    }

    public function addLinkedSku(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'linked_sku' => 'required|string',
        ]);

        $sku = trim($validated['sku']);
        $linkedSku = trim($validated['linked_sku']);
        $user = Auth::user()?->name ?? 'N/A';

        if ($sku === '' || $linkedSku === '') {
            return response()->json([
                'success' => false,
                'message' => 'Both SKUs are required.',
            ], 422);
        }

        if (strtoupper($sku) === strtoupper($linkedSku)) {
            return response()->json([
                'success' => false,
                'message' => 'A SKU cannot be linked to itself.',
            ], 422);
        }

        $this->skuLinkService->link($sku, $linkedSku, $user);
        $this->linkedSkuGroupService->prepareForSkus([$sku, $linkedSku]);

        ComparisonHistory::logChange(
            $sku,
            (string) (ProductMaster::query()->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])->value('parent') ?? ''),
            'linked_sku_add',
            '',
            $linkedSku,
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Linked SKU added.',
            'affected' => $this->buildAffectedLinkedSkuRows($sku),
        ]);
    }

    public function bulkLinkSkus(Request $request)
    {
        $validated = $request->validate([
            'skus' => 'required|array|min:2',
            'skus.*' => 'required|string',
        ]);

        $skus = array_values(array_unique(array_filter(array_map('trim', $validated['skus']))));
        $user = Auth::user()?->name ?? 'N/A';

        if (count($skus) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least two SKUs to link.',
            ], 422);
        }

        $this->skuLinkService->syncFullyConnectedGroup($skus, $user);
        $this->linkedSkuGroupService->prepareForSkus($skus);

        $affectedBySku = [];
        foreach ($this->buildAffectedLinkedSkuRows($skus[0]) as $row) {
            $affectedBySku[$row['sku']] = $row;
        }

        ComparisonHistory::logChange(
            $skus[0],
            (string) (ProductMaster::query()->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($skus[0])])->value('parent') ?? ''),
            'linked_sku_add',
            '',
            implode(', ', array_slice($skus, 1)),
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Selected SKUs linked.',
            'affected' => array_values($affectedBySku),
        ]);
    }

    public function removeLinkedSku(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'linked_sku' => 'required|string',
        ]);

        $sku = trim($validated['sku']);
        $linkedSku = trim($validated['linked_sku']);
        $user = Auth::user()?->name ?? 'N/A';

        if ($sku === '' || $linkedSku === '') {
            return response()->json([
                'success' => false,
                'message' => 'Both SKUs are required.',
            ], 422);
        }

        if (strtoupper($sku) === strtoupper($linkedSku)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove a SKU from itself.',
            ], 422);
        }

        $this->linkedSkuGroupService->prepareForSkus([$sku, $linkedSku]);
        $beforeGroup = $this->resolveLinkedSkuGroupMembers($sku);
        $this->skuLinkService->unlink($sku, $linkedSku);
        $this->linkedSkuGroupService->prepareForSkus($beforeGroup);

        $affectedBySku = [];
        foreach ($beforeGroup as $memberSku) {
            foreach ($this->buildAffectedLinkedSkuRows($memberSku) as $row) {
                $affectedBySku[$row['sku']] = $row;
            }
        }

        ComparisonHistory::logChange(
            $sku,
            (string) (ProductMaster::query()->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])->value('parent') ?? ''),
            'linked_sku_remove',
            $linkedSku,
            '',
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Linked SKU removed.',
            'affected' => array_values($affectedBySku),
        ]);
    }

    public function importGoogleSheet(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'parent' => 'nullable|string',
            'google_sheet_url' => 'nullable|string|max:2000',
            'google_sheet_tab' => 'nullable|string|max:120',
        ]);

        $sku = trim($validated['sku']);
        $parent = trim((string) ($validated['parent'] ?? ''));
        $clink = $this->clinkForSku($sku);
        $url = trim((string) ($validated['google_sheet_url'] ?? ''))
            ?: ($this->sheetStorage->isGoogleSheetUrl($clink) ? $clink : '');

        if ($url === '') {
            return response()->json([
                'success' => false,
                'message' => 'No C link Google Sheet URL found for this SKU.',
            ], 422);
        }

        $tab = trim((string) ($validated['google_sheet_tab'] ?? '')) ?: 'Sheet1';

        return $this->importSheetFromUrl($sku, $parent, $url, $tab, 'Imported comparison sheet from C link / Google Sheets');
    }

    public function syncFromClink(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'parent' => 'nullable|string',
            'google_sheet_tab' => 'nullable|string|max:120',
        ]);

        $sku = trim($validated['sku']);
        $parent = trim((string) ($validated['parent'] ?? ''));
        $clink = $this->clinkForSku($sku);

        if (! $this->sheetStorage->isGoogleSheetUrl($clink)) {
            return response()->json([
                'success' => false,
                'message' => 'C link is empty or not a Google Sheet URL. Set it in the C link column first.',
            ], 422);
        }

        $tab = trim((string) ($validated['google_sheet_tab'] ?? '')) ?: 'Sheet1';

        return $this->importSheetFromUrl($sku, $parent, $clink, $tab, 'Synced comparison sheet from C link');
    }

    private function importSheetFromUrl(string $sku, string $parent, string $url, string $tab, string $historyMessage)
    {
        $user = Auth::user()?->name ?? 'N/A';

        try {
            $cells = $this->sheetService->fetchFromGoogleSheet($url, $tab);
            if ($cells === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'The Google Sheet appears to be empty.',
                ], 422);
            }

            $cells = $this->sheetService->normalizeComparisonLayout($cells);

            $product = ProductMaster::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
                ->first(['id', 'parent', 'sku', 'Values', 'main_image', 'image1']);
            $shopify = ShopifySku::firstForProductSku($sku);
            $productImage = $product ? $this->resolveProductImage($product, $shopify) : null;
            $cells = $this->sheetService->enrichProductPhotoRow($cells, $productImage);
            $autoFormats = $this->sheetService->computeAutoFormats($cells);

            $record = ComparisonData::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])->first();
            $oldCells = $record?->sheet_data['cells'] ?? [];

            $this->persistSheetForSku($sku, $parent, $cells, $url, $tab, $user, $url, ComparisonData::defaultSheetFormats());

            ComparisonHistory::create([
                'sku' => $sku,
                'parent' => $parent !== '' ? $parent : null,
                'field' => 'google_import',
                'old_value' => count($oldCells) ? count($oldCells) . ' rows' : '',
                'new_value' => count($cells) . ' rows',
                'changes' => $historyMessage,
                'updated_by' => $user,
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comparison sheet loaded from C link.',
                'cells' => $cells,
                'formats' => ComparisonData::defaultSheetFormats(),
                'auto_formats' => $autoFormats,
                'clink' => $url,
                'google_sheet_url' => $url,
                'google_sheet_tab' => $tab,
                'sheet_file' => $this->sheetStorage->pathForSku($sku),
                'has_sheet_data' => true,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Comparison importSheetFromUrl failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Failed to import Google Sheet.',
            ], 500);
        }
    }

    private function persistSheetForSku(
        string $sku,
        string $parent,
        array $cells,
        ?string $url,
        string $tab,
        string $user,
        ?string $clink = null,
        ?array $formats = null
    ): void {
        $cells = ComparisonData::normalizeCells($cells);
        $formats = ComparisonData::normalizeFormats($formats);

        $this->sheetStorage->save($sku, [
            'parent' => $parent !== '' ? $parent : null,
            'cells' => $cells,
            'formats' => $formats,
            'google_sheet_url' => $url,
            'google_sheet_tab' => $tab,
            'clink' => $clink ?: $url,
            'updated_by' => $user,
        ]);

        ComparisonData::updateOrCreate(
            ['sku' => $sku],
            [
                'parent' => $parent !== '' ? $parent : null,
                'sheet_data' => $this->sheetStorage->sheetDataForDatabase($cells, $formats),
                'google_sheet_url' => $url,
                'google_sheet_tab' => $tab,
                'updated_by' => $user,
            ]
        );
    }

    private function clinkForSku(string $sku): string
    {
        $row = DB::table('forecast_analysis')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim($sku))])
            ->value('clink');

        return trim((string) ($row ?? ''));
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function sheetHasContent(array $cells): bool
    {
        foreach ($cells as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function countSupplierColumns(array $cells): int
    {
        if ($cells === []) {
            return 0;
        }

        $headerRow = null;
        foreach ($cells as $row) {
            if (! is_array($row)) {
                continue;
            }
            $joined = strtolower(implode(' ', $row));
            if (str_contains($joined, 'person name review') || str_contains($joined, 'product photo')) {
                $headerRow = $row;
                break;
            }
        }

        if ($headerRow === null) {
            $headerRow = $cells[0] ?? [];
        }

        return count(array_filter($headerRow, fn ($value) => trim((string) $value) !== ''));
    }

    public function suppliersForSku(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 400);
        }

        $linkedSkus = $request->query('linked_skus', []);
        if (is_string($linkedSkus)) {
            $linkedSkus = array_filter(array_map('trim', explode(',', $linkedSkus)));
        }
        if (! is_array($linkedSkus)) {
            $linkedSkus = [];
        }

        $parent = trim((string) $request->query('parent', ''));
        $category = trim((string) $request->query('category', ''));
        $byCategory = filter_var($request->query('by_category', false), FILTER_VALIDATE_BOOLEAN);

        if ($byCategory) {
            if ($category === '') {
                $product = ProductMaster::query()
                    ->with(['productCategory:id,category_name'])
                    ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
                    ->first(['category_id']);

                $category = trim((string) ($product?->productCategory?->category_name ?? ''));
            }

            if ($category === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Category is required to autopopulate suppliers.',
                ], 400);
            }

            $suppliers = $this->suppliersForCategory($category);

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'category' => $category,
                'suppliers' => $suppliers,
            ]);
        }

        if ($parent === '') {
            $product = ProductMaster::query()
                ->with(['productCategory:id,category_name'])
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
                ->first(['parent', 'category_id']);
            $parent = (string) ($product?->parent ?? '');
            if ($category === '') {
                $category = trim((string) ($product?->productCategory?->category_name ?? ''));
            }
        }

        $skuKeys = array_values(array_unique(array_filter(array_merge([$sku], $linkedSkus))));
        $suppliers = $this->matchSuppliersForSkus($skuKeys, $parent, $category);

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'linked_skus' => $skuKeys,
            'suppliers' => $suppliers,
        ]);
    }

    public function getHistory(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        $parent = trim((string) $request->query('parent', ''));

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 400);
        }

        try {
            $labels = ComparisonHistory::fieldLabels();
            $history = $this->historyRowsForSku($sku, $parent, $labels);

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'parent' => $parent !== '' ? $parent : null,
                'history' => $history,
            ]);
        } catch (\Throwable $e) {
            Log::error('Comparison getHistory failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load comparison history.',
            ], 500);
        }
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, array{history_count:int, latest_history_at:?string, latest_history_by:?string, latest_change:?string}>
     */
    private function buildHistorySummaryMap(array $skus): array
    {
        $skuList = collect($skus)
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($skuList === []) {
            return [];
        }

        $labels = ComparisonHistory::fieldLabels();
        $grouped = [];

        ComparisonHistory::query()
            ->whereIn('sku', $skuList)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->each(function ($row) use (&$grouped, $labels) {
                $key = strtoupper(trim((string) $row->sku));
                $grouped[$key][] = $this->mapHistoryRow($row, 'comparison', $labels);
            });

        ForecastAnalysisHistory::query()
            ->whereIn('sku', $skuList)
            ->where('field', 'clink')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->each(function ($row) use (&$grouped, $labels) {
                $key = strtoupper(trim((string) $row->sku));
                $grouped[$key][] = $this->mapHistoryRow($row, 'forecast', $labels);
            });

        $summary = [];
        foreach ($skuList as $sku) {
            $skuKey = strtoupper(trim($sku));
            $rows = collect($grouped[$skuKey] ?? [])
                ->sortByDesc(fn ($row) => $row['sort_at'] ? Carbon::parse($row['sort_at'])->timestamp : 0)
                ->values();

            if ($rows->isEmpty()) {
                continue;
            }

            $latest = $rows->first();
            $summary[$skuKey] = [
                'history_count' => $rows->count(),
                'latest_history_at' => $latest['updated_at']
                    ? Carbon::parse($latest['updated_at'])->timezone('America/New_York')->format('m-d-Y H:i')
                    : null,
                'latest_history_by' => $latest['updated_by'],
                'latest_change' => $latest['changes'],
            ];
        }

        return $summary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historyRowsForSku(string $sku, string $parent, array $labels): array
    {
        $rows = collect();

        $comparisonQuery = ComparisonHistory::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)]);
        if ($parent !== '') {
            $comparisonQuery->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [strtoupper($parent)]);
        }

        foreach ($comparisonQuery->orderByDesc('updated_at')->orderByDesc('id')->get() as $row) {
            $rows->push($this->mapHistoryRow($row, 'comparison', $labels));
        }

        $forecastQuery = ForecastAnalysisHistory::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
            ->where('field', 'clink');

        if ($parent !== '') {
            $forecastQuery->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [strtoupper($parent)]);
        }

        foreach ($forecastQuery->orderByDesc('updated_at')->orderByDesc('id')->get() as $row) {
            $rows->push($this->mapHistoryRow($row, 'forecast', $labels));
        }

        return $rows
            ->sortByDesc(fn ($row) => $row['sort_at'] ? Carbon::parse($row['sort_at'])->timestamp : 0)
            ->values()
            ->map(function ($row) {
                return [
                    'id' => $row['id'],
                    'field' => $row['field'],
                    'field_label' => $row['field_label'],
                    'old_value' => $row['old_value'],
                    'new_value' => $row['new_value'],
                    'changes' => $row['changes'],
                    'updated_by' => $row['updated_by'],
                    'updated_at' => $row['updated_at']
                        ? Carbon::parse($row['updated_at'])->timezone('America/New_York')->format('m-d-Y H:i')
                        : null,
                ];
            })
            ->all();
    }

    private function mapHistoryRow($row, string $source, array $labels): array
    {
        $field = (string) ($row->field ?? 'clink');
        $fieldLabel = ComparisonHistory::resolveFieldLabel($field);
        $prefix = $source === 'forecast' ? 'forecast' : 'comparison';

        return [
            'id' => $prefix . '-' . $row->id,
            'field' => $field,
            'field_label' => $fieldLabel,
            'old_value' => $row->old_value,
            'new_value' => $row->new_value,
            'changes' => $row->changes ?? $this->buildChangeText($fieldLabel, $row->old_value, $row->new_value),
            'updated_by' => $row->updated_by ?: 'N/A',
            'updated_at' => $row->updated_at,
            'sort_at' => $row->updated_at,
        ];
    }

    private function buildChangeText(string $label, $oldValue, $newValue): string
    {
        $old = trim((string) ($oldValue ?? ''));
        $new = trim((string) ($newValue ?? ''));

        return sprintf('%s changed from "%s" to "%s"', $label, $old ?: 'empty', $new ?: 'empty');
    }

    private function resolveProductImage(ProductMaster $product, ?ShopifySku $shopify): ?string
    {
        $candidates = [
            $shopify?->image_src,
        ];

        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($values)) {
            $values = [];
        }

        foreach (['image_path', 'image', 'Image', 'main_image'] as $key) {
            if (! empty($values[$key])) {
                $candidates[] = $values[$key];
                break;
            }
        }

        $candidates[] = $product->main_image;
        $candidates[] = $product->image1;

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeImageUrl($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeImageUrl(mixed $path): ?string
    {
        $p = trim((string) ($path ?? ''));
        if ($p === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $p) || str_starts_with($p, 'data:')) {
            return $p;
        }

        if (str_starts_with($p, '//')) {
            return 'https:' . $p;
        }

        return '/' . ltrim($p, '/');
    }

    /**
     * @return array{rfq_by_norm: array<string, array<string, true>>, parent_by_sku_norm: array<string, string>}
     */
    private function buildLinkedSkuContext(): array
    {
        $rfqByNorm = [];
        foreach (RfqForm::query()->whereNotNull('linked_skus')->get(['linked_skus']) as $form) {
            $linked = $form->linked_skus;
            if (! is_array($linked)) {
                $linked = json_decode((string) $linked, true) ?: [];
            }
            $displaySkus = array_values(array_unique(array_filter(array_map(
                fn ($value) => trim((string) $value),
                $linked
            ))));
            foreach ($displaySkus as $sku) {
                $norm = strtoupper($sku);
                if ($norm === '') {
                    continue;
                }
                foreach ($displaySkus as $relatedSku) {
                    $rfqByNorm[$norm][$relatedSku] = true;
                }
            }
        }

        $parentBySkuNorm = [];
        foreach (ProductMaster::query()->select('sku', 'parent')->get() as $product) {
            $norm = strtoupper(trim((string) ($product->sku ?? '')));
            if ($norm === '') {
                continue;
            }
            $parentBySkuNorm[$norm] = str_replace(' ', '', strtoupper(trim((string) ($product->parent ?? ''))));
        }

        return [
            'rfq_by_norm' => $rfqByNorm,
            'parent_by_sku_norm' => $parentBySkuNorm,
        ];
    }

    private function linkedSkusForProduct(string $sku, string $parent = '', string $categoryName = '', array $context = []): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $group = $this->linkedSkuGroupService->groupContaining($sku);

        return $group !== [] ? $group : [$sku];
    }

    /**
     * @param  array{rfq_by_norm: array<string, array<string, true>>, parent_by_sku_norm: array<string, string>}  $context
     * @return list<string>
     */
    private function linkedSkusFromRfqContext(string $sku, string $parent, array $context): array
    {
        $sku = trim($sku);
        $norm = strtoupper($sku);
        $parentNorm = str_replace(' ', '', strtoupper(trim($parent)));
        $rfqByNorm = $context['rfq_by_norm'] ?? [];
        $parentBySkuNorm = $context['parent_by_sku_norm'] ?? [];
        $matched = [];

        if ($norm !== '' && isset($rfqByNorm[$norm])) {
            $matched = array_merge($matched, array_keys($rfqByNorm[$norm]));
        } elseif ($sku !== '') {
            $matched[] = $sku;
        }

        if ($parentNorm !== '') {
            foreach ($rfqByNorm as $linkedNorm => $displayMap) {
                $linkedParentNorm = $parentBySkuNorm[$linkedNorm] ?? '';
                if ($linkedParentNorm !== '' && $linkedParentNorm === $parentNorm) {
                    $matched = array_merge($matched, array_keys($displayMap));
                }
            }
        }

        return $matched;
    }

    /**
     * Linked SKUs from supplier.list for the product category (supplier SKU field + RFQ links).
     *
     * @param  array{rfq_by_norm: array<string, array<string, true>>, parent_by_sku_norm: array<string, string>}  $context
     * @return list<string>
     */
    private function linkedSkusFromSupplierListCategory(string $sku, string $parent, string $categoryName, array $context): array
    {
        $category = Category::query()->where('name', $categoryName)->first();
        if (! $category) {
            return [];
        }

        $normSkus = array_values(array_unique(array_filter([strtoupper(trim($sku))])));
        $parentNorm = str_replace(' ', '', strtoupper(trim($parent)));
        $parentBySkuNorm = $context['parent_by_sku_norm'] ?? [];
        $rfqByNorm = $context['rfq_by_norm'] ?? [];

        $suppliers = Supplier::query()
            ->whereRaw('FIND_IN_SET(?, REPLACE(category_id, " ", ""))', [$category->id])
            ->get(['id', 'sku', 'parent']);

        if ($suppliers->isEmpty()) {
            return [];
        }

        $matched = [];
        foreach ($suppliers as $supplier) {
            if (! $this->supplierMatchesSkuKeys($supplier, $normSkus, $parentNorm, $parentBySkuNorm)) {
                continue;
            }

            foreach (preg_split('/\s*,\s*/', (string) ($supplier->sku ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $token = trim($token);
                if ($token !== '') {
                    $matched[] = $token;
                }
            }

            foreach ($this->rfqLinkedSkusForSupplier($supplier, $rfqByNorm, $parentBySkuNorm) as $linkedSku) {
                $matched[] = $linkedSku;
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $matched))));
    }

    /**
     * @param  array<string, array<string, true>>  $rfqByNorm
     * @param  array<string, string>  $parentBySkuNorm
     * @return list<string>
     */
    private function rfqLinkedSkusForSupplier(Supplier $supplier, array $rfqByNorm, array $parentBySkuNorm): array
    {
        if ($rfqByNorm === []) {
            return [];
        }

        $supplierSkuNorms = array_filter(array_map(
            static fn ($value) => strtoupper(trim($value)),
            preg_split('/\s*,\s*/', (string) ($supplier->sku ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        ));

        $supplierParentNorms = array_filter(array_map(
            static fn ($value) => str_replace(' ', '', strtoupper(trim($value))),
            preg_split('/\s*,\s*/', (string) ($supplier->parent ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        ));

        $matched = [];
        foreach ($rfqByNorm as $normSku => $displayMap) {
            if (in_array($normSku, $supplierSkuNorms, true)) {
                $matched = array_merge($matched, array_keys($displayMap));
                continue;
            }

            $skuParentNorm = $parentBySkuNorm[$normSku] ?? '';
            if ($skuParentNorm === '') {
                continue;
            }

            foreach ($supplierParentNorms as $supplierParentNorm) {
                if ($supplierParentNorm !== '' && $supplierParentNorm === $skuParentNorm) {
                    $matched = array_merge($matched, array_keys($displayMap));
                    break;
                }
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $matched))));
    }

    /**
     * All suppliers on supplier.list tagged to the given category name.
     *
     * @return list<array{id: int, name: string, company: string|null, link: string|null}>
     */
    private function suppliersForCategory(string $categoryName): array
    {
        $categoryName = trim($categoryName);
        if ($categoryName === '') {
            return [];
        }

        $category = Category::query()->where('name', $categoryName)->first();
        if (! $category) {
            return [];
        }

        $seenIds = [];

        return Supplier::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereRaw('FIND_IN_SET(?, REPLACE(category_id, " ", ""))', [$category->id])
            ->orderBy('name')
            ->get(['id', 'name', 'company', 'alibaba', 'link_1688', 'website', 'email', 'whatsapp', 'wechat', 'qq', 'phone', 'country_code'])
            ->filter(function ($supplier) use (&$seenIds) {
                if (isset($seenIds[$supplier->id])) {
                    return false;
                }
                $seenIds[$supplier->id] = true;

                return true;
            })
            ->map(fn ($supplier) => [
                'id' => (int) $supplier->id,
                'name' => trim((string) $supplier->name),
                'company' => trim((string) ($supplier->company ?? '')) ?: null,
                'link' => $this->resolveSupplierListLink($supplier),
                'platform_links' => $this->buildSupplierPlatformLinks($supplier),
            ])
            ->values()
            ->all();
    }

    /**
     * Communication / platform links for a supplier (matches MFRG In-Progress + supplier.list columns).
     *
     * @return list<array{label: string, url: ?string, external?: bool, display?: string}>
     */
    private function buildSupplierPlatformLinks(Supplier $supplier): array
    {
        $links = [];

        $website = trim((string) ($supplier->website ?? ''));
        if ($website !== '') {
            $url = preg_match('#^https?://#i', $website) ? $website : ('https://'.ltrim($website, '/'));
            $links[] = ['label' => 'Website', 'url' => $url, 'external' => true];
        }

        $email = trim((string) ($supplier->email ?? ''));
        if ($email !== '') {
            $links[] = ['label' => 'Email', 'url' => 'mailto:'.$email, 'external' => false, 'display' => $email];
        }

        $phone = trim((string) ($supplier->phone ?? ''));
        if ($phone !== '') {
            $digits = preg_replace('/\D/', '', $phone);
            $links[] = [
                'label' => 'Phone',
                'url' => $digits !== '' ? 'tel:'.$digits : null,
                'external' => false,
                'display' => $phone,
            ];
        }

        $whatsapp = trim((string) ($supplier->whatsapp ?? ''));
        if ($whatsapp !== '') {
            $digits = preg_replace('/\D/', '', $whatsapp);
            if ($digits !== '' && strlen($digits) < 10 && ! empty($supplier->country_code)) {
                $countryCode = preg_replace('/\D/', '', (string) $supplier->country_code);
                if ($countryCode !== '') {
                    $digits = $countryCode.$digits;
                }
            }
            if ($digits !== '') {
                $links[] = ['label' => 'WhatsApp', 'url' => 'https://wa.me/'.$digits, 'external' => true, 'display' => $whatsapp];
            }
        }

        $wechat = trim((string) ($supplier->wechat ?? ''));
        if ($wechat !== '') {
            $links[] = ['label' => 'WeChat', 'url' => null, 'display' => $wechat];
        }

        $qq = trim((string) ($supplier->qq ?? ''));
        if ($qq !== '') {
            $links[] = ['label' => 'QQ', 'url' => null, 'display' => $qq];
        }

        $alibaba = trim((string) ($supplier->alibaba ?? ''));
        if ($alibaba !== '') {
            $url = preg_match('#^https?://#i', $alibaba) ? $alibaba : ('https://'.ltrim($alibaba, '/'));
            $links[] = ['label' => 'Alibaba', 'url' => $url, 'external' => true];
        }

        $link1688 = trim((string) ($supplier->link_1688 ?? ''));
        if ($link1688 !== '') {
            $url = preg_match('#^https?://#i', $link1688) ? $link1688 : ('https://'.ltrim($link1688, '/'));
            $links[] = ['label' => '1688', 'url' => $url, 'external' => true];
        }

        return $links;
    }

    private function resolveSupplierListLink(Supplier $supplier): ?string
    {
        foreach (['alibaba', 'link_1688'] as $field) {
            $url = trim((string) ($supplier->{$field} ?? ''));
            if ($url === '') {
                continue;
            }
            if (preg_match('/^https?:\/\//i', $url)) {
                return $url;
            }
        }

        foreach (['alibaba', 'link_1688'] as $field) {
            $url = trim((string) ($supplier->{$field} ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $skuKeys
     * @return list<array{id: int, name: string, company: string|null}>
     */
    private function matchSuppliersForSkus(array $skuKeys, string $parent, string $categoryName = ''): array
    {
        $normSkus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => strtoupper(trim((string) $sku)),
            $skuKeys
        ))));

        if ($normSkus === []) {
            return [];
        }

        $context = $this->buildLinkedSkuContext();
        $parentBySkuNorm = $context['parent_by_sku_norm'];
        $parentNorm = str_replace(' ', '', strtoupper(trim($parent)));

        $query = Supplier::query()
            ->whereNotNull('name')
            ->where('name', '!=', '');

        if ($categoryName !== '') {
            $category = Category::query()->where('name', $categoryName)->first();
            if ($category) {
                $query->whereRaw('FIND_IN_SET(?, REPLACE(category_id, " ", ""))', [$category->id]);
            }
        }

        $suppliers = $query->orderBy('name')->get(['id', 'name', 'company', 'sku', 'parent']);

        $matched = [];
        $seenIds = [];

        foreach ($suppliers as $supplier) {
            if (! $this->supplierMatchesSkuKeys($supplier, $normSkus, $parentNorm, $parentBySkuNorm)) {
                continue;
            }
            if (isset($seenIds[$supplier->id])) {
                continue;
            }
            $seenIds[$supplier->id] = true;
            $matched[] = [
                'id' => (int) $supplier->id,
                'name' => trim((string) $supplier->name),
                'company' => trim((string) ($supplier->company ?? '')) ?: null,
            ];
        }

        return $matched;
    }

    /**
     * @param  list<string>  $normSkus
     * @param  array<string, string>  $parentBySkuNorm
     */
    private function supplierMatchesSkuKeys(Supplier $supplier, array $normSkus, string $parentNorm, array $parentBySkuNorm): bool
    {
        $supplierSkuNorms = array_filter(array_map(
            static fn ($value) => strtoupper(trim($value)),
            preg_split('/\s*,\s*/', (string) ($supplier->sku ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        ));

        $supplierParentNorms = array_filter(array_map(
            static fn ($value) => str_replace(' ', '', strtoupper(trim($value))),
            preg_split('/\s*,\s*/', (string) ($supplier->parent ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        ));

        foreach ($normSkus as $normSku) {
            if (in_array($normSku, $supplierSkuNorms, true)) {
                return true;
            }

            $skuParentNorm = $parentBySkuNorm[$normSku] ?? '';
            if ($skuParentNorm !== '') {
                foreach ($supplierParentNorms as $supplierParentNorm) {
                    if ($supplierParentNorm !== '' && $supplierParentNorm === $skuParentNorm) {
                        return true;
                    }
                }
            }
        }

        if ($parentNorm !== '') {
            foreach ($supplierParentNorms as $supplierParentNorm) {
                if ($supplierParentNorm !== '' && $supplierParentNorm === $parentNorm) {
                    return true;
                }
            }
        }

        return false;
    }

    public function shippingSlabRate(Request $request)
    {
        $weightLb = $request->query('weight_lb');
        $sku = trim((string) $request->query('sku', ''));
        $carrier = trim((string) $request->query('carrier', 'ship')) ?: 'ship';

        if ($weightLb === null || $weightLb === '' || ! is_numeric($weightLb)) {
            return response()->json([
                'success' => false,
                'message' => 'weight_lb is required.',
            ], 422);
        }

        $result = $this->shippingSlabRateService->getCarrierRateForWeight(
            (float) $weightLb,
            $carrier,
            $sku !== '' ? $sku : null
        );

        return response()->json($result);
    }

    public function lmpRates(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json([
                'success' => false,
                'message' => 'sku is required.',
            ], 422);
        }

        $amazonLowest = AmazonSkuCompetitor::getLowestPriceForSku($sku, 'amazon');
        $amazonLmp = ($amazonLowest && is_numeric($amazonLowest->price))
            ? (float) $amazonLowest->price
            : null;

        $ebayLowest = null;
        foreach (EbaySkuCompetitor::resolveLookupKeys($sku) as $lookupKey) {
            $ebayLowest = EbaySkuCompetitor::getLowestPriceForSku($lookupKey, 'ebay');
            if ($ebayLowest) {
                break;
            }
        }
        $ebayLmp = ($ebayLowest && is_numeric($ebayLowest->total_price))
            ? (float) $ebayLowest->total_price
            : null;

        return response()->json([
            'success' => true,
            'amazon_lmp' => $amazonLmp,
            'ebay_lmp' => $ebayLmp,
        ]);
    }

    public function saveRoiCell(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'parent' => 'nullable|string',
            'linked_skus' => 'nullable|array',
            'linked_skus.*' => 'string',
            'bulk_edit_skus' => 'nullable|array',
            'bulk_edit_skus.*' => 'string',
            'channel' => 'required|string|in:Amazon,Ebay,amazon,ebay',
            'field' => 'required|string|in:cp,cbm,freight,gw,shipping,sale',
            'old_value' => 'nullable|string',
            'new_value' => 'nullable|string',
            'row' => 'required|array',
        ]);

        $sku = trim($validated['sku']);
        $parent = trim((string) ($validated['parent'] ?? ''));
        $linkedSkus = is_array($validated['linked_skus'] ?? null) ? $validated['linked_skus'] : [];
        $bulkEditSkus = is_array($validated['bulk_edit_skus'] ?? null) ? $validated['bulk_edit_skus'] : [];
        $channel = ucfirst(strtolower(trim($validated['channel'])));
        if ($channel !== 'Ebay') {
            $channel = 'Amazon';
        }
        $user = Auth::user()?->name ?? 'N/A';

        $this->linkedSkuGroupService->reset();
        $skuGroup = $this->normalizeSkuGroup($sku, $linkedSkus);
        $sheetSku = (string) ($this->resolveSharedSheetSku($skuGroup)['sheet_sku'] ?? $sku);

        $record = ComparisonData::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sheetSku)])->first();
        $cells = $record?->sheet_data['cells'] ?? [];
        if ($cells === []) {
            $cells = $this->sheetStorage->cellsForSku($sheetSku) ?? [];
        }
        if ($cells === []) {
            $cells = ComparisonData::defaultSheetCells();
        }

        $cells = $this->sheetService->writeRoiChannelRow($cells, $channel, $validated['row']);
        $formats = ComparisonData::normalizeFormats($record?->sheet_data['formats'] ?? ComparisonData::defaultSheetFormats());
        $clink = $this->clinkForSku($sheetSku);
        $url = $record?->google_sheet_url
            ?: ($this->sheetStorage->isGoogleSheetUrl($clink) ? $clink : null);
        $tab = trim((string) ($record?->google_sheet_tab ?? '')) ?: 'Sheet1';

        $this->persistSheetForLinkedGroup($sku, $linkedSkus, $parent, $cells, $url, $tab, $user, $formats, $bulkEditSkus);

        $fieldKey = 'roi_'.strtolower($channel).'_'.$validated['field'];
        ComparisonHistory::logChange(
            $sku,
            $parent,
            $fieldKey,
            $validated['old_value'] ?? '',
            $validated['new_value'] ?? '',
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'ROI value saved.',
            'updated_by' => $user,
            'updated_at' => now()->format('m-d-Y H:i'),
            'cells' => $cells,
        ]);
    }

    /**
     * @return list<string>
     */
    private function parseLinkedSkusQuery(mixed $linkedSkus): array
    {
        if (is_string($linkedSkus)) {
            return array_values(array_unique(array_filter(array_map('trim', explode(',', $linkedSkus)))));
        }

        if (! is_array($linkedSkus)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($sku) => trim((string) $sku),
            $linkedSkus
        ))));
    }

    private function normalizeSkuGroup(string $sku, array $linkedSkus = []): array
    {
        return $this->linkedSkuGroupService->normalizeGroup($sku, $linkedSkus);
    }

    /**
     * @return list<string>
     */
    private function linkedSkuGroupContaining(string $sku): array
    {
        return $this->linkedSkuGroupService->groupContaining($sku);
    }

    private function skuHasSheetContent(string $sku): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return false;
        }

        $record = ComparisonData::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])->first();
        $fileCells = $this->sheetStorage->cellsForSku($sku) ?? [];
        $dbCells = $record?->sheet_data['cells'] ?? [];
        $cells = $fileCells !== [] ? $fileCells : $dbCells;

        return $this->sheetHasContent(is_array($cells) ? $cells : []) || $fileCells !== [];
    }

    private function sheetTimestampForSku(string $sku): ?Carbon
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $record = ComparisonData::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])->first();
        $filePayload = $this->sheetStorage->load($sku);
        $timestamps = [];

        if ($record?->updated_at) {
            $timestamps[] = $record->updated_at instanceof Carbon
                ? $record->updated_at
                : Carbon::parse($record->updated_at);
        }

        if (is_array($filePayload) && ! empty($filePayload['stored_at'])) {
            $timestamps[] = Carbon::parse((string) $filePayload['stored_at']);
        }

        return $timestamps === [] ? null : collect($timestamps)->max();
    }

    /**
     * @param  list<string>  $skuGroup
     * @param  array<string, object>  $forecastBySku
     * @return array{clink: string, clink_sku: string|null}
     */
    private function resolveSharedClinkFromForecastMap(array $skuGroup, array $forecastBySku): array
    {
        $fallbackSku = trim((string) ($skuGroup[0] ?? ''));
        $bestClink = '';
        $bestSku = null;
        $bestTs = null;

        foreach ($skuGroup as $candidateSku) {
            $candidateSku = trim((string) $candidateSku);
            if ($candidateSku === '') {
                continue;
            }

            $row = $forecastBySku[strtoupper($candidateSku)] ?? null;
            if (! $row) {
                continue;
            }

            $clink = trim((string) ($row->clink ?? ''));
            if ($clink === '') {
                continue;
            }

            $timestamp = $row->updated_at ? Carbon::parse($row->updated_at) : null;

            if ($bestSku === null) {
                $bestClink = $clink;
                $bestSku = trim((string) ($row->sku ?? $candidateSku));
                $bestTs = $timestamp;
                continue;
            }

            if ($timestamp && ($bestTs === null || $timestamp->gt($bestTs))) {
                $bestClink = $clink;
                $bestSku = trim((string) ($row->sku ?? $candidateSku));
                $bestTs = $timestamp;
            }
        }

        if ($bestClink === '' && $fallbackSku !== '') {
            $row = $forecastBySku[strtoupper($fallbackSku)] ?? null;
            $bestClink = trim((string) ($row->clink ?? ''));
            $bestSku = $fallbackSku;
        }

        return [
            'clink' => $bestClink,
            'clink_sku' => $bestSku,
        ];
    }

    /**
     * @param  list<string>  $skuGroup
     * @param  \Illuminate\Support\Collection<string, ComparisonData>  $sheetBySku
     * @return array{sheet_sku: string|null, has_sheet_data: bool}
     */
    private function resolveSharedSheetFromDbMap(array $skuGroup, $sheetBySku): array
    {
        $fallbackSku = $skuGroup[0] ?? null;
        $bestSku = null;
        $bestTs = null;
        $hasAny = false;

        foreach ($skuGroup as $candidateSku) {
            $candidateSku = trim((string) $candidateSku);
            if ($candidateSku === '') {
                continue;
            }

            $record = $sheetBySku->get(strtoupper($candidateSku));
            if (! $record) {
                continue;
            }

            $cells = $record->sheet_data['cells'] ?? [];
            if (! is_array($cells) || $cells === []) {
                continue;
            }

            $hasAny = true;
            $timestamp = $record->updated_at
                ? ($record->updated_at instanceof Carbon ? $record->updated_at : Carbon::parse($record->updated_at))
                : null;

            if ($bestSku === null) {
                $bestSku = $candidateSku;
                $bestTs = $timestamp;
                continue;
            }

            if ($timestamp && ($bestTs === null || $timestamp->gt($bestTs))) {
                $bestSku = $candidateSku;
                $bestTs = $timestamp;
            }
        }

        return [
            'sheet_sku' => $bestSku ?? $fallbackSku,
            'has_sheet_data' => $hasAny,
        ];
    }

    /**
     * @param  list<string>  $skuGroup
     * @return array{sheet_sku: string|null, has_sheet_data: bool}
     */
    private function resolveSharedSheetSku(array $skuGroup): array
    {
        $fallbackSku = $skuGroup[0] ?? null;
        $bestSku = null;
        $bestTs = null;
        $hasAny = false;

        foreach ($skuGroup as $candidateSku) {
            if (! $this->skuHasSheetContent($candidateSku)) {
                continue;
            }

            $hasAny = true;
            $timestamp = $this->sheetTimestampForSku($candidateSku);

            if ($bestSku === null) {
                $bestSku = $candidateSku;
                $bestTs = $timestamp;
                continue;
            }

            if ($timestamp && ($bestTs === null || $timestamp->gt($bestTs))) {
                $bestSku = $candidateSku;
                $bestTs = $timestamp;
            }
        }

        return [
            'sheet_sku' => $bestSku ?? $fallbackSku,
            'has_sheet_data' => $hasAny,
        ];
    }

    /**
     * @param  list<string>  $linkedSkus
     * @param  list<string>  $bulkEditSkus
     * @return list<string>
     */
    private function persistSkuTargets(string $primarySku, array $linkedSkus, array $bulkEditSkus = []): array
    {
        $bulkEditSkus = array_values(array_unique(array_filter(array_map(
            fn ($sku) => trim((string) $sku),
            $bulkEditSkus
        ))));

        if ($bulkEditSkus !== []) {
            return $bulkEditSkus;
        }

        return $this->normalizeSkuGroup($primarySku, $linkedSkus);
    }

    /**
     * @return list<string>
     */
    private function resolveLinkedSkuGroupMembers(string $sku): array
    {
        $group = $this->linkedSkuGroupService->groupContaining(trim($sku));

        return $group !== [] ? $group : [trim($sku)];
    }

    /**
     * @return list<array{sku: string, linked_skus: list<string>}>
     */
    private function buildAffectedLinkedSkuRows(string $sku): array
    {
        $group = $this->resolveLinkedSkuGroupMembers($sku);
        $rows = [];

        foreach ($group as $memberSku) {
            $rows[] = [
                'sku' => $memberSku,
                'linked_skus' => $group,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $linkedSkus
     * @param  list<string>  $bulkEditSkus
     */
    private function persistSheetForLinkedGroup(
        string $primarySku,
        array $linkedSkus,
        string $parent,
        array $cells,
        ?string $url,
        string $tab,
        string $user,
        ?array $formats,
        array $bulkEditSkus = []
    ): void {
        foreach ($this->persistSkuTargets($primarySku, $linkedSkus, $bulkEditSkus) as $targetSku) {
            $targetParent = (string) (ProductMaster::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim($targetSku))])
                ->value('parent') ?? $parent);
            $clink = $this->clinkForSku($targetSku);
            $this->persistSheetForSku($targetSku, $targetParent, $cells, $url, $tab, $user, $clink, $formats);
        }
    }
}
