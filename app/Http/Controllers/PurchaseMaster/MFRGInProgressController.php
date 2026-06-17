<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\MfrgProgress;
use App\Models\PurchaseOrder;
use App\Models\ReadyToShip;
use App\Models\Supplier;
use App\Services\PurchasePageExecService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MFRGInProgressController extends Controller
{
    public function index()
    {
        $t0 = microtime(true);
        $supplierCache = self::getMipSupplierCache();
        $mfrgData = self::loadEnrichedMipProgressCollection(
            onlyTrashed: false,
            supplierCache: $supplierCache,
        );

        // Get Ready-to-Ship data - SIMPLE VERSION
        // Only rows still on Ready to Ship (transit_inv_status = 0); rows already moved to
        // transit are flagged transit_inv_status = 1 and must not appear on the MIP page.
        $readyToShipData = ReadyToShip::where('transit_inv_status', 0)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        // Mark RTS items simply.
        // NOTE: stage MUST be the canonical 'r2s' (with the digit 2), not 'rts'. The dropdown
        // editor saves 'r2s', the page's stage filter / color map / label map all key on 'r2s',
        // so emitting 'rts' here previously made every Ready-to-Ship row render with the gray
        // "Not set" dot and be invisible to the "R2S only" filter.
        foreach ($readyToShipData as $item) {
            $item->stage = 'r2s';
            $item->source_table = 'ready_to_ship';
            $item->nr = 'RTS';
            $item->order_qty = $item->qty ?? 0;
            $item->ready_to_ship = 'No'; // Set to 'No' so it won't be filtered out
            $item->mip_po_number = '';
            // Keep original fields: sku, supplier, zone, packing, terms, etc.
        }

        self::attachProductCpToRows($readyToShipData);

        // Combine both datasets
        $combinedData = $mfrgData->concat($readyToShipData);

        \Log::info('=== MIP + RTS FINAL DATA ===', [
            'mip_count' => $mfrgData->count(),
            'rts_count' => $readyToShipData->count(),
            'combined_total' => $combinedData->count(),
            'rts_sample' => $readyToShipData->take(2)->map(function($item) {
                return [
                    'sku' => $item->sku,
                    'stage' => $item->stage,
                    'source_table' => $item->source_table,
                    'nr' => $item->nr,
                    'ready_to_ship' => $item->ready_to_ship,
                ];
            }),
            'ms' => round((microtime(true) - $t0) * 1000, 2)
        ]);

        return view('purchase-master.mfrg-progress.index', [
            'data' => $combinedData,
            'suppliers' => $supplierCache['names'],
            'supplier_platforms_by_name' => $supplierCache['platformsByName'],
            'execOptions' => app(PurchasePageExecService::class)->getOptions(),
            'pageExec' => app(PurchasePageExecService::class)->getAssignment('mip') ?? '',
            'execCanEdit' => PurchasePageExecService::userCanEdit(),
        ]);
    }

    public function newMfrgView()
    {
        $allSuppliers = Supplier::query()
            ->where('type', 'Supplier')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        return view('purchase-master.mfrg-progress.mfrg-new', [
            'allSuppliers' => $allSuppliers,
        ]);
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
            supplierCache: $supplierCache,
        );

        // Also include Ready-to-Ship data if not archived.
        // Only rows still on Ready to Ship (transit_inv_status = 0); rows moved to transit
        // are flagged transit_inv_status = 1 and must drop off the MIP (MIP + R2S) page,
        // matching the Ready to Ship page query.
        if (!$archived) {
            $readyToShipData = ReadyToShip::where('transit_inv_status', 0)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Mark RTS items simply. See note on canonical 'r2s' value above.
            foreach ($readyToShipData as $item) {
                $item->stage = 'r2s';
                $item->source_table = 'ready_to_ship';
                $item->nr = 'RTS';
                $item->order_qty = $item->qty ?? 0;
                $item->ready_to_ship = 'No';
            }

            self::attachProductCpToRows($readyToShipData);

            $mfrgData = $mfrgData->concat($readyToShipData);
        } else {
            // Archived view: also show archived (soft-deleted) Ready-to-Ship rows so they
            // can be restored from "Show archived".
            $trashedRts = ReadyToShip::onlyTrashed()
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($trashedRts as $item) {
                $item->stage = 'r2s';
                $item->source_table = 'ready_to_ship';
                $item->nr = 'RTS';
                $item->order_qty = $item->qty ?? 0;
                $item->ready_to_ship = 'No';
            }

            self::attachProductCpToRows($trashedRts);

            $mfrgData = $mfrgData->concat($trashedRts);
        }

        // Attach exec to the full set (incl. Ready-to-Ship rows). Exec is keyed by SKU in
        // to_order_analysis, so RTS rows that aren't in mfrg_progress still show their saved exec.
        self::attachExecBySku($mfrgData);

        if (config('app.debug')) {
            Log::debug('mip.getMfrgProgressData', [
                'archived' => $archived,
                'rows' => $mfrgData->count(),
                'ms' => round((microtime(true) - $t0) * 1000, 2),
            ]);
        }

        // Explicitly forbid caching at every layer (browser HTTP cache, the PWA service
        // worker in public/sw.js, any proxy). This endpoint is the live source of truth
        // shared across users — User A's edit must be visible to User B on the next
        // refresh. Without these headers the browser was free to reuse a prior 200 from
        // disk because Laravel returns no Cache-Control by default on JSON responses,
        // which manifested as "two users seeing different data after a refresh".
        return response()->json([
            'data' => $mfrgData->values()->all(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * Contact / marketplace links for each supplier name (same fields as supplier list / view modal).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Supplier>  $supplierRows
     * @return array<string, list<array{label: string, url: ?string, external?: bool, display?: string}>>
     */
    private static function buildPlatformsByNameFromSupplierRows(Collection $supplierRows): array
    {
        $byName = [];
        foreach ($supplierRows as $s) {
            $name = trim((string) ($s->name ?? ''));
            if ($name === '') {
                continue;
            }
            $links = [];

            $website = trim((string) ($s->website ?? ''));
            if ($website !== '') {
                $url = preg_match('#^https?://#i', $website) ? $website : ('https://'.ltrim($website, '/'));
                $links[] = ['label' => 'Website', 'url' => $url, 'external' => true];
            }

            $email = trim((string) ($s->email ?? ''));
            if ($email !== '') {
                $links[] = ['label' => 'Email', 'url' => 'mailto:'.$email, 'external' => false];
            }

            $whatsapp = trim((string) ($s->whatsapp ?? ''));
            if ($whatsapp !== '') {
                $digits = preg_replace('/\D/', '', $whatsapp);
                if ($digits !== '') {
                    $links[] = ['label' => 'WhatsApp', 'url' => 'https://wa.me/'.$digits, 'external' => true];
                }
            }

            $wechat = trim((string) ($s->wechat ?? ''));
            if ($wechat !== '') {
                $links[] = ['label' => 'WeChat', 'url' => null, 'display' => $wechat];
            }

            $alibaba = trim((string) ($s->alibaba ?? ''));
            if ($alibaba !== '') {
                $url = preg_match('#^https?://#i', $alibaba) ? $alibaba : ('https://'.ltrim($alibaba, '/'));
                $links[] = ['label' => 'Alibaba', 'url' => $url, 'external' => true];
            }

            if ($links !== []) {
                $byName[$name] = $links;
            }
        }

        return $byName;
    }

    /**
     * @return array{map: array<string, list<string>>, names: \Illuminate\Support\Collection<int, string>, platformsByName: array<string, list<array{label: string, url: ?string, external?: bool, display?: string}>>}
     */
    private static function buildSupplierMapFromDb(): array
    {
        $supplierRows = Supplier::query()
            ->where('type', 'Supplier')
            ->orderBy('name')
            ->get(['name', 'parent', 'website', 'email', 'whatsapp', 'wechat', 'alibaba']);

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
            'platformsByName' => self::buildPlatformsByNameFromSupplierRows($supplierRows),
        ];
    }

    /**
     * Supplier parent map, names (dropdown), and platform links — always from DB (no cache), so MIP stays in sync with /supplier.list edits.
     *
     * @return array{map: array<string, list<string>>, names: \Illuminate\Support\Collection<int, string>, platformsByName: array<string, list<array{label: string, url: ?string, external?: bool, display?: string}>>}
     */
    private static function getMipSupplierCache(): array
    {
        return self::buildSupplierMapFromDb();
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
    /**
     * Attach `exec` to each row from to_order_analysis (single source of truth, keyed by SKU).
     * Works for any row that has a `sku`, including Ready-to-Ship rows not present in mfrg_progress.
     */
    private static function attachExecBySku(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $execBySku = [];
        // The same SKU can appear in multiple to_order_analysis rows (different parent
        // variants, normalization history, etc.). Without an explicit ORDER BY, MySQL
        // returned rows in storage order, so the "winning" exec was non-deterministic
        // and the page would intermittently show a freshly-saved exec as "Unassigned"
        // (or revert to a stale value) on the very next refresh.
        //
        // We now order by latest write first and skip blanks; the first non-empty exec
        // we see for a given SKU is what the user just saved, so it always wins.
        $execRows = DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->whereNotNull('exec')
            ->where('exec', '!=', '')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->select(['sku', 'exec'])
            ->get();
        foreach ($execRows as $r) {
            $k = strtoupper(trim((string) ($r->sku ?? '')));
            if ($k !== '' && !isset($execBySku[$k])) {
                $execBySku[$k] = $r->exec;
            }
        }

        foreach ($rows as $row) {
            $key = strtoupper(trim((string) ($row->sku ?? '')));
            $row->exec = $execBySku[$key] ?? null;
        }
    }

    private static function loadEnrichedMipProgressCollection(bool $onlyTrashed, array $supplierCache): Collection
    {
        $normalizeSku = fn (?string $sku) => self::normalizeMipSku($sku);
        $supplierMapByParent = $supplierCache['map'];

        // No explicit select(): some installs omit columns present in migrations (e.g. `value`),
        // and SELECT * only returns columns that exist — avoids SQLSTATE[42S22] unknown column.
        // Filter: only show items with qty > 0 AND ready_to_ship = 'No' or empty (matching ForecastAnalysisController logic)
        $q = MfrgProgress::query()
            ->where('qty', '>', 0)
            ->where(function($query) {
                $query->where('ready_to_ship', 'No')
                      ->orWhere('ready_to_ship', '')
                      ->orWhereNull('ready_to_ship');
            });
        $mfrgData = $onlyTrashed ? $q->onlyTrashed()->get() : $q->get();

        if ($mfrgData->isEmpty()) {
            return $mfrgData;
        }

        $forecastData = self::buildForecastDataMapForMipRows($mfrgData, $normalizeSku);
        $shopifyImageByKey = self::buildShopifyImageByKeyForMipRows($mfrgData, $normalizeSku);
        $productMasterByKey = self::buildProductMasterByKeyForMipRows($mfrgData, $normalizeSku);
        $skuToPriceMap = self::buildSkuToPriceMapForMipRows($mfrgData, $normalizeSku);
        $platformsByName = $supplierCache['platformsByName'] ?? [];

        // Filter out SKUs that don't exist in product_master (to match Forecast page behavior)
        // Forecast page only shows MIP for SKUs that exist in product_master table
        $mfrgData = $mfrgData->filter(function($row) use ($productMasterByKey, $normalizeSku) {
            $sku = $normalizeSku($row->sku ?? '');
            if ($sku === '') {
                return false;
            }
            // Check all SKU variations
            $skuVariations = self::mipRowSkuVariations($row->sku ?? null, $normalizeSku);
            foreach ($skuVariations as $skuVar) {
                if ($skuVar !== '' && isset($productMasterByKey[$skuVar])) {
                    return true; // SKU exists in product_master
                }
            }
            return false; // SKU not found in product_master
        });

        foreach ($mfrgData as $row) {
            self::enrichSingleMipProgressRow(
                $row,
                $normalizeSku,
                $forecastData,
                $shopifyImageByKey,
                $productMasterByKey,
                $supplierMapByParent,
                $skuToPriceMap,
                $platformsByName
            );
        }

        // Attach exec from to_order_analysis (single source of truth shared with to-order-analysis page)
        self::attachExecBySku($mfrgData);

        // Filter to show items with stage='mip' or stage='r2s' (exclude transit, all good, etc.)
        // The MIP In Progress page now lists both MIP and R2S stage rows from forecast_analysis.
        $mfrgData = $mfrgData->filter(function($row) {
            $rowStage = strtolower(trim($row->stage ?? ''));
            // Allow MIP, R2S, or empty (empty defaults to mip for items in mfrg_progress)
            return $rowStage === 'mip' || $rowStage === 'r2s' || $rowStage === '';
        });

        self::attachMipPoNumbers($mfrgData);

        return $mfrgData;
    }

    /**
     * Attach PO numbers from mfrg_progress_po (separate table).
     */
    private static function attachMipPoNumbers(Collection $mfrgData): void
    {
        $ids = $mfrgData->pluck('id')->filter()->unique()->values()->all();
        if ($ids === []) {
            foreach ($mfrgData as $row) {
                $row->mip_po_number = '';
            }

            return;
        }

        $poByMipId = DB::table('mfrg_progress_po')
            ->whereIn('mfrg_progress_id', $ids)
            ->get()
            ->keyBy('mfrg_progress_id');

        foreach ($mfrgData as $row) {
            $id = $row->id ?? null;
            $row->mip_po_number = ($id && isset($poByMipId[$id]))
                ? trim((string) $poByMipId[$id]->po_number)
                : '';
        }
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
        array $skuToPriceMap,
        array $platformsByName
    ): void {
        $sku = $normalizeSku($row->sku);
        $image = null;
        $cbm = null;
        $ctnCbmE = null;
        $parent = null;
        $supplierNames = [];
        $priceFromPO = null;
        $currencyFromPO = null;
        $productCp = null;

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
                // CP (cost price) stored in product_master Values->cp (also accept 'CP').
                $cpRaw = $values['cp'] ?? $values['CP'] ?? null;
                if ($cpRaw !== null && $cpRaw !== '' && is_numeric($cpRaw)) {
                    $productCp = (float) $cpRaw;
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

            // Get stage from forecast_analysis if available, otherwise default to 'mip'
            // This is used to filter out items that are in r2s or transit stage
            $stage = 'mip'; // Default to 'mip' if no forecast data
            $nr = '';
            if ($forecastData->has($sku)) {
                $forecast = $forecastData->get($sku);
                $nr = strtoupper(trim($forecast->nr ?? ''));
                $forecastStage = $forecast->stage ?? '';
                if (! empty($forecastStage)) {
                    $stage = strtolower(trim($forecastStage));
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
        $row->product_cp = $productCp;

        $supplierName = trim((string) ($row->supplier ?? ''));
        $row->supplier_platform_links = ($supplierName !== '' && isset($platformsByName[$supplierName]))
            ? $platformsByName[$supplierName]
            : [];
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

    /**
     * Aggressive SKU normalization for inline update lookup (Unicode spaces, NBSP, ZWSP, case, collapsed spaces).
     */
    private static function normalizeSkuForMipLookup(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }
        $s = (string) $sku;
        $s = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\r", "\n", "\t"], ' ', $s);
        // Thin space, narrow NBSP, figure space, etc. → regular space
        $s = preg_replace('/\p{Z}+/u', ' ', $s);
        $s = strtoupper(trim($s));
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }

    private static function normalizeSkuForMipLookupCompact(?string $sku): string
    {
        $n = self::normalizeSkuForMipLookup($sku);

        return $n === '' ? '' : preg_replace('/\s+/u', '', $n);
    }

    private static function normalizeSkuForMipLookupAlnum(?string $sku): string
    {
        $n = self::normalizeSkuForMipLookup($sku);

        return $n === '' ? '' : strtoupper(preg_replace('/[^A-Z0-9]/u', '', $n));
    }

    /**
     * Resolve MIP row by SKU: exact → TRIM+UPPER SQL → normalized → space-stripped → alphanumeric (hyphen/space drift).
     */
    private static function findMfrgProgressBySkuForInlineUpdate(?string $sku): ?MfrgProgress
    {
        if ($sku === null) {
            return null;
        }
        $sku = (string) $sku;
        if ($sku === '') {
            return null;
        }

        $byExact = MfrgProgress::query()->where('sku', $sku)->first();
        if ($byExact) {
            return $byExact;
        }

        $byTrimUpper = MfrgProgress::query()
            ->whereRaw('UPPER(TRIM(sku)) = UPPER(TRIM(?))', [$sku])
            ->first();
        if ($byTrimUpper) {
            return $byTrimUpper;
        }

        $needle = self::normalizeSkuForMipLookup($sku);
        if ($needle === '') {
            return null;
        }

        $needleCompact = self::normalizeSkuForMipLookupCompact($sku);
        $needleAlnum = self::normalizeSkuForMipLookupAlnum($sku);
        $useAlnum = strlen($needleAlnum) >= 10;

        foreach (MfrgProgress::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->cursor() as $candidate) {
            $raw = (string) ($candidate->sku ?? '');
            if (self::normalizeSkuForMipLookup($raw) === $needle) {
                return $candidate;
            }
            if ($needleCompact !== '' && self::normalizeSkuForMipLookupCompact($raw) === $needleCompact) {
                return $candidate;
            }
            if ($useAlnum && self::normalizeSkuForMipLookupAlnum($raw) === $needleAlnum) {
                return $candidate;
            }
        }

        return null;
    }

    public function inlineUpdateBySku(Request $request)
    {
        $skuRaw = $request->input('sku');
        $skuKey = (is_string($skuRaw) || is_int($skuRaw) || is_float($skuRaw)) ? (string) $skuRaw : null;
        $column = $request->input('column');

        $validColumns = [
            'advance_amt', 'pay_conf_date', 'o_links', 'adv_date', 'del_date', 'delivery_date', 'total_cbm',
            'barcode_sku', 'artwork_manual_book', 'notes', 'ready_to_ship', 'rate', 'rate_currency',
            'photo_packing', 'photo_int_sale', 'supplier', 'supplier_sku', 'created_at', 'qty',
            'pkg_inst', 'u_manual', 'compliance', 'exec',
        ];

        if (! in_array($column, $validColumns)) {
            return response()->json(['success' => false, 'message' => 'Invalid column.']);
        }

        // Ready-to-Ship rows live in their own table with an independent id space.
        // Their mip_id is a ready_to_ship.id, so routing it through MfrgProgress::find()
        // would match an unrelated SKU. Update the ready_to_ship row directly instead.
        if ((string) $request->input('source_table') === 'ready_to_ship') {
            return $this->inlineUpdateReadyToShip($request, $skuKey, $column);
        }

        $columnsAllowCreate = ['supplier', 'supplier_sku'];

        $progress = null;
        $mipIdRaw = $request->input('mip_id');
        if ($mipIdRaw !== null && $mipIdRaw !== '' && ctype_digit((string) $mipIdRaw)) {
            $rowById = MfrgProgress::query()->find((int) $mipIdRaw);
            if ($rowById) {
                if ($skuKey !== null && $skuKey !== ''
                    && self::normalizeSkuForMipLookup((string) $rowById->sku) !== self::normalizeSkuForMipLookup($skuKey)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Row does not match this SKU — refresh the page.',
                    ], 409);
                }
                $progress = $rowById;
            }
        }

        if (! $progress) {
            $progress = self::findMfrgProgressBySkuForInlineUpdate($skuKey);
        }
        if (! $progress) {
            if (in_array($column, $columnsAllowCreate) && $skuKey !== null && $skuKey !== '') {
                $progress = new MfrgProgress;
                $progress->sku = $skuKey;
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
        try {
            $progress->{$column} = $value;
            $progress->save();
        } catch (\Throwable $e) {
            Log::warning('mip.inlineUpdateBySku.save_failed', [
                'sku' => $skuKey,
                'column' => $column,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Save failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Inline update for a Ready-to-Ship row (its mip_id is a ready_to_ship.id, not a mfrg_progress.id).
     */
    private function inlineUpdateReadyToShip(Request $request, ?string $skuKey, string $column)
    {
        $rts = null;
        $mipIdRaw = $request->input('mip_id');
        if ($mipIdRaw !== null && $mipIdRaw !== '' && ctype_digit((string) $mipIdRaw)) {
            $rts = ReadyToShip::query()->find((int) $mipIdRaw);
        }
        if (! $rts && $skuKey !== null && $skuKey !== '') {
            $rts = ReadyToShip::query()
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim($skuKey))])
                ->first();
        }
        if (! $rts) {
            return response()->json(['success' => false, 'message' => 'Ready-to-Ship row not found.']);
        }

        if (! Schema::hasColumn('ready_to_ship', $column)) {
            return response()->json(['success' => false, 'message' => 'This field cannot be edited for a Ready-to-Ship row.']);
        }

        $value = $request->input('value');
        if ($column === 'qty') {
            $value = is_numeric($value) ? (float) $value : 0;
        }
        if (in_array($column, ['delivery_date', 'created_at'], true) && ($value === '' || $value === null)) {
            $value = null;
        }

        try {
            $rts->{$column} = $value;
            $rts->save();
        } catch (\Throwable $e) {
            Log::warning('mip.inlineUpdateReadyToShip.save_failed', [
                'sku' => $skuKey,
                'column' => $column,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Save failed: '.$e->getMessage()], 500);
        }

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
            // Preferred: archive specific rows by id + source table (only the selected rows,
            // even when several rows share the same SKU).
            $items = $request->input('items', []);
            if (is_array($items) && ! empty($items)) {
                $archivedCount = 0;
                foreach ($items as $item) {
                    $id = (int) ($item['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $source = ($item['source'] ?? '') === 'ready_to_ship' ? 'ready_to_ship' : 'mfrg_progress';
                    if ($source === 'ready_to_ship') {
                        $archivedCount += ReadyToShip::query()->where('id', $id)->delete();
                    } else {
                        $archivedCount += MfrgProgress::query()->where('id', $id)->delete();
                    }
                }

                return response()->json([
                    'success' => true,
                    'deleted_count' => $archivedCount,
                    'message' => $archivedCount > 0
                        ? "Archived {$archivedCount} row(s). You can restore them from “Show archived”."
                        : 'No matching rows to archive.',
                ]);
            }

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

                // MIP page also lists Ready-to-Ship rows; archive those too (soft delete).
                $archivedCount += ReadyToShip::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$ns])
                    ->where('transit_inv_status', 0)
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
            // Preferred: restore specific rows by id + source table.
            $items = $request->input('items', []);
            if (is_array($items) && ! empty($items)) {
                $restoredCount = 0;
                foreach ($items as $item) {
                    $id = (int) ($item['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $source = ($item['source'] ?? '') === 'ready_to_ship' ? 'ready_to_ship' : 'mfrg_progress';
                    if ($source === 'ready_to_ship') {
                        $row = ReadyToShip::onlyTrashed()->find($id);
                    } else {
                        $row = MfrgProgress::onlyTrashed()->find($id);
                    }
                    if ($row) {
                        $row->restore();
                        $restoredCount++;
                    }
                }

                return response()->json([
                    'success' => true,
                    'restored_count' => $restoredCount,
                    'message' => $restoredCount > 0
                        ? "Restored {$restoredCount} row(s)."
                        : 'No archived rows matched.',
                ]);
            }

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

                // Restore archived Ready-to-Ship rows as well.
                $rtsRows = ReadyToShip::onlyTrashed()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$ns])
                    ->get();
                foreach ($rtsRows as $rtsRow) {
                    $rtsRow->restore();
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

    public function bulkUpdateDeliveryDate(Request $request)
    {
        try {
            $skus = $request->input('skus', []);
            $deliveryDate = $request->input('delivery_date');

            if (empty($skus) || ! is_array($skus)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SKUs provided.',
                ], 400);
            }

            if (empty($deliveryDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery date is required.',
                ], 400);
            }

            $normalizedSkus = array_map(function ($sku) {
                return strtoupper(trim((string) $sku));
            }, $skus);

            $updatedCount = 0;
            foreach ($normalizedSkus as $ns) {
                if ($ns === '') {
                    continue;
                }
                $updated = MfrgProgress::query()
                    ->whereNull('deleted_at')
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$ns])
                    ->update([
                        'delivery_date' => $deliveryDate,
                        'updated_at' => now(),
                    ]);
                $updatedCount += $updated;
            }

            return response()->json([
                'success' => true,
                'updated_count' => $updatedCount,
                'message' => "Updated delivery date for {$updatedCount} row(s).",
            ]);
        } catch (\Exception $e) {
            Log::error('Error bulk updating delivery dates: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getAllSuppliersFollowup()
    {
        try {
            // Get all suppliers
            $suppliers = Supplier::where('type', 'Supplier')
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->orderBy('name')
                ->get(['name', 'next_followup']);

            $result = [];

            // Check if supplier_remarks table exists
            $remarksTableExists = DB::getSchemaBuilder()->hasTable('supplier_remarks');

            foreach ($suppliers as $supplier) {
                $supplierName = trim($supplier->name);
                $latestRemark = null;
                $remarkCount = 0;

                // Only query remarks if table exists
                if ($remarksTableExists) {
                    try {
                        $latestRemark = DB::table('supplier_remarks')
                            ->where('supplier_name', $supplierName)
                            ->orderByDesc('created_at')
                            ->first();

                        $remarkCount = DB::table('supplier_remarks')
                            ->where('supplier_name', $supplierName)
                            ->count();
                    } catch (\Exception $e) {
                        // Silently handle if remarks query fails
                        Log::debug('Could not query supplier_remarks for '.$supplierName);
                    }
                }

                $result[] = [
                    'name' => $supplierName,
                    'next_followup' => $supplier->next_followup ? \Carbon\Carbon::parse($supplier->next_followup)->format('Y-m-d') : '',
                    'latest_remark' => $latestRemark ? htmlspecialchars($latestRemark->remark ?? '') : '',
                    'last_updated' => $latestRemark ? \Carbon\Carbon::parse($latestRemark->created_at)->format('d M Y, h:i A') : '',
                    'remark_count' => $remarkCount,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting all suppliers followup: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateSupplierNextFollowup(Request $request)
    {
        try {
            $supplierName = $request->input('supplier_name');
            $nextFollowup = $request->input('next_followup');

            if (empty($supplierName)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier name is required.',
                ], 400);
            }

            $supplier = Supplier::where('name', $supplierName)
                ->where('type', 'Supplier')
                ->first();

            if (! $supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier not found.',
                ], 404);
            }

            $supplier->next_followup = $nextFollowup ?: null;
            $supplier->save();

            return response()->json([
                'success' => true,
                'message' => 'Next follow-up date updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating supplier next followup: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
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

        // Fallback: try upper-cased variants via a second targeted whereIn (avoids full table scan)
        $missingCandidates = array_values(array_filter($candidates, function ($c) use ($productMasterByKey) {
            return $c !== '' && ! isset($productMasterByKey[$c]);
        }));
        foreach (array_chunk($missingCandidates, 450) as $chunk) {
            $chunk = array_values(array_filter($chunk, fn ($s) => $s !== ''));
            if ($chunk === []) continue;
            foreach (DB::table('product_master')
                ->whereIn(DB::raw('UPPER(TRIM(sku))'), $chunk)
                ->select('sku', 'parent', 'Values')
                ->get() as $item) {
                $norm = $normalizeSku($item->sku);
                if ($norm === '') continue;
                foreach (array_unique([$norm, str_replace(' ', '', $norm)]) as $k) {
                    if ($k !== '' && ! isset($productMasterByKey[$k])) {
                        $productMasterByKey[$k] = $item;
                    }
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
        if (empty($candidates)) {
            return [];
        }
        $mipKeySet = array_fill_keys($candidates, true);
        $shopifyImageByKey = [];

        foreach (DB::table('shopify_skus')
            ->select('sku', 'image_src')
            ->whereNotNull('image_src')
            ->where('image_src', '!=', '')
            ->whereIn('sku', $candidates)
            ->get() as $item) {
            $norm = $normalizeSku($item->sku);
            if ($norm === '') {
                continue;
            }
            $keys = array_unique(array_filter([$norm, str_replace(' ', '', $norm)]));
            foreach ($keys as $k) {
                if ($k !== '' && ! isset($shopifyImageByKey[$k])) {
                    $shopifyImageByKey[$k] = $item->image_src;
                }
            }
        }

        return $shopifyImageByKey;
    }

    /**
     * @param  iterable<int, object>  $mfrgRows
     */
    /**
     * Attach product_master CP (Values->cp) to Ready-to-Ship rows so the MIP page CP column
     * is populated for SKUs that have a cost price in /product-master.
     */
    private static function attachProductCpToRows(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $normalizeSku = fn (?string $sku) => self::normalizeMipSku($sku);

        $candidates = [];
        foreach ($rows as $r) {
            $n = $normalizeSku($r->sku ?? '');
            if ($n !== '') {
                $candidates[$n] = true;
                $candidates[str_replace(' ', '', $n)] = true;
            }
        }
        $candidates = array_keys($candidates);

        $cpByKey = [];
        foreach (array_chunk($candidates, 450) as $chunk) {
            $chunk = array_values(array_filter($chunk, fn ($s) => $s !== ''));
            if ($chunk === []) {
                continue;
            }
            foreach (DB::table('product_master')->whereIn('sku', $chunk)->select('sku', 'Values')->get() as $item) {
                $values = json_decode($item->Values ?? '{}', true);
                $cpRaw = is_array($values) ? ($values['cp'] ?? $values['CP'] ?? null) : null;
                if ($cpRaw === null || $cpRaw === '' || ! is_numeric($cpRaw)) {
                    continue;
                }
                $norm = $normalizeSku($item->sku);
                foreach (array_unique([$norm, str_replace(' ', '', $norm)]) as $k) {
                    if ($k !== '' && ! isset($cpByKey[$k])) {
                        $cpByKey[$k] = (float) $cpRaw;
                    }
                }
            }
        }

        foreach ($rows as $r) {
            $n = $normalizeSku($r->sku ?? '');
            $r->product_cp = $cpByKey[$n] ?? $cpByKey[str_replace(' ', '', $n)] ?? null;
        }
    }

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
            ->limit(300)
            ->get() as $po) {
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

    /**
     * Enrich Ready-to-Ship data to match MIP format
     */
    private static function enrichReadyToShipData(Collection $rtsData, array $supplierCache): Collection
    {
        if ($rtsData->isEmpty()) {
            return $rtsData;
        }

        $normalizeSku = fn (?string $sku) => self::normalizeMipSku($sku);
        
        try {
            $forecastData = self::buildForecastDataMapForMipRows($rtsData, $normalizeSku);
            $shopifyImageByKey = self::buildShopifyImageByKeyForMipRows($rtsData, $normalizeSku);
            $productMasterByKey = self::buildProductMasterByKeyForMipRows($rtsData, $normalizeSku);
            $skuToPriceMap = self::buildSkuToPriceMapForMipRows($rtsData, $normalizeSku);
        } catch (\Exception $e) {
            Log::warning('RTS enrichment partial failure', ['error' => $e->getMessage()]);
            $forecastData = collect();
            $shopifyImageByKey = [];
            $productMasterByKey = [];
            $skuToPriceMap = [];
        }
        
        $platformsByName = $supplierCache['platformsByName'] ?? [];

        foreach ($rtsData as $row) {
            // Mark as RTS FIRST (canonical stage = 'r2s' so the front-end color map,
            // tooltip label and "R2S only" filter all match — see note above).
            $row->stage = 'r2s';
            $row->source_table = 'ready_to_ship';
            $row->order_qty = $row->qty ?? 0;
            $row->nr = 'RTS';
            
            // Basic enrichment
            $sku = $normalizeSku($row->sku);
            
            // Image
            if ($sku && isset($shopifyImageByKey[$sku])) {
                $row->Image = $shopifyImageByKey[$sku];
            } else {
                $row->Image = null;
            }
            
            // CBM and other product master data
            if ($sku && isset($productMasterByKey[$sku])) {
                $productRow = $productMasterByKey[$sku];
                $values = json_decode($productRow->Values ?? '{}', true);
                if (is_array($values)) {
                    $row->CBM = $values['cbm'] ?? $row->cbm ?? null;
                    $row->ctn_cbm_e = $values['CBM E'] ?? $values['cbm_e'] ?? null;
                }
            }
            
            // Price from PO
            if ($sku && isset($skuToPriceMap[$sku])) {
                $row->price_from_po = $skuToPriceMap[$sku]['price'];
                $row->currency_from_po = $skuToPriceMap[$sku]['currency'];
            }
            
            // Supplier platform links
            $supplierName = trim((string) ($row->supplier ?? ''));
            $row->supplier_platform_links = ($supplierName !== '' && isset($platformsByName[$supplierName]))
                ? $platformsByName[$supplierName]
                : [];
        }

        return $rtsData;
    }
}
