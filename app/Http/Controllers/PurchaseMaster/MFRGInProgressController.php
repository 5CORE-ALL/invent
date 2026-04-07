<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\MfrgProgress;
use App\Models\PurchaseOrder;
use App\Models\ReadyToShip;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MFRGInProgressController extends Controller
{
    /** Cache key for supplier parent map + name list (invalidates on TTL). */
    private const SUPPLIER_CACHE_KEY = 'mfrg_mip_suppliers_struct_v1';

    private const SUPPLIER_CACHE_TTL_SECONDS = 120;

    public function index()
    {
        $t0 = microtime(true);
        $supplierCache = self::getMipSupplierCache();
        $mfrgData = self::loadEnrichedMipProgressCollection(
            onlyTrashed: false,
            supplierMapByParent: $supplierCache['map'],
        );

        if (config('app.debug')) {
            Log::debug('mip.index enriched', ['ms' => round((microtime(true) - $t0) * 1000, 2)]);
        }

        return view('purchase-master.mfrg-progress.index', [
            'data' => $mfrgData,
            'suppliers' => $supplierCache['names'],
        ]);
    }

    public function newMfrgView()
    {
        return view('purchase-master.mfrg-progress.mfrg-new');
    }

    public function archivedMfrgCount()
    {
        return response()->json([
            'count' => MfrgProgress::onlyTrashed()->count(),
        ]);
    }

    public function getMfrgProgressData()
    {
        $t0 = microtime(true);
        $archived = request()->boolean('archived');
        $supplierCache = self::getMipSupplierCache();
        $mfrgData = self::loadEnrichedMipProgressCollection(
            onlyTrashed: $archived,
            supplierMapByParent: $supplierCache['map'],
        );

        if (config('app.debug')) {
            Log::debug('mip.getMfrgProgressData', [
                'archived' => $archived,
                'rows' => $mfrgData->count(),
                'ms' => round((microtime(true) - $t0) * 1000, 2),
            ]);
        }

        return response()->json([
            'data' => $mfrgData->values()->all(),
        ]);
    }

    /**
     * @return array{map: array<string, list<string>>, names: \Illuminate\Support\Collection<int, string>}
     */
    private static function buildSupplierMapFromDb(): array
    {
        $supplierRows = Supplier::query()
            ->where('type', 'Supplier')
            ->orderBy('name')
            ->get(['name', 'parent']);

        $supplierMapByParent = [];
        foreach ($supplierRows as $srow) {
            $parents = array_map('trim', explode(',', strtoupper($srow->parent ?? '')));
            foreach ($parents as $parent) {
                if ($parent !== '') {
                    $supplierMapByParent[$parent][] = $srow->name;
                }
            }
        }

        return [
            'map' => $supplierMapByParent,
            'names' => $supplierRows->pluck('name')->values(),
        ];
    }

    /**
     * @return array{map: array<string, list<string>>, names: \Illuminate\Support\Collection<int, string>}
     */
    private static function getMipSupplierCache(): array
    {
        return Cache::remember(
            self::SUPPLIER_CACHE_KEY,
            self::SUPPLIER_CACHE_TTL_SECONDS,
            fn () => self::buildSupplierMapFromDb()
        );
    }

    /**
     * Forecast rows only for SKU variants present on MIP rows (replaces full-table scan).
     */
    private static function buildForecastDataMapForMipRows(iterable $mfrgRows, callable $normalizeSku): Collection
    {
        $candidates = self::collectMipSkuCandidates($mfrgRows, $normalizeSku);
        if ($candidates === []) {
            return collect();
        }

        $buckets = collect();
        foreach (array_chunk($candidates, 400) as $chunk) {
            $chunk = array_values(array_filter($chunk, fn ($s) => $s !== ''));
            if ($chunk === []) {
                continue;
            }
            $rows = DB::table('forecast_analysis')
                ->select('sku', 'stage', 'nr')
                ->whereIn('sku', $chunk)
                ->get();
            foreach ($rows as $item) {
                $key = $normalizeSku($item->sku);
                if ($key === '') {
                    continue;
                }
                if (! $buckets->has($key)) {
                    $buckets->put($key, collect());
                }
                $buckets->get($key)->push($item);
            }
        }

        return $buckets->map(function (Collection $group) {
            $withStage = $group->firstWhere('stage', '!=', null);
            if ($withStage && ! empty(trim((string) $withStage->stage))) {
                return $withStage;
            }

            return $group->first();
        });
    }

    /**
     * Load MfrgProgress rows with shared enrichment (index blade + Tabulator JSON).
     */
    private static function loadEnrichedMipProgressCollection(bool $onlyTrashed, array $supplierMapByParent): Collection
    {
        $normalizeSku = fn (?string $sku) => self::normalizeMipSku($sku);

        // No explicit select(): some installs omit columns present in migrations (e.g. `value`),
        // and SELECT * only returns columns that exist — avoids SQLSTATE[42S22] unknown column.
        $q = MfrgProgress::query();
        $mfrgData = $onlyTrashed ? $q->onlyTrashed()->get() : $q->get();

        if ($mfrgData->isEmpty()) {
            return $mfrgData;
        }

        $forecastData = self::buildForecastDataMapForMipRows($mfrgData, $normalizeSku);
        $shopifyImageByKey = self::buildShopifyImageByKeyForMipRows($mfrgData, $normalizeSku);
        $productMasterByKey = self::buildProductMasterByKeyForMipRows($mfrgData, $normalizeSku);
        $skuToPriceMap = self::buildSkuToPriceMapForMipRows($mfrgData, $normalizeSku);

        foreach ($mfrgData as $row) {
            self::enrichSingleMipProgressRow(
                $row,
                $normalizeSku,
                $forecastData,
                $shopifyImageByKey,
                $productMasterByKey,
                $supplierMapByParent,
                $skuToPriceMap
            );
        }

        return $mfrgData;
    }

    /**
     * Mutates the model: Image, CBM, stage, nr, supplier default, PO price, etc.
     */
    private static function enrichSingleMipProgressRow(
        object $row,
        callable $normalizeSku,
        Collection $forecastData,
        array $shopifyImageByKey,
        array $productMasterByKey,
        array $supplierMapByParent,
        array $skuToPriceMap
    ): void {
        $sku = $normalizeSku($row->sku);
        $image = null;
        $cbm = null;
        $ctnCbmE = null;
        $parent = null;
        $supplierNames = [];
        $priceFromPO = null;
        $currencyFromPO = null;

        $skuVariations = self::mipRowSkuVariations($row->sku ?? null, $normalizeSku);

        foreach ($skuVariations as $skuVar) {
            if ($skuVar !== '' && isset($shopifyImageByKey[$skuVar])) {
                $image = $shopifyImageByKey[$skuVar];
                break;
            }
        }

        $productRow = null;
        foreach ($skuVariations as $skuVar) {
            if ($skuVar !== '' && isset($productMasterByKey[$skuVar])) {
                $productRow = $productMasterByKey[$skuVar];
                break;
            }
        }

        if ($productRow) {
            $values = json_decode($productRow->Values ?? '{}', true);

            if (is_array($values)) {
                if (! empty($values['image_path']) && empty($image)) {
                    $image = 'storage/'.ltrim($values['image_path'], '/');
                }
                if (isset($values['cbm'])) {
                    $cbm = $values['cbm'];
                }
                if (isset($values['CBM E'])) {
                    $ctnCbmE = $values['CBM E'];
                } elseif (isset($values['cbm_e'])) {
                    $ctnCbmE = $values['cbm_e'];
                } elseif (isset($values['ctn_cbm_e'])) {
                    $ctnCbmE = $values['ctn_cbm_e'];
                }
            }

            $parent = strtoupper(trim($productRow->parent ?? ''));
        }

        if (! empty($parent) && isset($supplierMapByParent[$parent])) {
            $supplierNames = $supplierMapByParent[$parent];
        }

        if (empty($row->supplier)) {
            $row->supplier = implode(', ', $supplierNames);
        }

        if (isset($skuToPriceMap[$sku])) {
            $priceFromPO = $skuToPriceMap[$sku]['price'];
            $currencyFromPO = $skuToPriceMap[$sku]['currency'];
        }

        $stage = '';
        $nr = '';
        if ($forecastData->has($sku)) {
            $forecast = $forecastData->get($sku);
            $stage = $forecast->stage ?? '';
            $nr = strtoupper(trim($forecast->nr ?? ''));
            if (! empty($stage)) {
                $stage = strtolower(trim($stage));
            }
        }

        $row->stage = $stage;
        $row->nr = $nr;
        $row->order_qty = $row->qty;
        $row->Image = ! empty($image) ? $image : null;
        $row->CBM = $cbm;
        $row->ctn_cbm_e = $ctnCbmE;
        $row->price_from_po = $priceFromPO;
        $row->currency_from_po = $currencyFromPO;
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

    public function inlineUpdateBySku(Request $request)
    {
        $sku = $request->input('sku');
        $column = $request->input('column');

        $validColumns = [
            'advance_amt', 'pay_conf_date', 'o_links', 'adv_date', 'del_date', 'delivery_date', 'total_cbm',
            'barcode_sku', 'artwork_manual_book', 'notes', 'ready_to_ship', 'rate', 'rate_currency',
            'photo_packing', 'photo_int_sale', 'supplier', 'supplier_sku', 'created_at', 'qty',
            'pkg_inst', 'u_manual', 'compliance',
        ];

        if (! in_array($column, $validColumns)) {
            return response()->json(['success' => false, 'message' => 'Invalid column.']);
        }

        $columnsAllowCreate = ['supplier', 'supplier_sku'];
        $progress = MfrgProgress::where('sku', $sku)->first();
        if (! $progress) {
            if (in_array($column, $columnsAllowCreate)) {
                $progress = new MfrgProgress;
                $progress->sku = $sku;
            } else {
                return response()->json(['success' => false, 'message' => 'SKU not found.']);
            }
        }

        if ($request->hasFile('value') && in_array($column, ['photo_packing', 'photo_int_sale', 'barcode_sku'])) {
            $file = $request->file('value');
            $filename = uniqid().'_'.time().'.'.$file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/mfrg_images');

            if (! file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $file->move($destinationPath, $filename);
            $url = url("uploads/mfrg_images/{$filename}");

            $progress->{$column} = $url;
            $progress->save();

            return response()->json(['success' => true, 'url' => $url]);
        }

        if ($column === 'advance_amt') {
            if (! $progress->supplier) {
                return response()->json(['success' => false, 'message' => 'Supplier not found.']);
            }

            MfrgProgress::where('supplier', $progress->supplier)->update([
                'advance_amt' => $request->input('value'),
            ]);

            return response()->json(['success' => true, 'message' => 'Advance updated.']);
        }

        $value = $request->input('value');
        if ($column === 'qty') {
            $value = is_numeric($value) ? (float) $value : 0;
        }
        if ($column === 'delivery_date' && ($value === '' || $value === null)) {
            $value = null;
        }
        $progress->{$column} = $value;
        $progress->save();

        return response()->json(['success' => true]);
    }

    public function storeDataReadyToShip(Request $request)
    {
        try {
            $data = [
                'supplier' => $request->supplier,
                'cbm' => $request->totalCbm,
                'qty' => $request->qty,
                'rate' => $request->rate,
                'transit_inv_status' => 0,
            ];

            $readyToShip = ReadyToShip::where('parent', $request->parent)
                ->where('sku', $request->sku)
                ->first();

            if ($readyToShip) {
                $readyToShip->update($data);
            } else {
                ReadyToShip::create(array_merge([
                    'parent' => $request->parent,
                    'sku' => $request->sku,
                ], $data));
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteBySkus(Request $request)
    {
        try {
            $skus = $request->input('skus', []);

            if (empty($skus) || ! is_array($skus)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SKUs provided for archiving.',
                ], 400);
            }

            $normalizedSkus = array_map(function ($sku) {
                return strtoupper(trim((string) $sku));
            }, $skus);

            $archivedCount = 0;
            foreach ($normalizedSkus as $ns) {
                if ($ns === '') {
                    continue;
                }
                $archivedCount += MfrgProgress::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$ns])
                    ->delete();
            }

            return response()->json([
                'success' => true,
                'deleted_count' => $archivedCount,
                'message' => $archivedCount > 0
                    ? "Archived {$archivedCount} row(s). You can restore them from “Show archived”."
                    : 'No matching rows to archive.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error archiving MFRG Progress records: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while archiving: '.$e->getMessage(),
            ], 500);
        }
    }

    public function restoreBySkus(Request $request)
    {
        try {
            $skus = $request->input('skus', []);

            if (empty($skus) || ! is_array($skus)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SKUs provided for restore.',
                ], 400);
            }

            $normalizedSkus = array_map(function ($sku) {
                return strtoupper(trim((string) $sku));
            }, $skus);

            $restoredCount = 0;
            foreach ($normalizedSkus as $ns) {
                if ($ns === '') {
                    continue;
                }
                $rows = MfrgProgress::onlyTrashed()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$ns])
                    ->get();
                foreach ($rows as $row) {
                    $row->restore();
                    $restoredCount++;
                }
            }

            return response()->json([
                'success' => true,
                'restored_count' => $restoredCount,
                'message' => $restoredCount > 0
                    ? "Restored {$restoredCount} row(s)."
                    : 'No archived rows matched those SKUs.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error restoring MFRG Progress records: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while restoring: '.$e->getMessage(),
            ], 500);
        }
    }

    private static function normalizeMipSku(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\r", "\n", "\t"], ' ', $sku);
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku);
        $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);

        return trim($sku);
    }

    /**
     * @param  iterable<int, object>  $mfrgRows
     */
    private static function collectMipSkuCandidates(iterable $mfrgRows, callable $normalizeSku): array
    {
        $candidates = [];
        foreach ($mfrgRows as $row) {
            $rawSku = $row->sku ?? '';
            $raw = trim((string) $rawSku);
            if ($raw !== '') {
                $candidates[$raw] = true;
            }
            $n = $normalizeSku($rawSku);
            if ($n !== '') {
                $candidates[$n] = true;
                $candidates[str_replace(' ', '', $n)] = true;
                $candidates[preg_replace('/\s+/', ' ', $n)] = true;
            }
            if ($raw !== '') {
                $candidates[strtoupper($raw)] = true;
                $candidates[strtoupper(preg_replace('/\s+/', ' ', $raw))] = true;
            }
        }

        return array_keys($candidates);
    }

    private static function mipRowSkuVariations(?string $rawSku, callable $normalizeSku): array
    {
        $sku = $normalizeSku($rawSku ?? '');
        $var = [
            $sku,
            str_replace(' ', '', $sku),
            preg_replace('/\s+/', ' ', $sku),
        ];
        $t = trim((string) $rawSku);
        if ($t !== '') {
            $var[] = strtoupper($t);
            $var[] = strtoupper(preg_replace('/\s+/', ' ', $t));
        }

        return array_values(array_unique(array_filter($var)));
    }

    /**
     * @param  iterable<int, object>  $mfrgRows
     */
    private static function anyMipRowMissingProductMaster(iterable $mfrgRows, array $productMasterByKey, callable $normalizeSku): bool
    {
        foreach ($mfrgRows as $row) {
            $vars = self::mipRowSkuVariations($row->sku ?? null, $normalizeSku);
            $found = false;
            foreach ($vars as $skuVar) {
                if ($skuVar !== '' && isset($productMasterByKey[$skuVar])) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  iterable<int, object>  $mfrgRows
     */
    private static function buildProductMasterByKeyForMipRows(iterable $mfrgRows, callable $normalizeSku): array
    {
        $candidates = self::collectMipSkuCandidates($mfrgRows, $normalizeSku);
        $productMasterByKey = [];

        foreach (array_chunk($candidates, 450) as $chunk) {
            $chunk = array_values(array_filter($chunk, fn ($s) => $s !== ''));
            if ($chunk === []) {
                continue;
            }
            foreach (DB::table('product_master')->whereIn('sku', $chunk)->select('sku', 'parent', 'Values')->get() as $item) {
                $norm = $normalizeSku($item->sku);
                if ($norm === '') {
                    continue;
                }
                foreach (array_unique([$norm, str_replace(' ', '', $norm)]) as $k) {
                    if ($k !== '' && ! isset($productMasterByKey[$k])) {
                        $productMasterByKey[$k] = $item;
                    }
                }
            }
        }

        if (! self::anyMipRowMissingProductMaster($mfrgRows, $productMasterByKey, $normalizeSku)) {
            return $productMasterByKey;
        }

        $mipKeySet = array_fill_keys($candidates, true);
        foreach (DB::table('product_master')->select('sku', 'parent', 'Values')->cursor() as $item) {
            $norm = $normalizeSku($item->sku);
            if ($norm === '') {
                continue;
            }
            $keys = array_unique(array_filter([$norm, str_replace(' ', '', $norm)]));
            $hit = false;
            foreach ($keys as $k) {
                if (isset($mipKeySet[$k])) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                continue;
            }
            foreach ($keys as $k) {
                if ($k !== '' && ! isset($productMasterByKey[$k])) {
                    $productMasterByKey[$k] = $item;
                }
            }
        }

        return $productMasterByKey;
    }

    /**
     * @param  iterable<int, object>  $mfrgRows
     */
    private static function buildShopifyImageByKeyForMipRows(iterable $mfrgRows, callable $normalizeSku): array
    {
        $candidates = self::collectMipSkuCandidates($mfrgRows, $normalizeSku);
        $mipKeySet = array_fill_keys($candidates, true);
        $shopifyImageByKey = [];

        foreach (DB::table('shopify_skus')
            ->select('sku', 'image_src')
            ->whereNotNull('image_src')
            ->where('image_src', '!=', '')
            ->cursor() as $item) {
            $norm = $normalizeSku($item->sku);
            if ($norm === '') {
                continue;
            }
            $keys = array_unique(array_filter([$norm, str_replace(' ', '', $norm)]));
            $hit = false;
            foreach ($keys as $k) {
                if (isset($mipKeySet[$k])) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                continue;
            }
            $src = $item->image_src;
            foreach ($keys as $k) {
                if ($k !== '' && ! isset($shopifyImageByKey[$k])) {
                    $shopifyImageByKey[$k] = $src;
                }
            }
        }

        return $shopifyImageByKey;
    }

    /**
     * @param  iterable<int, object>  $mfrgRows
     */
    private static function buildSkuToPriceMapForMipRows(iterable $mfrgRows, callable $normalizeSku): array
    {
        $mipSkuSet = [];
        foreach ($mfrgRows as $row) {
            $n = $normalizeSku($row->sku ?? '');
            if ($n !== '') {
                $mipSkuSet[$n] = true;
            }
        }
        if ($mipSkuSet === []) {
            return [];
        }
        $needed = count($mipSkuSet);
        $skuToPriceMap = [];

        foreach (PurchaseOrder::query()
            ->whereNotNull('items')
            ->select(['items', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->cursor() as $po) {
            $items = json_decode($po->items, true);
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (! isset($item['sku'], $item['price'])) {
                    continue;
                }
                $normalizedSku = $normalizeSku($item['sku']);
                if ($normalizedSku === '' || ! isset($mipSkuSet[$normalizedSku])) {
                    continue;
                }
                if (isset($skuToPriceMap[$normalizedSku])) {
                    continue;
                }
                $skuToPriceMap[$normalizedSku] = [
                    'price' => $item['price'],
                    'currency' => $item['currency'] ?? 'USD',
                    'date' => $po->created_at,
                ];
            }
            if (count($skuToPriceMap) >= $needed) {
                break;
            }
        }

        return $skuToPriceMap;
    }
}
