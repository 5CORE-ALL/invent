<?php

namespace App\Http\Controllers;

use App\Models\ArrivedContainer;
use App\Models\ClaimReimbursement;
use App\Models\ComparisonData;
use App\Models\ProductMaster;
use App\Models\QcContainerAudit;
use App\Models\ShopifySku;
use App\Models\Supplier;
use App\Services\ComparisonSheetService;
use App\Services\ComparisonSheetStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * QC Container — same datatable data as /arrived/container (arrived_containers).
 * Saves and history use Arrived Container endpoints.
 */
class QcContainerController extends Controller
{
    public function index()
    {
        $allRecords = ArrivedContainer::with('user')->where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->get();

        $tabs = ArrivedContainer::with('user')->where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->distinct()->pluck('tab_name')->toArray();

        if (empty($tabs)) {
            $tabs = ['Container 1'];
        }

        usort($tabs, function ($a, $b) {
            preg_match('/(\d+)/', (string) $a, $mA);
            preg_match('/(\d+)/', (string) $b, $mB);

            return ((int) ($mB[1] ?? 0)) <=> ((int) ($mA[1] ?? 0));
        });

        $normalizeSku = static function ($value) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)));
        };

        $skuParentMap = ProductMaster::pluck('parent', 'sku')
            ->mapWithKeys(function ($parent, $sku) use ($normalizeSku) {
                return [$normalizeSku($sku) => $normalizeSku($parent)];
            })->toArray();

        // Same supplier source as /forecast.analysis:
        // 1) to_order_analysis.supplier_name (when set, incl. empty string)
        // 2) else mfrg_progress.supplier
        $supplierRows = Supplier::where('type', 'Supplier')->get(['name', 'parent']);
        $parentSupplierMap = [];
        foreach ($supplierRows as $supplier) {
            $parentList = array_map('trim', explode(',', strtoupper($supplier->parent ?? '')));
            foreach ($parentList as $parent) {
                if ($parent === '') {
                    continue;
                }
                $parentSupplierMap[$parent][] = $supplier->name;
            }
        }

        $toOrderSupplierBySku = [];
        $toOrderRows = DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'supplier_name']);
        foreach ($toOrderRows as $r) {
            $k = $normalizeSku($r->sku);
            if ($k === '' || array_key_exists($k, $toOrderSupplierBySku)) {
                continue;
            }
            if ($r->supplier_name !== null) {
                $toOrderSupplierBySku[$k] = (string) $r->supplier_name;
            }
        }

        $mfrgSupplierBySku = [];
        $mfrgRows = DB::table('mfrg_progress')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'supplier']);
        foreach ($mfrgRows as $r) {
            $k = $normalizeSku($r->sku);
            if ($k === '' || array_key_exists($k, $mfrgSupplierBySku)) {
                continue;
            }
            $mfrgSupplierBySku[$k] = trim((string) ($r->supplier ?? ''));
        }

        $shopifyImages = ShopifySku::pluck('image_src', 'sku')->mapWithKeys(function ($value, $key) use ($normalizeSku) {
            return [$normalizeSku($key) => $value];
        })->toArray();

        $productValuesMap = ProductMaster::pluck('Values', 'sku')->mapWithKeys(function ($value, $key) use ($normalizeSku) {
            return [$normalizeSku($key) => $value];
        })->toArray();

        $allRecords->transform(function ($record) use (
            $skuParentMap,
            $parentSupplierMap,
            $shopifyImages,
            $productValuesMap,
            $toOrderSupplierBySku,
            $mfrgSupplierBySku,
            $normalizeSku
        ) {
            $sku = $normalizeSku($record->our_sku ?? '');

            $parent = $skuParentMap[$sku] ?? null;

            if (empty($record->parent) && $parent) {
                $record->parent = $parent;
            }

            $parentKey = $normalizeSku($record->parent ?? '');
            $record->supplier_names = isset($parentSupplierMap[$parentKey])
                ? array_values(array_unique($parentSupplierMap[$parentKey]))
                : [];

            // Display supplier = same as Forecast Supplier column (mfrg_supplier)
            if (array_key_exists($sku, $toOrderSupplierBySku)) {
                $record->supplier_name = $toOrderSupplierBySku[$sku];
            } else {
                $record->supplier_name = $mfrgSupplierBySku[$sku] ?? '';
            }

            $record->image_src = $shopifyImages[$sku] ?? null;
            $values = $productValuesMap[$sku] ?? null;
            if (is_string($values)) {
                $decoded = json_decode($values, true);
                $values = is_array($decoded) ? $decoded : null;
            }
            $record->Values = $values;
            // Unit from CP Master (product_master.Values.unit) — same as /product-master datatable.
            $pmUnit = is_array($values) ? trim((string) ($values['unit'] ?? '')) : '';
            $record->unit = $pmUnit !== '' ? $pmUnit : null;
            $record->created_by_name = $record->user->name ?? '—';

            return $record;
        });

        // QC page only: hide duplicate SKUs (keep first occurrence by id).
        $seenSkus = [];
        $allRecords = $allRecords->sortBy('id')->filter(function ($record) use (&$seenSkus) {
            $sku = strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($record->our_sku ?? ''))));
            if ($sku === '') {
                return true;
            }
            if (isset($seenSkus[$sku])) {
                return false;
            }
            $seenSkus[$sku] = true;

            return true;
        })->values();

        // CD column: comparison_data sheet presence (same source as Forecast / To Order).
        $skuList = $allRecords
            ->pluck('our_sku')
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sheetBySku = $skuList === []
            ? collect()
            : ComparisonData::query()
                ->whereIn('sku', $skuList)
                ->get(['sku', 'sheet_data'])
                ->keyBy(fn ($row) => strtoupper(trim((string) $row->sku)));

        $auditIds = $allRecords->pluck('id')->filter()->values()->all();
        $auditRows = $auditIds === []
            ? collect()
            : QcContainerAudit::query()
                ->whereIn('arrived_container_id', $auditIds)
                ->get(['arrived_container_id', 'items', 'action_history'])
                ->keyBy(fn ($row) => (int) $row->arrived_container_id);

        $allRecords->transform(function ($record) use ($sheetBySku, $auditRows) {
            $skuKey = strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($record->our_sku ?? ''))));
            $sheetRow = $skuKey !== '' ? $sheetBySku->get($skuKey) : null;
            $cells = is_array($sheetRow?->sheet_data['cells'] ?? null) ? $sheetRow->sheet_data['cells'] : [];
            $hasSheet = false;
            foreach ($cells as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach ($row as $value) {
                    if (trim((string) $value) !== '') {
                        $hasSheet = true;
                        break 2;
                    }
                }
            }
            $audit = $auditRows->get((int) $record->id);
            $auditItems = is_array($audit?->items) ? $audit->items : [];
            $hasDiscrepancy = false;
            foreach ($auditItems as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (strtolower(trim((string) ($item['answer'] ?? ''))) === 'no'
                    || trim((string) ($item['discrepancy'] ?? '')) !== '') {
                    $hasDiscrepancy = true;
                    break;
                }
            }
            $record->has_sheet_data = $hasSheet;
            $record->has_qc_audit = $audit && $auditItems !== [];
            $record->has_qc_discrepancy = $hasDiscrepancy;
            $record->action_history = is_array($audit?->action_history) ? $audit->action_history : [];

            return $record;
        });

        $groupedData = $allRecords->groupBy('tab_name');
        foreach ($tabs as $tab) {
            if (! isset($groupedData[$tab])) {
                $groupedData[$tab] = collect([]);
            }
        }

        $groupedData = collect($tabs)->mapWithKeys(fn ($tab) => [$tab => $groupedData[$tab]]);

        return view('purchase-master.transit_container.qc-container', [
            'tabs' => $tabs,
            'groupedData' => $groupedData,
        ]);
    }

    /**
     * Specs for QC modal — OLD (5 Core) + Supplier columns from comparison sheet.
     */
    public function getSpecs(Request $request, ComparisonSheetService $sheetService, ComparisonSheetStorage $sheetStorage)
    {
        $sku = trim((string) $request->query('sku', ''));
        $supplierName = trim((string) $request->query('supplier', ''));
        $arrivedId = (int) $request->query('arrived_id', 0);

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 400);
        }

        $headerMeta = $this->productHeaderMeta($sku, $arrivedId, $supplierName);

        $cells = $this->loadComparisonCells($sku, $sheetService, $sheetStorage);
        if ($cells === null) {
            return response()->json([
                'success' => true,
                'sku' => $headerMeta['sku'],
                'supplier_name' => $headerMeta['supplier_name'],
                'image_src' => $headerMeta['image_src'],
                'basic_header' => 'OLD',
                'supplier_header' => $headerMeta['supplier_name'] !== '' ? $headerMeta['supplier_name'] : 'Supplier',
                'specs' => [],
                'audit' => null,
                'message' => 'No comparison sheet data found for this SKU.',
                'claim_reimbursement_url' => route('claim.reimbursement'),
                'related_claims' => $this->relatedClaimsForSku($sku),
            ]);
        }

        $specCol = $sheetService->detectSpecColumnIndex($cells);
        $basicCol = max(0, $specCol - 1); // 5 Core sits immediately before Spec
        $criticalCol = $sheetService->detectCriticalColumnIndex($cells, $specCol);
        $qcCol = $sheetService->detectQcColumnIndex($cells, $specCol);
        $firstSupplierCol = $sheetService->getFirstSupplierColumnIndex($cells, $specCol);
        $supplierCol = $this->resolveSupplierColumn($cells, $sheetService, $specCol, $firstSupplierCol, $supplierName);
        $resolvedSupplier = $supplierCol !== null
            ? $this->supplierNameAtColumn($cells, $sheetService, $specCol, $supplierCol)
            : '';
        if ($resolvedSupplier !== '') {
            $headerMeta['supplier_name'] = $resolvedSupplier;
        }

        // Exact meta labels to skip (not substring matches — keep "Company", "Supplier Price", etc.).
        $skipExactLabels = [
            'supplier name',
            'supplier',
            'suppliers',
            'critical',
            'qc',
            'amazon',
            '5 core',
            '5core',
        ];

        $specs = [];
        $lastNonEmptyLabel = '';
        foreach ($cells as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }

            // Skip sheet header row that labels columns (Spec / Critical / QC).
            if ((int) $rowIndex === 0) {
                $headerCandidate = strtolower(trim((string) ($row[$specCol] ?? '')));
                $qcHeader = ($qcCol !== null) ? strtolower(trim((string) ($row[$qcCol] ?? ''))) : '';
                if (in_array($headerCandidate, ['spec', 'specs', 'supplier name', ''], true) || $qcHeader === 'qc') {
                    continue;
                }
            }

            if ($sheetService->isSupplierNameRow($cells, (int) $rowIndex, $specCol)) {
                continue;
            }

            $label = trim((string) ($row[$specCol] ?? ''));
            if ($label !== '') {
                $lastNonEmptyLabel = $label;
            }

            $labelKey = strtolower($label);
            if ($label !== '' && in_array($labelKey, $skipExactLabels, true)) {
                continue;
            }

            // Prefer QC column marking; also accept Critical column marking.
            $qcPriority = $this->normalizeQcPriority(
                $qcCol !== null ? ($row[$qcCol] ?? '') : ''
            );
            $criticalPriority = $this->normalizeQcPriority(
                $criticalCol !== null ? ($row[$criticalCol] ?? '') : ''
            );

            $priority = null;
            if (in_array($qcPriority, ['Critical', 'Important'], true)) {
                $priority = $qcPriority;
            } elseif (in_array($criticalPriority, ['Critical', 'Important'], true)) {
                $priority = $criticalPriority;
            }

            // Only Critical + Important (hide Normal / blank).
            if ($priority === null) {
                continue;
            }

            // Continuation rows (photo extras) with empty Spec label still belong to prior spec.
            $displayLabel = $label !== '' ? $label : ($lastNonEmptyLabel !== '' ? $lastNonEmptyLabel : 'Spec');

            $basicValue = $this->cellDisplayValue($row[$basicCol] ?? '');
            $supplierValue = $supplierCol !== null
                ? $this->cellDisplayValue($row[$supplierCol] ?? '')
                : '';

            // Color always reflects QC column when set; else Critical column.
            $colorSource = in_array($qcPriority, ['Critical', 'Important'], true)
                ? $qcPriority
                : $priority;

            $specs[] = [
                'row_index' => (int) $rowIndex,
                'spec' => $displayLabel,
                'basic' => $basicValue,
                'supplier' => $supplierValue,
                'qc_priority' => $colorSource,
                'qc_color' => $colorSource === 'Critical' ? '#dc2626' : '#2563eb',
            ];
        }

        // Critical first, Important second (preserve sheet order within each group).
        usort($specs, function (array $a, array $b) {
            $rank = ['Critical' => 0, 'Important' => 1];
            $ra = $rank[$a['qc_priority']] ?? 9;
            $rb = $rank[$b['qc_priority']] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return ($a['row_index'] ?? 0) <=> ($b['row_index'] ?? 0);
        });

        $audit = null;
        if ($arrivedId > 0) {
            $auditRow = QcContainerAudit::query()
                ->where('arrived_container_id', $arrivedId)
                ->first();
            if ($auditRow) {
                $items = collect($auditRow->items ?? [])->map(function ($item) {
                    if (! is_array($item)) {
                        return $item;
                    }
                    $imagePath = trim((string) ($item['image'] ?? ''));
                    $item['image_url'] = $imagePath !== '' ? asset('storage/' . ltrim($imagePath, '/')) : null;

                    return $item;
                })->values()->all();

                $audit = [
                    'items' => $items,
                    'claim_links' => is_array($auditRow->claim_links ?? null) ? $auditRow->claim_links : [],
                    'audited_by' => $auditRow->audited_by,
                    'updated_at' => optional($auditRow->updated_at)->format('Y-m-d H:i'),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'sku' => $headerMeta['sku'],
            'supplier_name' => $headerMeta['supplier_name'],
            'image_src' => $headerMeta['image_src'],
            'basic_header' => 'OLD',
            'supplier_header' => ($headerMeta['supplier_name'] !== '' ? $headerMeta['supplier_name'] : 'Supplier'),
            'specs' => $specs,
            'audit' => $audit,
            'claim_reimbursement_url' => route('claim.reimbursement'),
            'related_claims' => $this->relatedClaimsForSku($sku),
        ]);
    }

    public function addAction(Request $request)
    {
        $arrivedId = (int) $request->input('arrived_id', 0);
        $note = trim((string) $request->input('note', ''));
        $sku = trim((string) $request->input('sku', ''));
        $supplierName = trim((string) $request->input('supplier_name', ''));

        if ($arrivedId <= 0) {
            return response()->json(['success' => false, 'message' => 'Arrived container id is required.'], 400);
        }

        if ($note === '') {
            return response()->json(['success' => false, 'message' => 'Action / communication note is required.'], 422);
        }

        $arrived = ArrivedContainer::query()->find($arrivedId);
        if (! $arrived) {
            return response()->json(['success' => false, 'message' => 'Arrived container row not found.'], 404);
        }

        if ($sku === '') {
            $sku = trim((string) ($arrived->our_sku ?? ''));
        }
        if ($supplierName === '') {
            $supplierName = trim((string) ($arrived->supplier_name ?? ''));
        }

        $audit = QcContainerAudit::firstOrNew(['arrived_container_id' => $arrivedId]);
        $history = is_array($audit->action_history) ? $audit->action_history : [];
        $history[] = [
            'action' => 'Action / Communication',
            'note' => $note,
            'user' => Auth::user()->name ?? 'System',
            'date' => now()->format('j M'),
            'datetime' => now()->format('j M Y, g:i A'),
        ];

        $audit->our_sku = $sku !== '' ? $sku : $audit->our_sku;
        $audit->supplier_name = $supplierName !== '' ? $supplierName : $audit->supplier_name;
        $audit->action_history = $history;
        if (! $audit->exists && empty($audit->items)) {
            $audit->items = [];
        }
        $audit->save();

        return response()->json([
            'success' => true,
            'message' => 'Action added successfully.',
            'action_history' => $audit->action_history,
        ]);
    }

    public function saveAudit(Request $request)
    {
        $arrivedId = (int) $request->input('arrived_id', 0);
        $sku = trim((string) $request->input('sku', ''));
        $supplierName = trim((string) $request->input('supplier_name', ''));
        $itemsRaw = $request->input('items', []);

        if (is_string($itemsRaw)) {
            $decoded = json_decode($itemsRaw, true);
            $itemsRaw = is_array($decoded) ? $decoded : [];
        }

        $claimLinksRaw = $request->input('claim_links', []);
        if (is_string($claimLinksRaw)) {
            $decodedLinks = json_decode($claimLinksRaw, true);
            $claimLinksRaw = is_array($decodedLinks) ? $decodedLinks : [];
        }

        if ($arrivedId <= 0) {
            return response()->json(['success' => false, 'message' => 'Arrived container id is required.'], 400);
        }

        if (! ArrivedContainer::query()->where('id', $arrivedId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Arrived container row not found.'], 404);
        }

        if (! is_array($itemsRaw) || $itemsRaw === []) {
            return response()->json(['success' => false, 'message' => 'At least one spec answer is required.'], 422);
        }

        $existing = QcContainerAudit::query()
            ->where('arrived_container_id', $arrivedId)
            ->first();
        $existingByKey = [];
        foreach ($existing?->items ?? [] as $old) {
            if (! is_array($old)) {
                continue;
            }
            $key = ((int) ($old['row_index'] ?? 0)) . '|' . trim((string) ($old['spec'] ?? ''));
            $existingByKey[$key] = $old;
        }

        $normalized = [];
        foreach ($itemsRaw as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }

            $answer = strtolower(trim((string) ($item['answer'] ?? '')));
            if (! in_array($answer, ['yes', 'no'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Each spec must be marked Yes or No.',
                ], 422);
            }

            $discrepancy = trim((string) ($item['discrepancy'] ?? ''));
            if ($answer === 'no') {
                if ($discrepancy === '') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Audit Discrepancy is required when No is selected.',
                    ], 422);
                }
                if (mb_strlen($discrepancy) > 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Audit Discrepancy must be 100 characters or less.',
                    ], 422);
                }
            } else {
                $discrepancy = '';
            }

            $instructions = trim((string) ($item['instructions'] ?? ''));
            if (mb_strlen($instructions) > 500) {
                return response()->json([
                    'success' => false,
                    'message' => 'Further Suggestion / Instructions must be 500 characters or less.',
                ], 422);
            }

            $rowIndex = (int) ($item['row_index'] ?? 0);
            $specLabel = trim((string) ($item['spec'] ?? ''));
            $key = $rowIndex . '|' . $specLabel;
            $imagePath = trim((string) ($existingByKey[$key]['image'] ?? ''));

            $removeImage = filter_var($item['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($removeImage && $imagePath !== '') {
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
                $imagePath = '';
            }

            $file = $request->file('images.' . $idx) ?? $request->file('image_' . $idx);
            if ($file && $file->isValid()) {
                if ($imagePath !== '' && Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
                $imagePath = $file->store('qc-audits/' . $arrivedId, 'public');
            }

            $normalized[] = [
                'row_index' => $rowIndex,
                'spec' => $specLabel,
                'basic' => trim((string) ($item['basic'] ?? '')),
                'supplier' => trim((string) ($item['supplier'] ?? '')),
                'answer' => $answer,
                'discrepancy' => $discrepancy,
                'instructions' => $instructions,
                'image' => $imagePath,
            ];
        }

        if ($normalized === []) {
            return response()->json(['success' => false, 'message' => 'No valid audit items provided.'], 422);
        }

        $claimLinks = [];
        foreach ($claimLinksRaw as $link) {
            $url = trim((string) (is_array($link) ? ($link['url'] ?? '') : $link));
            if ($url === '') {
                continue;
            }
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid claim link URL: ' . $url,
                ], 422);
            }
            $label = is_array($link) ? trim((string) ($link['label'] ?? '')) : '';
            $claimLinks[] = [
                'label' => $label !== '' ? $label : 'Claim',
                'url' => $url,
            ];
        }

        $audit = QcContainerAudit::updateOrCreate(
            ['arrived_container_id' => $arrivedId],
            [
                'our_sku' => $sku,
                'supplier_name' => $supplierName,
                'items' => $normalized,
                'claim_links' => $claimLinks,
                'audited_by' => Auth::id(),
            ]
        );

        $hasDiscrepancy = false;
        foreach ($normalized as $item) {
            if (($item['answer'] ?? '') === 'no' || trim((string) ($item['discrepancy'] ?? '')) !== '') {
                $hasDiscrepancy = true;
                break;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'QC audit saved.',
            'audit_id' => $audit->id,
            'has_qc_audit' => true,
            'has_qc_discrepancy' => $hasDiscrepancy,
        ]);
    }

    /**
     * @return list<array{id:int,claim_number:string,url:string,supplier_name:string}>
     */
    private function relatedClaimsForSku(string $sku): array
    {
        $skuKey = strtoupper(trim($sku));
        if ($skuKey === '') {
            return [];
        }

        $pageUrl = route('claim.reimbursement');
        $matches = [];

        ClaimReimbursement::query()
            ->with('supplier:id,name')
            ->where('is_archived', false)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'claim_number', 'supplier_id', 'items'])
            ->each(function (ClaimReimbursement $claim) use ($skuKey, $pageUrl, &$matches) {
                $items = is_array($claim->items) ? $claim->items : [];
                foreach ($items as $item) {
                    $itemSku = strtoupper(trim((string) (is_array($item) ? ($item['item'] ?? '') : '')));
                    if ($itemSku !== '' && $itemSku === $skuKey) {
                        $matches[] = [
                            'id' => (int) $claim->id,
                            'claim_number' => (string) $claim->claim_number,
                            'url' => $pageUrl,
                            'supplier_name' => (string) ($claim->supplier->name ?? ''),
                        ];
                        break;
                    }
                }
            });

        return $matches;
    }

    /**
     * @return array<int, array<int, string>>|null
     */
    private function loadComparisonCells(string $sku, ComparisonSheetService $sheetService, ComparisonSheetStorage $sheetStorage): ?array
    {
        $filePayload = $sheetStorage->load($sku);
        $fileCells = is_array($filePayload) && ! empty($filePayload['cells']) && is_array($filePayload['cells'])
            ? ComparisonData::normalizeCells($filePayload['cells'])
            : null;

        $record = ComparisonData::query()
            ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper($sku)])
            ->first();

        $dbCells = is_array($record?->sheet_data['cells'] ?? null)
            ? ComparisonData::normalizeCells($record->sheet_data['cells'])
            : null;

        $cells = $fileCells ?? $dbCells;
        if ($cells === null) {
            return null;
        }

        $cells = $sheetService->ensureLeadColumns($cells);

        return ComparisonData::normalizeCells($cells);
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function resolveSupplierColumn(
        array $cells,
        ComparisonSheetService $sheetService,
        int $specCol,
        int $firstSupplierCol,
        string $supplierName
    ): ?int {
        $maxCols = 0;
        foreach ($cells as $row) {
            if (is_array($row)) {
                $maxCols = max($maxCols, count($row));
            }
        }

        if ($firstSupplierCol >= $maxCols) {
            return null;
        }

        $target = strtoupper(trim($supplierName));
        if ($target === '') {
            return $firstSupplierCol;
        }

        $supplierRow = null;
        foreach ($cells as $rowIndex => $row) {
            if ($sheetService->isSupplierNameRow($cells, (int) $rowIndex, $specCol)) {
                $supplierRow = (int) $rowIndex;
                break;
            }
        }

        if ($supplierRow !== null) {
            $row = $cells[$supplierRow] ?? [];
            for ($col = $firstSupplierCol; $col < $maxCols; $col++) {
                $name = strtoupper(trim((string) ($row[$col] ?? '')));
                if ($name === '') {
                    continue;
                }
                if ($name === $target || str_contains($name, $target) || str_contains($target, $name)) {
                    return $col;
                }
            }
        }

        return $firstSupplierCol;
    }

    /**
     * @param  array<int, array<int, string>>  $cells
     */
    private function supplierNameAtColumn(
        array $cells,
        ComparisonSheetService $sheetService,
        int $specCol,
        int $col
    ): string {
        foreach ($cells as $rowIndex => $row) {
            if (! $sheetService->isSupplierNameRow($cells, (int) $rowIndex, $specCol)) {
                continue;
            }

            return trim((string) (($row[$col] ?? '')));
        }

        return '';
    }

    private function cellDisplayValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        // Hide heavy image payloads in the QC modal.
        if (str_starts_with(strtolower($text), 'data:image/')
            || str_starts_with($text, '[[photo:')
            || str_contains(strtolower($text), 'base64,')) {
            return '[Photo]';
        }

        if (mb_strlen($text) > 200) {
            return mb_substr($text, 0, 200) . '…';
        }

        return $text;
    }

    /**
     * @return array{sku: string, supplier_name: string, image_src: string|null}
     */
    private function productHeaderMeta(string $sku, int $arrivedId, string $supplierName): array
    {
        $sku = trim($sku);
        $supplier = trim($supplierName);
        $imageSrc = null;

        if ($arrivedId > 0) {
            $arrived = ArrivedContainer::query()->find($arrivedId);
            if ($arrived) {
                if ($supplier === '') {
                    $supplier = trim((string) ($arrived->supplier_name ?? ''));
                }
                $photos = trim((string) ($arrived->photos ?? ''));
                $img = trim((string) ($arrived->image_src ?? ''));
                if ($photos !== '') {
                    $imageSrc = $photos;
                } elseif ($img !== '') {
                    $imageSrc = $img;
                }
            }
        }

        $skuKey = strtoupper(preg_replace('/\s+/', ' ', $sku) ?? $sku);

        if ($imageSrc === null || $imageSrc === '') {
            $shopifyImage = ShopifySku::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuKey])
                ->value('image_src');
            $imageSrc = $shopifyImage ? trim((string) $shopifyImage) : null;
        }

        if ($imageSrc === null || $imageSrc === '') {
            $values = ProductMaster::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuKey])
                ->value('Values');
            if (is_string($values)) {
                $decoded = json_decode($values, true);
                $values = is_array($decoded) ? $decoded : null;
            }
            if (is_array($values)) {
                $path = trim((string) ($values['image_path'] ?? ''));
                if ($path !== '') {
                    $imageSrc = '/storage/' . ltrim(preg_replace('#^storage/#', '', $path) ?? $path, '/');
                }
            }
        }

        return [
            'sku' => $sku,
            'supplier_name' => $supplier,
            'image_src' => $imageSrc !== '' ? $imageSrc : null,
        ];
    }

    private function normalizeQcPriority(mixed $value): string
    {
        $text = strtolower(trim((string) $value));
        if ($text === 'critical') {
            return 'Critical';
        }
        if ($text === 'important') {
            return 'Important';
        }
        if ($text === 'normal') {
            return 'Normal';
        }

        return '';
    }
}
