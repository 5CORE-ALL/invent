<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\AmazonSkuCompetitor;
use App\Models\Category;
use App\Models\ComparisonData;
use App\Models\ComparisonHistory;
use App\Models\ForecastAnalysisHistory;
use App\Models\ProductMaster;
use App\Models\RfqForm;
use App\Models\ShopifySku;
use App\Models\Supplier;
use App\Services\ComparisonSheetService;
use App\Services\ComparisonSheetStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComparisonController extends Controller
{
    public function __construct(
        private ComparisonSheetService $sheetService,
        private ComparisonSheetStorage $sheetStorage
    ) {
    }
    public function index()
    {
        return view('purchase-master.comparison.index');
    }

    public function getData()
    {
        try {
            $products = ProductMaster::query()
                ->with(['productCategory:id,category_name'])
                ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'")
                ->orderBy('parent')
                ->orderBy('sku')
                ->get(['id', 'parent', 'sku', 'category_id', 'Values', 'main_image', 'image1']);

            $skus = $products->pluck('sku')->filter()->values()->all();
            $shopifyBySku = ShopifySku::mapByProductSkus($skus);

            $forecastBySku = [];
            foreach (DB::table('forecast_analysis')->select('sku', 'clink')->get() as $row) {
                $key = strtoupper(trim((string) $row->sku));
                if ($key !== '') {
                    $forecastBySku[$key] = $row;
                }
            }

            $lmpLookups = AmazonSkuCompetitor::buildGroupedLookup('amazon');
            $lmpDetailsLookup = $lmpLookups['details'];
            $lmpLowestLookup = $lmpLookups['lowest'];

            $historySummary = $this->buildHistorySummaryMap($skus);

            $sheetBySku = ComparisonData::query()
                ->whereIn('sku', $skus)
                ->get()
                ->keyBy(fn ($row) => strtoupper(trim((string) $row->sku)));

            $linkedSkuContext = $this->buildLinkedSkuContext();

            $data = $products->map(function ($product) use ($shopifyBySku, $forecastBySku, $lmpDetailsLookup, $lmpLowestLookup, $historySummary, $sheetBySku, $linkedSkuContext) {
                $shopify = $shopifyBySku->get($product->sku);
                $image = $this->resolveProductImage($product, $shopify);

                $skuKey = strtoupper(trim((string) $product->sku));
                $forecast = $forecastBySku[$skuKey] ?? null;
                $clink = trim((string) ($forecast->clink ?? ''));

                $lmpEntries = $lmpDetailsLookup->get($skuKey);
                if (!$lmpEntries instanceof \Illuminate\Support\Collection) {
                    $lmpEntries = collect();
                }
                $lowestLmp = $lmpLowestLookup->get($skuKey);
                $lmpPrice = ($lowestLmp && isset($lowestLmp->price) && is_numeric($lowestLmp->price))
                    ? (float) $lowestLmp->price
                    : null;

                $history = $historySummary[$skuKey] ?? [
                    'history_count' => 0,
                    'latest_history_at' => null,
                    'latest_history_by' => null,
                    'latest_change' => null,
                ];

                $sheetRow = $sheetBySku->get($skuKey);
                $sheetCells = $sheetRow?->sheet_data['cells'] ?? [];
                $fileCells = $this->sheetStorage->cellsForSku($product->sku) ?? [];
                if ($fileCells !== []) {
                    $sheetCells = $fileCells;
                }
                $hasSheetData = $this->sheetHasContent(is_array($sheetCells) ? $sheetCells : [])
                    || $fileCells !== [];
                $clinkIsSheet = $this->sheetStorage->isGoogleSheetUrl($clink);

                $productCategory = trim((string) ($product->productCategory?->category_name ?? ''));

                $linkedSkus = $this->linkedSkusForProduct(
                    (string) $product->sku,
                    (string) ($product->parent ?? ''),
                    $productCategory,
                    $linkedSkuContext
                );

                return [
                    'id' => $product->id,
                    'image' => $image,
                    'parent' => $product->parent,
                    'sku' => $product->sku,
                    'category' => $productCategory,
                    'linked_skus' => $linkedSkus,
                    'clink' => $clink,
                    'clink_is_sheet' => $clinkIsSheet,
                    'lmp_price' => $lmpPrice,
                    'lmp_link' => $lowestLmp?->product_link ?? null,
                    'lmp_entries_total' => $lmpEntries->count(),
                    'history_count' => $history['history_count'],
                    'latest_history_at' => $history['latest_history_at'],
                    'latest_history_by' => $history['latest_history_by'],
                    'latest_change' => $history['latest_change'],
                    'has_sheet_data' => $hasSheetData,
                    'sheet_supplier_count' => $this->countSupplierColumns($sheetCells),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
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

        $clink = $this->clinkForSku($sku);
        $record = ComparisonData::whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])->first();
        $filePayload = $this->sheetStorage->load($sku);

        if (! $record && is_array($filePayload) && ! empty($filePayload['cells'])) {
            $record = ComparisonData::updateOrCreate(
                ['sku' => $sku],
                [
                    'parent' => $filePayload['parent'] ?? null,
                    'sheet_data' => $this->sheetStorage->sheetDataForDatabase(
                        ComparisonData::normalizeCells($filePayload['cells']),
                        $this->sheetStorage->formatsFromPayload($filePayload)
                    ),
                    'google_sheet_url' => $filePayload['google_sheet_url'] ?? $clink,
                    'google_sheet_tab' => $filePayload['google_sheet_tab'] ?? 'Sheet1',
                    'updated_by' => $filePayload['updated_by'] ?? null,
                ]
            );
        }

        $cells = $this->sheetStorage->cellsForSku($sku)
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
            ?: ($this->sheetStorage->isGoogleSheetUrl($clink) ? $clink : null);
        $hasSheetData = $this->sheetHasContent($cells) || $this->sheetStorage->cellsForSku($sku) !== null;

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'parent' => $record?->parent,
            'cells' => ComparisonData::normalizeCells($cells),
            'formats' => $formats,
            'auto_formats' => $autoFormats,
            'clink' => $clink,
            'clink_is_sheet' => $this->sheetStorage->isGoogleSheetUrl($clink),
            'google_sheet_url' => $sheetUrl,
            'google_sheet_tab' => $record?->google_sheet_tab ?? 'Sheet1',
            'has_sheet_data' => $hasSheetData,
            'sheet_file' => $this->sheetStorage->pathForSku($sku),
            'updated_by' => $record?->updated_by,
            'updated_at' => $record?->updated_at?->format('m-d-Y H:i'),
        ]);
    }

    public function saveSheet(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'parent' => 'nullable|string',
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

        $this->persistSheetForSku($sku, $parent, $cells, $url, $tab, $user, $clink, $formats);

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
        $fieldLabel = $labels[$field] ?? ($field === 'clink' ? 'Comparison Link' : $field);
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
            foreach ($linked as $sku) {
                $display = trim((string) $sku);
                $norm = strtoupper($display);
                if ($norm === '') {
                    continue;
                }
                $rfqByNorm[$norm][$display] = true;
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

    private function linkedSkusForProduct(string $sku, string $parent, string $categoryName, array $context): array
    {
        $sku = trim($sku);
        $categoryName = trim($categoryName);
        $matched = [];

        if ($categoryName !== '') {
            $matched = array_merge($matched, $this->linkedSkusFromSupplierListCategory($sku, $parent, $categoryName, $context));
        }

        $matched = array_merge($matched, $this->linkedSkusFromRfqContext($sku, $parent, $context));
        $matched = array_values(array_unique(array_filter(array_map('trim', $matched))));

        if ($matched === [] && $sku !== '') {
            return [$sku];
        }

        return $matched;
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
}
