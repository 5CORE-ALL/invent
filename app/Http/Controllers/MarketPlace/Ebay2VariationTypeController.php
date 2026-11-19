<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\Ebay2VariationTypePush;
use App\Models\ShopifySku;
use App\Services\Ebay2ApiService;
use App\Services\Support\EbayTradingReviseItem;
use App\Support\Marketplace\EbayListingEnded;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * eBay 2 Variation Type: import the variation aspect each main (parent) listing varies by (Color, Size, ...)
 * with the Trading API GetItem call, edit it, and push the new name back with ReviseFixedPriceItem.
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
        $masterTypes = $this->variationMasterTypes();
        $saved = Ebay2VariationTypePush::query()->get()->keyBy('item_id');

        $rows = [];
        foreach ($listings as $itemId => $listing) {
            $rec = $saved->get($itemId);
            $parentNorm = ShopifySku::normalizeSkuForShopifyLookup($listing['parent_key']);
            $rows[] = [
                'item_id' => (string) $itemId,
                'main_listing' => $listing['main_listing'],
                'children' => $listing['children'],
                'master_type' => $masterTypes[$parentNorm] ?? '',
                'current_type' => (string) ($rec->current_type ?? ''),
                'imported_at' => $rec?->imported_at?->format('d M Y H:i'),
                'import_error' => (string) ($rec->import_error ?? ''),
                'new_type' => (string) ($rec->new_type ?? ''),
                'push_status' => (string) ($rec->push_status ?? ''),
                'push_message' => (string) ($rec->push_message ?? ''),
                'pushed_at' => $rec?->pushed_at?->format('d M Y H:i'),
            ];
        }

        usort($rows, static fn (array $a, array $b) => strnatcasecmp($a['main_listing'], $b['main_listing']));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => count($rows),
                'imported' => count(array_filter($rows, static fn ($r) => $r['current_type'] !== '')),
                'success' => count(array_filter($rows, static fn ($r) => $r['push_status'] === 'success')),
                'failed' => count(array_filter($rows, static fn ($r) => $r['push_status'] === 'failed')),
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
        $service = app(Ebay2ApiService::class);
        $results = [];

        foreach (array_values(array_unique($data['item_ids'])) as $itemId) {
            $itemId = trim((string) $itemId);
            $listing = $listings[$itemId] ?? null;

            try {
                $item = $service->getItem($itemId);
                if (! is_array($item)) {
                    $why = trim((string) ($service->lastGetItemError ?? ''));
                    $results[] = $this->saveImport($itemId, $listing, null, $why !== '' ? $why : 'eBay did not return this listing.');

                    continue;
                }

                $aspects = EbayTradingReviseItem::variationAspects($item);
                if ($aspects === []) {
                    $results[] = $this->saveImport($itemId, $listing, '', 'This eBay listing has no variations (no variation type to import).');

                    continue;
                }

                $results[] = $this->saveImport($itemId, $listing, implode(' / ', array_keys($aspects)), null);
            } catch (\Throwable $e) {
                Log::warning('eBay2 variation type import failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
                $results[] = $this->saveImport($itemId, $listing, null, $e->getMessage());
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
     * Rename the variation type of one eBay 2 listing (Trading API ReviseFixedPriceItem) and record the result.
     */
    public function push(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => 'required|string|max:40',
            'new_type' => 'required|string|max:'.self::MAX_TYPE_LENGTH,
        ]);

        if (! $this->tableReady()) {
            return $this->missingTable();
        }

        set_time_limit(300);
        $itemId = trim($data['item_id']);
        $newType = trim($data['new_type']);
        $listing = $this->mainListings()[$itemId] ?? null;

        $rec = Ebay2VariationTypePush::firstOrNew(['item_id' => $itemId]);
        if (! $rec->exists || ! $rec->parent_sku) {
            $rec->parent_sku = $listing['main_listing'] ?? $rec->parent_sku;
        }
        $rec->new_type = $newType;

        try {
            if ($newType === '') {
                $result = ['success' => false, 'message' => 'Enter the new variation type first.'];
            } else {
                $result = $this->pushToEbay($itemId, $newType);
            }
        } catch (\Throwable $e) {
            Log::warning('eBay2 variation type push failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        $rec->push_status = $result['success'] ? 'success' : 'failed';
        $rec->push_message = mb_substr(trim((string) $result['message']), 0, 2000);
        $rec->pushed_at = now();
        $rec->pushed_by = (string) ($request->user()->name ?? 'unknown');
        if ($result['success']) {
            $rec->current_type = $newType;
            $rec->imported_at = now();
            $rec->import_error = null;
        }
        $rec->save();

        return response()->json([
            'success' => $result['success'],
            'row' => $this->rowPayload($rec),
        ]);
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function pushToEbay(string $itemId, string $newType): array
    {
        $service = app(Ebay2ApiService::class);
        $item = $service->getItem($itemId);
        if (! is_array($item)) {
            $why = trim((string) ($service->lastGetItemError ?? ''));

            return ['success' => false, 'message' => $why !== '' ? $why : 'eBay did not return the listing.'];
        }

        $ctx = $service->tradingReviseContext();
        $creds = [$ctx['endpoint'], $ctx['compatLevel'], $ctx['devId'], $ctx['appId'], $ctx['certId'], $ctx['siteId'], $ctx['authToken']];

        return EbayTradingReviseItem::renameVariationAspect(...$creds, getItemResponse: $item, newName: $newType);
    }

    /**
     * @param  array<string, mixed>|null  $listing
     * @return array<string, mixed>
     */
    private function saveImport(string $itemId, ?array $listing, ?string $currentType, ?string $error): array
    {
        $rec = Ebay2VariationTypePush::firstOrNew(['item_id' => $itemId]);
        if (! $rec->exists || ! $rec->parent_sku) {
            $rec->parent_sku = $listing['main_listing'] ?? $rec->parent_sku;
        }
        if ($currentType !== null) {
            $rec->current_type = $currentType;
        }
        $rec->import_error = $error;
        $rec->imported_at = now();
        $rec->save();

        return $this->rowPayload($rec);
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
        ];
    }

    private function tableReady(): bool
    {
        return Schema::hasTable('ebay2_variation_type_pushes');
    }

    private function missingTable(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Run php artisan migrate (ebay2_variation_type_pushes table is missing).',
        ], 500);
    }

    /**
     * Live eBay 2 main listings with variations, keyed by item_id. A main listing is the item that carries a
     * "PARENT xxx" SKU (or, failing that, an item shared by several SKUs). Ended listings are skipped, and only the
     * latest inventory pull counts as current.
     *
     * @return array<string, array{main_listing: string, parent_key: string, children: int}>
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
            ];
        }

        return $out;
    }

    /**
     * Variation Master "Variation Type" per CP Master parent (most common value among its children), shown as a
     * reference next to what eBay currently has.
     *
     * @return array<string, string> normalized parent => type
     */
    private function variationMasterTypes(): array
    {
        $counts = [];
        $rows = DB::table('product_master')
            ->whereNull('deleted_at')
            ->whereNotNull('parent')->where('parent', '!=', '')
            ->get(['parent', 'sku', 'Values']);

        foreach ($rows as $pm) {
            $sku = trim((string) ($pm->sku ?? ''));
            if ($sku === '' || preg_match('/^PARENT/i', $sku)) {
                continue;
            }
            $values = $pm->Values ?? null;
            if (is_string($values)) {
                $values = json_decode($values, true) ?: [];
            }
            $type = is_array($values) ? trim((string) ($values['variation_type'] ?? '')) : '';
            if ($type === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup(trim((string) $pm->parent));
            if ($norm === '') {
                continue;
            }
            $counts[$norm][$type] = ($counts[$norm][$type] ?? 0) + 1;
        }

        $out = [];
        foreach ($counts as $norm => $types) {
            arsort($types);
            $out[$norm] = (string) array_key_first($types);
        }

        return $out;
    }
}
