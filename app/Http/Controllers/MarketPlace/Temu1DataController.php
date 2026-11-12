<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Services\MarketplaceManager\Temu1DataService;
use App\Services\MarketplaceManager\TemuCategoryTree;
use App\Services\TemuApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Temu 1 Data": the same page as Ebay1 Data for the Temu 1 shop. Every row is a Listing Temu 1 row (Listed / Missing L),
 * the category and the item specifics are the ones Temu holds for the goods (category attribute list), and the column
 * heads are Temu's. Title and price are pushed with the Temu calls the app already uses. Item specifics are pushed
 * only on request for the SKUs that are ticked (test), never by Auto Push.
 */
class Temu1DataController extends Ebay1DataController
{
    /** @var array<string, array{category_path: string, specifics: array<string, mixed>}>|null */
    private ?array $pulledMemo = null;

    protected function cfg(): array
    {
        return [
            'key' => 'temu1',
            'label' => 'Temu 1',
            'prefix' => 'temu1',
            'registry' => 'temu',
            'base' => '/temu1-item-data',
            'metric' => TemuMetric::class,
            'dataView' => TemuDataView::class,
            'service' => TemuApiService::class,
            'main' => false,
            // Temu wording and behaviour used by the page.
            'channel' => 'temu',
            'site' => 'Temu',
            'page' => 'Temu 1 Data',
            'title_name' => 'Product name',
            'price_name' => 'Base price',
            'title_max' => 500,
            'item_url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=',
            'col_titles' => [
                'item_id' => 'Goods ID',
                'ebay_title' => 'Product name',
                'ebay_price' => 'Base price',
                'ebay_stock' => 'Quantity',
                'ebay_l30' => 'Qty sold L30',
                'ebay_l60' => 'Qty sold L60',
                'views' => 'Impressions L30',
                'l7_views' => 'Clicks L7',
                'l1_views' => 'Clicks L1',
                'organic_clicks' => 'Clicks L30',
                'report_date' => 'Synced on',
                'category' => 'Temu category',
            ],
            'hide' => ['ebay_l7'],
        ];
    }

    protected function titleName(): string
    {
        return 'Product name';
    }

    protected function priceName(): string
    {
        return 'Base price';
    }

    protected function titleMax(): int
    {
        return 500;
    }

    protected function hasUpcColumn(): bool
    {
        return false;
    }

    protected function fillsFixedValues(): bool
    {
        // Temu shows the values the pull saved. The eBay fixed values (Brand "5 Core", MPN = the SKU) must not replace them.
        return false;
    }

    protected function categoryMasterColumn(): string
    {
        return 'category_temu';
    }

    /** Goods ID of the live Temu 1 listing of a SKU, '' when it has none. */
    protected function liveItemId(string $sku): string
    {
        if (! Schema::hasTable($this->metricsTable())) {
            return '';
        }
        $goodsId = trim((string) DB::table($this->metricsTable())
            ->where('sku', $sku)
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->orderByDesc('id')
            ->value('goods_id'));

        return preg_match('/^\d{6,20}$/', $goodsId) ? $goodsId : '';
    }

    protected function pulledSpecificsBySku(): array
    {
        if ($this->pulledMemo !== null) {
            return $this->pulledMemo;
        }
        $out = parent::pulledSpecificsBySku();
        if (Schema::hasTable($this->t('item_specifics')) && Schema::hasColumn($this->t('item_specifics'), 'category_id')) {
            foreach (DB::table($this->t('item_specifics'))->select(['sku', 'category_id'])->get() as $row) {
                $key = $this->skuKey((string) $row->sku);
                if (isset($out[$key])) {
                    $out[$key]['category_id'] = (string) ($row->category_id ?? '');
                }
            }
        }

        return $this->pulledMemo = $out;
    }

    // ---- Attribute columns of every category, without pulling each product ----

    /** @var array<string, int>|null lower case category path => Temu category id (leaf categories) */
    private ?array $idByPath = null;

    /** @var array<int, list<string>> */
    private array $columnsMemo = [];

    /** @var array<int, true> categories whose attribute list has never been loaded */
    private array $needTemplate = [];

    private function normalPath(string $path): string
    {
        $parts = array_values(array_filter(array_map('trim', explode('>', mb_strtolower($path))), static fn ($p) => $p !== ''));

        return implode(' > ', $parts);
    }

    private function categoryIdForPath(string $path): int
    {
        if ($this->idByPath === null) {
            $this->idByPath = [];
            foreach (TemuCategoryTree::read(Temu1DataService::SHOP)['nodes'] as $id => $node) {
                if (! empty($node[2])) {
                    $full = TemuCategoryTree::path(Temu1DataService::SHOP, (string) $id);
                    if ($full !== '') {
                        $this->idByPath[$this->normalPath($full)] = (int) $id;
                    }
                }
            }
        }

        return $this->idByPath[$this->normalPath($path)] ?? 0;
    }

    /**
     * Every SKU of a category shows the attributes Temu defines for it as columns, blank until its value is known.
     * Reads the stored attribute lists only; a category without one is loaded by the page (load-attributes).
     */
    protected function withChannelColumns(array $specifics, string $category, ?array $live): array
    {
        $id = (int) ($live['category_id'] ?? 0);
        if ($id <= 0 && $category !== '') {
            $id = $this->categoryIdForPath($category);
        }
        if ($id <= 0) {
            return $specifics;
        }
        if (! isset($this->columnsMemo[$id])) {
            $cached = Temu1DataService::cachedTemplate($id);
            if ($cached['state'] === 'none') {
                $this->needTemplate[$id] = true;
            }
            $this->columnsMemo[$id] = Temu1DataService::columnNames($cached['props']);
        }
        if ($this->columnsMemo[$id] === []) {
            return $specifics;
        }
        $have = [];
        foreach (array_keys($specifics) as $name) {
            $have[$this->aspectKey((string) $name)] = true;
        }
        foreach ($this->columnsMemo[$id] as $name) {
            if (! isset($have[$this->aspectKey($name)])) {
                $specifics[$name] = '';
            }
        }

        return $specifics;
    }

    protected function rowsExtra(): array
    {
        return ['temu_pending' => array_keys($this->needTemplate)];
    }

    /**
     * POST /temu1-item-data/load-attributes   { category_ids: [..] }
     * Loads (and stores) the attribute list of up to 3 Temu categories, so their columns show for every SKU.
     */
    public function loadAttributes(Request $request)
    {
        $data = $request->validate([
            'category_ids' => 'required|array|min:1|max:3',
            'category_ids.*' => 'required|integer|min:1',
        ]);
        @set_time_limit(300);
        $api = app(TemuApiService::class);
        if (! $api->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Temu credentials are not configured.'], 500);
        }
        $service = new Temu1DataService($api);
        $results = [];
        foreach ($data['category_ids'] as $id) {
            try {
                $props = $service->template((int) $id, true);
                $names = Temu1DataService::columnNames($props);
                // Saved under the path the page shows for this category, so the columns land on that tab.
                foreach ($this->pathsForCategory((int) $id) as $path) {
                    $service->saveCategoryColumns($this->t('item_specifics'), $path, $names);
                }
                $results[] = [
                    'id' => (int) $id,
                    'ok' => $props !== [],
                    'columns' => count(Temu1DataService::columnNames($props)),
                    'message' => $props !== [] ? '' : ($service->lastError !== '' ? $service->lastError : 'Temu returned no attributes.'),
                ];
            } catch (\Throwable $e) {
                Log::warning('Temu1Data: load attributes failed', ['category_id' => $id, 'error' => $e->getMessage()]);
                $results[] = ['id' => (int) $id, 'ok' => false, 'columns' => 0, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * GET /temu1-item-data/category-field-targets
     * Every Temu category the SKUs of this page belong to, so the All fields button can fetch each one once.
     */
    public function categoryFieldTargets()
    {
        $ids = [];
        if (Schema::hasTable($this->t('item_specifics')) && Schema::hasColumn($this->t('item_specifics'), 'category_id')) {
            foreach (DB::table($this->t('item_specifics'))->whereNotNull('category_id')->distinct()->pluck('category_id') as $id) {
                if ((int) $id > 0) {
                    $ids[(int) $id] = true;
                }
            }
        }
        if (Schema::hasTable('category_masters') && Schema::hasColumn('category_masters', 'category_temu')) {
            foreach (DB::table('category_masters')->whereNotNull('category_temu')->where('category_temu', '!=', '')->distinct()->pluck('category_temu') as $path) {
                $id = $this->categoryIdForPath((string) $path);
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }

        $categories = [];
        foreach (array_keys($ids) as $id) {
            $paths = $this->pathsForCategory($id);
            $categories[] = ['id' => $id, 'path' => $paths[0] ?? ('Category '.$id)];
        }
        usort($categories, static fn ($a, $b) => strcasecmp($a['path'], $b['path']));

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    /**
     * The category path the page shows for a Temu category id: the one stored with the pulled SKUs, else the tree path.
     *
     * @return list<string>
     */
    private function pathsForCategory(int $id): array
    {
        $paths = [];
        if (Schema::hasTable($this->t('item_specifics'))) {
            foreach (DB::table($this->t('item_specifics'))->where('category_id', (string) $id)->whereNotNull('category_path')->distinct()->pluck('category_path') as $path) {
                $path = trim((string) $path);
                if ($path !== '') {
                    $paths[$path] = true;
                }
            }
        }
        if ($paths === []) {
            $tree = TemuCategoryTree::path(Temu1DataService::SHOP, (string) $id);
            if ($tree !== '') {
                $paths[$tree] = true;
            }
        }

        return array_keys($paths);
    }

    /**
     * Latest temu_metrics row per SKU, in the keys the page uses (ebay_* keys hold the Temu figures).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function metricsBySku(): array
    {
        $table = $this->metricsTable();
        if (! Schema::hasTable($table)) {
            return [];
        }
        $wanted = ['sku', 'goods_id', 'base_price', 'quantity', 'quantity_purchased_l30', 'quantity_purchased_l60',
            'product_impressions_l30', 'product_clicks_l30', 'product_clicks_l7', 'product_clicks_l1',
            'listing_status', 'inactive_reason', 'updated_at'];
        $columns = array_values(array_filter($wanted, fn ($c) => Schema::hasColumn($table, $c)));
        if (! in_array('sku', $columns, true)) {
            return [];
        }

        $out = [];
        DB::table($table)->whereNotNull('sku')->orderBy('id')->select($columns)->chunk(1000, function ($rows) use (&$out) {
            foreach ($rows as $row) {
                $r = (array) $row;
                $goodsId = trim((string) ($r['goods_id'] ?? ''));
                $out[$this->skuKey((string) $r['sku'])] = [
                    'sku' => $r['sku'],
                    'item_id' => $goodsId,
                    'ebay_link' => $goodsId !== '' ? 'https://www.temu.com/goods.html?_bg_fs=1&goods_id='.$goodsId : '',
                    'ebay_price' => $r['base_price'] ?? null,
                    'ebay_stock' => $r['quantity'] ?? null,
                    'ebay_l7' => null,
                    'ebay_l30' => $r['quantity_purchased_l30'] ?? null,
                    'ebay_l60' => $r['quantity_purchased_l60'] ?? null,
                    'views' => $r['product_impressions_l30'] ?? null,
                    'l7_views' => $r['product_clicks_l7'] ?? null,
                    'l1_views' => $r['product_clicks_l1'] ?? null,
                    'organic_clicks' => $r['product_clicks_l30'] ?? null,
                    'listing_status' => $r['listing_status'] ?? '',
                    'inactive_reason' => $r['inactive_reason'] ?? '',
                    'report_date' => ! empty($r['updated_at']) ? substr((string) $r['updated_at'], 0, 10) : '',
                    'ebay_title' => '',
                ];
            }
        });

        // The product name is the one read from Temu with the pull.
        foreach ($this->pulledSpecificsBySku() as $key => $pull) {
            $name = trim((string) ($pull['specifics']['__goods_name'] ?? ''));
            if ($name !== '') {
                $out[$key]['ebay_title'] = $name;
            }
        }

        return $out;
    }

    // ---- Pull: category and item specifics exactly as Temu holds them ----

    /**
     * POST /temu1-data/pull   { skus: [..] }
     */
    public function pullFromTemu(Request $request)
    {
        $data = $request->validate([
            'skus' => 'required|array|min:1|max:8',
            'skus.*' => 'required|string|max:191',
        ]);
        if (! Schema::hasTable($this->t('item_specifics'))) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table temu1_item_specifics is missing).'], 500);
        }
        @set_time_limit(300);

        $api = app(TemuApiService::class);
        if (! $api->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Temu credentials are not configured.'], 500);
        }
        $service = new Temu1DataService($api);
        $pending = $this->pendingSpecificsBySku();

        $results = [];
        foreach ($data['skus'] as $sku) {
            $sku = trim((string) $sku);
            $goodsId = $this->liveItemId($sku);
            if ($goodsId === '') {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => 'Not listed on Temu 1 (no goods ID).'];

                continue;
            }
            try {
                $keep = [];
                foreach (array_keys($pending[$this->skuKey($sku)] ?? []) as $nameKey) {
                    $keep[(string) $nameKey] = true;
                }
                $done = $service->pull($this->t('item_specifics'), $sku, $goodsId, $keep);
                $results[] = [
                    'sku' => $sku,
                    'status' => $done['ok'] ? 'pulled' : 'failed',
                    'message' => $done['message'],
                    'category' => $done['category'],
                    'properties' => $done['properties'],
                ];
            } catch (\Throwable $e) {
                Log::warning('Temu1Data: pull failed', ['sku' => $sku, 'error' => $e->getMessage()]);
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $e->getMessage()];
            }
        }
        $this->pulledMemo = null;
        \Illuminate\Support\Facades\Cache::forget($this->cacheKey('column_options'));

        return response()->json(['success' => true, 'results' => $results]);
    }

    // ---- Push: title and price as the app already pushes them; item specifics only on request (test) ----

    /**
     * POST /temu1-data/push
     * Title: bg.local.goods.partial.update (goodsName). Base price: bg.local.goods.priceorder.change.sku.price.
     * Item specifics are sent only when the page asks for it ("specifics": true, set for ticked rows), never by Auto Push.
     */
    public function pushSpecifics(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:10',
            'items.*.sku' => 'required|string|max:191',
            'items.*.item_id' => 'nullable|string|max:64',
            'items.*.specifics' => 'nullable|array|max:300',
            'items.*.title' => 'nullable|string|max:500',
            'items.*.price' => 'nullable|numeric|min:0.01|max:100000',
            'specifics' => 'nullable|boolean',
            'auto' => 'nullable|boolean',
        ]);
        @set_time_limit(300);

        $sendSpecifics = $request->boolean('specifics') && ! $request->boolean('auto');
        $api = app(TemuApiService::class);
        $service = new Temu1DataService($api);
        $results = [];

        foreach ($validated['items'] as $entry) {
            $sku = trim((string) $entry['sku']);
            $goodsId = trim((string) ($entry['item_id'] ?? ''));
            if (! preg_match('/^\d{6,20}$/', $goodsId)) {
                $goodsId = $this->liveItemId($sku);
            }
            if ($goodsId === '') {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => 'Not listed on Temu 1.'];

                continue;
            }

            $edits = [];
            if ($sendSpecifics) {
                foreach ((array) ($entry['specifics'] ?? []) as $name => $value) {
                    $name = trim((string) $name);
                    $value = is_scalar($value) ? trim((string) $value) : '';
                    if ($name !== '' && $value !== '' && ! str_starts_with($name, '__') && strcasecmp($name, 'UPC') !== 0) {
                        $edits[$name] = $value;
                    }
                }
            }
            $title = trim((string) ($entry['title'] ?? ''));
            $price = is_numeric($entry['price'] ?? null) ? round((float) $entry['price'], 2) : 0.0;
            if ($edits === [] && $title === '' && $price <= 0) {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => $sendSpecifics
                    ? 'No values to send.'
                    : 'No title or price to send. Item specifics are sent only for ticked rows (test).'];

                continue;
            }

            $messages = [];
            $failed = false;

            if ($title !== '') {
                try {
                    $r = $api->updateTitle($sku, $title);
                } catch (\Throwable $e) {
                    $r = ['success' => false, 'message' => $e->getMessage()];
                }
                if ($r['success'] ?? false) {
                    $this->clearPendingTitle($sku);
                    $this->setStoredReserved($sku, '__goods_name', $title);
                    $messages[] = 'Product name updated.';
                } else {
                    $failed = true;
                    $messages[] = 'Product name: '.(string) ($r['message'] ?? 'Temu rejected it.');
                }
            }

            if ($price > 0) {
                try {
                    $r = $api->updateSkuBasePrice($sku, $price, $goodsId);
                } catch (\Throwable $e) {
                    $r = ['success' => false, 'message' => $e->getMessage()];
                }
                if ($r['success'] ?? false) {
                    $this->clearPendingPrice($sku);
                    if (Schema::hasColumn($this->metricsTable(), 'base_price')) {
                        DB::table($this->metricsTable())->where('sku', $sku)->update(['base_price' => $price]);
                    }
                    $messages[] = 'Base price set to '.number_format($price, 2).'.';
                } else {
                    $failed = true;
                    $messages[] = 'Base price: '.(string) ($r['message'] ?? 'Temu rejected it.');
                }
            }

            if ($edits !== []) {
                try {
                    $r = $service->pushProperties($goodsId, $edits);
                } catch (\Throwable $e) {
                    Log::warning('Temu1Data: item specifics push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
                    $r = ['success' => false, 'message' => $e->getMessage(), 'applied' => []];
                }
                if ($r['success']) {
                    $this->clearPendingSpecifics($sku, $r['applied']);
                    $this->storeAppliedSpecifics($sku, $edits, $r['applied']);
                    $messages[] = $r['message'];
                } else {
                    $failed = true;
                    $messages[] = 'Item specifics: '.$r['message'];
                }
            }

            $results[] = ['sku' => $sku, 'status' => $failed ? 'failed' : 'pushed', 'message' => trim(implode(' ', $messages))];
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * @param  array<string, string>  $edits
     * @param  list<string>  $applied
     */
    private function storeAppliedSpecifics(string $sku, array $edits, array $applied): void
    {
        $decoded = $this->storedSpecifics($sku);
        foreach ($applied as $name) {
            $target = $name;
            foreach (array_keys($decoded) as $existing) {
                if ($this->aspectKey((string) $existing) === $this->aspectKey($name)) {
                    $target = (string) $existing;
                    break;
                }
            }
            $decoded[$target] = $edits[$name] ?? '';
        }
        $this->writeSpecifics($sku, $decoded);
    }

    private function setStoredReserved(string $sku, string $field, string $value): void
    {
        $decoded = $this->storedSpecifics($sku);
        $decoded[$field] = $value;
        $this->writeSpecifics($sku, $decoded);
    }

    /** @return array<string, mixed> */
    private function storedSpecifics(string $sku): array
    {
        $row = DB::table($this->t('item_specifics'))->where('sku', $this->skuKey($sku))->first();
        $decoded = $row ? json_decode((string) $row->specifics, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $decoded */
    private function writeSpecifics(string $sku, array $decoded): void
    {
        $payload = ['specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
        $key = $this->skuKey($sku);
        if (DB::table($this->t('item_specifics'))->where('sku', $key)->exists()) {
            DB::table($this->t('item_specifics'))->where('sku', $key)->update($payload);
        } else {
            DB::table($this->t('item_specifics'))->insert($payload + ['sku' => $key, 'created_at' => now()]);
        }
    }

    // ---- Delete column: app only (Temu holds the list of attributes of a category) ----

    /**
     * POST /temu1-data/delete-specific
     * Removes the column from the open category tab and from the stored copy of each SKU. Nothing is deleted on Temu:
     * the attributes of a category belong to Temu. A later pull brings the category's own attributes back.
     */
    public function deleteSpecific(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:191',
            'names' => 'nullable|array|max:500',
            'names.*' => 'required|string|max:191',
            'category' => 'nullable|string|max:500',
            'items' => 'nullable|array|max:20',
            'items.*.sku' => 'required|string|max:191',
            'items.*.item_id' => 'nullable|string|max:64',
        ]);
        if (! Schema::hasTable($this->t('item_specifics'))) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table temu1_item_specifics is missing).'], 500);
        }

        $names = [];
        foreach (array_merge((array) ($validated['names'] ?? []), isset($validated['name']) ? [$validated['name']] : []) as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && $this->aspectKey($candidate) !== '') {
                $names[$this->aspectKey($candidate)] = $candidate;
            }
        }
        if ($names === []) {
            return response()->json(['success' => false, 'message' => 'Choose a column.'], 422);
        }
        $wantedKeys = array_keys($names);

        if (array_key_exists('category', $validated) && Schema::hasTable($this->t('extra_columns'))) {
            $hash = $this->categoryHash((string) $validated['category']);
            DB::table($this->t('extra_columns'))->where('category_hash', $hash)->whereIn('name_key', $wantedKeys)->delete();
            if (Schema::hasTable($this->t('column_sources'))) {
                DB::table($this->t('column_sources'))->where('category_hash', $hash)->whereIn('column_key', $wantedKeys)->delete();
            }
            \Illuminate\Support\Facades\Cache::forget($this->cacheKey('column_options'));
        }

        $results = [];
        foreach ((array) ($validated['items'] ?? []) as $entry) {
            $sku = trim((string) $entry['sku']);
            $key = $this->skuKey($sku);
            if (Schema::hasTable($this->t('pending_specifics'))) {
                DB::table($this->t('pending_specifics'))->where('sku', $key)->whereIn('name_key', $wantedKeys)->delete();
            }
            $decoded = $this->storedSpecifics($sku);
            $changed = false;
            foreach (array_keys($decoded) as $storedName) {
                if (in_array($this->aspectKey((string) $storedName), $wantedKeys, true)) {
                    unset($decoded[$storedName]);
                    $changed = true;
                }
            }
            if ($changed) {
                $this->writeSpecifics($sku, $decoded);
            }
            $results[] = ['sku' => $sku, 'status' => 'app_only', 'message' => 'Removed in the app. Temu is not changed.'];
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    // ---- Change category: Temu's own category tree; the category is changed in the app ----

    /**
     * GET /temu1-data/ebay-categories?q=&refresh=1   (the route name is shared with the eBay pages)
     */
    public function ebayCategories(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');
        $shop = Temu1DataService::SHOP;
        $state = TemuCategoryTree::read($shop);
        $crawlNote = '';
        if (empty($state['done']) && ($state['nodes'] === [] || $request->boolean('refresh'))) {
            try {
                $api = app(TemuApiService::class);
                if ($api->isConfigured()) {
                    $r = (new TemuCategoryTree($api, $shop))->crawl(70);
                    $crawlNote = $r['done'] ? '' : 'The Temu category list is still downloading ('.$r['nodes'].' so far). Press refresh to continue, or run php artisan temu:sync-categories.';
                    TemuCategoryTree::forget($shop);
                    $state = TemuCategoryTree::read($shop);
                } else {
                    $crawlNote = 'Temu credentials are not configured.';
                }
            } catch (\Throwable $e) {
                $crawlNote = $e->getMessage();
            }
        }
        $nodes = $state['nodes'];
        if ($nodes === []) {
            return response()->json([
                'success' => false,
                'message' => $crawlNote !== '' ? $crawlNote : 'The Temu categories could not be loaded. Run php artisan temu:sync-categories.',
                'results' => [],
                'total' => 0,
            ], 502);
        }

        $tokens = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim((string) $request->query('q', '')))) ?: []));
        $results = [];
        $total = 0;
        foreach ($nodes as $id => $node) {
            if (empty($node[2])) {
                continue; // only leaf categories can hold a product
            }
            $path = TemuCategoryTree::path($shop, (string) $id);
            if ($path === '') {
                continue;
            }
            $hay = mb_strtolower($path);
            $ok = true;
            foreach ($tokens as $token) {
                if (! str_contains($hay, $token)) {
                    $ok = false;
                    break;
                }
            }
            if (! $ok) {
                continue;
            }
            $total++;
            if (count($results) < 200) {
                $results[] = ['id' => (string) $id, 'path' => $path];
            }
        }
        usort($results, static fn ($a, $b) => strcasecmp($a['path'], $b['path']));

        return response()->json([
            'success' => true,
            'results' => $results,
            'total' => $total,
            'fetched_at' => (string) ($state['updated_at'] ?? ''),
            'note' => $crawlNote,
        ]);
    }

    /**
     * POST /temu1-data/change-category
     * App only: Category Master (Temu column) and the stored Temu copy of each SKU. The live Temu product keeps its
     * category, and the next pull from Temu restores the category Temu has.
     */
    public function changeCategory(Request $request)
    {
        $data = $request->validate([
            'skus' => 'required|array|min:1|max:5000',
            'skus.*' => 'required|string|max:191',
            'category_id' => 'required|string|max:64',
            'category_path' => 'nullable|string|max:500',
        ]);
        $categoryId = trim((string) $data['category_id']);
        $path = TemuCategoryTree::path(Temu1DataService::SHOP, $categoryId);
        if ($path === '') {
            $path = trim((string) ($data['category_path'] ?? ''));
        }
        if ($path === '') {
            return response()->json(['success' => false, 'message' => 'That category is not in the Temu category list.'], 422);
        }
        if (mb_strlen($path) > 255) {
            return response()->json(['success' => false, 'message' => 'The category path is longer than 255 characters.'], 422);
        }

        $skus = array_values(array_unique(array_filter(array_map('trim', $data['skus']))));
        $now = now();
        $userId = $request->user()?->id;
        $masters = 0;
        $pulls = 0;
        $results = [];
        foreach ($skus as $sku) {
            $results[] = ['sku' => $sku, 'ebay' => 'not_live', 'message' => 'Changed in the app only. The live Temu product keeps its category.'];
        }

        DB::transaction(function () use ($skus, $categoryId, $path, $now, $userId, &$masters, &$pulls) {
            if (Schema::hasTable('category_masters') && Schema::hasColumn('category_masters', 'category_temu')) {
                $hasId = Schema::hasColumn('category_masters', 'category_temu_id');
                $parents = [];
                if (Schema::hasTable('product_master')) {
                    foreach (array_chunk($skus, 500) as $chunk) {
                        foreach (DB::table('product_master')->whereIn('sku', $chunk)->get(['sku', 'parent']) as $pm) {
                            $parents[$this->skuKey((string) $pm->sku)] = trim((string) ($pm->parent ?? ''));
                        }
                    }
                }
                $existing = [];
                foreach (array_chunk($skus, 500) as $chunk) {
                    foreach (DB::table('category_masters')->whereIn('sku', $chunk)->get() as $row) {
                        $existing[$this->skuKey((string) $row->sku)] = $row;
                    }
                }
                foreach ($skus as $sku) {
                    $key = $this->skuKey($sku);
                    $payload = ['category_temu' => $path, 'updated_at' => $now];
                    if ($hasId) {
                        $payload['category_temu_id'] = $categoryId;
                    }
                    if (Schema::hasColumn('category_masters', 'updated_by')) {
                        $payload['updated_by'] = $userId;
                    }
                    if (isset($existing[$key])) {
                        DB::table('category_masters')->where('id', $existing[$key]->id)->update($payload);
                    } else {
                        $parent = $parents[$key] ?? '';
                        DB::table('category_masters')->insert($payload + [
                            'sku' => $sku,
                            'parent' => $parent !== '' ? $parent : null,
                            'created_at' => $now,
                        ]);
                    }
                    $masters++;
                }
            }
            if (Schema::hasTable($this->t('item_specifics'))) {
                $hasId = Schema::hasColumn($this->t('item_specifics'), 'category_id');
                foreach (array_chunk($skus, 500) as $chunk) {
                    $payload = ['category_path' => $path, 'updated_at' => $now];
                    if ($hasId) {
                        $payload['category_id'] = $categoryId;
                    }
                    $pulls += DB::table($this->t('item_specifics'))->whereIn('sku', array_map(fn ($s) => $this->skuKey($s), $chunk))->update($payload);
                }
            }
        });

        return response()->json([
            'success' => true,
            'skus' => count($skus),
            'failed' => 0,
            'results' => $results,
            'category_master_rows' => $masters,
            'pull_rows' => $pulls,
            'category_id' => $categoryId,
            'category_path' => $path,
        ]);
    }
}
