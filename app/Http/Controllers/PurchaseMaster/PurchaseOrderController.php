<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Models\ProductMaster;
use App\Models\PurchaseOrder;
use App\Models\ShortTitle;
use App\Services\ComparisonSpecTechService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Picqer\Barcode\BarcodeGeneratorPNG;

class PurchaseOrderController extends Controller
{
    
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
            'sku' => 'required|array',
            'sku.*' => 'nullable|string',
        ]);

        $poNumber = $request->po_number;
        $supplierId = $request->supplier;
        $advanceAmt = $request->advance_amount;
        $today = now()->toDateString();

        // Extract arrays from request
        $skus = $request->sku;
        $supplierSkus = $request->supplier_sku ?? [];
        $qtys = $request->qty ?? [];
        $prices = $request->price ?? [];
        $techs = $request->tech ?? [];
        $currencies = $request->currency ?? [];
        $priceTypes = $request->price_type ?? [];
        $nws = $request->nw ?? [];
        $gws = $request->gw ?? [];
        $cbms = $request->cbm ?? [];

        $photos = $request->file('photo') ?? [];

        $items = [];
        $totalAmount = 0;
        $techBySku = app(ComparisonSpecTechService::class)->techBySkus($skus);

        foreach ($skus as $index => $sku) {
            $photoPath = isset($photos[$index]) ? $photos[$index]->store('purchase_orders/photos', 'public') : null;

            $qty = $qtys[$index] ?? 0;
            $price = $prices[$index] ?? 0;

            $lineTotal = (float)$qty * (float)$price;
            $totalAmount += $lineTotal;

            $tech = trim((string) ($techs[$index] ?? ''));
            if ($tech === '') {
                $tech = $techBySku[trim((string) $sku)] ?? '';
            }

            $items[] = [
                'sku' => $sku,
                'supplier_sku' => $supplierSkus[$index] ?? null,
                'qty' => $qtys[$index] ?? null,
                'price' => $prices[$index] ?? null,
                'tech' => $tech !== '' ? $tech : null,
                'currency' => $currencies[$index] ?? null,
                'price_type' => $priceTypes[$index] ?? null,
                'nw' => $nws[$index] ?? null,
                'gw' => $gws[$index] ?? null,
                'cbm' => $cbms[$index] ?? null,
                'photo' => $photoPath,
            ];
        }

        PurchaseOrder::create([
            'po_number' => $poNumber,
            'supplier_id' => $supplierId,
            'po_date' => $today,
            'items' => json_encode($items),
            'advance_amount' => $advanceAmt,
            'total_amount' => $totalAmount,
        ]);

        return redirect()->back()->with('flash_message', 'PO with all items saved as one row successfully.');
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
        foreach (['supplier_sku', 'short_name', 'tech', 'nw', 'gw', 'cbm', 'qty', 'price_usd', 'price_rmb', 'currency'] as $key) {
            if (array_key_exists($key, $normalized) && $normalized[$key] === '') {
                $normalized[$key] = null;
            }
        }
        $request->merge($normalized);

        $validated = $request->validate([
            'item_index' => 'required|integer|min:0',
            'supplier_sku' => 'nullable|string|max:255',
            'short_name' => 'nullable|string|max:40',
            'tech' => 'nullable|string|max:5000',
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

        // Persist Short Name to Short Title Master (by 5 Core SKU).
        $shortName = array_key_exists('short_name', $validated)
            ? trim((string) ($validated['short_name'] ?? ''))
            : null;
        if ($sku !== '' && $shortName !== null) {
            ShortTitle::updateOrCreate(
                ['sku' => $sku],
                ['short_title' => $shortName]
            );
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
                'nw' => $item['nw'] ?? '',
                'gw' => $item['gw'] ?? '',
                'cbm' => $item['cbm'] ?? '',
                'qty' => $item['qty'] ?? '',
                'price' => $item['price'] ?? '',
                'currency' => $item['currency'] ?? 'USD',
            ],
        ]);
    }

    public function updatePurchaseOrder(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        $po->supplier_id = $request->supplier;
        $po->advance_amount = $request->advance_amount;
        $po->po_date = $request->po_date;

        $items = [];
        $totalAmount = 0;
        $techBySku = app(ComparisonSpecTechService::class)->techBySkus($request->sku ?? []);

        if ($request->sku) {
            for ($i = 0; $i < count($request->sku); $i++) {
                
                $photoPath = $request->hasFile("photo.$i") ? $request->file("photo.$i")->store('purchase_orders/photos', 'public') : null;

                $existingItems = json_decode($po->items, true);
                $existingPhoto = $existingItems[$i]['photo'] ?? null;

                $qty = $request->qty[$i] ?? 0;
                $price = $request->price[$i] ?? 0;

                $lineTotal = $qty * $price;
                $totalAmount += $lineTotal;

                $tech = trim((string) ($request->tech[$i] ?? ''));
                if ($tech === '') {
                    $tech = $techBySku[trim((string) $request->sku[$i])] ?? '';
                }

                $items[] = [
                    'sku' => $request->sku[$i],
                    'supplier_sku' => $request->supplier_sku[$i],
                    'tech' => $tech,
                    'qty' => $request->qty[$i] ?? 0,
                    'price' => $request->price[$i] ?? 0,
                    'currency' => $request->currency[$i] ?? 'USD',
                    'price_type' => $request->price_type[$i] ?? 'EXW',
                    'nw' => $request->nw[$i] ?? 0,
                    'gw' => $request->gw[$i] ?? 0,
                    'cbm' => $request->cbm[$i] ?? 0,
                    'photo' => $photoPath ?? $existingPhoto,
                ];
            }
        }

        $po->items = json_encode($items);
        $po->total_amount = $totalAmount;
        $po->save();

        return redirect()->back()->with('success', 'Purchase Order updated successfully!');
    }

    public function getPurchaseOrdersData(Request $request)
    {
        $filter = $request->query('filter', 'active'); // active | archived | all

        $query = PurchaseOrder::select('id', 'po_number', 'po_date', 'supplier_id', 'items', 'advance_amount', 'total_amount', 'is_archived')
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

        $orders = $orders->map(function ($order) use ($imageBySku, $techBySku) {
            $items = collect(json_decode($order->items));
            $firstItem = $items->first();

            $skuList = $items->pluck('sku')->take(3)->implode(', ');
            if ($items->count() > 3) {
                $skuList .= '...';
            }

            $advance = (float) ($order->advance_amount ?? 0);
            $totalAmount = (float) ($order->total_amount ?? 0);
            $balance = $totalAmount - $advance;

            $totalCbm = $items->sum(function ($item) {
                return (float) ($item->cbm ?? 0);
            });

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
                'advance_amount' => $order->advance_amount ?? '',
                'total_amount' => $totalAmount,
                'balance' => $balance,
                'total_cbm' => $totalCbm,
                'is_archived' => (bool) ($order->is_archived ?? false),
                'sku_list' => $skuList,
                'photo' => $imageBySku[$firstSku] ?? ($firstItem->photo ?? ''),
                'items_json' => json_encode($enrichedItems),
            ];
        });

        return response()->json($orders);
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
        $skus = collect($items)->pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->values()->all();
        $imageBySku = $this->cpMasterImagesBySku($skus);
        $barcodeBySku = $this->cpMasterBarcodesBySku($skus);
        $specTech = app(ComparisonSpecTechService::class);
        $techBySku = $specTech->techBySkus($skus);
        $weightsBySku = $specTech->weightsKgBySkus($skus);
        $cbmBySku = $specTech->cbmBySkus($skus);
        $shortNameBySku = $this->shortNamesBySku($skus);

        foreach ($items as $item) {
            if (!is_object($item)) {
                continue;
            }
            $sku = trim((string) ($item->sku ?? ''));
            $item->photo_url = $imageBySku[$sku] ?? $this->fallbackPoPhotoUrl($item->photo ?? null);
            $item->barcode_url = $barcodeBySku[$sku]['image'] ?? null;
            $item->barcode_code = $barcodeBySku[$sku]['code'] ?? null;
            // If Masters Barcode has a code but no saved image, generate bars for the proforma.
            if (empty($item->barcode_url) && !empty($item->barcode_code)) {
                $item->barcode_url = $this->barcodeDataUri((string) $item->barcode_code);
            }
            $item->short_name = $shortNameBySku[$sku] ?? '';
            $item->tech = $this->normalizeTechText($item->tech ?? '');
            if ($item->tech === '' && !empty($techBySku[$sku])) {
                $item->tech = $this->normalizeTechText($techBySku[$sku]);
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

        return view('purchase-master.purchase-order.proforma', [
            'order'    => $order,
            'items'    => $items,
            'supplier' => $supplier,
            'usdToCny' => $this->usdToCnyRate(),
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

    /**
     * Map PO SKU => image URL from CP Master (product_master Values.image_path / main_image).
     * Exact SKU match first; then longest CP Master SKU that is a prefix of the PO SKU
     * (e.g. PO "PS FLR RB BLK 66" → CP "PS FLR RB BLK").
     */
    private function isBlankPoValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        $text = trim((string) $value);

        return $text === '' || $text === '0' || $text === '0.0' || $text === '0.00';
    }

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
     * Short Name from Short Title Master (`short_titles.short_title`) keyed by 5 Core SKU.
     *
     * @param  array<int, string>  $skus
     * @return array<string, string>
     */
    private function shortNamesBySku(array $skus): array
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

        $map = [];
        ShortTitle::query()
            ->whereIn('sku', $skuList)
            ->select(['sku', 'short_title'])
            ->get()
            ->each(function ($row) use (&$map) {
                $sku = trim((string) $row->sku);
                $title = trim((string) ($row->short_title ?? ''));
                if ($sku !== '' && $title !== '') {
                    $map[$sku] = $title;
                }
            });

        return $map;
    }

    private function cpMasterImagesBySku(array $skus): array
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

        $products = ProductMaster::query()
            ->whereIn('sku', $skuList)
            ->get(['sku', 'Values', 'main_image', 'image1']);

        $map = [];
        foreach ($products as $product) {
            $url = $this->resolveCpMasterImage($product);
            if ($url) {
                $map[trim((string) $product->sku)] = $url;
            }
        }

        $missing = array_values(array_filter($skuList, fn ($sku) => empty($map[$sku])));
        if ($missing === []) {
            return $map;
        }

        // Prefix fallback for sized variants not stored as exact CP Master SKUs.
        $candidates = ProductMaster::query()
            ->where(function ($q) use ($missing) {
                foreach ($missing as $sku) {
                    $parts = preg_split('/\s+/', $sku);
                    if (!$parts || count($parts) < 2) {
                        continue;
                    }
                    // Prefer matching on first 2–3 tokens to keep the query small.
                    $prefix = implode(' ', array_slice($parts, 0, min(3, count($parts) - 1)));
                    if ($prefix !== '') {
                        $q->orWhere('sku', 'like', $prefix . '%');
                    }
                }
            })
            ->get(['sku', 'Values', 'main_image', 'image1']);

        foreach ($missing as $poSku) {
            $bestSku = null;
            $bestLen = -1;
            $bestUrl = null;
            foreach ($candidates as $product) {
                $cpSku = trim((string) $product->sku);
                if ($cpSku === '' || strlen($cpSku) > strlen($poSku)) {
                    continue;
                }
                if ($poSku === $cpSku || str_starts_with($poSku, $cpSku . ' ')) {
                    $url = $this->resolveCpMasterImage($product);
                    if ($url && strlen($cpSku) > $bestLen) {
                        $bestLen = strlen($cpSku);
                        $bestSku = $cpSku;
                        $bestUrl = $url;
                    }
                }
            }
            if ($bestUrl) {
                $map[$poSku] = $bestUrl;
            }
        }

        return $map;
    }

    private function resolveCpMasterImage(ProductMaster $product): ?string
    {
        $candidates = [];
        $values = $this->productValuesArray($product);

        foreach (['image_path', 'image', 'Image', 'main_image'] as $key) {
            if (!empty($values[$key])) {
                $candidates[] = $values[$key];
                break;
            }
        }

        if (!empty($product->main_image)) {
            $candidates[] = $product->main_image;
        }
        if (!empty($product->image1)) {
            $candidates[] = $product->image1;
        }

        foreach ($candidates as $candidate) {
            $url = $this->normalizeImageUrl($candidate);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Map PO SKU => Masters Barcode image + code (Values.barcode_image / barcode / upc).
     * Same exact + prefix matching as product photos.
     *
     * @return array<string, array{image: ?string, code: ?string}>
     */
    private function cpMasterBarcodesBySku(array $skus): array
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

        $select = ['sku', 'Values'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('product_master', 'barcode')) {
            $select[] = 'barcode';
        }

        $map = [];
        $products = ProductMaster::query()->whereIn('sku', $skuList)->get($select);
        foreach ($products as $product) {
            $resolved = $this->resolveCpMasterBarcode($product);
            if ($resolved['image'] || $resolved['code']) {
                $map[trim((string) $product->sku)] = $resolved;
            }
        }

        $missing = array_values(array_filter($skuList, fn ($sku) => empty($map[$sku]['image'])));
        if ($missing === []) {
            return $map;
        }

        $candidates = ProductMaster::query()
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
            ->get($select);

        foreach ($missing as $poSku) {
            $bestLen = -1;
            $best = null;
            foreach ($candidates as $product) {
                $cpSku = trim((string) $product->sku);
                if ($cpSku === '' || strlen($cpSku) > strlen($poSku)) {
                    continue;
                }
                if ($poSku === $cpSku || str_starts_with($poSku, $cpSku . ' ')) {
                    $resolved = $this->resolveCpMasterBarcode($product);
                    if (($resolved['image'] || $resolved['code']) && strlen($cpSku) > $bestLen) {
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
