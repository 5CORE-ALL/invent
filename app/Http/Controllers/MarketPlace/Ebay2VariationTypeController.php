<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\Ebay2VariationTypePush;
use App\Models\ShopifySku;
use App\Services\Ebay2ApiService;
use App\Services\Support\EbayTradingReviseItem;
use App\Support\Marketplace\EbayListingEnded;
use App\Support\Marketplace\ListingManagerFamily;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * eBay 2 Variation Type: import the variation aspect each main (parent) listing varies by (Color, Size, ...)
 * with the Trading API GetItem call, and add a new variation type to a parent with ReviseFixedPriceItem (the value
 * of each SKU comes from Variation Master's Variation Name).
 */
class Ebay2VariationTypeController extends Controller
{
    private const MAX_IMPORT_BATCH = 10;

    private const MAX_TYPE_LENGTH = 65;

    public function index()
    {
        return view('market-places.ebay2_variation_type');
    }

    /**
     * One row per live eBay 2 main listing that has variations, with what was last imported / typed / pushed.
     */
    public function data(): JsonResponse
    {
        if (! $this->tableReady()) {
            return response()->json(['data' => [], 'meta' => ['message' => 'Run php artisan migrate (ebay2_variation_type_pushes table is missing).']], 500);
        }

        $listings = $this->mainListings();
        $master = $this->variationMasterInfo();
        $saved = Ebay2VariationTypePush::query()->get()->keyBy('item_id');

        $rows = [];
        foreach ($listings as $itemId => $listing) {
            $rec = $saved->get($itemId);
            $parentNorm = ShopifySku::normalizeSkuForShopifyLookup($listing['parent_key']);
            $rows[] = [
                'item_id' => (string) $itemId,
                'main_listing' => $listing['main_listing'],
                'children' => $listing['children'],
                'master_type' => $master[$parentNorm]['type'] ?? '',
            ] + $this->skuColumns($listing, $rec, $master) + ($rec ? $this->rowPayload($rec) : $this->emptyPayload());
        }

        usort($rows, static fn (array $a, array $b) => strnatcasecmp($a['main_listing'], $b['main_listing']));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => count($rows),
                'imported' => count(array_filter($rows, static fn ($r) => $r['current_type'] !== '')),
                'success' => count(array_filter($rows, static fn ($r) => $r['push_status'] === 'success')),
                'failed' => count(array_filter($rows, static fn ($r) => $r['push_status'] === 'failed')),
                'values_success' => count(array_filter($rows, static fn ($r) => $r['values_status'] === 'success')),
                'values_failed' => count(array_filter($rows, static fn ($r) => $r['values_status'] === 'failed')),
            ],
        ]);
    }

    /**
     * Read the current variation type(s) of up to 10 listings from eBay 2 (GetItem). The page calls this in
     * batches so a long list never hits a request timeout.
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_ids' => 'required|array|min:1|max:'.self::MAX_IMPORT_BATCH,
            'item_ids.*' => 'required|string|max:40',
        ]);

        if (! $this->tableReady()) {
            return $this->missingTable();
        }

        set_time_limit(300);
        $listings = $this->mainListings();
        $master = $this->variationMasterInfo();
        $service = app(Ebay2ApiService::class);
        $results = [];

        foreach (array_values(array_unique($data['item_ids'])) as $itemId) {
            $itemId = trim((string) $itemId);
            $listing = $listings[$itemId] ?? null;

            try {
                $item = $service->getItem($itemId);
                if (! is_array($item)) {
                    $why = trim((string) ($service->lastGetItemError ?? ''));
                    $results[] = $this->saveImport($itemId, $listing, null, $why !== '' ? $why : 'eBay did not return this listing.', null, null, $master);

                    continue;
                }

                $aspects = EbayTradingReviseItem::variationAspects($item);
                if ($aspects === []) {
                    $results[] = $this->saveImport($itemId, $listing, '', 'This eBay listing has no variations (no variation type to import).', [], [], $master);

                    continue;
                }

                // Type and values of the listing, plus the child SKUs that exist on it and the value each one has.
                $results[] = $this->saveImport(
                    $itemId, $listing, implode(' / ', array_keys($aspects)), null, $aspects,
                    EbayTradingReviseItem::variationSpecificsList($item), $master,
                );
            } catch (\Throwable $e) {
                Log::warning('eBay2 variation type import failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
                $results[] = $this->saveImport($itemId, $listing, null, $e->getMessage(), null, null, $master);
            }
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /** Remember the value typed in the New Type cell. */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => 'required|string|max:40',
            'new_type' => 'nullable|string|max:'.self::MAX_TYPE_LENGTH,
        ]);

        if (! $this->tableReady()) {
            return $this->missingTable();
        }

        $itemId = trim($data['item_id']);
        $newType = trim((string) ($data['new_type'] ?? ''));
        $rec = Ebay2VariationTypePush::firstOrNew(['item_id' => $itemId]);
        if (! $rec->exists) {
            $rec->parent_sku = $this->mainListings()[$itemId]['main_listing'] ?? null;
        }
        $rec->new_type = $newType === '' ? null : $newType;
        $rec->save();

        return response()->json(['success' => true, 'new_type' => $newType]);
    }

    /**
     * Add a new variation type to one eBay 2 listing (Trading API ReviseFixedPriceItem) and record the result. The
     * existing variation types stay; each variation gets the Variation Master "Variation Name" of its SKU as the
     * value of the new type. Without a typed New Type the parent's Variation Master type is added.
     */
    public function push(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => 'required|string|max:40',
            'new_type' => 'nullable|string|max:'.self::MAX_TYPE_LENGTH,
        ]);

        if (! $this->tableReady()) {
            return $this->missingTable();
        }

        set_time_limit(300);
        $itemId = trim($data['item_id']);
        $listing = $this->mainListings()[$itemId] ?? null;
        $masterInfo = $listing ? ($this->variationMasterInfo()[ShopifySku::normalizeSkuForShopifyLookup($listing['parent_key'])] ?? null) : null;
        $newType = trim((string) ($data['new_type'] ?? ''));
        if ($newType === '') {
            $newType = trim((string) ($masterInfo['type'] ?? ''));
        }

        $rec = Ebay2VariationTypePush::firstOrNew(['item_id' => $itemId]);
        if (! $rec->exists || ! $rec->parent_sku) {
            $rec->parent_sku = $listing['main_listing'] ?? $rec->parent_sku;
        }
        $rec->new_type = $newType === '' ? null : $newType;

        try {
            if ($newType === '') {
                $result = ['success' => false, 'message' => 'Enter the new variation type first (this parent has no Variation Master type to use).'];
            } else {
                $result = $this->addToEbay($itemId, $newType, $masterInfo['names'] ?? []);
            }
        } catch (\Throwable $e) {
            Log::warning('eBay2 variation type push failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        $rec->push_status = $result['success'] ? 'success' : 'failed';
        $rec->push_message = mb_substr(trim((string) $result['message']), 0, 2000);
        $rec->pushed_at = now();
        $rec->pushed_by = (string) ($request->user()->name ?? 'unknown');
        if ($result['success'] && is_array($result['aspects'] ?? null)) {
            // The listing now varies by its old types plus the new one.
            $rec->current_values = $result['aspects'];
            $rec->current_type = implode(' / ', array_keys($result['aspects']));
            $rec->imported_at = now();
            $rec->import_error = null;
            // Keep the pulled child variations in step: every SKU now has a value for the new type too.
            if (is_array($rec->live_variations) && $rec->live_variations !== []) {
                $rec->live_variations = array_map(function ($v) use ($result, $newType) {
                    $sku = trim((string) ($v['sku'] ?? ''));
                    $v['specifics'] = (array) ($v['specifics'] ?? []);
                    $v['specifics'][$newType] = (string) ($result['values_by_sku'][$sku] ?? '');

                    return $v;
                }, $rec->live_variations);
            }
        }
        $rec->save();

        return response()->json([
            'success' => $result['success'],
            'row' => $this->rowWithSkus($rec),
        ]);
    }

    /**
     * @param  array<string, string>  $masterNames  normalized SKU => Variation Master Variation Name
     * @return array{success: bool, message: string, aspects?: array<string, list<string>>, values_by_sku?: array<string, string>}
     */
    private function addToEbay(string $itemId, string $newType, array $masterNames): array
    {
        $service = app(Ebay2ApiService::class);
        $item = $service->getItem($itemId);
        if (! is_array($item)) {
            $why = trim((string) ($service->lastGetItemError ?? ''));

            return ['success' => false, 'message' => $why !== '' ? $why : 'eBay did not return the listing.'];
        }

        // Value of the new type for each variation on eBay: the Variation Name of that SKU in Variation Master.
        $vars = EbayTradingReviseItem::itemFromGetItem($item)['Variations']['Variation'] ?? [];
        if (isset($vars['SKU']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }
        $valuesBySku = [];
        foreach ((array) $vars as $variation) {
            $vSku = is_array($variation) ? trim((string) ($variation['SKU'] ?? '')) : '';
            if ($vSku !== '' && ($masterNames[$this->skuKey($vSku)] ?? '') !== '') {
                $valuesBySku[$vSku] = $masterNames[$this->skuKey($vSku)];
            }
        }

        $ctx = $service->tradingReviseContext();
        $creds = [$ctx['endpoint'], $ctx['compatLevel'], $ctx['devId'], $ctx['appId'], $ctx['certId'], $ctx['siteId'], $ctx['authToken']];

        $result = EbayTradingReviseItem::addVariationAspect(...$creds, getItemResponse: $item, newName: $newType, valuesBySku: $valuesBySku);
        $result['values_by_sku'] = $valuesBySku;

        return $result;
    }

    /**
     * The SKU-related columns of a row.
     *  - skus: every child SKU of the parent. After a Pull these are the SKUs that really exist on the live eBay 2
     *    listing (GetItem); before one, the SKUs eBay 2 inventory reports. Variation Master children that are not
     *    on eBay are added too.
     *  - skus_not_on_ebay: the Variation Master children that are not on the eBay 2 listing.
     *  - sku_names: Variation Master "Variation Name" of each SKU in skus, same order ('' when none).
     *  - ebay_names: the variation value each SKU has on the live listing, same order ('' when not pulled / not live).
     *
     * @param  array<string, mixed>|null  $listing
     * @param  array<string, array{type: string, skus: list<string>, names: array<string, string>}>  $master
     * @return array<string, mixed>
     */
    private function skuColumns(?array $listing, ?Ebay2VariationTypePush $rec, array $master): array
    {
        if ($listing === null) {
            return [];
        }
        $parentNorm = ShopifySku::normalizeSkuForShopifyLookup($listing['parent_key']);
        $live = is_array($rec?->live_variations) ? $rec->live_variations : [];
        $liveSkus = array_values(array_filter(array_map(static fn ($v) => trim((string) ($v['sku'] ?? '')), $live)));
        $liveBySku = [];
        foreach ($live as $v) {
            $sku = trim((string) ($v['sku'] ?? ''));
            if ($sku !== '') {
                $liveBySku[$this->skuKey($sku)] = array_values(array_filter(array_map('strval', (array) ($v['specifics'] ?? []))));
            }
        }

        [$childSkus, $notOnEbay] = $this->childSkuLists($live !== [] ? $liveSkus : $listing['skus'], $master[$parentNorm]['skus'] ?? []);

        return [
            'skus' => $childSkus,
            'skus_not_on_ebay' => $notOnEbay,
            'sku_names' => array_map(fn (string $s) => $master[$parentNorm]['names'][$this->skuKey($s)] ?? '', $childSkus),
            'ebay_names' => array_map(fn (string $s) => implode(' / ', $liveBySku[$this->skuKey($s)] ?? []), $childSkus),
            // Lower-case SKUs for the SKU search box.
            'sku_search' => mb_strtolower(implode(' ', $childSkus)),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $listing
     * @param  array<string, array{type: string, skus: list<string>, names: array<string, string>}>|null  $master
     * @return array<string, mixed>
     */
    private function saveImport(string $itemId, ?array $listing, ?string $currentType, ?string $error, ?array $values = null, ?array $variations = null, ?array $master = null): array
    {
        $rec = Ebay2VariationTypePush::firstOrNew(['item_id' => $itemId]);
        if (! $rec->exists || ! $rec->parent_sku) {
            $rec->parent_sku = $listing['main_listing'] ?? $rec->parent_sku;
        }
        if ($currentType !== null) {
            $rec->current_type = $currentType;
        }
        if ($values !== null) {
            $rec->current_values = $values;
            // Edits typed against values that are no longer on the listing are dropped.
            $rec->value_edits = $this->pruneEdits((array) ($rec->value_edits ?? []), $values) ?: null;
        }
        if ($variations !== null) {
            $rec->live_variations = $variations;
        }
        $rec->import_error = $error;
        $rec->imported_at = now();
        $rec->save();

        return $this->rowPayload($rec) + ($master !== null ? $this->skuColumns($listing, $rec, $master) : []);
    }

    /** The row as the page shows it, including the SKU columns (they depend on the pulled live variations). */
    private function rowWithSkus(Ebay2VariationTypePush $rec): array
    {
        $listing = $this->mainListings()[(string) $rec->item_id] ?? null;

        return $this->rowPayload($rec) + $this->skuColumns($listing, $rec, $listing ? $this->variationMasterInfo() : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function rowPayload(Ebay2VariationTypePush $rec): array
    {
        return [
            'item_id' => (string) $rec->item_id,
            'current_type' => (string) ($rec->current_type ?? ''),
            'imported_at' => $rec->imported_at?->format('d M Y H:i'),
            'import_error' => (string) ($rec->import_error ?? ''),
            'new_type' => (string) ($rec->new_type ?? ''),
            'push_status' => (string) ($rec->push_status ?? ''),
            'push_message' => (string) ($rec->push_message ?? ''),
            'pushed_at' => $rec->pushed_at?->format('d M Y H:i'),
            // aspect => [values], an empty object (not []) when nothing was imported so the page can tell.
            'current_values' => (object) (is_array($rec->current_values) ? $rec->current_values : []),
            'value_edits' => array_values(is_array($rec->value_edits) ? $rec->value_edits : []),
            'values_status' => (string) ($rec->values_status ?? ''),
            'values_message' => (string) ($rec->values_message ?? ''),
            'values_pushed_at' => $rec->values_pushed_at?->format('d M Y H:i'),
            // True once the child SKUs and their variation values were pulled from the live listing.
            'live_pulled' => is_array($rec->live_variations) && $rec->live_variations !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'current_type' => '', 'imported_at' => null, 'import_error' => '', 'new_type' => '',
            'push_status' => '', 'push_message' => '', 'pushed_at' => null,
            'current_values' => (object) [], 'value_edits' => [],
            'values_status' => '', 'values_message' => '', 'values_pushed_at' => null,
            'live_pulled' => false,
        ];
    }

    /**
     * Keep only well-formed edits that point at a value still on the listing and actually change its name.
     *
     * @param  array<int|string, mixed>  $edits
     * @param  array<string, list<string>>  $values
     * @return list<array{aspect: string, old: string, new: string}>
     */
    private function pruneEdits(array $edits, array $values): array
    {
        $out = [];
        $seen = [];
        foreach ($edits as $e) {
            if (! is_array($e)) {
                continue;
            }
            $aspect = trim((string) ($e['aspect'] ?? ''));
            $old = trim((string) ($e['old'] ?? ''));
            $new = trim((string) ($e['new'] ?? ''));
            if ($aspect === '' || $old === '' || $new === '' || $new === $old || mb_strlen($new) > self::MAX_TYPE_LENGTH) {
                continue;
            }
            if (! isset($values[$aspect]) || ! in_array($old, array_map('strval', $values[$aspect]), true)) {
                continue;
            }
            $key = $aspect."\0".$old;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['aspect' => $aspect, 'old' => $old, 'new' => $new];
        }

        return $out;
    }

    /** Remember the value renames typed in the values editor. */
    public function saveValues(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => 'required|string|max:40',
            'edits' => 'nullable|array|max:500',
            'edits.*.aspect' => 'required|string|max:191',
            'edits.*.old' => 'required|string|max:191',
            'edits.*.new' => 'nullable|string|max:'.self::MAX_TYPE_LENGTH,
        ]);

        if (! $this->tableReady()) {
            return $this->missingTable();
        }

        $rec = Ebay2VariationTypePush::firstOrNew(['item_id' => trim($data['item_id'])]);
        if (! $rec->exists) {
            $rec->parent_sku = $this->mainListings()[$rec->item_id]['main_listing'] ?? null;
        }
        $known = is_array($rec->current_values) ? $rec->current_values : [];
        $edits = $this->pruneEdits($data['edits'] ?? [], $known);
        $rec->value_edits = $edits === [] ? null : $edits;
        $rec->save();

        return response()->json(['success' => true, 'value_edits' => $edits]);
    }

    /**
     * Rename values of the variation type on eBay 2 (Trading API ReviseFixedPriceItem) and record the result.
     */
    public function pushValues(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => 'required|string|max:40',
        ]);

        if (! $this->tableReady()) {
            return $this->missingTable();
        }

        set_time_limit(300);
        $itemId = trim($data['item_id']);
        $rec = Ebay2VariationTypePush::firstOrNew(['item_id' => $itemId]);
        if (! $rec->exists || ! $rec->parent_sku) {
            $rec->parent_sku = $this->mainListings()[$itemId]['main_listing'] ?? $rec->parent_sku;
        }

        $edits = is_array($rec->value_edits) ? array_values($rec->value_edits) : [];

        try {
            if ($edits === []) {
                $result = ['success' => false, 'message' => 'No value edits to push. Use Edit to rename a value first.'];
            } else {
                $result = $this->pushValuesToEbay($itemId, $edits);
            }
        } catch (\Throwable $e) {
            Log::warning('eBay2 variation values push failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        $rec->values_status = $result['success'] ? 'success' : 'failed';
        $rec->values_message = mb_substr(trim((string) $result['message']), 0, 2000);
        $rec->values_pushed_at = now();
        $rec->values_pushed_by = (string) ($request->user()->name ?? 'unknown');
        // Only the renames eBay really applied move into the current values; a failed one stays as a waiting edit.
        $applied = (array) ($result['applied'] ?? []);
        if ($applied !== []) {
            $values = is_array($rec->current_values) ? $rec->current_values : [];
            foreach ($applied as $e) {
                foreach (($values[$e['aspect']] ?? []) as $i => $v) {
                    if ((string) $v === $e['old']) {
                        $values[$e['aspect']][$i] = $e['new'];
                    }
                }
            }
            $rec->current_values = $values;
            // The pulled child variations carry the renamed values too.
            if (is_array($rec->live_variations) && $rec->live_variations !== []) {
                $rec->live_variations = array_map(static function ($v) use ($applied) {
                    foreach ((array) ($v['specifics'] ?? []) as $aspect => $value) {
                        foreach ($applied as $e) {
                            if (mb_strtolower((string) $aspect) === mb_strtolower((string) $e['aspect']) && (string) $value === $e['old']) {
                                $v['specifics'][$aspect] = $e['new'];
                            }
                        }
                    }

                    return $v;
                }, $rec->live_variations);
            }
            $isApplied = static function (array $edit) use ($applied): bool {
                foreach ($applied as $a) {
                    if ($a['aspect'] === $edit['aspect'] && $a['old'] === $edit['old']) {
                        return true;
                    }
                }

                return false;
            };
            $left = array_values(array_filter($edits, static fn ($e) => ! $isApplied($e)));
            $rec->value_edits = $left === [] ? null : $left;
            $rec->imported_at = now();
        }
        $rec->save();

        return response()->json([
            'success' => $result['success'],
            'row' => $this->rowWithSkus($rec),
        ]);
    }

    /**
     * @param  list<array{aspect: string, old: string, new: string}>  $edits
     * @return array{success: bool, message: string, applied?: list<array{aspect: string, old: string, new: string}>}
     */
    private function pushValuesToEbay(string $itemId, array $edits): array
    {
        $service = app(Ebay2ApiService::class);
        $item = $service->getItem($itemId);
        if (! is_array($item)) {
            $why = trim((string) ($service->lastGetItemError ?? ''));

            return ['success' => false, 'message' => $why !== '' ? $why : 'eBay did not return the listing.'];
        }

        $ctx = $service->tradingReviseContext();
        $creds = [$ctx['endpoint'], $ctx['compatLevel'], $ctx['devId'], $ctx['appId'], $ctx['certId'], $ctx['siteId'], $ctx['authToken']];

        return EbayTradingReviseItem::renameVariationValues(...$creds, getItemResponse: $item, renames: $edits);
    }

    private function tableReady(): bool
    {
        return Schema::hasTable('ebay2_variation_type_pushes')
            && Schema::hasColumn('ebay2_variation_type_pushes', 'current_values')
            && Schema::hasColumn('ebay2_variation_type_pushes', 'live_variations');
    }

    private function missingTable(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Run php artisan migrate (ebay2_variation_type_pushes table is missing).',
        ], 500);
    }

    /**
     * All child SKUs of one parent: the SKUs on the live eBay listing plus the Variation Master children, without
     * the PARENT label and without duplicates (compared the way Shopify SKUs are matched).
     *
     * @param  list<string>  $ebaySkus
     * @param  list<string>  $masterSkus
     * @return array{0: list<string>, 1: list<string>} [all SKUs sorted, Variation Master SKUs missing on eBay]
     */
    private function childSkuLists(array $ebaySkus, array $masterSkus): array
    {
        $all = [];
        $onEbay = [];
        foreach ($ebaySkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || preg_match('/^PARENT\b/i', $sku)) {
                continue;
            }
            $key = ShopifySku::normalizeSkuForShopifyLookup($sku) ?: strtoupper($sku);
            $onEbay[$key] = true;
            $all[$key] = $sku;
        }

        $notOnEbay = [];
        foreach ($masterSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || preg_match('/^PARENT\b/i', $sku)) {
                continue;
            }
            $key = ShopifySku::normalizeSkuForShopifyLookup($sku) ?: strtoupper($sku);
            if (isset($onEbay[$key])) {
                continue;
            }
            $all[$key] = $all[$key] ?? $sku;
            $notOnEbay[$key] = $sku;
        }

        $all = array_values($all);
        $notOnEbay = array_values($notOnEbay);
        natcasesort($all);
        natcasesort($notOnEbay);

        return [array_values($all), array_values($notOnEbay)];
    }

    /**
     * Live eBay 2 main listings with variations, keyed by item_id. A main listing is the item that carries a
     * "PARENT xxx" SKU (or, failing that, an item shared by several SKUs). Ended listings are skipped, and only the
     * latest inventory pull counts as current.
     *
     * @return array<string, array{main_listing: string, parent_key: string, children: int, skus: list<string>}>
     */
    private function mainListings(): array
    {
        $hasStatus = Schema::hasColumn('ebay_2_metrics', 'listing_status');
        $hasRange = Schema::hasColumn('ebay_2_metrics', 'report_range');

        $latestRange = null;
        if ($hasRange) {
            $max = DB::table('ebay_2_metrics')->max('report_range');
            $latestRange = $max !== null && $max !== '' ? (string) $max : null;
        }

        $columns = ['sku', 'item_id'];
        if ($hasStatus) {
            $columns[] = 'listing_status';
        }
        if ($hasRange) {
            $columns[] = 'report_range';
        }

        $items = [];
        $rows = DB::table('ebay_2_metrics')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('item_id')->where('item_id', '!=', '')
            ->get($columns);

        foreach ($rows as $row) {
            if ($hasRange && $latestRange !== null && (string) ($row->report_range ?? '') !== $latestRange) {
                continue;
            }
            if ($hasStatus) {
                $status = (string) ($row->listing_status ?? '');
                if ($status !== '' && (EbayListingEnded::isEnded($status) || strtoupper(trim($status)) === 'MISSING')) {
                    continue;
                }
            }

            $itemId = trim((string) $row->item_id);
            $sku = trim((string) $row->sku);
            if ($itemId === '' || $sku === '') {
                continue;
            }
            $items[$itemId][$sku] = true;
        }

        $out = [];
        foreach ($items as $itemId => $skuSet) {
            $skus = array_keys($skuSet);
            $parentSku = null;
            foreach ($skus as $sku) {
                if (preg_match('/^PARENT\s+/i', $sku)) {
                    $parentSku = $sku;
                    break;
                }
            }
            $childCount = count(array_filter($skus, static fn ($s) => ! preg_match('/^PARENT\s+/i', $s)));
            if ($parentSku === null && count($skus) < 2) {
                continue; // single-SKU listing: no variation type
            }

            $label = $parentSku ?? $skus[0];
            $out[(string) $itemId] = [
                'main_listing' => $label,
                'parent_key' => trim((string) preg_replace('/^PARENT\s+/i', '', $label)),
                'children' => $childCount,
                'skus' => array_values(array_map('strval', $skus)),
            ];
        }

        return $out;
    }

    /**
     * What Variation Master (product_master.Values) holds per parent, shown next to what eBay has:
     *  - type: the most common Variation Type among the children,
     *  - skus: the child SKUs,
     *  - names: normalized child SKU => its Variation Name (the SKU-wise "Variation Name" column of /variation-master).
     *
     * @return array<string, array{type: string, skus: list<string>, names: array<string, string>}> normalized parent => info
     */
    private function variationMasterInfo(): array
    {
        $typeCounts = [];
        $skuNames = [];
        $childSkus = [];

        $rows = DB::table('product_master')
            ->whereNull('deleted_at')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->get(['parent', 'sku', 'Values']);

        $clean = static fn ($v): string => trim((string) preg_replace('/\s+/', ' ', trim((string) ($v ?? ''))));

        foreach ($rows as $pm) {
            $sku = $clean($pm->sku);
            if ($sku === '') {
                continue;
            }
            $values = $pm->Values ?? null;
            if (is_string($values)) {
                $values = json_decode($values, true) ?: [];
            }
            $values = is_array($values) ? $values : [];
            $name = $clean($values['variation_name'] ?? null);

            // The PARENT row has no per-SKU name of its own to show.
            if (preg_match('/^PARENT/i', $sku)) {
                continue;
            }

            $parent = $clean($pm->parent);
            if ($parent === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup(ListingManagerFamily::normalizeParentKey($parent));
            if ($norm === '') {
                continue;
            }
            $type = $clean($values['variation_type'] ?? null);
            if ($type !== '') {
                $typeCounts[$norm][$type] = ($typeCounts[$norm][$type] ?? 0) + 1;
            }
            $childSkus[$norm][] = $sku;
            if ($name !== '') {
                $skuNames[$norm][$this->skuKey($sku)] = $name;
            }
        }

        $out = [];
        foreach (array_unique(array_merge(array_keys($typeCounts), array_keys($childSkus))) as $norm) {
            $types = $typeCounts[$norm] ?? [];
            arsort($types);
            $out[(string) $norm] = [
                'type' => $types === [] ? '' : (string) array_key_first($types),
                'skus' => $childSkus[$norm] ?? [],
                'names' => $skuNames[$norm] ?? [],
            ];
        }

        return $out;
    }

    /** Key that matches the same SKU across eBay and product_master (case, spacing and Shopify-style variants). */
    private function skuKey(string $sku): string
    {
        $sku = trim($sku);

        return ShopifySku::normalizeSkuForShopifyLookup($sku) ?: strtoupper($sku);
    }
}
