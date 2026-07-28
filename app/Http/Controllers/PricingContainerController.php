<?php

namespace App\Http\Controllers;

use App\Models\ArrivedContainer;
use App\Models\ComparisonData;
use App\Models\CpHistory;
use App\Models\ProductMaster;
use App\Models\PurchaseOrder;
use App\Models\ShopifySku;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pricing Container — same arrived_containers source as /qc/container.
 */
class PricingContainerController extends Controller
{
    public const AUTO_APPROVE_REASON = 'Price lower than previous CP';

    public const APPROVE_YES_REASONS = [
        'Price lower than previous CP',
        'Negotiated rate accepted',
        'Market aligned / competitive',
        'Volume discount justified',
        'Other',
    ];

    public const APPROVE_NO_REASONS = [
        'Price higher than previous CP',
        'Need renegotiation',
        'Quality / terms concern',
        'Wrong PO linked',
        'Other',
    ];

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
            $pmUnit = is_array($values) ? trim((string) ($values['unit'] ?? '')) : '';
            $record->unit = $pmUnit !== '' ? $pmUnit : null;
            $cpRaw = is_array($values) ? ($values['cp'] ?? null) : null;
            $record->cp = ($cpRaw === null || $cpRaw === '') ? null : (is_numeric($cpRaw) ? round((float) $cpRaw, 2) : $cpRaw);

            return $record;
        });

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

        [$poBySku, $allPoOptions, $poPriceLookup] = $this->buildPoLookups();
        $rmbToUsd = $this->fetchRmbToUsdRate();

        $allRecords->transform(function ($record) use (
            $sheetBySku,
            $poBySku,
            $poPriceLookup,
            $normalizeSku,
            $rmbToUsd
        ) {
            $skuKey = $normalizeSku($record->our_sku ?? '');
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
            $record->has_sheet_data = $hasSheet;
            $record->po_options = $poBySku[$skuKey] ?? [];

            $pricing = $this->resolvePricingFields(
                $record,
                $skuKey,
                $poPriceLookup,
                $rmbToUsd
            );
            foreach ($pricing as $key => $value) {
                $record->{$key} = $value;
            }

            if (! empty($pricing['_should_auto_save'])) {
                $record->_persist_approval = true;
            }
            unset($record->_should_auto_save);

            return $record;
        });

        // Persist auto-approve / clear stale auto rows one-by-one (values may differ).
        foreach ($allRecords as $record) {
            if (empty($record->_persist_approval)) {
                continue;
            }
            ArrivedContainer::query()->where('id', $record->id)->update([
                'cp_approved' => $record->cp_approved,
                'cp_approved_reason' => $record->cp_approved_reason,
                'cp_approved_auto' => (bool) $record->cp_approved_auto,
            ]);
            // Auto Yes → push CP New into CP Master + history (idempotent if already same).
            if (($record->cp_approved ?? null) === 'Yes' && is_numeric($record->cp_new ?? null)) {
                $sync = $this->syncApprovedCpToMaster(
                    (string) ($record->our_sku ?? ''),
                    (float) $record->cp_new,
                    (string) ($record->cp_approved_reason ?? self::AUTO_APPROVE_REASON)
                );
                if (! empty($sync['updated']) && isset($sync['current_cp'])) {
                    $record->cp = $sync['current_cp'];
                }
            }
            unset($record->_persist_approval);
        }

        $groupedData = $allRecords->groupBy('tab_name');
        foreach ($tabs as $tab) {
            if (! isset($groupedData[$tab])) {
                $groupedData[$tab] = collect([]);
            }
        }

        $groupedData = collect($tabs)->mapWithKeys(fn ($tab) => [$tab => $groupedData[$tab]]);

        return view('purchase-master.transit_container.pricing-container', [
            'tabs' => $tabs,
            'groupedData' => $groupedData,
            'allPoOptions' => $allPoOptions,
            'rmbToUsdRate' => $rmbToUsd,
            'approveYesReasons' => self::APPROVE_YES_REASONS,
            'approveNoReasons' => self::APPROVE_NO_REASONS,
        ]);
    }

    public function savePo(Request $request)
    {
        $arrivedId = (int) $request->input('id', 0);
        $poNumber = trim((string) $request->input('po_number', ''));
        $orderLink = trim((string) $request->input('order_link', ''));

        if ($arrivedId <= 0) {
            return response()->json(['success' => false, 'message' => 'Row id is required.'], 400);
        }

        $row = ArrivedContainer::query()->find($arrivedId);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Arrived container row not found.'], 404);
        }

        if ($orderLink !== '' && ! filter_var($orderLink, FILTER_VALIDATE_URL)) {
            if (! str_starts_with($orderLink, '/')) {
                return response()->json(['success' => false, 'message' => 'Link must be a valid URL.'], 422);
            }
        }

        $row->po_number = $poNumber !== '' ? $poNumber : null;
        $row->order_link = $orderLink !== '' ? $orderLink : null;
        $row->save();

        $pricing = $this->buildPricingResponseForRow($row);

        return response()->json(array_merge([
            'success' => true,
            'message' => 'PO Number / Link saved.',
            'po_number' => $row->po_number,
            'order_link' => $row->order_link,
        ], $pricing));
    }

    public function saveApproval(Request $request)
    {
        $arrivedId = (int) $request->input('id', 0);
        $approved = trim((string) $request->input('cp_approved', ''));
        $reason = trim((string) $request->input('cp_approved_reason', ''));

        if ($arrivedId <= 0) {
            return response()->json(['success' => false, 'message' => 'Row id is required.'], 400);
        }

        if (! in_array($approved, ['Yes', 'No'], true)) {
            return response()->json(['success' => false, 'message' => 'Approved must be Yes or No.'], 422);
        }

        $allowed = $approved === 'Yes' ? self::APPROVE_YES_REASONS : self::APPROVE_NO_REASONS;
        if ($reason === '' || ! in_array($reason, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Select a valid reason for '.$approved.'.'], 422);
        }

        $row = ArrivedContainer::query()->find($arrivedId);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Arrived container row not found.'], 404);
        }

        // Resolve CP New from linked PO (do not rely on auto-approve side effects here).
        $normalizeSku = static function ($value) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)));
        };
        $skuKey = $normalizeSku($row->our_sku ?? '');
        $pmValues = ProductMaster::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$skuKey])->value('Values');
        if (is_string($pmValues)) {
            $decoded = json_decode($pmValues, true);
            $pmValues = is_array($decoded) ? $decoded : null;
        }
        $cpRaw = is_array($pmValues) ? ($pmValues['cp'] ?? null) : null;
        $row->cp = ($cpRaw === null || $cpRaw === '') ? null : (is_numeric($cpRaw) ? round((float) $cpRaw, 2) : $cpRaw);
        [, , $poPriceLookup] = $this->buildPoLookups();
        $rmbToUsd = $this->fetchRmbToUsdRate();
        $pricing = $this->resolvePricingFields($row, $skuKey, $poPriceLookup, $rmbToUsd);
        unset($pricing['_should_auto_save']);
        $cpNew = is_numeric($pricing['cp_new'] ?? null) ? (float) $pricing['cp_new'] : null;

        $cpMasterSync = [
            'success' => true,
            'updated' => false,
            'message' => null,
            'current_cp' => is_numeric($row->cp) ? (float) $row->cp : null,
        ];

        if ($approved === 'Yes') {
            if ($cpNew === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link a PO with price first — CP New is required to update CP Master.',
                ], 422);
            }

            $cpMasterSync = $this->syncApprovedCpToMaster(
                (string) ($row->our_sku ?? ''),
                $cpNew,
                $reason
            );
            if (empty($cpMasterSync['success'])) {
                return response()->json([
                    'success' => false,
                    'message' => $cpMasterSync['message'] ?? 'Failed to update CP Master.',
                ], 422);
            }
        }

        $row->cp_approved = $approved;
        $row->cp_approved_reason = $reason;
        $row->cp_approved_auto = false;
        $row->save();

        $message = 'Approval saved.';
        if ($approved === 'Yes' && ! empty($cpMasterSync['updated'])) {
            $message = 'Approval saved. CP Master updated to '.$cpMasterSync['current_cp'].' and history recorded.';
        } elseif ($approved === 'Yes' && ! empty($cpMasterSync['message'])) {
            $message = 'Approval saved. '.$cpMasterSync['message'];
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'cp_approved' => $row->cp_approved,
            'cp_approved_reason' => $row->cp_approved_reason,
            'cp_approved_auto' => (bool) $row->cp_approved_auto,
            'cp' => $cpMasterSync['current_cp'] ?? ($pricing['cp'] ?? null),
            'cp_new' => $cpNew,
            'cp_master_updated' => (bool) ($cpMasterSync['updated'] ?? false),
        ]);
    }

    /**
     * @return array{0: array, 1: array, 2: array}
     */
    private function buildPoLookups(): array
    {
        $poBySku = [];
        $allPoOptions = [];
        $poPriceLookup = [];

        $poRows = PurchaseOrder::query()
            ->where(function ($q) {
                $q->where('is_archived', false)->orWhereNull('is_archived');
            })
            ->orderByDesc('id')
            ->get(['id', 'po_number', 'items']);

        foreach ($poRows as $po) {
            $poNumber = trim((string) ($po->po_number ?? ''));
            if ($poNumber === '') {
                continue;
            }
            $pdfUrl = route('generate-pdf', ['id' => $po->id]);
            $baseOption = [
                'id' => (int) $po->id,
                'po_number' => $poNumber,
                'link' => $pdfUrl,
                'page_url' => route('list-all-purchase-orders').'?po='.urlencode($poNumber),
            ];
            $allPoOptions[] = $baseOption;

            $items = $po->items;
            if (is_string($items)) {
                $items = json_decode($items, true);
            }
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $itemSku = strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($item['sku'] ?? ''))));
                if ($itemSku === '') {
                    continue;
                }
                $price = is_numeric($item['price'] ?? null) ? (float) $item['price'] : null;
                $currency = strtoupper(trim((string) ($item['currency'] ?? 'USD')));
                if ($currency === '') {
                    $currency = 'USD';
                }

                $poPriceLookup[$poNumber][$itemSku] = [
                    'price' => $price,
                    'currency' => $currency,
                ];

                $option = array_merge($baseOption, [
                    'price' => $price,
                    'currency' => $currency,
                ]);

                $poBySku[$itemSku] ??= [];
                $seen = false;
                foreach ($poBySku[$itemSku] as $existing) {
                    if (($existing['po_number'] ?? '') === $poNumber) {
                        $seen = true;
                        break;
                    }
                }
                if (! $seen) {
                    $poBySku[$itemSku][] = $option;
                }
            }
        }

        return [$poBySku, $allPoOptions, $poPriceLookup];
    }

    /**
     * Live CNY/RMB → USD rate from internet FX APIs. Cached ~1 hour.
     */
    private function fetchRmbToUsdRate(): ?float
    {
        return Cache::remember('fx_cny_usd_rate', 3600, function () {
            $rate = $this->pullLiveCnyUsdRate();

            return $rate ?? 0.14; // fallback only if all live sources fail
        });
    }

    private function pullLiveCnyUsdRate(): ?float
    {
        // Primary: Frankfurter (ECB) — same source used by Purchase Orders convert()
        try {
            $response = Http::timeout(8)->get('https://api.frankfurter.app/latest', [
                'amount' => 1,
                'from' => 'CNY',
                'to' => 'USD',
            ]);
            if ($response->successful()) {
                $rate = $response->json('rates.USD');
                if (is_numeric($rate) && (float) $rate > 0) {
                    return round((float) $rate, 6);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PricingContainer Frankfurter FX failed: '.$e->getMessage());
        }

        // Secondary live source
        try {
            $response = Http::timeout(8)->get('https://open.er-api.com/v6/latest/CNY');
            if ($response->successful()) {
                $rate = $response->json('rates.USD');
                if (is_numeric($rate) && (float) $rate > 0) {
                    return round((float) $rate, 6);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PricingContainer open.er-api FX failed: '.$e->getMessage());
        }

        return null;
    }

    private function convertPoPriceToUsd(?float $price, ?string $currency, ?float $rmbToUsd): ?float
    {
        if ($price === null) {
            return null;
        }
        $currency = strtoupper(trim((string) $currency));
        if ($currency === '' || $currency === 'USD') {
            return round($price, 2);
        }
        if (in_array($currency, ['RMB', 'CNY', 'CNH'], true)) {
            if ($rmbToUsd === null || $rmbToUsd <= 0) {
                return null;
            }

            return round($price * $rmbToUsd, 2);
        }

        // Unknown currency — treat as USD amount as-is
        return round($price, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePricingFields($record, string $skuKey, array $poPriceLookup, ?float $rmbToUsd): array
    {
        $poNumber = trim((string) ($record->po_number ?? ''));
        $poPrice = null;
        $poCurrency = null;
        $cpNew = null;

        if ($poNumber !== '' && $skuKey !== '' && isset($poPriceLookup[$poNumber][$skuKey])) {
            $hit = $poPriceLookup[$poNumber][$skuKey];
            $poPrice = $hit['price'] ?? null;
            $poCurrency = $hit['currency'] ?? null;
            $cpNew = $this->convertPoPriceToUsd(
                is_numeric($poPrice) ? (float) $poPrice : null,
                $poCurrency,
                $rmbToUsd
            );
        }

        $cp = is_numeric($record->cp ?? null) ? (float) $record->cp : null;
        $pctDiff = null;
        if ($cpNew !== null && $cp !== null && $cp != 0.0) {
            $pctDiff = round((($cpNew - $cp) / $cp) * 100, 2);
        }

        $approved = trim((string) ($record->cp_approved ?? ''));
        $reason = trim((string) ($record->cp_approved_reason ?? ''));
        $isAuto = (bool) ($record->cp_approved_auto ?? false);
        $shouldAutoSave = false;

        // Auto-approve when CP New < previous CP (only if empty or previously auto).
        if ($cpNew !== null && $cp !== null && $cpNew < $cp) {
            if ($approved === '' || $isAuto) {
                $prevApproved = $approved;
                $prevReason = $reason;
                $prevAuto = $isAuto;
                $approved = 'Yes';
                $reason = self::AUTO_APPROVE_REASON;
                $isAuto = true;
                $shouldAutoSave = $prevApproved !== 'Yes'
                    || $prevReason !== self::AUTO_APPROVE_REASON
                    || ! $prevAuto;
            }
        } elseif ($isAuto) {
            // Previous auto-approval no longer valid (price not lower).
            $approved = null;
            $reason = null;
            $isAuto = false;
            $shouldAutoSave = true;
        }

        return [
            'po_price' => $poPrice,
            'po_currency' => $poCurrency,
            'cp_new' => $cpNew,
            'cp_diff_pct' => $pctDiff,
            'rmb_to_usd' => $rmbToUsd,
            'cp_approved' => $approved !== '' ? $approved : null,
            'cp_approved_reason' => $reason !== '' ? $reason : null,
            'cp_approved_auto' => $isAuto,
            '_should_auto_save' => $shouldAutoSave && (int) ($record->id ?? 0) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPricingResponseForRow(ArrivedContainer $row): array
    {
        $normalizeSku = static function ($value) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)));
        };

        $skuKey = $normalizeSku($row->our_sku ?? '');
        $values = ProductMaster::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$skuKey])->value('Values');
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : null;
        }
        $cpRaw = is_array($values) ? ($values['cp'] ?? null) : null;
        $row->cp = ($cpRaw === null || $cpRaw === '') ? null : (is_numeric($cpRaw) ? round((float) $cpRaw, 2) : $cpRaw);

        [, , $poPriceLookup] = $this->buildPoLookups();
        $rmbToUsd = $this->fetchRmbToUsdRate();
        $pricing = $this->resolvePricingFields($row, $skuKey, $poPriceLookup, $rmbToUsd);

        if (! empty($pricing['_should_auto_save'])) {
            $row->cp_approved = 'Yes';
            $row->cp_approved_reason = self::AUTO_APPROVE_REASON;
            $row->cp_approved_auto = true;
            $row->save();
            $pricing['cp_approved'] = 'Yes';
            $pricing['cp_approved_reason'] = self::AUTO_APPROVE_REASON;
            $pricing['cp_approved_auto'] = true;

            if (is_numeric($pricing['cp_new'] ?? null)) {
                $sync = $this->syncApprovedCpToMaster(
                    (string) ($row->our_sku ?? ''),
                    (float) $pricing['cp_new'],
                    self::AUTO_APPROVE_REASON
                );
                if (! empty($sync['updated']) && isset($sync['current_cp'])) {
                    $row->cp = $sync['current_cp'];
                }
            }
        }
        unset($pricing['_should_auto_save']);

        return [
            'cp' => $row->cp,
            'cp_new' => $pricing['cp_new'],
            'cp_diff_pct' => $pricing['cp_diff_pct'],
            'po_price' => $pricing['po_price'],
            'po_currency' => $pricing['po_currency'],
            'rmb_to_usd' => $pricing['rmb_to_usd'],
            'cp_approved' => $pricing['cp_approved'],
            'cp_approved_reason' => $pricing['cp_approved_reason'],
            'cp_approved_auto' => (bool) $pricing['cp_approved_auto'],
        ];
    }

    /**
     * Push approved CP New into product-master Values.cp and write cp_histories.
     *
     * @return array{success:bool,updated:bool,message:?string,current_cp:?float}
     */
    private function syncApprovedCpToMaster(string $sku, float $newCp, string $reason): array
    {
        $skuKey = strtoupper(trim(preg_replace('/\s+/', ' ', $sku)));
        if ($skuKey === '') {
            return [
                'success' => false,
                'updated' => false,
                'message' => 'SKU is missing.',
                'current_cp' => null,
            ];
        }

        $product = ProductMaster::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuKey])
            ->first();

        if (! $product) {
            return [
                'success' => false,
                'updated' => false,
                'message' => 'SKU not found in CP Master (/product-master).',
                'current_cp' => null,
            ];
        }

        $values = is_array($product->Values) ? $product->Values : [];
        $oldRaw = $values['cp'] ?? null;
        $oldCp = is_numeric($oldRaw) ? round((float) $oldRaw, 2) : null;
        $newCp = round($newCp, 2);

        if ($oldCp !== null && abs($oldCp - $newCp) < 0.0001) {
            return [
                'success' => true,
                'updated' => false,
                'message' => 'CP Master already has this price.',
                'current_cp' => $oldCp,
            ];
        }

        $isIncrease = $oldCp !== null && $newCp > $oldCp;
        $historyReason = trim($reason) !== ''
            ? 'Pricing Container: '.$reason
            : 'Pricing Container approval';

        try {
            $values['cp'] = $newCp;
            $product->Values = $values;
            $product->save();

            CpHistory::create([
                'sku' => $product->sku,
                'old_cp' => $oldCp,
                'new_cp' => $newCp,
                'is_increase' => $isIncrease,
                'reason' => $historyReason,
                'changed_by' => Auth::user()->email ?? Auth::user()->name ?? 'system',
                'approved' => false,
            ]);

            return [
                'success' => true,
                'updated' => true,
                'message' => 'CP Master updated.',
                'current_cp' => $newCp,
            ];
        } catch (\Throwable $e) {
            Log::error('PricingContainer CP Master sync failed: '.$e->getMessage());

            return [
                'success' => false,
                'updated' => false,
                'message' => 'Error updating CP Master: '.$e->getMessage(),
                'current_cp' => $oldCp,
            ];
        }
    }
}
