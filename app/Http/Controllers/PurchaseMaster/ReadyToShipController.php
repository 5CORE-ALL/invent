<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\MfrgProgress;
use App\Models\ReadyToShip;
use App\Services\ReadyToShipPackingListSheetService;
use App\Models\Supplier;
use App\Models\TransitContainerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReadyToShipController extends Controller
{
    /**
     * Active ready_to_ship rows belong on the R2S page even when forecast stage is
     * "transit" (same SKU can be split across transit + ready-to-ship). MIP lists
     * those rows as R2S; this page must match. Only exclude zero-qty rows and
     * terminal stages that are not shippable.
     */
    private static function readyToShipRowBelongsOnPage(object $item, ?object $forecast): bool
    {
        $qty = (float) ($item->qty ?? 0);
        if ($qty <= 0) {
            return false;
        }
        if ($forecast === null) {
            return true;
        }
        $stage = strtolower(trim((string) ($forecast->stage ?? '')));

        return $stage !== 'all_good';
    }

    private static function normalizeReadyToShipSku(?string $sku): string
    {
        if (empty($sku)) {
            return '';
        }
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku);
        $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);

        return trim($sku);
    }

    /** After move/partial move, keep forecast stage aligned with remaining R2S qty. */
    private static function syncForecastStageFromRemainingR2s(string $sku): void
    {
        $skuNorm = self::normalizeReadyToShipSku($sku);
        if ($skuNorm === '') {
            return;
        }

        $remaining = (float) ReadyToShip::query()
            ->whereNull('deleted_at')
            ->where('transit_inv_status', 0)
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuNorm])
            ->sum('qty');

        $newStage = $remaining > 0 ? 'r2s' : 'transit';

        DB::table('forecast_analysis')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuNorm])
            ->update(['stage' => $newStage, 'updated_at' => now()]);
    }

    /**
     * Heal duplicate ready_to_ship rows: when the same SKU has multiple active R2S
     * lines and one line's qty exactly matches a container move, mark that line moved.
     */
    private static function reconcileDuplicateReadyToShipMovedToTransit(): void
    {
        $transitLines = TransitContainerDetail::query()
            ->whereNull('deleted_at')
            ->whereNotNull('created_by')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '');
            })
            ->get(['id', 'our_sku', 'total_ctn']);

        $skusSynced = [];

        foreach ($transitLines as $line) {
            $skuNorm = self::normalizeReadyToShipSku($line->our_sku ?? '');
            $tQty = (float) ($line->total_ctn ?? 0);
            if ($skuNorm === '' || $tQty <= 0) {
                continue;
            }

            $activeRows = ReadyToShip::query()
                ->whereNull('deleted_at')
                ->where('transit_inv_status', 0)
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuNorm])
                ->where('qty', '>', 0)
                ->get();

            if ($activeRows->count() < 2) {
                continue;
            }

            $match = $activeRows->first(fn ($r) => (float) ($r->qty ?? 0) === $tQty);
            if (! $match) {
                continue;
            }

            $match->update([
                'qty' => 0,
                'transit_inv_status' => 1,
                'rec_qty' => null,
                'updated_at' => now(),
            ]);
            $skusSynced[$skuNorm] = true;
        }

        foreach (array_keys($skusSynced) as $skuNorm) {
            self::syncForecastStageFromRemainingR2s($skuNorm);
        }
    }

    public function index()
    {
        self::reconcileDuplicateReadyToShipMovedToTransit();

        $normalizeSku = static function ($sku) {
            if (empty($sku)) {
                return '';
            }
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);

            return trim($sku);
        };

        $supplierRows = Supplier::where('type', 'Supplier')->get(['id', 'name', 'parent', 'zone']);

        $supplierMapByParent = [];
        $supplierZoneMap = [];
        foreach ($supplierRows as $row) {
            $parents = array_map('trim', explode(',', strtoupper($row->parent ?? '')));
            foreach ($parents as $parent) {
                if (! empty($parent)) {
                    $supplierMapByParent[$parent][] = $row->name;
                }
            }
            $z = trim((string) ($row->zone ?? ''));
            if ($z !== '') {
                $supplierZoneMap[trim((string) $row->name)] = $z;
            }
        }

        foreach ($supplierMapByParent as $parent => $suppliers) {
            $supplierMapByParent[$parent] = array_unique($suppliers);
        }

        // One streamed pass over product_master: lookup map + transit modal data (avoids loading the table twice)
        $productMaster = [];
        $transitProductValuesMap = [];
        $transitSkusSeen = [];
        foreach (DB::table('product_master')->select('sku', 'parent', 'Values')->cursor() as $pm) {
            $norm = $normalizeSku($pm->sku ?? '');
            if ($norm !== '') {
                foreach (array_unique([$norm, str_replace(' ', '', $norm)]) as $k) {
                    if ($k !== '' && ! isset($productMaster[$k])) {
                        $productMaster[$k] = $pm;
                    }
                }
            }
            $normSkuTransit = strtoupper(trim(preg_replace('/\s+/', ' ', $pm->sku ?? '')));
            if ($normSkuTransit !== '') {
                $val = $pm->Values;
                if (is_string($val) && $val !== '') {
                    $decoded = json_decode($val, true);
                    $transitProductValuesMap[$normSkuTransit] = is_array($decoded) ? $decoded : [];
                } elseif (! isset($transitProductValuesMap[$normSkuTransit])) {
                    $transitProductValuesMap[$normSkuTransit] = [];
                }
            }
            $rawSku = trim((string) ($pm->sku ?? ''));
            if ($rawSku !== '') {
                $transitSkusSeen[$rawSku] = true;
            }
        }
        $transitSkus = array_keys($transitSkusSeen);
        sort($transitSkus);

        // Same resolution as MFRG: Shopify image_src first, else product_master Values.image_path
        $shopifyImageByKey = [];
        foreach (DB::table('shopify_skus')
            ->select('sku', 'image_src')
            ->whereNotNull('image_src')
            ->where('image_src', '!=', '')
            ->cursor() as $shopRow) {
            $norm = $normalizeSku($shopRow->sku ?? '');
            if ($norm === '') {
                continue;
            }
            $src = $shopRow->image_src;
            foreach (array_unique([$norm, str_replace(' ', '', $norm)]) as $k) {
                if ($k !== '' && ! isset($shopifyImageByKey[$k])) {
                    $shopifyImageByKey[$k] = $src;
                }
            }
        }

        // Same source as Forecast Analysis "Supplier" column (mfrg_supplier): mfrg_progress.supplier
        $mfrgSuppliersBySku = [];
        foreach (MfrgProgress::query()->select('sku', 'supplier')->cursor() as $mfrgRow) {
            $ns = $normalizeSku($mfrgRow->sku ?? '');
            if ($ns !== '') {
                $mfrgSuppliersBySku[$ns] = trim((string) ($mfrgRow->supplier ?? ''));
            }
        }

        // Get stage data from forecast_analysis table - match by SKU only, prefer record with stage value
        $forecastData = DB::table('forecast_analysis')
            ->select('sku', 'stage', 'nr')
            ->get()
            ->groupBy(function ($item) use ($normalizeSku) {
                return $normalizeSku($item->sku);
            })
            ->map(function ($group) {
                $withStage = $group->firstWhere('stage', '!=', null);
                if ($withStage && ! empty(trim((string) $withStage->stage))) {
                    return $withStage;
                }

                return $group->first();
            });

        $readyToShipData = ReadyToShip::where('transit_inv_status', 0)->whereNull('deleted_at')->get();

        $readyToShipData = $readyToShipData->filter(function ($item) use ($forecastData, $normalizeSku) {
            $sku = $normalizeSku($item->sku);
            $forecast = $forecastData->has($sku) ? $forecastData->get($sku) : null;

            return self::readyToShipRowBelongsOnPage($item, $forecast);
        });

        $readyToShipData->transform(function ($item) use ($supplierMapByParent, $productMaster, $forecastData, $normalizeSku, $mfrgSuppliersBySku, $shopifyImageByKey) {
            $sku = $normalizeSku($item->sku);
            $parent = strtoupper(trim($item->parent ?? ''));
            $item->supplier_names = $supplierMapByParent[$parent] ?? [];

            $cbm = null;
            $cp = null;
            $image = null;

            $skuVariations = [
                $sku,
                str_replace(' ', '', $sku),
                preg_replace('/\s+/', ' ', $sku),
            ];
            if (! empty($item->sku)) {
                $skuVariations[] = strtoupper(trim($item->sku));
                $skuVariations[] = strtoupper(preg_replace('/\s+/', ' ', trim($item->sku)));
            }
            $skuVariations = array_unique(array_filter($skuVariations));

            foreach ($skuVariations as $skuVar) {
                if ($skuVar !== '' && isset($shopifyImageByKey[$skuVar])) {
                    $image = $shopifyImageByKey[$skuVar];
                    break;
                }
            }

            $productRow = null;
            foreach ($skuVariations as $skuVar) {
                if ($skuVar !== '' && isset($productMaster[$skuVar])) {
                    $productRow = $productMaster[$skuVar];
                    break;
                }
            }

            if ($productRow) {
                $valuesRaw = $productRow->Values ?? '{}';
                $values = json_decode($valuesRaw, true);

                if (is_array($values)) {
                    if (! empty($values['image_path']) && empty($image)) {
                        $image = 'storage/'.ltrim($values['image_path'], '/');
                    }
                    if (isset($values['cbm'])) {
                        $cbm = (float) $values['cbm'];
                    }

                    if (isset($values['cp'])) {
                        $cp = (float) $values['cp'];
                    }
                }
            }

            // Get nr from forecast_analysis; stage on this page is always r2s for listed rows.
            $nr = '';
            if ($forecastData->has($sku)) {
                $forecast = $forecastData->get($sku);
                $nr = strtoupper(trim($forecast->nr ?? ''));
            }
            $item->stage = 'r2s';
            $item->nr = $nr;
            $item->order_qty = $item->qty; // Add order_qty field for validation

            $item->mfrg_supplier = $mfrgSuppliersBySku[$sku] ?? '';
            $item->CBM = $cbm;
            $item->CP = $cp;
            $item->Image = ! empty($image) ? $image : null;

            return $item;
        });

        // Distinct zones from supplier master (same source as supplier list Zone column / modals)
        $standardSupplierZones = ['GHZ', 'Ningbo', 'Tianjin'];
        $supplierZoneListOptions = $supplierRows->pluck('zone')
            ->map(fn ($z) => trim((string) $z))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
        $supplierZoneListOptions = array_values(array_unique(array_merge($standardSupplierZones, $supplierZoneListOptions)));

        $resolveZoneForSupplier = static function (string $supplierName, array $map): ?string {
            $s = trim($supplierName);
            if ($s === '') {
                return null;
            }
            if (isset($map[$s])) {
                return $map[$s];
            }
            foreach ($map as $n => $z) {
                if (strcasecmp(trim((string) $n), $s) === 0) {
                    return $z;
                }
            }

            return null;
        };

        foreach ($readyToShipData as $item) {
            $area = trim((string) ($item->area ?? ''));
            $sup = trim((string) ($item->mfrg_supplier ?? ''));
            if ($sup === '') {
                $sup = trim((string) ($item->supplier ?? ''));
            }
            if ($area !== '' || $sup === '') {
                continue;
            }
            $z = $resolveZoneForSupplier($sup, $supplierZoneMap);
            if ($z !== null && $z !== '' && ! empty($item->id)) {
                ReadyToShip::whereKey($item->id)->update(['area' => $z]);
                $item->area = $z;
            }
        }

        // Total CBM per zone (badges on index): include all r2s grid rows (NR included); zone = zone_x or supplier-mapped zone; line CBM = Or. QTY × CBM (matches Total CBM column).
        $resolveR2sRowZone = static function ($item, array $supplierZoneMap): string {
            $mfrgSup = trim((string) ($item->mfrg_supplier ?? ''));
            if ($mfrgSup === '') {
                $mfrgSup = trim((string) ($item->supplier ?? ''));
            }
            $mappedZone = '';
            if ($mfrgSup !== '') {
                if (isset($supplierZoneMap[$mfrgSup])) {
                    $mappedZone = trim((string) $supplierZoneMap[$mfrgSup]);
                } else {
                    foreach ($supplierZoneMap as $n => $z) {
                        if (strcasecmp(trim((string) $n), $mfrgSup) === 0) {
                            $mappedZone = trim((string) $z);
                            break;
                        }
                    }
                }
            }
            $zoneXStored = trim((string) ($item->zone_x ?? ''));

            return $zoneXStored !== '' ? $zoneXStored : $mappedZone;
        };

        $r2sCbmBadgeZones = ['GHZ', 'Ningbo', 'Tianjin'];
        $r2sCbmByZone = array_fill_keys($r2sCbmBadgeZones, 0.0);
        foreach ($readyToShipData as $item) {
            $zone = $resolveR2sRowZone($item, $supplierZoneMap);
            $qty = $item->qty;
            $cbm = $item->CBM ?? null;
            if (! is_numeric($qty) || ! is_numeric($cbm)) {
                continue;
            }
            $lineTotal = (float) $qty * (float) $cbm;
            foreach ($r2sCbmBadgeZones as $bz) {
                if (strcasecmp($zone, $bz) === 0) {
                    $r2sCbmByZone[$bz] += $lineTotal;
                    break;
                }
            }
        }

        // Transit container modal (same as transit-container-details)
        $transitTabs = TransitContainerDetail::where(function ($q) {
            $q->whereNull('status')->orWhereRaw("TRIM(status) = ''");
        })->distinct()->pluck('tab_name')->toArray();
        if (empty($transitTabs)) {
            $transitTabs = ['Container 1'];
        }
        $packingListSheetService = app(ReadyToShipPackingListSheetService::class);
        $packingListCsvMap = $packingListSheetService->getSkuToLinkMap();
        $packingListLinks = $packingListSheetService->mergeDbLinksOverCsv($packingListCsvMap);

        return view('purchase-master.ready-to-ship.index', [
            'readyToShipList' => $readyToShipData,
            'r2sCbmByZone' => $r2sCbmByZone,
            'suppliers' => $supplierRows->pluck('name')->unique()->values(),
            'supplierZoneMap' => $supplierZoneMap,
            'supplierZoneListOptions' => $supplierZoneListOptions,
            'transitTabs' => $transitTabs,
            'transitSuppliers' => $supplierRows,
            'transitSkus' => $transitSkus,
            'transitProductValuesMap' => json_encode($transitProductValuesMap, JSON_UNESCAPED_UNICODE),
            'packingListLinks' => $packingListLinks,
            'packingListSheetEditUrl' => trim((string) config('googlesheets.ready_to_ship_packing_list.sheet_edit_url', '')),
        ]);
    }

    /**
     * JSON map of normalized SKU → packing list URL (from public Google Sheet CSV).
     * Optional ?refresh=1 bypasses cache for a few seconds after edits.
     */
    public function packingListLinksJson(Request $request)
    {
        $service = app(ReadyToShipPackingListSheetService::class);
        $csv = $service->getSkuToLinkMap($request->boolean('refresh'));
        $links = $service->mergeDbLinksOverCsv($csv);

        return response()->json(['success' => true, 'links' => $links]);
    }

    /**
     * Return current R2S total (same logic as Ready to Ship blade: sum of qty*CP for stage r2s, nr != NR).
     * Ready to Ship uses CP from product_master Values->cp, not rate. Used by Forecast Analysis etc.
     */
    public function r2sTotal(Request $request)
    {
        $normalizeSku = function ($sku) {
            if (empty($sku)) return '';
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return trim($sku);
        };

        $forecastData = DB::table('forecast_analysis')
            ->get()
            ->groupBy(function ($item) use ($normalizeSku) {
                return $normalizeSku($item->sku);
            })
            ->map(function ($group) {
                $withStage = $group->firstWhere('stage', '!=', null);
                if ($withStage && !empty(trim($withStage->stage ?? ''))) {
                    return $withStage;
                }
                return $group->first();
            });

        $productMaster = DB::table('product_master')->get()->keyBy(fn($row) => strtoupper(trim($row->sku ?? '')));

        $readyToShipRows = ReadyToShip::where('transit_inv_status', 0)->whereNull('deleted_at')->get();
        $total = 0;

        foreach ($readyToShipRows as $item) {
            $sku = $normalizeSku($item->sku);
            $forecast = $forecastData->has($sku) ? $forecastData->get($sku) : null;
            if (! self::readyToShipRowBelongsOnPage($item, $forecast)) {
                continue;
            }
            $nr = strtoupper(trim($forecast ? ($forecast->nr ?? '') : ''));
            if ($nr === 'NR') {
                continue;
            }

            $cp = null;
            $productRow = $productMaster[$sku] ?? null;
            if (!$productRow) {
                foreach ([$sku, str_replace(' ', '', $sku)] as $skuVar) {
                    if (isset($productMaster[$skuVar])) {
                        $productRow = $productMaster[$skuVar];
                        break;
                    }
                }
            }
            if (!$productRow && !empty($item->sku)) {
                $productRow = DB::table('product_master')
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                    ->orWhereRaw('UPPER(TRIM(sku)) LIKE ?', ['%' . str_replace(' ', '%', $sku) . '%'])
                    ->first();
            }
            if ($productRow) {
                $values = json_decode($productRow->Values ?? '{}', true);
                if (is_array($values) && isset($values['cp'])) {
                    $cp = (float) $values['cp'];
                }
            }
            $qty = is_numeric($item->qty) ? (float) $item->qty : 0;
            if ($qty > 0 && $cp !== null && $cp > 0) {
                $total += $qty * $cp;
            }
        }

        return response()->json(['value' => round($total)]);
    }

    public function inlineUpdateBySku(Request $request)
    {
        $sku = $request->input('sku');
        $column = $request->input('column');
        $value = $request->input('value');
        $normalizedSku = trim((string) $sku);
        $item = ReadyToShip::query()
            ->whereRaw('TRIM(sku) = ?', [$normalizedSku])
            ->where('transit_inv_status', 0)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'SKU not found in ready_to_ships']);
        }
        $qty = $item->qty;

        if($column === 'rec_qty'){
            $value = is_numeric($value) ? (int)$value : null;
            if($value !== null) {
                $item->qty = $qty - $value;
                $item->save();
            }
        }

        if ($column === 'zone_x') {
            $value = trim((string) $value);
            if ($value === '') {
                $item->zone_x = null;
                $item->save();

                return response()->json(['success' => true]);
            }
            $allowedStandard = ['GHZ', 'Ningbo', 'Tianjin'];
            $valid = in_array($value, $allowedStandard, true)
                || Supplier::query()
                    ->where('type', 'Supplier')
                    ->whereRaw('TRIM(zone) = ?', [$value])
                    ->exists();
            if (! $valid) {
                return response()->json(['success' => false, 'message' => 'Zone must match a zone from the supplier master.']);
            }
            $item->zone_x = $value;
            $item->save();

            return response()->json(['success' => true]);
        }

        if ($column === 'packing_list_link') {
            $value = trim((string) $value);
            if ($value !== '' && ! ReadyToShipPackingListSheetService::isAllowedHttpUrl($value)) {
                return response()->json(['success' => false, 'message' => 'Enter a valid http(s) URL or leave empty to clear.']);
            }
            $item->packing_list_link = $value !== '' ? $value : null;
            $item->save();

            $sheetService = app(ReadyToShipPackingListSheetService::class);
            $sheetService->pushLinkToSheet((string) ($item->sku ?? ''), $value);
            Cache::forget('r2s_packing_list_links_v1');

            return response()->json([
                'success' => true,
                'packing_list_link' => $item->packing_list_link,
            ]);
        }

        if (!in_array($column, [
            'rec_qty',
            'rate',
            'area',
            'pay_term',
            'payment_confirmation',
            'payment',
            'packing_list',
            'photo_mail_send',
            'supplier',
            'supplier_sku',
        ])) {
            return response()->json(['success' => false, 'message' => 'Invalid column.']);
        }

        if ($column === 'pay_term') {
            $value = strtoupper(trim((string) $value));
            if (!in_array($value, ['EXW', 'FOB'], true)) {
                $value = 'EXW';
            }
        }

        $item->$column = $value;
        $item->save();

        return response()->json(['success' => true]);
    }

    public function revertBackMfrg(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', [])), fn ($id) => $id > 0));
        $skus = array_values(array_filter(array_map(
            fn ($s) => trim((string) $s),
            (array) $request->input('skus', [])
        ), fn ($s) => $s !== ''));

        if ($ids === [] && $skus === []) {
            return response()->json(['success' => false, 'message' => 'No rows provided.']);
        }

        try {
            $rows = $this->resolveReadyToShipRowsForAction($ids, $skus);
            if ($rows->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No matching rows to revert.'], 404);
            }

            $revertedCount = 0;
            foreach ($rows as $row) {
                $skuNorm = strtoupper(trim((string) ($row->sku ?? '')));
                $row->delete();
                if ($skuNorm !== '') {
                    MfrgProgress::query()
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
                        ->update(['ready_to_ship' => 'No']);
                }
                $revertedCount++;
            }

            return response()->json([
                'success' => true,
                'reverted_count' => $revertedCount,
                'reverted_ids' => $rows->pluck('id')->values()->all(),
                'message' => "Reverted {$revertedCount} row(s) to MIP.",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
        }
    }

    /**
     * @param  array<int>  $ids
     * @param  array<int, string>  $skus
     */
    private function resolveReadyToShipRowsForAction(array $ids, array $skus)
    {
        $baseQuery = ReadyToShip::query()
            ->where('transit_inv_status', 0)
            ->whereNull('deleted_at');

        if ($ids !== []) {
            return $baseQuery->whereIn('id', $ids)->get();
        }

        $rows = collect();
        foreach ($skus as $sku) {
            $norm = strtoupper(trim($sku));
            if ($norm === '') {
                continue;
            }
            $row = (clone $baseQuery)
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$norm])
                ->orderByDesc('id')
                ->first();
            if ($row) {
                $rows->push($row);
            }
        }

        return $rows->unique('id')->values();
    }

    public function moveToTransit(Request $request)
    {
        $tabName = '';
        $ids = [];
        $skus = [];
        $recQtyById = [];
        $recQtyBySku = [];

        // Always parse JSON from raw body when it looks like JSON (fixes missing ids when Content-Type is odd)
        $raw = (string) $request->getContent();
        if ($raw !== '' && str_starts_with(ltrim($raw), '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $tabName = trim((string) ($decoded['tab_name'] ?? ''));
                if (!empty($decoded['ids']) && is_array($decoded['ids'])) {
                    $ids = array_values(array_filter(array_map('intval', $decoded['ids']), fn ($id) => $id > 0));
                }
                if (!empty($decoded['skus']) && is_array($decoded['skus'])) {
                    $skus = array_values(array_filter(array_map('strval', $decoded['skus']), fn ($s) => $s !== ''));
                }
                if (!empty($decoded['rec_qty_by_id']) && is_array($decoded['rec_qty_by_id'])) {
                    foreach ($decoded['rec_qty_by_id'] as $k => $v) {
                        $id = (int) $k;
                        if ($id > 0 && is_numeric($v)) {
                            $recQtyById[$id] = max(0, min(100000, (float) $v));
                        }
                    }
                }
                if (!empty($decoded['rec_qty_by_sku']) && is_array($decoded['rec_qty_by_sku'])) {
                    foreach ($decoded['rec_qty_by_sku'] as $k => $v) {
                        $skuKey = trim((string) $k);
                        if ($skuKey !== '' && is_numeric($v)) {
                            $recQtyBySku[strtoupper(preg_replace('/\s+/u', ' ', $skuKey))] = max(0, min(100000, (float) $v));
                        }
                    }
                }
            }
        }

        if ($tabName === '') {
            $tabName = trim((string) $request->input('tab_name', ''));
        }
        if (empty($ids)) {
            $rawIds = $request->input('ids', []);
            if (!is_array($rawIds)) {
                $rawIds = $rawIds !== null && $rawIds !== '' ? [$rawIds] : [];
            }
            $ids = array_values(array_filter(array_map('intval', $rawIds), fn ($id) => $id > 0));
        }
        if (empty($skus)) {
            $rawSkus = $request->input('skus', []);
            if (!is_array($rawSkus)) {
                $rawSkus = $rawSkus ? [(string) $rawSkus] : [];
            }
            $skus = array_values(array_filter(array_map('strval', $rawSkus), fn ($s) => $s !== ''));
        }

        if ($tabName === '') {
            Log::warning('[ReadyToShip] moveToTransit rejected: empty tab_name', ['ids' => $ids, 'skus_count' => count($skus)]);

            return response()->json(['success' => false, 'message' => 'Please choose a container.']);
        }

        if (!empty($ids)) {
            $readyItems = ReadyToShip::whereIn('id', $ids)
                ->where('transit_inv_status', 0)
                ->whereNull('deleted_at')
                ->get();
        } elseif (!empty($skus)) {
            $readyItems = ReadyToShip::whereIn('sku', $skus)
                ->where('transit_inv_status', 0)
                ->whereNull('deleted_at')
                ->get();
        } else {
            Log::warning('[ReadyToShip] moveToTransit rejected: no ids or skus', ['parsed_ids' => $ids]);

            return response()->json(['success' => false, 'message' => 'No rows selected.']);
        }

        if ($readyItems->isEmpty()) {
            Log::warning('[ReadyToShip] moveToTransit: no matching rows', [
                'tab_name' => $tabName,
                'ids' => $ids,
                'skus' => $skus,
            ]);

            return response()->json(['success' => false, 'message' => 'No matching ready-to-ship rows to move.']);
        }

        $removedIds = [];
        $partialUpdates = [];
        $affectedSkus = [];

        try {
            DB::beginTransaction();

            $normalizeSku = function ($sku) {
                return self::normalizeReadyToShipSku($sku);
            };

            $productMaster = DB::table('product_master')
                ->get()
                ->keyBy(fn ($row) => $normalizeSku($row->sku ?? ''));

            foreach ($readyItems as $item) {
                $orderQty = (float) ($item->qty ?? 0);
                if ($orderQty <= 0) {
                    continue;
                }

                $skuNorm = $normalizeSku($item->sku ?? '');
                if ($skuNorm !== '') {
                    $affectedSkus[$skuNorm] = true;
                }

                $recQtyInput = $recQtyById[$item->id] ?? null;
                if ($recQtyInput === null) {
                    $skuNorm = $normalizeSku($item->sku ?? '');
                    $recQtyInput = $skuNorm !== '' ? ($recQtyBySku[$skuNorm] ?? null) : null;
                }
                if ($recQtyInput === null) {
                    $recQtyInput = $item->rec_qty ?? $item->qty;
                }
                $recQtyInput = is_numeric($recQtyInput) ? (float) $recQtyInput : 0.0;
                $recQtyInput = max(0, min(100000, $recQtyInput));

                if ($recQtyInput <= 0) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Rec. Qty must be greater than 0 (SKU: ' . ($item->sku ?? '') . ').',
                    ], 422);
                }

                /** Rec < Or. Qty → partial move; Rec ≥ Or. Qty → move full order qty and remove R2S row */
                $isPartial = $recQtyInput < $orderQty;
                $qtyToTransit = $isPartial ? $recQtyInput : $orderQty;

                $rate = $item->rate ?? null;
                $cbm = $item->cbm ?? null;
                if ($cbm === null || $cbm === '') {
                    $skuNorm = $normalizeSku($item->sku);
                    if (isset($productMaster[$skuNorm])) {
                        $valuesRaw = $productMaster[$skuNorm]->Values ?? '{}';
                        $values = json_decode($valuesRaw, true);
                        if (is_array($values) && isset($values['cbm'])) {
                            $cbm = (float) $values['cbm'];
                        }
                    }
                } else {
                    $cbm = is_numeric($cbm) ? (float) $cbm : $cbm;
                }

                $existing = TransitContainerDetail::where('our_sku', $item->sku)->where('tab_name', $tabName)->first();
                if ($existing) {
                    $prevCtn = (float) ($existing->total_ctn ?? 0);
                    $newCtn = $prevCtn + $qtyToTransit;
                    $existing->update([
                        'total_ctn' => $newCtn,
                        'rec_qty' => $newCtn,
                        'rate' => $rate ?? $existing->rate,
                        'cbm' => $cbm ?? $existing->cbm,
                        'updated_at' => now(),
                    ]);
                } else {
                    TransitContainerDetail::create([
                        'our_sku' => $item->sku,
                        'tab_name' => $tabName,
                        'rec_qty' => $qtyToTransit,
                        'no_of_units' => 1,
                        'total_ctn' => $qtyToTransit,
                        'rate' => $rate,
                        'cbm' => $cbm,
                        'created_at' => now(),
                        'created_by' => auth()->id(),
                        'updated_at' => now(),
                    ]);
                }

                if ($isPartial) {
                    $newQty = $orderQty - $recQtyInput;
                    if ($newQty < 0) {
                        $newQty = 0;
                    }
                    $item->update([
                        'qty' => $newQty,
                        'rec_qty' => null,
                        'updated_at' => now(),
                    ]);
                    $partialUpdates[] = [
                        'id' => (int) $item->id,
                        'new_qty' => $newQty,
                        'sku' => (string) ($item->sku ?? ''),
                    ];
                } else {
                    $item->update([
                        'qty' => 0,
                        'transit_inv_status' => 1,
                        'rec_qty' => null,
                        'updated_at' => now(),
                    ]);
                    $removedIds[] = (int) $item->id;
                }
            }

            foreach (array_keys($affectedSkus) as $skuNorm) {
                self::syncForecastStageFromRemainingR2s($skuNorm);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('moveToTransit failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Move failed: ' . $e->getMessage(),
            ], 500);
        }

        $nRemoved = count($removedIds);
        $nPartial = count($partialUpdates);
        $parts = [];
        if ($nRemoved) {
            $parts[] = $nRemoved . ' row(s) moved completely';
        }
        if ($nPartial) {
            $parts[] = $nPartial . ' row(s) updated (balance left on Ready to Ship)';
        }
        $msg = $parts ? implode('; ', $parts) . ' to "' . $tabName . '".' : 'No changes.';

        return response()->json([
            'success' => true,
            'message' => $msg . ' Open Transit Container Details to view.',
            'removed_ids' => $removedIds,
            'partial_updates' => $partialUpdates,
        ]);
    }

    public function deleteItems(Request $request)
    {
        try {
            $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', [])), fn ($id) => $id > 0));
            $skus = array_values(array_filter(array_map(
                fn ($s) => trim((string) $s),
                (array) $request->input('skus', [])
            ), fn ($s) => $s !== ''));

            if ($ids === [] && $skus === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rows provided.',
                ], 400);
            }

            $user = auth()->check() ? auth()->user()->name : 'System';
            $rows = $this->resolveReadyToShipRowsForAction($ids, $skus);

            if ($rows->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No matching rows to delete.',
                ], 404);
            }

            $deletedCount = 0;
            foreach ($rows as $row) {
                $row->auth_user = $user;
                $row->save();
                $row->delete();
                $deletedCount++;
            }

            return response()->json([
                'success' => true,
                'deleted_count' => $deletedCount,
                'deleted_ids' => $rows->pluck('id')->values()->all(),
                'message' => "Deleted {$deletedCount} row(s).",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting records: ' . $e->getMessage(),
            ], 500);
        }
    }


}
