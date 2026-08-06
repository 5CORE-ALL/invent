<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ToOrderAnalysisController;
use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Models\SupplierAdvance;
use App\Models\SupplierBankAccount;
use App\Models\ProductMaster;
use App\Models\PurchaseOrder;
use App\Models\ClaimReimbursement;
use App\Models\ShortTitle;
use App\Models\InstructionsItemPkg;
use App\Models\QcImprovementReqBeforeItemPkg;
use App\Services\ComparisonSpecTechService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Picqer\Barcode\BarcodeGeneratorPNG;

class PurchaseOrderController extends Controller
{
    /**
     * PO PDF "Approved BY" buttons (key => display label + name/email match needles).
     * All 6: Joy, Candy, Amarjit, Sruti, Hritiksha, Ritu — only the matching user may toggle.
     *
     * @return array<string, array{label: string, match: list<string>}>
     */
    private function poApprovalDefinitions(): array
    {
        return [
            'joy' => [
                'label' => 'Joy',
                'match' => ['joy huang', 'sourcing@5core.com'],
            ],
            'candy' => [
                'label' => 'Candy',
                'match' => ['candy', 'purchase@5core.com'],
            ],
            'amarjit' => [
                'label' => 'Amarjit',
                'match' => ['amarjit', 'president@5core.com'],
            ],
            'sruti' => [
                'label' => 'Sruti',
                'match' => ['sruti', 'sruthi', 'sourcing1@5core.com'],
            ],
            'hritiksha' => [
                'label' => 'Hritiksha',
                'match' => ['hritiksha', 'mgr-operations@5core.com'],
            ],
            'ritu' => [
                'label' => 'Ritu',
                'match' => ['ritu', 'inventory@5core.com', 'ritu.kaur013@gmail.com'],
            ],
        ];
    }

    private function currentUserMatchesApproval(array $def): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $hay = mb_strtolower(trim((string) ($user->name ?? '')).' '.trim((string) ($user->email ?? '')));
        foreach ($def['match'] ?? [] as $needle) {
            $needle = mb_strtolower(trim((string) $needle));
            if ($needle !== '' && str_contains($hay, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<int, array<string, mixed>>
     */
    private function buildPoApprovalButtons(?array $stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        $buttons = [];
        foreach ($this->poApprovalDefinitions() as $key => $def) {
            $row = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $approved = ! empty($row['approved']);
            $canToggle = $this->currentUserMatchesApproval($def);
            $buttons[] = [
                'key' => $key,
                'label' => $def['label'],
                'approved' => $approved,
                'approved_at' => $row['approved_at'] ?? null,
                'approved_by' => $row['approved_by'] ?? null,
                'can_toggle' => $canToggle,
            ];
        }

        return $buttons;
    }

    public function index()
    {
        $poNumber = $this->generateOrderNumber();
        $suppliers = Supplier::select('id', 'name')->get();
        $orders = PurchaseOrder::with('supplier')->latest()->get();
        return view('purchase-master.purchase-order.purchase-order',compact('suppliers','orders','poNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'po_number' => 'required|string|unique:purchase_orders,po_number',
            'supplier' => 'required|exists:suppliers,id',
        ]);

        $poNumber = $request->po_number;
        $supplierId = $request->supplier;
        $advanceAmt = $request->filled('advance_amount') ? $request->advance_amount : 0;
        // Always autogenerate PO date on create (today).
        $today = now()->toDateString();

        // Product Details removed from Create modal — start with empty items.
        PurchaseOrder::create([
            'po_number' => $poNumber,
            'supplier_id' => $supplierId,
            'po_date' => $today,
            'items' => json_encode([]),
            'advance_amount' => $advanceAmt,
            'total_amount' => 0,
        ]);

        return redirect()->back()->with('flash_message', 'Purchase Contract created successfully.');
    }

    public function showPurchaseOrders($id)
    {
        $po = PurchaseOrder::with('supplier')->findOrFail($id);
        $items = json_decode($po->items, true) ?? [];
        $techBySku = app(ComparisonSpecTechService::class)->techBySkus(
            collect($items)->pluck('sku')->all()
        );
        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? ''));
            if (trim((string) ($item['tech'] ?? '')) === '' && !empty($techBySku[$sku])) {
                $item['tech'] = $techBySku[$sku];
            }
        }
        unset($item);

        return response()->json([
            'id' => $po->id,
            'po_number' => $po->po_number,
            'supplier_id' => $po->supplier_id,
            'advance_amount' => $po->advance_amount ?? 0,
            'po_date' => $po->po_date,
            'items' => $items,
        ]);
    }

    /**
     * Update a single proforma line: Supplier SKU, Short Name, Tech, NW, GW, CBM, QTY, prices.
     */
    public function updateItemSupplierSku(Request $request, $id)
    {
        $normalized = $request->all();
        foreach (['supplier_sku', 'short_name', 'tech', 'item_pkg', 'ctn_pkg', 'nw', 'gw', 'cbm', 'qty', 'price_usd', 'price_rmb', 'currency'] as $key) {
            if (array_key_exists($key, $normalized) && $normalized[$key] === '') {
                $normalized[$key] = null;
            }
        }
        $request->merge($normalized);

        $validated = $request->validate([
            'item_index' => 'required|integer|min:0',
            'supplier_sku' => 'nullable|string|max:255',
            'short_name' => 'nullable|string|max:5000',
            'tech' => 'nullable|string|max:5000',
            'item_pkg' => 'nullable|string',
            'ctn_pkg' => 'nullable|string|max:100',
            'nw' => 'nullable|numeric',
            'gw' => 'nullable|numeric',
            'cbm' => 'nullable|numeric',
            'qty' => 'nullable|numeric|min:0',
            'price_usd' => 'nullable|numeric|min:0',
            'price_rmb' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:USD,RMB',
        ]);

        $po = PurchaseOrder::findOrFail($id);
        $items = json_decode($po->items ?? '[]', true);
        if (!is_array($items)) {
            $items = [];
        }

        $index = (int) $validated['item_index'];
        if (!array_key_exists($index, $items) || !is_array($items[$index])) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        $item = $items[$index];
        $sku = trim((string) ($item['sku'] ?? ''));

        if (array_key_exists('supplier_sku', $validated)) {
            $item['supplier_sku'] = trim((string) ($validated['supplier_sku'] ?? ''));
        }
        if (array_key_exists('tech', $validated)) {
            $item['tech'] = $this->normalizeTechText($validated['tech'] ?? '');
        }
        if (array_key_exists('nw', $validated)) {
            $item['nw'] = $validated['nw'];
        }
        if (array_key_exists('gw', $validated)) {
            $item['gw'] = $validated['gw'];
        }
        if (array_key_exists('cbm', $validated)) {
            $item['cbm'] = $validated['cbm'];
        }
        if (array_key_exists('qty', $validated)) {
            $item['qty'] = $validated['qty'];
        }

        // Price source rules:
        // - Entered in RMB → store as RMB (proforma converts to USD and shows both).
        // - Entered in USD only → store as USD (no RMB autopopulate).
        $priceUsd = $validated['price_usd'] ?? null;
        $priceRmb = $validated['price_rmb'] ?? null;
        if ($priceRmb !== null) {
            $item['currency'] = 'RMB';
            $item['price'] = $priceRmb;
        } elseif ($priceUsd !== null) {
            $item['currency'] = 'USD';
            $item['price'] = $priceUsd;
        }

        $items[$index] = $item;

        // Persist Short Name to short_titles (by 5 Core SKU).
        $shortName = array_key_exists('short_name', $validated)
            ? trim((string) ($validated['short_name'] ?? ''))
            : null;
        if ($sku !== '' && $shortName !== null) {
            try {
                ShortTitle::updateOrCreate(
                    ['sku' => $sku],
                    ['short_title' => $shortName]
                );
                // Also keep a copy on the PO line for display fallback.
                $item['short_name'] = $shortName;
                $items[$index] = $item;
            } catch (\Throwable $e) {
                Log::error('PO short name save failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save Short Name: '.$e->getMessage(),
                ], 500);
            }
        }

        // Persist Item Pkg / Ctn Pkg to Dim Wt Master sources.
        $savedItemPkg = null;
        $savedCtnPkg = null;
        if (array_key_exists('item_pkg', $validated) || array_key_exists('ctn_pkg', $validated)) {
            $pkgResult = $this->saveDimWtPkgForSku(
                $sku,
                array_key_exists('item_pkg', $validated) ? (string) ($validated['item_pkg'] ?? '') : null,
                array_key_exists('ctn_pkg', $validated) ? (string) ($validated['ctn_pkg'] ?? '') : null
            );
            if (!$pkgResult['success']) {
                return response()->json($pkgResult, 422);
            }
            $savedItemPkg = $pkgResult['item_pkg'] ?? null;
            $savedCtnPkg = $pkgResult['ctn_pkg'] ?? null;
        }

        $totalAmount = 0.0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $totalAmount += ((float) ($row['qty'] ?? 0)) * ((float) ($row['price'] ?? 0));
        }

        $po->items = json_encode(array_values($items));
        $po->total_amount = $totalAmount;
        $po->save();

        return response()->json([
            'success' => true,
            'message' => 'Line item saved.',
            'item_index' => $index,
            'item' => [
                'supplier_sku' => $item['supplier_sku'] ?? '',
                'short_name' => $shortName ?? '',
                'tech' => $item['tech'] ?? '',
                'item_pkg' => $savedItemPkg,
                'ctn_pkg' => $savedCtnPkg,
                'nw' => $item['nw'] ?? '',
                'gw' => $item['gw'] ?? '',
                'cbm' => $item['cbm'] ?? '',
                'qty' => $item['qty'] ?? '',
                'price' => $item['price'] ?? '',
                'currency' => $item['currency'] ?? 'USD',
            ],
        ]);
    }

    /**
     * Append a new line item to a purchase order (proforma Add Row).
     */
    public function addItem(Request $request, $id)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'supplier_sku' => 'nullable|string|max:255',
            'short_name' => 'nullable|string|max:5000',
            'tech' => 'nullable|string|max:5000',
            'nw' => 'nullable|numeric',
            'gw' => 'nullable|numeric',
            'cbm' => 'nullable|numeric',
            'qty' => 'nullable|numeric|min:0',
            'price_usd' => 'nullable|numeric|min:0',
            'price_rmb' => 'nullable|numeric|min:0',
            'item_pkg' => 'nullable|string',
            'ctn_pkg' => 'nullable|string|max:100',
            'item_pkg_cover' => 'nullable|string|max:2048',
            'design_file' => 'nullable|string|max:2048',
            'ctn_qty' => 'nullable|string|max:100',
            'ctn_print_file' => 'nullable|string|max:2048',
            'special_instruction_qc' => 'nullable|string|max:10000',
        ]);

        $sku = trim((string) $validated['sku']);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        $po = PurchaseOrder::findOrFail($id);
        $items = json_decode($po->items ?? '[]', true);
        if (!is_array($items)) {
            $items = [];
        }

        $priceUsd = $validated['price_usd'] ?? null;
        $priceRmb = $validated['price_rmb'] ?? null;
        if ($priceRmb !== null && $priceRmb !== '') {
            $currency = 'RMB';
            $price = (float) $priceRmb;
        } else {
            $currency = 'USD';
            $price = ($priceUsd !== null && $priceUsd !== '') ? (float) $priceUsd : 0;
        }

        $qty = isset($validated['qty']) && $validated['qty'] !== null && $validated['qty'] !== ''
            ? (float) $validated['qty']
            : 0;

        $shortName = trim((string) ($validated['short_name'] ?? ''));
        // Auto-fill Short name from title-master / short_titles when left blank.
        if ($shortName === '') {
            $fromMaster = $this->shortNamesBySku([$sku]);
            $shortName = $fromMaster[$sku] ?? '';
        }

        $item = [
            'sku' => $sku,
            'supplier_sku' => trim((string) ($validated['supplier_sku'] ?? '')),
            'short_name' => $shortName,
            'tech' => $this->normalizeTechText($validated['tech'] ?? ''),
            'nw' => $validated['nw'] ?? null,
            'gw' => $validated['gw'] ?? null,
            'cbm' => $validated['cbm'] ?? null,
            'qty' => $qty,
            'price' => $price,
            'currency' => $currency,
            'amount' => $qty * $price,
        ];

        if ($shortName !== '' && trim((string) ($validated['short_name'] ?? '')) !== '') {
            try {
                ShortTitle::updateOrCreate(
                    ['sku' => $sku],
                    ['short_title' => $shortName]
                );
            } catch (\Throwable $e) {
                Log::error('PO add-item short name save failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $items[] = $item;

        $hasPkg = array_key_exists('item_pkg', $validated) || array_key_exists('ctn_pkg', $validated);
        if ($hasPkg && (
            trim((string) ($validated['item_pkg'] ?? '')) !== ''
            || trim((string) ($validated['ctn_pkg'] ?? '')) !== ''
        )) {
            $pkgResult = $this->saveDimWtPkgForSku(
                $sku,
                array_key_exists('item_pkg', $validated) ? (string) ($validated['item_pkg'] ?? '') : null,
                array_key_exists('ctn_pkg', $validated) ? (string) ($validated['ctn_pkg'] ?? '') : null
            );
            if (!$pkgResult['success']) {
                // Soft-fail packaging: still add the PO row; surface warning in message.
                Log::warning('PO add-item pkg save skipped', [
                    'sku' => $sku,
                    'message' => $pkgResult['message'] ?? '',
                ]);
            }
        }

        $product = ProductMaster::query()
            ->where('sku', $sku)
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->first();

        if ($product) {
            $values = $this->productValuesArray($product);
            $valuesDirty = false;

            $normalizePath = static function (string $path): string {
                if (preg_match('/^https?:\/\//i', $path) || str_starts_with($path, 'data:')) {
                    return $path;
                }

                return ltrim($path, '/');
            };

            // Only write non-empty packaging fields so blank add-row inputs do not wipe master data.
            $cover = trim((string) ($validated['item_pkg_cover'] ?? ''));
            if ($cover !== '') {
                $values['item_pkg_cover'] = $normalizePath($cover);
                $valuesDirty = true;
            }
            $design = trim((string) ($validated['design_file'] ?? ''));
            if ($design !== '') {
                $values['packing_cdr_path'] = $normalizePath($design);
                $valuesDirty = true;
            }
            $ctnPrint = trim((string) ($validated['ctn_print_file'] ?? ''));
            if ($ctnPrint !== '') {
                $values['ctn_print_file'] = $normalizePath($ctnPrint);
                $valuesDirty = true;
            }
            $ctnQty = trim((string) ($validated['ctn_qty'] ?? ''));
            if ($ctnQty !== '') {
                $values['ctn_qty'] = $ctnQty;
                $valuesDirty = true;
            }
            if ($valuesDirty) {
                $product->Values = $values;
                $product->save();
            }

            $qcText = trim((string) ($validated['special_instruction_qc'] ?? ''));
            if ($qcText !== '') {
                QcImprovementReqBeforeItemPkg::updateOrCreate(
                    ['product_master_id' => $product->id],
                    ['qc_improvement_req' => $qcText]
                );
            }
        }

        $totalAmount = 0.0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $totalAmount += ((float) ($row['qty'] ?? 0)) * ((float) ($row['price'] ?? 0));
        }

        $po->items = json_encode(array_values($items));
        $po->total_amount = $totalAmount;
        $po->save();

        return response()->json([
            'success' => true,
            'message' => 'Row added.',
            'item_index' => count($items) - 1,
        ]);
    }

    /**
     * Delete a single proforma line item by index.
     */
    public function deleteItem(Request $request, $id)
    {
        $validated = $request->validate([
            'item_index' => 'required|integer|min:0',
        ]);

        $po = PurchaseOrder::findOrFail($id);
        $items = json_decode($po->items ?? '[]', true);
        if (!is_array($items)) {
            $items = [];
        }

        $index = (int) $validated['item_index'];
        if (!array_key_exists($index, $items)) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        unset($items[$index]);
        $items = array_values($items);

        $totalAmount = 0.0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $totalAmount += ((float) ($row['qty'] ?? 0)) * ((float) ($row['price'] ?? 0));
        }

        $po->items = json_encode($items);
        $po->total_amount = $totalAmount;
        $po->save();

        return response()->json([
            'success' => true,
            'message' => 'Row deleted.',
            'remaining' => count($items),
        ]);
    }

    /**
     * Bulk-add SKUs visible on the Order page (to-order-analysis) for this PO's supplier.
     * Same cohort as the Order page grid: Forecast "Order" column > 0 + supplier match.
     * PO line qty = Order column qty (not MOQ-only rows from to_order_analysis).
     */
    public function addItemsFromToOrder(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);
        $supplierId = (int) ($po->supplier_id ?? 0);
        if ($supplierId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This purchase contract has no supplier.',
            ], 422);
        }

        $supplier = DB::table('suppliers')->where('id', $supplierId)->first();
        $supplierName = trim((string) ($supplier->name ?? ''));
        if ($supplierName === '') {
            return response()->json([
                'success' => false,
                'message' => 'Supplier name not found.',
            ], 422);
        }

        $orderRows = app(ToOrderAnalysisController::class)->orderPageRowsForSupplier($supplierName);
        if ($orderRows === []) {
            return response()->json([
                'success' => false,
                'message' => 'No Order-page SKUs (Order qty > 0) for supplier "'.$supplierName.'".',
            ], 404);
        }

        $norm = static function ($s): string {
            return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', (string) $s)) ?? '');
        };

        $orderQtyBySku = [];
        foreach ($orderRows as $row) {
            $sku = $norm($row['sku'] ?? '');
            $qty = (float) ($row['order_qty'] ?? 0);
            if ($sku === '' || ! is_finite($qty) || $qty <= 0) {
                continue;
            }
            $orderQtyBySku[$sku] = $qty;
        }

        $skus = array_keys($orderQtyBySku);
        if ($skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'No Order-page SKUs (Order qty > 0) for supplier "'.$supplierName.'".',
            ], 404);
        }

        $shortBySku = $this->shortNamesBySku($skus);
        $usdToCny = $this->usdToCnyRate();
        $rmbToUsd = $usdToCny > 0 ? (1 / $usdToCny) : (1 / 7.2);
        $cmpPrices = app(ComparisonSpecTechService::class)
            ->pricesForRelevantSupplierBySkus($skus, $supplierName, $rmbToUsd);

        $items = json_decode($po->items ?? '[]', true);
        if (!is_array($items)) {
            $items = [];
        }

        $existingSkuKeys = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = mb_strtolower($norm($row['sku'] ?? ''));
            if ($key !== '') {
                $existingSkuKeys[$key] = true;
            }
        }

        $added = 0;
        $skippedExisting = 0;
        $skippedNoOrderQty = 0;
        $pricedFromComparison = 0;

        foreach ($skus as $sku) {
            $key = mb_strtolower($sku);
            if (isset($existingSkuKeys[$key])) {
                $skippedExisting++;
                continue;
            }

            $orderQty = $orderQtyBySku[$sku] ?? null;
            if ($orderQty === null || $orderQty <= 0) {
                $skippedNoOrderQty++;
                continue;
            }

            $shortName = $shortBySku[$sku] ?? '';
            $cmp = $cmpPrices[$sku] ?? null;
            $price = 0.0;
            $rateNotLowest = false;
            if (is_array($cmp) && ! empty($cmp['found']) && isset($cmp['price_usd']) && (float) $cmp['price_usd'] > 0) {
                $price = round((float) $cmp['price_usd'], 2);
                $rateNotLowest = empty($cmp['is_lowest']);
                $pricedFromComparison++;
            }

            $items[] = [
                'sku' => $sku,
                'supplier_sku' => '',
                'short_name' => $shortName,
                'tech' => '',
                'nw' => null,
                'gw' => null,
                'cbm' => null,
                'qty' => $orderQty,
                'price' => $price,
                'currency' => 'USD',
                'amount' => round($price * (float) $orderQty, 2),
                'rate_not_lowest' => $rateNotLowest,
                'comparison_price_source' => is_array($cmp) ? ($cmp['source'] ?? null) : null,
            ];
            $existingSkuKeys[$key] = true;
            $added++;
        }

        if ($added === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No new SKUs added. Existing: '.$skippedExisting.', Order qty ≤ 0: '.$skippedNoOrderQty.'.',
                'added' => 0,
                'skipped_existing' => $skippedExisting,
                'skipped_no_order_qty' => $skippedNoOrderQty,
            ], 422);
        }

        $totalAmount = 0.0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $totalAmount += ((float) ($row['qty'] ?? 0)) * ((float) ($row['price'] ?? 0));
        }

        $po->items = json_encode(array_values($items));
        $po->total_amount = $totalAmount;
        $po->save();

        return response()->json([
            'success' => true,
            'message' => "Added {$added} SKU(s) from Order page for {$supplierName}"
                .($pricedFromComparison > 0 ? " ({$pricedFromComparison} priced from comparison sheet)." : '.')
                ." Skipped existing: {$skippedExisting}.",
            'added' => $added,
            'skipped_existing' => $skippedExisting,
            'skipped_no_order_qty' => $skippedNoOrderQty,
            'priced_from_comparison' => $pricedFromComparison,
            'supplier' => $supplierName,
            'matched' => count($skus),
        ]);
    }

    /**
     * Save / replace Itm pkg Cover on product_master.Values.item_pkg_cover.
     * Accepts image URL/path text, or optional file upload.
     */
    public function saveItemPkgCover(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:2048',
            'url' => 'nullable|string|max:2048',
            'image' => 'nullable|file|image|max:10240',
        ]);

        $product = ProductMaster::find($validated['product_id']);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $hasFile = $request->hasFile('image');
        $pathInput = trim((string) ($validated['path'] ?? $validated['url'] ?? ''));
        if (!$hasFile && !$request->exists('path') && !$request->exists('url')) {
            return response()->json([
                'success' => false,
                'message' => 'Cover path/URL is required.',
            ], 422);
        }

        try {
            $publicPath = '';
            $displayName = null;

            if ($hasFile) {
                $safeSku = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string) $product->sku)) ?: ('id_'.$product->id);
                $stored = $request->file('image')->storeAs(
                    'packing_instruction_images/covers',
                    $product->id.'_'.$safeSku.'_cover.'.$request->file('image')->getClientOriginalExtension(),
                    'public'
                );
                $publicPath = 'storage/'.$stored;
                $displayName = $request->file('image')->getClientOriginalName();
            } else {
                // Store relative paths without leading slash; keep absolute http(s)/data URLs as-is.
                if ($pathInput === '') {
                    $publicPath = '';
                } elseif (preg_match('/^https?:\/\//i', $pathInput) || str_starts_with($pathInput, 'data:')) {
                    $publicPath = $pathInput;
                } else {
                    $publicPath = ltrim($pathInput, '/');
                }
                $displayName = basename(parse_url($publicPath, PHP_URL_PATH) ?: $publicPath) ?: 'cover';
            }

            $values = $this->productValuesArray($product);
            if ($publicPath === '') {
                unset($values['item_pkg_cover']);
            } else {
                $values['item_pkg_cover'] = $publicPath;

                // Keep Packing Instructions photos in sync: put cover first in packing_images.
                $list = [];
                $raw = $values['packing_images'] ?? [];
                if (is_array($raw)) {
                    foreach ($raw as $item) {
                        if (is_string($item) && trim($item) !== '') {
                            $list[] = ['path' => trim($item)];
                        } elseif (is_array($item) && !empty($item['path'])) {
                            $list[] = $item;
                        }
                    }
                }
                array_unshift($list, [
                    'id' => 'cover-'.$product->id,
                    'path' => $publicPath,
                    'name' => $displayName,
                    'uploaded_at' => now()->toIso8601String(),
                ]);
                // Dedupe by path
                $seen = [];
                $deduped = [];
                foreach ($list as $img) {
                    $p = (string) ($img['path'] ?? '');
                    if ($p === '' || isset($seen[$p])) {
                        continue;
                    }
                    $seen[$p] = true;
                    $deduped[] = $img;
                }
                $values['packing_images'] = $deduped;
            }

            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Itm pkg Cover saved.',
                'url' => $publicPath === '' ? '' : $this->normalizeImageUrl($publicPath),
                'path' => $publicPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('PO item pkg cover save failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save cover: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save Design File path on product_master.Values.packing_cdr_path
     * (same field as Packing Instructions → CDR / design file).
     */
    public function saveDesignFile(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:2048',
            'url' => 'nullable|string|max:2048',
        ]);

        $product = ProductMaster::find($validated['product_id']);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        try {
            $pathInput = trim((string) ($validated['path'] ?? $validated['url'] ?? ''));
            if ($pathInput === '') {
                $publicPath = '';
            } elseif (preg_match('/^https?:\/\//i', $pathInput) || str_starts_with($pathInput, 'data:')) {
                $publicPath = $pathInput;
            } else {
                $publicPath = ltrim($pathInput, '/');
            }

            $values = $this->productValuesArray($product);
            if ($publicPath === '') {
                unset($values['packing_cdr_path']);
            } else {
                $values['packing_cdr_path'] = $publicPath;
            }
            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Design File saved.',
                'url' => $publicPath === '' ? '' : $this->normalizeImageUrl($publicPath),
                'path' => $publicPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('PO design file save failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save Design File: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save Ctn Print File on product_master.Values.ctn_print_file (path or upload).
     */
    public function saveCtnPrintFile(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:2048',
            'url' => 'nullable|string|max:2048',
            'file' => 'nullable|file|max:51200',
        ]);

        $product = ProductMaster::find($validated['product_id']);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        try {
            $publicPath = '';
            if ($request->hasFile('file')) {
                $safeSku = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string) $product->sku)) ?: ('id_'.$product->id);
                $ext = $request->file('file')->getClientOriginalExtension() ?: 'bin';
                $stored = $request->file('file')->storeAs(
                    'packing_instruction_ctn_print',
                    $product->id.'_'.$safeSku.'_ctn_print.'.$ext,
                    'public'
                );
                $publicPath = 'storage/'.$stored;
            } else {
                $pathInput = trim((string) ($validated['path'] ?? $validated['url'] ?? ''));
                if ($pathInput === '') {
                    $publicPath = '';
                } elseif (preg_match('/^https?:\/\//i', $pathInput) || str_starts_with($pathInput, 'data:')) {
                    $publicPath = $pathInput;
                } else {
                    $publicPath = ltrim($pathInput, '/');
                }
            }

            $values = $this->productValuesArray($product);
            if ($publicPath === '') {
                unset($values['ctn_print_file']);
            } else {
                $values['ctn_print_file'] = $publicPath;
            }
            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Ctn Print File saved.',
                'url' => $publicPath === '' ? '' : $this->normalizeImageUrl($publicPath),
                'path' => $publicPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('PO ctn print file save failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save Ctn Print File: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cover image for Itm pkg: Values.item_pkg_cover, else first packing_images entry.
     */
    private function resolveItemPkgCoverUrl(array $values): ?string
    {
        $direct = trim((string) ($values['item_pkg_cover'] ?? ''));
        if ($direct !== '') {
            return $this->normalizeImageUrl($direct);
        }

        $raw = $values['packing_images'] ?? [];
        if (!is_array($raw) || $raw === []) {
            return null;
        }

        $first = $raw[0] ?? null;
        if (is_string($first) && trim($first) !== '') {
            return $this->normalizeImageUrl($first);
        }
        if (is_array($first) && !empty($first['path'])) {
            return $this->normalizeImageUrl((string) $first['path']);
        }

        return null;
    }

    /**
     * Save Item Pkg / Ctn Pkg using the same Dim Wt Master data sources.
     *
     * @return array{success: bool, message?: string, item_pkg?: string, ctn_pkg?: string}
     */
    private function saveDimWtPkgForSku(string $sku, ?string $itemPkg, ?string $ctnPkg): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required to save Item/Ctn Pkg.'];
        }

        $resolved = $this->dimWtPkgBySku([$sku]);
        $productId = $resolved[$sku]['product_id'] ?? null;
        $matchedSku = $resolved[$sku]['matched_sku'] ?? $sku;

        if (!$productId) {
            $product = ProductMaster::query()
                ->where('sku', $sku)
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first(['id', 'sku']);
            if (!$product) {
                return [
                    'success' => false,
                    'message' => 'Product not found in Dim Wt Master for SKU: '.$sku,
                ];
            }
            $productId = $product->id;
            $matchedSku = trim((string) $product->sku);
        }

        $product = ProductMaster::find($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found in Dim Wt Master.'];
        }

        $outItem = null;
        $outCtn = null;

        if ($itemPkg !== null) {
            $text = trim($itemPkg);
            if ($text === '') {
                InstructionsItemPkg::where('product_master_id', $product->id)->delete();
                $outItem = '';
            } else {
                $row = InstructionsItemPkg::updateOrCreate(
                    ['product_master_id' => $product->id],
                    ['instructions' => $text]
                );
                $outItem = (string) $row->instructions;
            }
        }

        if ($ctnPkg !== null) {
            $values = $this->productValuesArray($product);
            $raw = trim($ctnPkg);
            if ($raw === '') {
                unset($values['ctn_instructions']);
                $outCtn = '';
            } else {
                $values['ctn_instructions'] = mb_substr($raw, 0, 100);
                $outCtn = $values['ctn_instructions'];
            }
            $product->Values = $values;
            $product->save();
        }

        return [
            'success' => true,
            'message' => 'Dim Wt pkg saved.',
            'item_pkg' => $outItem,
            'ctn_pkg' => $outCtn,
            'matched_sku' => $matchedSku,
        ];
    }

    public function updatePurchaseOrder(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        $request->validate([
            'supplier' => 'required|exists:suppliers,id',
        ]);

        $po->supplier_id = $request->supplier;
        $po->po_date = $request->po_date;
        // Advance Amount + Product Details removed from Edit modal — keep existing values.
        $po->save();

        return redirect()->back()->with('success', 'Purchase Order updated successfully!');
    }

    public function getPurchaseOrdersData(Request $request)
    {
        $filter = $request->query('filter', 'active'); // active | archived | all

        $select = ['id', 'po_number', 'po_date', 'supplier_id', 'items', 'advance_amount', 'total_amount', 'is_archived'];
        if (Schema::hasColumn('purchase_orders', 'advance_percent')) {
            $select[] = 'advance_percent';
        }

        $query = PurchaseOrder::select($select)
            ->with('supplier:id,name');

        if ($filter === 'active') {
            $query->where(function ($q) {
                $q->where('is_archived', false)->orWhereNull('is_archived');
            });
        } elseif ($filter === 'archived') {
            $query->where('is_archived', true);
        }

        $orders = $query->orderByDesc('po_date')->orderByDesc('id')->get();

        $allSkus = [];
        foreach ($orders as $order) {
            foreach (json_decode($order->items ?? '[]') ?: [] as $item) {
                $sku = trim((string) ($item->sku ?? ''));
                if ($sku !== '') {
                    $allSkus[$sku] = true;
                }
            }
        }
        $imageBySku = $this->cpMasterImagesBySku(array_keys($allSkus));
        $techBySku = app(ComparisonSpecTechService::class)->techBySkus(array_keys($allSkus));
        $usdToCny = $this->usdToCnyRate();

        $orders = $orders->map(function ($order) use ($imageBySku, $techBySku, $usdToCny) {
            $items = collect(json_decode($order->items ?? '[]') ?: []);
            $firstItem = $items->first();

            // Summary tags from PO line SKUs (display-only; no DB backfill).
            $skus = $items->map(function ($item) {
                return trim((string) ($item->sku ?? ''));
            })->filter()->values()->all();
            $skuList = implode(', ', $skus);

            // Same as proforma: Grand Total (USD preferred), Advance, Balance Due, CBM Total (qty × cbm).
            $totals = $this->computePoListTotalsFromItems($items, $usdToCny);
            $totalAmount = $totals['grand_total'];
            $totalCbm = $totals['cbm_total'];

            $advancePercent = null;
            if (isset($order->advance_percent) && $order->advance_percent !== null && $order->advance_percent !== '') {
                $advancePercent = (float) $order->advance_percent;
            }
            if ($advancePercent !== null && $totalAmount > 0) {
                $advance = (float) round($totalAmount * ($advancePercent / 100));
            } else {
                $advance = (float) round((float) ($order->advance_amount ?? 0));
            }
            $balance = (float) round($totalAmount - $advance);

            $enrichedItems = $items->map(function ($item) use ($imageBySku, $techBySku) {
                $arr = (array) $item;
                $sku = trim((string) ($arr['sku'] ?? ''));
                $arr['photo_url'] = $imageBySku[$sku] ?? $this->fallbackPoPhotoUrl($arr['photo'] ?? null);
                if (trim((string) ($arr['tech'] ?? '')) === '' && !empty($techBySku[$sku])) {
                    $arr['tech'] = $techBySku[$sku];
                }
                return $arr;
            })->values()->all();

            $firstSku = trim((string) ($firstItem->sku ?? ''));

            return [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'po_date' => $order->po_date,
                'supplier_name' => $order->supplier->name ?? '',
                'supplier_id' => $order->supplier_id ?? '',
                'advance_amount' => $advance,
                'advance_percent' => $advancePercent,
                'total_amount' => $totalAmount,
                'balance' => $balance,
                'total_cbm' => $totalCbm,
                'is_archived' => (bool) ($order->is_archived ?? false),
                'skus' => $skus,
                'sku_list' => $skuList,
                'photo' => $imageBySku[$firstSku] ?? ($firstItem->photo ?? ''),
                'items_json' => json_encode($enrichedItems),
            ];
        });

        return response()->json($orders);
    }

    /**
     * Compute list-page O Amount / CBM from PO items (matches proforma Grand Total + CBM Total).
     *
     * @param  \Illuminate\Support\Collection|array  $items
     * @return array{grand_total: float, cbm_total: float}
     */
    private function computePoListTotalsFromItems($items, ?float $usdToCny = null): array
    {
        $items = collect($items);
        $usdToCny = $usdToCny ?? $this->usdToCnyRate();

        $subtotalUsd = 0.0;
        $subtotalOther = 0.0;
        $hasUsd = false;
        $hasOther = false;
        $cbmTotal = 0.0;

        foreach ($items as $item) {
            $item = is_array($item) ? (object) $item : $item;
            $qty = (float) ($item->qty ?? $item->quantity ?? 0);
            $price = (float) ($item->price ?? 0);
            $cbm = (float) ($item->cbm ?? 0);
            $cbmTotal += $qty * $cbm;

            if ($qty <= 0 || $price <= 0) {
                continue;
            }

            $curr = strtoupper((string) ($item->currency ?? 'USD'));
            if ($curr === 'RMB' || $curr === 'CNY') {
                if ($usdToCny && $usdToCny > 0) {
                    $subtotalUsd += $qty * ($price / $usdToCny);
                    $hasUsd = true;
                } else {
                    $subtotalOther += $qty * $price;
                    $hasOther = true;
                }
            } else {
                $subtotalUsd += $qty * $price;
                $hasUsd = true;
            }
        }

        $grand = $hasUsd ? $subtotalUsd : ($hasOther ? $subtotalOther : 0.0);

        return [
            'grand_total' => (float) round($grand),
            'cbm_total' => round($cbmTotal, 2),
        ];
    }

    public function archivePurchaseOrder($id)
    {
        $po = PurchaseOrder::findOrFail($id);
        $po->update(['is_archived' => true]);
        return response()->json(['success' => true, 'message' => 'Purchase order archived.']);
    }

    public function restorePurchaseOrder($id)
    {
        $po = PurchaseOrder::findOrFail($id);
        $po->update(['is_archived' => false]);
        return response()->json(['success' => true, 'message' => 'Purchase order restored.']);
    }

    public function generatePdf($orderId){
        $order = DB::table('purchase_orders')->where('id', $orderId)->first();
        if (!$order) abort(404, 'Purchase Order not found');

        return $this->renderPurchaseOrderPdf($order);
    }

    public function generatePdfByPoNumber(string $poNumber)
    {
        $poNumber = trim(urldecode($poNumber));
        if ($poNumber === '') {
            abort(404, 'Purchase Order not found');
        }

        $order = DB::table('purchase_orders')->where('po_number', $poNumber)->first();
        if (!$order) {
            abort(404, 'Purchase Order not found');
        }

        return $this->renderPurchaseOrderPdf($order);
    }

    private function renderPurchaseOrderPdf(object $order)
    {
        $items = json_decode($order->items ?? '[]') ?: [];
        $supplier = DB::table('suppliers')->where('id', $order->supplier_id)->first();
        $normalizeSku = static function ($s): string {
            return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', (string) $s)) ?? '');
        };
        $skus = collect($items)->pluck('sku')->map($normalizeSku)->filter()->values()->all();
        $specTech = app(ComparisonSpecTechService::class);
        $techBySku = $specTech->techBySkus($skus);
        $weightsBySku = $specTech->weightsKgBySkus($skus);
        $cbmBySku = $specTech->cbmBySkus($skus);
        $shortNameBySku = $this->shortNamesBySku($skus);
        $pkgBySku = $this->dimWtPkgBySku($skus);

        $supplierName = trim((string) ($supplier->name ?? ''));
        $usdToCny = $this->usdToCnyRate();
        $rmbToUsd = $usdToCny > 0 ? (1 / $usdToCny) : (1 / 7.2);
        $cmpPricesBySku = $supplierName !== ''
            ? $specTech->pricesForRelevantSupplierBySkus($skus, $supplierName, $rmbToUsd)
            : [];

        foreach ($items as $item) {
            if (!is_object($item)) {
                continue;
            }
            $sku = $normalizeSku($item->sku ?? '');
            $item->tech = $this->normalizeTechText($item->tech ?? '');
            if ($item->tech === '' && !empty($techBySku[$sku])) {
                $item->tech = $this->normalizeTechText($techBySku[$sku]);
            }

            $hint = trim($item->tech.' '.((string) ($item->supplier_sku ?? '')));
            $product = $this->findProductMasterForPoSku($sku, $hint);

            $photoUrls = $product
                ? $this->resolveCpMasterImageCandidates($product)
                : [];
            $fallbackPhoto = $this->fallbackPoPhotoUrl($item->photo ?? null);
            if ($fallbackPhoto) {
                $photoUrls[] = $fallbackPhoto;
            }
            if ($photoUrls === [] && $sku !== '') {
                $shopifyPhoto = $this->shopifyImageForSku($sku);
                if ($shopifyPhoto) {
                    $photoUrls[] = $shopifyPhoto;
                }
            }
            $photoUrls = array_values(array_unique(array_filter($photoUrls)));
            $item->photo_url = $photoUrls[0] ?? null;
            $item->photo_fallback_url = $photoUrls[1] ?? null;

            $barcode = $product
                ? $this->resolveCpMasterBarcode($product)
                : ['image' => null, 'code' => null];
            $item->barcode_url = $barcode['image'] ?? null;
            $item->barcode_code = $barcode['code'] ?? null;
            // If Masters Barcode has a code but no saved image, generate bars for the proforma.
            if (empty($item->barcode_url) && !empty($item->barcode_code)) {
                $item->barcode_url = $this->barcodeDataUri((string) $item->barcode_code);
            }

            $matchedSku = $product ? $normalizeSku($product->sku) : $normalizeSku($pkgBySku[$sku]['matched_sku'] ?? $sku);
            if ($matchedSku !== '' && $matchedSku !== $sku && !isset($shortNameBySku[$matchedSku])) {
                $shortNameBySku = array_merge($shortNameBySku, $this->shortNamesBySku([$matchedSku]));
            }
            // Always prefer title-master Short name (short_titles) by SKU.
            $item->short_name = $shortNameBySku[$sku]
                ?? $shortNameBySku[$matchedSku]
                ?? trim((string) ($item->short_name ?? ''));

            $pkg = $pkgBySku[$sku] ?? null;
            if ((!$pkg || empty($pkg['product_id'])) && $product) {
                $pkg = $this->dimWtPkgBySku([$matchedSku])[$matchedSku] ?? $pkg;
            }
            $item->item_pkg = $pkg['item_pkg'] ?? '';
            $item->ctn_pkg = $pkg['ctn_pkg'] ?? '';
            $item->item_pkg_cover = $pkg['item_pkg_cover'] ?? null;
            $item->design_file = $pkg['design_file'] ?? null;
            $item->ctn_qty = $pkg['ctn_qty'] ?? null;
            $item->ctn_print_file = $pkg['ctn_print_file'] ?? null;
            $item->special_instruction_qc = $pkg['special_instruction_qc'] ?? '';
            $item->product_master_id = $pkg['product_id'] ?? ($product?->id);
            $item->product_master_sku = $matchedSku;

            // CP$ from product-master (Values.cp) for Rate vs CP indicator on proforma.
            $item->cp = null;
            if ($product) {
                $values = $this->productValuesArray($product);
                $cpRaw = $values['cp'] ?? $values['CP'] ?? $values['CP$'] ?? null;
                if ($cpRaw !== null && $cpRaw !== '' && is_numeric($cpRaw)) {
                    $cpNum = (float) $cpRaw;
                    if (is_finite($cpNum) && $cpNum >= 0) {
                        $item->cp = round($cpNum, 2);
                    }
                }
            }

            // Comparison sheet-view rate for this PO's supplier (USD preferred; RMB auto-converted).
            $cmp = $cmpPricesBySku[$sku] ?? null;
            $item->rate_not_lowest = ! empty($item->rate_not_lowest);
            $item->comparison_price_source = $item->comparison_price_source ?? null;
            $item->comparison_lowest_usd = null;
            if (is_array($cmp) && ! empty($cmp['found'])) {
                $item->rate_not_lowest = empty($cmp['is_lowest']);
                $item->comparison_price_source = $cmp['source'] ?? null;
                $item->comparison_lowest_usd = $cmp['lowest_usd'] ?? null;

                $storedPrice = (float) ($item->price ?? 0);
                $storedCurrency = strtoupper((string) ($item->currency ?? 'USD'));
                if (($storedPrice <= 0 || $this->isBlankPoValue($item->price ?? null))
                    && isset($cmp['price_usd'])
                    && (float) $cmp['price_usd'] > 0) {
                    $item->price = round((float) $cmp['price_usd'], 2);
                    $item->currency = 'USD';
                } elseif ($storedCurrency === 'USD' && $storedPrice > 0 && isset($cmp['lowest_usd'])) {
                    // Keep stored rate; still flag when PO supplier is not cheapest on sheet.
                    $item->rate_not_lowest = empty($cmp['is_lowest']);
                }
            }

            // NW / GW / CBM from Tech Spec only when the PO line has no value yet.
            $weights = $weightsBySku[$sku] ?? ['nw' => null, 'gw' => null];
            if ($this->isBlankPoValue($item->nw ?? null) && !empty($weights['nw'])) {
                $item->nw = $weights['nw'];
            }
            if ($this->isBlankPoValue($item->gw ?? null) && !empty($weights['gw'])) {
                $item->gw = $weights['gw'];
            }
            $cbm = $cbmBySku[$sku] ?? null;
            if ($this->isBlankPoValue($item->cbm ?? null) && $cbm !== null && $cbm !== '') {
                $item->cbm = $cbm;
            }
        }

        $approvalsStored = [];
        if (Schema::hasColumn('purchase_orders', 'approvals')) {
            $rawApprovals = $order->approvals ?? null;
            if (is_string($rawApprovals) && $rawApprovals !== '') {
                $decoded = json_decode($rawApprovals, true);
                $approvalsStored = is_array($decoded) ? $decoded : [];
            } elseif (is_array($rawApprovals)) {
                $approvalsStored = $rawApprovals;
            }
        }

        $bankAccounts = collect();
        $supplierId = (int) ($order->supplier_id ?? ($supplier->id ?? 0));
        if ($supplierId > 0 && Schema::hasTable('supplier_bank_accounts')) {
            $bankAccounts = SupplierBankAccount::query()
                ->where('supplier_id', $supplierId)
                ->orderByDesc('id')
                ->get();
        }

        $authEmail = mb_strtolower(trim((string) (Auth::user()->email ?? '')));
        $canEditPoBank = $authEmail === 'purchase@5core.com';

        $supplierDefaultAdvancePercent = null;
        if ($supplierId > 0 && Schema::hasTable('supplier_advances')) {
            $latestAdv = SupplierAdvance::query()
                ->where('supplier_id', $supplierId)
                ->orderByDesc('id')
                ->first();
            if ($latestAdv && $latestAdv->advance_percent !== null) {
                $supplierDefaultAdvancePercent = (float) $latestAdv->advance_percent;
            }
        }

        $qcLookupSkus = [];
        foreach ($items as $item) {
            if (! is_object($item)) {
                continue;
            }
            $sku = trim((string) ($item->product_master_sku ?? $item->sku ?? ''));
            if ($sku !== '') {
                $qcLookupSkus[] = $sku;
            }
        }
        $qcHasIssuesBySku = $this->qcHasIssuesBySkus($qcLookupSkus);
        $claimsBySku = $this->claimLinesBySkuForSupplier($supplierId, $qcLookupSkus);

        foreach ($items as $item) {
            if (! is_object($item)) {
                continue;
            }
            $sku = trim((string) ($item->product_master_sku ?? $item->sku ?? ''));
            $key = mb_strtoupper($sku);
            $item->qc_has_issues = $key !== '' && ! empty($qcHasIssuesBySku[$key]);
            $item->claim_lines = ($key !== '' && ! empty($claimsBySku[$key]))
                ? $claimsBySku[$key]
                : [];
        }

        return view('purchase-master.purchase-order.proforma', [
            'order'    => $order,
            'items'    => $items,
            'supplier' => $supplier,
            'usdToCny' => $usdToCny,
            'approvalButtons' => $this->buildPoApprovalButtons($approvalsStored),
            'bankAccounts' => $bankAccounts,
            'canEditPoBank' => $canEditPoBank,
            'supplierDefaultAdvancePercent' => $supplierDefaultAdvancePercent,
        ]);
    }

    /**
     * Active claim-reimbursement line items for a supplier, keyed by UPPER(SKU).
     * Source: /claim-reimbursement (claim_reimbursements.items[].item = SKU).
     *
     * @param  list<string>  $skus  PO line SKUs (used only to seed empty keys)
     * @return array<string, list<array<string, mixed>>>
     */
    private function claimLinesBySkuForSupplier(int $supplierId, array $skus = []): array
    {
        $bySku = [];
        foreach ($skus as $sku) {
            $key = mb_strtoupper(trim((string) $sku));
            if ($key !== '') {
                $bySku[$key] = [];
            }
        }

        if ($supplierId <= 0 || ! Schema::hasTable('claim_reimbursements')) {
            return $bySku;
        }

        $claimsQuery = ClaimReimbursement::query()->where('supplier_id', $supplierId);
        if (Schema::hasColumn('claim_reimbursements', 'is_archived')) {
            $claimsQuery->where(function ($q) {
                $q->where('is_archived', false)->orWhereNull('is_archived');
            });
        }
        $claims = $claimsQuery
            ->orderByDesc('claim_date')
            ->orderByDesc('id')
            ->get(['id', 'claim_number', 'claim_date', 'items', 'total_amount', 'received_amount', 'details_note']);

        foreach ($claims as $claim) {
            $rawItems = $claim->items;
            if (is_string($rawItems)) {
                $decoded = json_decode($rawItems, true);
                $rawItems = is_array($decoded) ? $decoded : [];
            }
            if (! is_array($rawItems)) {
                continue;
            }

            $claimDate = $claim->claim_date
                ? Carbon::parse($claim->claim_date)->format('j M y')
                : '';

            foreach ($rawItems as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $lineSku = trim((string) ($line['item'] ?? ''));
                if ($lineSku === '') {
                    continue;
                }
                $key = mb_strtoupper($lineSku);
                $bySku[$key][] = [
                    'claim_id' => (int) $claim->id,
                    'claim_number' => (string) ($claim->claim_number ?? ''),
                    'claim_date' => $claimDate,
                    'sku' => $lineSku,
                    'qty' => $line['qty'] ?? '',
                    'rate' => $line['rate'] ?? '',
                    'amount' => $line['amount'] ?? '',
                    'reason' => trim((string) ($line['reason'] ?? '')),
                    'image' => $line['image'] ?? null,
                    'received_amount' => $claim->received_amount,
                    'details_note' => $claim->details_note,
                    'claim_total' => $claim->total_amount,
                ];
            }
        }

        return $bySku;
    }

    /**
     * Whether each SKU (incl. siblings) has any non-archived QC & Packing issue.
     *
     * @param  list<string>  $skus
     * @return array<string, bool> keyed by UPPER(TRIM(sku))
     */
    private function qcHasIssuesBySkus(array $skus): array
    {
        $result = [];
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));

        foreach ($skus as $sku) {
            $result[mb_strtoupper($sku)] = false;
        }
        if ($skus === [] || ! Schema::hasTable('qc_and_packing_issues')) {
            return $result;
        }

        $normToParents = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhereRaw('TRIM(UPPER(sku)) = ?', [mb_strtoupper($sku)]);
                }
            })
            ->get(['sku', 'parent']);

        $parentBySku = [];
        foreach ($normToParents as $row) {
            $parentBySku[mb_strtoupper(trim((string) $row->sku))] = trim((string) ($row->parent ?? ''));
        }

        $parents = array_values(array_unique(array_filter(array_values($parentBySku))));
        $siblingsByParent = [];
        if ($parents !== []) {
            $sibRows = ProductMaster::query()
                ->whereNull('deleted_at')
                ->where(function ($q) use ($parents) {
                    foreach ($parents as $parent) {
                        $q->orWhereRaw('TRIM(parent) = ?', [$parent]);
                    }
                })
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->get(['sku', 'parent']);
            foreach ($sibRows as $row) {
                $p = trim((string) ($row->parent ?? ''));
                $s = trim((string) ($row->sku ?? ''));
                if ($p === '' || $s === '' || stripos($s, 'PARENT ') === 0) {
                    continue;
                }
                $siblingsByParent[$p][] = mb_strtoupper($s);
            }
        }

        $allNorms = [];
        $normsForSku = [];
        foreach ($skus as $sku) {
            $key = mb_strtoupper($sku);
            $parent = $parentBySku[$key] ?? '';
            $norms = [$key];
            if ($parent !== '' && ! empty($siblingsByParent[$parent])) {
                $norms = array_values(array_unique(array_merge($norms, $siblingsByParent[$parent])));
            }
            $normsForSku[$key] = $norms;
            foreach ($norms as $n) {
                $allNorms[$n] = true;
            }
        }

        $issueNorms = [];
        $normList = array_keys($allNorms);
        if ($normList !== []) {
            $placeholders = implode(',', array_fill(0, count($normList), '?'));
            $hitSkus = DB::table('qc_and_packing_issues')
                ->where(function ($q) {
                    $q->whereNull('is_archived')->orWhere('is_archived', false);
                })
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $normList)
                ->distinct()
                ->pluck('sku');
            foreach ($hitSkus as $hit) {
                $issueNorms[mb_strtoupper(trim((string) $hit))] = true;
            }
        }

        foreach ($normsForSku as $key => $norms) {
            foreach ($norms as $n) {
                if (! empty($issueNorms[$n])) {
                    $result[$key] = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Save Advance % on PO, autofill advance amount from Grand Total * %,
     * and persist a row in supplier_advances.
     */
    public function updateAdvance(Request $request, $id)
    {
        $validated = $request->validate([
            'advance_percent' => 'nullable|numeric|min:0|max:100',
            'grand_total' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
        ]);

        $po = PurchaseOrder::findOrFail($id);
        $percent = array_key_exists('advance_percent', $validated) && $validated['advance_percent'] !== null
            ? round((float) $validated['advance_percent'], 2)
            : null;

        $grandTotal = isset($validated['grand_total'])
            ? round((float) $validated['grand_total'], 2)
            : round((float) ($po->total_amount ?? 0), 2);

        if ($grandTotal <= 0) {
            $items = json_decode($po->items ?? '[]', true) ?: [];
            $computed = 0.0;
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);
                if ($qty > 0 && $price > 0) {
                    $computed += $qty * $price;
                }
            }
            $grandTotal = round($computed, 2);
        }

        $advanceAmount = $percent !== null
            ? round($grandTotal * ($percent / 100), 2)
            : round((float) ($po->advance_amount ?? 0), 2);

        $currency = strtoupper(trim((string) ($validated['currency'] ?? 'USD'))) ?: 'USD';

        if (Schema::hasColumn('purchase_orders', 'advance_percent')) {
            $po->advance_percent = $percent;
        }
        $po->advance_amount = $advanceAmount;
        if (Schema::hasColumn('purchase_orders', 'total_amount') && $grandTotal > 0) {
            $po->total_amount = $grandTotal;
        }
        $po->save();

        $supplierAdvance = null;
        if ((int) $po->supplier_id > 0 && Schema::hasTable('supplier_advances')) {
            $user = Auth::user();
            $supplierAdvance = SupplierAdvance::create([
                'supplier_id' => (int) $po->supplier_id,
                'purchase_order_id' => (int) $po->id,
                'advance_percent' => $percent,
                'advance_amount' => $advanceAmount,
                'grand_total' => $grandTotal,
                'currency' => $currency,
                'created_by' => $user?->id,
                'created_by_name' => trim((string) ($user->name ?? '')),
            ]);
        }

        return response()->json([
            'success' => true,
            'advance_percent' => $percent,
            'advance_amount' => $advanceAmount,
            'grand_total' => $grandTotal,
            'currency' => $currency,
            'balance_due' => round($grandTotal - $advanceAmount, 2),
            'supplier_advance_id' => $supplierAdvance?->id,
            'message' => 'Advance saved.',
        ]);
    }

    /**
     * Spec (comparison sheet-view) → Tech text for a SKU.
     */
    public function techFromComparison(Request $request, ComparisonSpecTechService $specTech)
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.', 'tech' => ''], 400);
        }

        $tech = $specTech->techForSku($sku);

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'tech' => $tech,
        ]);
    }

    /**
     * Toggle an Approved BY button on the PO PDF. Only the named user may toggle their own button.
     */
    public function toggleApproval(Request $request, $id)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:40',
        ]);

        $key = mb_strtolower(trim((string) $validated['key']));
        $defs = $this->poApprovalDefinitions();
        if (! isset($defs[$key])) {
            return response()->json(['success' => false, 'message' => 'Unknown approval button.'], 422);
        }

        if (! $this->currentUserMatchesApproval($defs[$key])) {
            return response()->json([
                'success' => false,
                'message' => 'Only '.$defs[$key]['label'].' can approve this button.',
            ], 403);
        }

        $po = PurchaseOrder::findOrFail($id);
        $approvals = is_array($po->approvals) ? $po->approvals : [];
        $row = is_array($approvals[$key] ?? null) ? $approvals[$key] : [];
        $nowApproved = empty($row['approved']);

        $user = Auth::user();
        if ($nowApproved) {
            $approvals[$key] = [
                'approved' => true,
                'approved_at' => now()->toDateTimeString(),
                'approved_by' => trim((string) ($user->name ?? '')),
                'user_id' => $user?->id,
            ];
        } else {
            $approvals[$key] = [
                'approved' => false,
                'approved_at' => null,
                'approved_by' => null,
                'user_id' => null,
            ];
        }

        $po->approvals = $approvals;
        $po->save();

        return response()->json([
            'success' => true,
            'key' => $key,
            'approved' => $nowApproved,
            'approved_at' => $approvals[$key]['approved_at'] ?? null,
            'approved_by' => $approvals[$key]['approved_by'] ?? null,
            'message' => $nowApproved
                ? $defs[$key]['label'].' approved.'
                : $defs[$key]['label'].' approval cleared.',
        ]);
    }

    /**
     * QC & Packing issues for a SKU + sibling SKUs (same parent), same source as
     * /customer-care/qc-and-packing.
     */
    public function qcIssuesForSkuWithSiblings(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json([
                'success' => false,
                'message' => 'SKU is required.',
                'sku' => '',
                'parent' => null,
                'siblings' => [],
                'issues' => [],
            ], 422);
        }

        $parent = trim((string) (ProductMaster::query()
            ->whereRaw('TRIM(UPPER(sku)) = ?', [mb_strtoupper($sku)])
            ->value('parent') ?? ''));

        $siblings = [$sku];
        if ($parent !== '') {
            $sibs = ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw('TRIM(parent) = ?', [$parent])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->orderBy('sku')
                ->pluck('sku')
                ->map(fn ($s) => trim((string) $s))
                ->filter(fn ($s) => $s !== '' && stripos($s, 'PARENT ') !== 0)
                ->unique(fn ($s) => mb_strtoupper($s))
                ->values()
                ->all();
            if ($sibs !== []) {
                $siblings = $sibs;
            }
            if (! in_array($sku, $siblings, true)) {
                $siblings[] = $sku;
            }
        }

        $siblingNorms = array_values(array_unique(array_map(
            static fn ($s) => mb_strtoupper(trim((string) $s)),
            $siblings
        )));

        $issues = [];
        if ($siblingNorms !== [] && Schema::hasTable('qc_and_packing_issues')) {
            $placeholders = implode(',', array_fill(0, count($siblingNorms), '?'));
            $rows = DB::table('qc_and_packing_issues')
                ->where(function ($q) {
                    $q->whereNull('is_archived')->orWhere('is_archived', false);
                })
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $siblingNorms)
                ->orderByDesc('id')
                ->limit(500)
                ->get([
                    'id',
                    'sku',
                    'qty',
                    'order_qty',
                    'parent',
                    'marketplace_1',
                    'marketplace_2',
                    'what_happened',
                    'issue',
                    'issue_remark',
                    'action_1',
                    'action_1_remark',
                    'replacement_tracking',
                    'c_action_1',
                    'c_action_1_remark',
                    'close_note',
                    'department',
                    'created_by',
                    'created_at',
                ]);

            $tz = config('app.timezone');
            $issues = $rows->map(static function ($row) use ($tz) {
                return [
                    'id' => (int) $row->id,
                    'sku' => $row->sku,
                    'qty' => $row->qty !== null ? (float) $row->qty : null,
                    'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                    'parent' => $row->parent,
                    'marketplace_1' => $row->marketplace_1,
                    'marketplace_2' => $row->marketplace_2,
                    'what_happened' => $row->what_happened,
                    'issue' => $row->issue,
                    'issue_remark' => $row->issue_remark,
                    'action_1' => $row->action_1,
                    'action_1_remark' => $row->action_1_remark,
                    'replacement_tracking' => $row->replacement_tracking,
                    'c_action_1' => $row->c_action_1,
                    'c_action_1_remark' => $row->c_action_1_remark,
                    'close_note' => $row->close_note,
                    'department' => $row->department,
                    'created_by' => $row->created_by,
                    'created_at' => $row->created_at,
                    'created_at_display' => $row->created_at
                        ? Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i')
                        : '',
                ];
            })->values()->all();
        }

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'parent' => $parent !== '' ? $parent : null,
            'siblings' => array_values($siblings),
            'issues' => $issues,
            'page_url' => url('/customer-care/qc-and-packing'),
        ]);
    }

    /** USD → CNY (RMB) rate; falls back so proforma can always show both price columns. */
    private function usdToCnyRate(): float
    {
        $fallback = 7.2;

        try {
            $response = Http::timeout(4)->get('https://api.frankfurter.app/latest', [
                'amount' => 1,
                'from' => 'USD',
                'to' => 'CNY',
            ]);
            if ($response->successful()) {
                $rate = (float) data_get($response->json(), 'rates.CNY');
                if ($rate > 0) {
                    return $rate;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PO PDF: USD→CNY rate fetch failed, using fallback', ['error' => $e->getMessage()]);
        }

        return $fallback;
    }

    private function isBlankPoValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        $text = trim((string) $value);

        return $text === '' || $text === '0' || $text === '0.0' || $text === '0.00';
    }

    /**
     * Alternate spellings for PO SKUs that differ from CP Master naming
     * (e.g. "DM 9EC" → "DM E9").
     *
     * @return array<int, string>
     */
    private function skuSearchVariants(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $out = [$sku];

        // DM 9EC → DM E9 (letter cluster after digits often means series letter + count)
        if (preg_match('/^([A-Za-z]+)\s+(\d+)([A-Za-z]{1,4})$/', $sku, $m)) {
            $out[] = $m[1].' E'.$m[2];
            $out[] = $m[1].$m[2].$m[3];
        }

        // Compact / spaced forms
        $compact = preg_replace('/\s+/', '', $sku) ?? '';
        if ($compact !== '' && $compact !== $sku) {
            $out[] = $compact;
        }

        return array_values(array_unique(array_filter($out, fn ($s) => trim((string) $s) !== '')));
    }

    /**
     * Color token from Tech / supplier text → CP Master suffix (BLK, SLV, …).
     */
    private function extractColorCodeFromText(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $map = [
            'black' => 'BLK',
            'blk' => 'BLK',
            'silver' => 'SLV',
            'slv' => 'SLV',
            'chrome' => 'SLV',
            'gold' => 'GLD',
            'gld' => 'GLD',
            'green' => 'GRN',
            'grn' => 'GRN',
            'blue' => 'BLU',
            'blu' => 'BLU',
            'red' => 'RED',
            'yellow' => 'YLW',
            'ylw' => 'YLW',
            'white' => 'WH',
            'wh' => 'WH',
            'purple' => 'PRPL',
            'prpl' => 'PRPL',
            'grey' => 'GREY',
            'gray' => 'GREY',
            'copper' => 'COPPEREX',
        ];

        if (preg_match('/color\s*[:\-]\s*([A-Za-z]+)/i', $text, $m)) {
            $key = strtolower($m[1]);

            return $map[$key] ?? strtoupper($m[1]);
        }

        foreach ($map as $word => $code) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/i', $text)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * True when PO SKU and CP Master SKU refer to the same family
     * (exact, PO is sized variant of CP, or CP is color/size variant of PO).
     */
    private function poSkuMatchesCpSku(string $poSku, string $cpSku): bool
    {
        $poSku = trim($poSku);
        $cpSku = trim($cpSku);
        if ($poSku === '' || $cpSku === '') {
            return false;
        }
        if (strcasecmp($poSku, $cpSku) === 0) {
            return true;
        }

        foreach ([[$poSku, $cpSku], [$cpSku, $poSku]] as [$shorter, $longer]) {
            if (strlen($shorter) >= strlen($longer)) {
                continue;
            }
            if (!str_starts_with($longer, $shorter)) {
                continue;
            }
            $next = $longer[strlen($shorter)] ?? '';
            // Boundary: end, space, quote, hyphen — not a glued digit (WF 8 vs WF 8120).
            if ($next === '' || $next === ' ' || $next === '"' || $next === '-' || $next === "'") {
                return true;
            }
        }

        return false;
    }

    private function scorePoSkuMatch(string $poSku, string $cpSku, ?string $colorCode): int
    {
        if (strcasecmp($poSku, $cpSku) === 0) {
            return 100000;
        }

        // Prefer the closest family member (smallest length delta) over a long distant SKU.
        $delta = abs(strlen($cpSku) - strlen($poSku));
        $score = 5000 - min(4000, $delta * 20);
        if ($colorCode) {
            $cpUpper = strtoupper($cpSku);
            if (preg_match('/(?:^|\s)'.preg_quote(strtoupper($colorCode), '/').'(?:\s|$)/', $cpUpper)) {
                $score += 8000;
            } else {
                $score -= 2500;
            }
        }

        return $score;
    }

    /**
     * Resolve CP Master row for a PO line (photo / barcode / packaging).
     */
    private function findProductMasterForPoSku(string $poSku, ?string $hintText = null): ?ProductMaster
    {
        $variants = $this->skuSearchVariants($poSku);
        if ($variants === []) {
            return null;
        }

        $colorCode = $this->extractColorCodeFromText((string) ($hintText ?? ''));
        $select = ['id', 'sku', 'Values', 'main_image', 'image1'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('product_master', 'barcode')) {
            $select[] = 'barcode';
        }

        // Exact SKU hit (no color hint, or the exact row already is that color).
        foreach ($variants as $variant) {
            $exact = ProductMaster::query()
                ->where('sku', $variant)
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first($select);
            if (!$exact) {
                continue;
            }
            if (!$colorCode) {
                return $exact;
            }
            if ($this->scorePoSkuMatch($variant, (string) $exact->sku, $colorCode) >= 5000) {
                return $exact;
            }
        }

        $candidates = ProductMaster::query()
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('sku', $variant)
                        ->orWhere('sku', 'like', $variant.' %')
                        ->orWhere('sku', 'like', $variant.'"%')
                        ->orWhere('sku', 'like', $variant.'-%');

                    $parts = preg_split('/\s+/', $variant) ?: [];
                    if (count($parts) >= 2) {
                        $prefix = implode(' ', array_slice($parts, 0, min(3, count($parts) - 1)));
                        if ($prefix !== '') {
                            $q->orWhere('sku', 'like', $prefix.'%');
                        }
                    }
                }
            })
            ->limit(120)
            ->get($select);

        $best = null;
        $bestScore = -1;
        foreach ($candidates as $product) {
            $cpSku = trim((string) $product->sku);
            foreach ($variants as $variant) {
                if (!$this->poSkuMatchesCpSku($variant, $cpSku)) {
                    continue;
                }
                $score = $this->scorePoSkuMatch($variant, $cpSku, $colorCode);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $product;
                }
            }
        }

        return $best;
    }

    /**
     * Map PO SKU => image URL from CP Master (product_master Values.image_path / main_image).
     * Exact SKU match first; then bidirectional family match (base ↔ color/size variant).
     */

    /**
     * Undo nested HTML-entity / JSON-quote wrapping and literal \n sequences in Tech text.
     */
    private function normalizeTechText(mixed $tech): string
    {
        $text = trim((string) ($tech ?? ''));
        if ($text === '') {
            return '';
        }

        // Decode HTML entities until stable (&amp;quot; → &quot; → ").
        for ($i = 0; $i < 5; $i++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $text) {
                break;
            }
            $text = $decoded;
        }

        // Unwrap accidental JSON string encoding: "\"INCHES...\\nOHM...\""
        for ($i = 0; $i < 3; $i++) {
            $trimmed = trim($text);
            if (
                strlen($trimmed) >= 2
                && str_starts_with($trimmed, '"')
                && str_ends_with($trimmed, '"')
            ) {
                $jsonDecoded = json_decode($trimmed, true);
                if (is_string($jsonDecoded)) {
                    $text = $jsonDecoded;
                    continue;
                }

                // Fallback for invalid JSON-wrapped blobs with literal \n.
                $inner = substr($trimmed, 1, -1);
                if (str_contains($inner, '\\n') || str_contains($inner, ':')) {
                    $text = stripcslashes($inner);
                    continue;
                }
            }
            break;
        }

        // Literal backslash-n / backslash-r from over-escaped storage.
        $text = str_replace(["\\r\\n", "\\n", "\\r"], ["\n", "\n", "\n"], $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }

    /**
     * Short Name from title-master (`short_titles.short_title`) keyed by 5 Core SKU.
     *
     * @param  array<int, string>  $skus
     * @return array<string, string>
     */
    private function shortNamesBySku(array $skus): array
    {
        $skuList = [];
        foreach ($skus as $sku) {
            $sku = trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', (string) $sku)) ?? '');
            if ($sku !== '') {
                $skuList[$sku] = true;
            }
        }
        $skuList = array_keys($skuList);
        if ($skuList === []) {
            return [];
        }

        $map = [];
        ShortTitle::query()
            ->whereIn('sku', $skuList)
            ->select(['sku', 'short_title'])
            ->get()
            ->each(function ($row) use (&$map) {
                $sku = trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', (string) $row->sku)) ?? '');
                $title = trim((string) ($row->short_title ?? ''));
                if ($sku !== '' && $title !== '') {
                    $map[$sku] = $title;
                }
            });

        // Case-insensitive / whitespace-normalized fallback for missed exact matches.
        $missing = array_values(array_filter($skuList, fn ($s) => !isset($map[$s])));
        if ($missing !== []) {
            $lowerMissing = [];
            foreach ($missing as $s) {
                $lowerMissing[mb_strtolower($s)] = $s;
            }
            ShortTitle::query()
                ->select(['sku', 'short_title'])
                ->whereNotNull('short_title')
                ->whereRaw('TRIM(short_title) != ""')
                ->where(function ($q) use ($missing) {
                    foreach ($missing as $s) {
                        $q->orWhereRaw('LOWER(TRIM(REPLACE(sku, UNHEX("C2A0"), " "))) = ?', [mb_strtolower($s)]);
                    }
                })
                ->get()
                ->each(function ($row) use (&$map, $lowerMissing) {
                    $sku = trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', (string) $row->sku)) ?? '');
                    $title = trim((string) ($row->short_title ?? ''));
                    if ($sku === '' || $title === '') {
                        return;
                    }
                    $key = $lowerMissing[mb_strtolower($sku)] ?? $sku;
                    if (!isset($map[$key])) {
                        $map[$key] = $title;
                    }
                    $map[$sku] = $title;
                });
        }

        return $map;
    }

    /**
     * Lookup Short name (title-master) for a SKU — used by proforma Add Row autofill.
     */
    public function shortNameBySku(Request $request)
    {
        $sku = trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', (string) $request->query('sku', ''))) ?? '');
        if ($sku === '') {
            return response()->json(['success' => false, 'short_name' => '', 'message' => 'SKU is required.'], 422);
        }

        $map = $this->shortNamesBySku([$sku]);
        $shortName = $map[$sku] ?? '';

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'short_name' => $shortName,
        ]);
    }

    private function cpMasterImagesBySku(array $skus): array
    {
        $map = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || isset($map[$sku])) {
                continue;
            }
            $product = $this->findProductMasterForPoSku($sku);
            if (!$product) {
                continue;
            }
            $url = $this->resolveCpMasterImage($product);
            if ($url) {
                $map[$sku] = $url;
            }
        }

        return $map;
    }

    private function resolveCpMasterImage(ProductMaster $product): ?string
    {
        $urls = $this->resolveCpMasterImageCandidates($product);

        return $urls[0] ?? null;
    }

    /**
     * Ordered image URL candidates from CP Master (Values + columns).
     *
     * @return array<int, string>
     */
    private function resolveCpMasterImageCandidates(ProductMaster $product): array
    {
        $raw = [];
        $values = $this->productValuesArray($product);

        foreach (['image_path', 'image', 'Image', 'main_image', 'Image Path', 'photo'] as $key) {
            if (!empty($values[$key])) {
                $raw[] = $values[$key];
            }
        }

        if (!empty($product->main_image)) {
            $raw[] = $product->main_image;
        }
        if (!empty($product->image1)) {
            $raw[] = $product->image1;
        }

        $urls = [];
        foreach ($raw as $candidate) {
            $url = $this->normalizeImageUrl($candidate);
            if ($url !== null) {
                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    private function shopifyImageForSku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('shopify_skus')) {
            return null;
        }

        $row = DB::table('shopify_skus')
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [mb_strtoupper($sku)])
            ->orderBy('id')
            ->first(['image_src']);

        if (!$row) {
            return null;
        }

        return $this->normalizeImageUrl($row->image_src ?? null);
    }

    /**
     * Map PO SKU => Masters Barcode image + code (Values.barcode_image / barcode / upc).
     * Same exact + prefix matching as product photos.
     *
     * @return array<string, array{image: ?string, code: ?string}>
     */
    private function cpMasterBarcodesBySku(array $skus): array
    {
        $map = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || isset($map[$sku])) {
                continue;
            }
            $product = $this->findProductMasterForPoSku($sku);
            if (!$product) {
                continue;
            }
            $resolved = $this->resolveCpMasterBarcode($product);
            if ($resolved['image'] || $resolved['code']) {
                $map[$sku] = $resolved;
            }
        }

        return $map;
    }

    /**
     * Item Pkg + Ctn Pkg from Dim Wt Master sources:
     * - item_pkg → instructions_item_pkg.instructions
     * - ctn_pkg  → product_master.Values.ctn_instructions
     * Always resolves product_id (even when texts are empty) so edits can be saved.
     * Same exact + prefix SKU matching as product photos / barcodes.
     *
     * @return array<string, array{item_pkg: string, ctn_pkg: string, item_pkg_cover: ?string, design_file: ?string, ctn_qty: mixed, ctn_print_file: ?string, product_id: ?int, matched_sku: string}>
     */
    private function dimWtPkgBySku(array $skus): array
    {
        $skuList = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $skuList[$sku] = true;
            }
        }
        $skuList = array_keys($skuList);
        if ($skuList === []) {
            return [];
        }

        $resolve = function (ProductMaster $product, $pkgByProductId, $qcByProductId): array {
            $values = $this->productValuesArray($product);
            $ctn = trim((string) ($values['ctn_instructions'] ?? ''));
            $pkg = $pkgByProductId->get($product->id);
            $item = $pkg && $pkg->instructions !== null ? trim((string) $pkg->instructions) : '';
            $designRaw = trim((string) ($values['packing_cdr_path'] ?? ''));
            $printRaw = trim((string) ($values['ctn_print_file'] ?? ''));
            $ctnQty = $values['ctn_qty'] ?? null;
            if ($ctnQty !== null && $ctnQty !== '') {
                $ctnQty = is_scalar($ctnQty) ? trim((string) $ctnQty) : null;
                if ($ctnQty === '') {
                    $ctnQty = null;
                }
            } else {
                $ctnQty = null;
            }
            $qc = $qcByProductId->get($product->id);
            $specialQc = $qc && $qc->qc_improvement_req !== null
                ? trim((string) $qc->qc_improvement_req)
                : '';

            return [
                'item_pkg' => $item,
                'ctn_pkg' => $ctn,
                'item_pkg_cover' => $this->resolveItemPkgCoverUrl($values),
                'design_file' => $designRaw !== '' ? $this->normalizeImageUrl($designRaw) : null,
                'ctn_qty' => $ctnQty,
                'ctn_print_file' => $printRaw !== '' ? $this->normalizeImageUrl($printRaw) : null,
                'special_instruction_qc' => $specialQc,
                'product_id' => (int) $product->id,
                'matched_sku' => trim((string) $product->sku),
            ];
        };

        $products = ProductMaster::query()
            ->whereIn('sku', $skuList)
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->get(['id', 'sku', 'Values']);
        $pkgByProductId = InstructionsItemPkg::query()
            ->whereIn('product_master_id', $products->pluck('id'))
            ->get()
            ->keyBy('product_master_id');
        $qcByProductId = QcImprovementReqBeforeItemPkg::query()
            ->whereIn('product_master_id', $products->pluck('id'))
            ->get()
            ->keyBy('product_master_id');

        $map = [];
        foreach ($products as $product) {
            $map[trim((string) $product->sku)] = $resolve($product, $pkgByProductId, $qcByProductId);
        }

        $missing = array_values(array_filter($skuList, fn ($sku) => empty($map[$sku])));
        if ($missing === []) {
            return $map;
        }

        $candidates = ProductMaster::query()
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->where(function ($q) use ($missing) {
                foreach ($missing as $sku) {
                    $parts = preg_split('/\s+/', $sku);
                    if (!$parts || count($parts) < 2) {
                        continue;
                    }
                    $prefix = implode(' ', array_slice($parts, 0, min(3, count($parts) - 1)));
                    if ($prefix !== '') {
                        $q->orWhere('sku', 'like', $prefix . '%');
                    }
                }
            })
            ->get(['id', 'sku', 'Values']);

        if ($candidates->isNotEmpty()) {
            $extraPkg = InstructionsItemPkg::query()
                ->whereIn('product_master_id', $candidates->pluck('id'))
                ->get()
                ->keyBy('product_master_id');
            $pkgByProductId = $pkgByProductId->union($extraPkg);
            $extraQc = QcImprovementReqBeforeItemPkg::query()
                ->whereIn('product_master_id', $candidates->pluck('id'))
                ->get()
                ->keyBy('product_master_id');
            $qcByProductId = $qcByProductId->union($extraQc);
        }

        foreach ($missing as $poSku) {
            $bestLen = -1;
            $best = null;
            foreach ($candidates as $product) {
                $cpSku = trim((string) $product->sku);
                if ($cpSku === '' || strlen($cpSku) > strlen($poSku)) {
                    continue;
                }
                if ($poSku === $cpSku || str_starts_with($poSku, $cpSku . ' ')) {
                    $resolved = $resolve($product, $pkgByProductId, $qcByProductId);
                    if (strlen($cpSku) > $bestLen) {
                        $bestLen = strlen($cpSku);
                        $best = $resolved;
                    }
                }
            }
            if ($best) {
                $map[$poSku] = $best;
            }
        }

        return $map;
    }

    /**
     * @return array{image: ?string, code: ?string}
     */
    private function resolveCpMasterBarcode(ProductMaster $product): array
    {
        $values = $this->productValuesArray($product);
        $image = $this->normalizeImageUrl($values['barcode_image'] ?? null);
        $code = trim((string) ($product->barcode ?? ''));
        if ($code === '') {
            foreach (['upc', 'UPC', 'gtin', 'ean'] as $key) {
                $raw = trim((string) ($values[$key] ?? ''));
                if ($raw === '' || $raw === '-') {
                    continue;
                }
                if (is_numeric($raw)) {
                    $raw = preg_replace('/\D/', '', (string) $raw) ?? '';
                }
                $raw = preg_replace('/\s+/', '', $raw) ?? '';
                if ($raw !== '') {
                    $code = $raw;
                    break;
                }
            }
        }

        return [
            'image' => $image,
            'code' => $code !== '' ? $code : null,
        ];
    }

    /**
     * PNG data-URI barcode for proforma when no stored barcode_image exists.
     */
    private function barcodeDataUri(string $code): ?string
    {
        $code = preg_replace('/\s+/', '', trim($code)) ?? '';
        if ($code === '' || $code === '-') {
            return null;
        }

        try {
            $generator = new BarcodeGeneratorPNG();
            $digits = preg_replace('/\D/', '', $code) ?? '';
            $type = (strlen($digits) === 11 || strlen($digits) === 12)
                ? $generator::TYPE_UPC_A
                : $generator::TYPE_CODE_128;
            $payload = $code;

            if ($type === $generator::TYPE_UPC_A) {
                if (strlen($digits) === 12) {
                    $payload = substr($digits, 0, 11);
                } elseif (strlen($digits) === 11) {
                    $payload = $digits;
                } else {
                    $type = $generator::TYPE_CODE_128;
                    $payload = $code;
                }
            }

            $png = $generator->getBarcode($payload, $type, 2, 55);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable $e) {
            Log::warning('PO proforma barcode render failed', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function productValuesArray(ProductMaster $product): array
    {
        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($values)) {
            $values = [];
        }

        return $values;
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

        return '/' . ltrim($p, '/');
    }

    private function fallbackPoPhotoUrl(mixed $photo): ?string
    {
        $p = trim((string) ($photo ?? ''));
        if ($p === '') {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $p) || str_starts_with($p, '/')) {
            return $p;
        }

        return '/storage/' . ltrim($p, '/');
    }

    public function convert(Request $request)
    {
        $amount = $request->query('amount', 1);
        $from = $request->query('from', 'USD');
        $to = $request->query('to', 'CNY');

        try {
            $apiUrl = "https://api.frankfurter.app/latest?amount=$amount&from=$from&to=$to";
            $response = Http::get($apiUrl);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json(['error' => 'Frankfurter API error'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    function generateOrderNumber()
    {
        $datePart = Carbon::now()->format('dmy'); 
        $prefix = 'PO-' . $datePart;

        $latestOrder = PurchaseOrder::select('po_number')
            ->where('po_number', 'like', "$prefix-%")
            ->orderBy('po_number', 'desc')
            ->first();

        if ($latestOrder) {
            $parts = explode('-', $latestOrder->po_number);
            $lastSerial = intval(end($parts));
            $newSerial = str_pad($lastSerial + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $newSerial = '01';
        }
        return "$prefix-$newSerial";
    }

    public function deletePurchaseOrders(Request $request)
    {
        $ids = $request->ids ?? [];
        PurchaseOrder::whereIn('id', $ids)->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Selected orders deleted successfully.']);
        }
        return redirect()->back()->with('flash_message', 'Selected orders deleted successfully.');
    }
}
