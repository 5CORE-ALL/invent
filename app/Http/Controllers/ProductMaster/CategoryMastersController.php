<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\CategoryMaster;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\CategoryMasters\CategoryChannelSyncService;
use App\Services\CategoryMasters\ShopifyCategoryFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Category Masters": CP Master parents and SKUs with the eBay / Amazon / Temu category
 * each one is listed under. Editing a parent row cascades to its SKU rows.
 */
class CategoryMastersController extends Controller
{
    public function index(Request $request)
    {
        return view('category-masters', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    public function getData()
    {
        try {
            $products = ProductMaster::query()
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 0 ELSE 1 END")
                ->orderBy('sku', 'asc')
                ->get(['id', 'sku', 'parent', 'Values']);

            $shopify = ShopifySku::query()
                ->orderBy('id')
                ->get(['id', 'sku', 'inv', 'image_src'])
                ->keyBy(fn ($item) => str_replace("\u{00a0}", ' ', (string) $item->sku));

            $categories = Schema::hasTable('category_masters')
                ? CategoryMaster::query()->get()->keyBy(fn ($row) => $this->skuKey((string) $row->sku))
                : collect();

            $rows = [];
            $childInvByParent = [];
            $childShopifyByParent = [];
            foreach ($products as $product) {
                $sku = trim((string) $product->sku);
                if ($sku === '') {
                    continue;
                }

                $parent = trim((string) ($product->parent ?? ''));
                $isParent = $this->isParentSku($sku);
                $shopifyRow = $shopify[str_replace("\u{00a0}", ' ', (string) $product->sku)] ?? null;
                $inv = $shopifyRow && $shopifyRow->inv !== null ? (float) $shopifyRow->inv : 0.0;

                if (! $isParent) {
                    $childInvByParent[$parent] = ($childInvByParent[$parent] ?? 0) + $inv;
                }

                $category = $categories->get($this->skuKey($sku));

                $rows[] = [
                    'id' => $product->id,
                    'image' => $this->resolveImage($product, $shopifyRow?->image_src),
                    'parent' => $parent,
                    'sku' => $sku,
                    'is_parent' => $isParent,
                    'inv' => $inv,
                    'category_ebay' => (string) ($category->category_ebay ?? ''),
                    'category_ebay_id' => (string) ($category->category_ebay_id ?? ''),
                    'category_amz' => (string) ($category->category_amz ?? ''),
                    'category_amz_id' => (string) ($category->category_amz_id ?? ''),
                    'category_temu' => (string) ($category->category_temu ?? ''),
                    'category_temu_id' => (string) ($category->category_temu_id ?? ''),
                    'category_shopify' => (string) ($category->category_shopify ?? ''),
                    'category_shopify_id' => (string) ($category->category_shopify_id ?? ''),
                    'shopify_synced' => $category && $category->category_shopify_synced_at !== null,
                ];

                $shopifyName = trim((string) ($category->category_shopify ?? ''));
                if (! $isParent && $parent !== '' && $shopifyName !== '' && ! isset($childShopifyByParent[$parent])) {
                    $childShopifyByParent[$parent] = [
                        'name' => $shopifyName,
                        'id' => (string) ($category->category_shopify_id ?? ''),
                    ];
                }
            }

            // A parent row has no stock of its own: show the total of its SKUs.
            // It has no Shopify variant either, so it shows its first SKU's Shopify category.
            foreach ($rows as &$row) {
                if ($row['is_parent']) {
                    $row['inv'] = $childInvByParent[$row['parent']] ?? 0;
                    $fromChild = $childShopifyByParent[$row['parent']] ?? null;
                    $row['category_shopify'] = $fromChild['name'] ?? '';
                    $row['category_shopify_id'] = $fromChild['id'] ?? '';
                    $row['shopify_synced'] = true;
                }
            }
            unset($row);

            return response()->json([
                'status' => 200,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            Log::error('Category Masters data failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to load Category Masters data.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Bulk save. Each item carries only the category fields that changed.
     * A parent row also pushes its changed fields to every SKU of that parent,
     * unless that SKU carries its own value for the field in the same request.
     */
    public function saveBulk(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1|max:5000',
            'items.*.sku' => 'required|string|max:191',
            'items.*.category_ebay' => 'nullable|string|max:255',
            'items.*.category_amz' => 'nullable|string|max:255',
            'items.*.category_temu' => 'nullable|string|max:255',
            'items.*.category_ebay_id' => 'nullable|string|max:64',
            'items.*.category_amz_id' => 'nullable|string|max:64',
            'items.*.category_temu_id' => 'nullable|string|max:64',
        ]);

        if (! Schema::hasTable('category_masters')) {
            return response()->json(['message' => 'Category Masters table is missing. Run the migrations first.'], 500);
        }

        $requested = [];
        foreach ($request->input('items') as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            foreach (CategoryMaster::FIELDS as $field) {
                if (array_key_exists($field, $item)) {
                    $requested[$sku][$field] = $this->cleanCategory($item[$field]);
                    // A retyped name no longer matches the ID Pull saved, so the ID goes with it.
                    $requested[$sku][$field.'_id'] = $this->cleanCategory($item[$field.'_id'] ?? null);
                }
            }
            $requested[$sku] = $requested[$sku] ?? [];
        }

        if ($requested === []) {
            return response()->json(['message' => 'Nothing to save.'], 422);
        }

        try {
            $products = collect();
            foreach (array_chunk(array_keys($requested), 500) as $chunk) {
                $products = $products->merge(ProductMaster::query()->whereIn('sku', $chunk)->get(['sku', 'parent']));
            }
            $products = $products->keyBy(fn ($row) => $this->skuKey((string) $row->sku));

            /** @var array<string, array{sku: string, parent: string, fields: array<string, string|null>}> $changes */
            $changes = [];
            foreach ($requested as $sku => $fields) {
                $product = $products->get($this->skuKey($sku));
                if (! $product) {
                    continue;
                }
                $changes[$this->skuKey($sku)] = [
                    'sku' => (string) $product->sku,
                    'parent' => trim((string) ($product->parent ?? '')),
                    'fields' => $fields,
                ];
            }

            if ($changes === []) {
                return response()->json(['message' => 'None of the SKUs exist in CP Master.'], 422);
            }

            // Cascade parent rows to their SKUs. A SKU's own value in this request wins.
            $cascaded = [];
            foreach ($changes as $change) {
                if (! $this->isParentSku($change['sku']) || $change['parent'] === '' || $change['fields'] === []) {
                    continue;
                }
                $children = ProductMaster::query()
                    ->where('parent', $change['parent'])
                    ->where('sku', 'not like', 'PARENT %')
                    ->get(['sku', 'parent']);
                foreach ($children as $child) {
                    $key = $this->skuKey((string) $child->sku);
                    $own = $changes[$key]['fields'] ?? [];
                    // Name and ID travel as a pair: a SKU that carries its own name keeps its own ID too.
                    $patch = [];
                    foreach (CategoryMaster::FIELDS as $field) {
                        if (array_key_exists($field, $change['fields']) && ! array_key_exists($field, $own)) {
                            $patch[$field] = $change['fields'][$field];
                            $patch[$field.'_id'] = $change['fields'][$field.'_id'] ?? null;
                        }
                    }
                    if ($patch === []) {
                        continue;
                    }
                    $cascaded[$key] = [
                        'sku' => (string) $child->sku,
                        'parent' => $change['parent'],
                        'fields' => array_merge($cascaded[$key]['fields'] ?? [], $patch),
                    ];
                }
            }
            foreach ($cascaded as $key => $change) {
                if (isset($changes[$key])) {
                    $changes[$key]['fields'] = array_merge($change['fields'], $changes[$key]['fields']);
                } else {
                    $changes[$key] = $change;
                }
            }

            $updatedBy = $request->user()?->id;
            $now = now();

            $saved = DB::transaction(function () use ($changes, $updatedBy, $now) {
                $existing = collect();
                foreach (array_chunk(array_column($changes, 'sku'), 500) as $chunk) {
                    $existing = $existing->merge(CategoryMaster::query()->whereIn('sku', $chunk)->get());
                }
                $existing = $existing->keyBy(fn ($row) => $this->skuKey((string) $row->sku));

                $rows = [];
                foreach ($changes as $key => $change) {
                    $current = $existing->get($key);
                    $row = [
                        'sku' => $current->sku ?? $change['sku'],
                        'parent' => $change['parent'] !== '' ? $change['parent'] : null,
                        'category_ebay' => $current->category_ebay ?? null,
                        'category_ebay_id' => $current->category_ebay_id ?? null,
                        'category_amz' => $current->category_amz ?? null,
                        'category_amz_id' => $current->category_amz_id ?? null,
                        'category_temu' => $current->category_temu ?? null,
                        'category_temu_id' => $current->category_temu_id ?? null,
                        'updated_by' => $updatedBy,
                        'created_at' => $current->created_at ?? $now,
                        'updated_at' => $now,
                    ];
                    foreach ($change['fields'] as $field => $value) {
                        $row[$field] = $value;
                    }
                    $rows[] = $row;
                }

                foreach (array_chunk($rows, 400) as $chunk) {
                    CategoryMaster::query()->upsert(
                        $chunk,
                        ['sku'],
                        [
                            'parent',
                            'category_ebay', 'category_ebay_id',
                            'category_amz', 'category_amz_id',
                            'category_temu', 'category_temu_id',
                            'updated_by', 'updated_at',
                        ]
                    );
                }

                return count($rows);
            });
        } catch (\Throwable $e) {
            Log::error('Category Masters save failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Unable to save categories.'], 500);
        }

        return response()->json([
            'status' => 200,
            'updated' => $saved,
        ]);
    }

    /**
     * One-time autofill of the Shopify category. Each SKU is looked up once; a SKU that already
     * carries a lookup time is never asked for again. Parent rows have no variant, so they are skipped.
     */
    public function shopifyFill(Request $request, ShopifyCategoryFetcher $fetcher)
    {
        $data = $request->validate([
            'skus' => 'required|array|min:1|max:25',
            'skus.*' => 'required|string|max:191',
        ]);

        if (! Schema::hasTable('category_masters') || ! Schema::hasColumn('category_masters', 'category_shopify_synced_at')) {
            return response()->json(['message' => 'Run the migrations first: the Shopify category column is missing.'], 500);
        }

        @set_time_limit(120);
        $products = ProductMaster::query()->whereIn('sku', $data['skus'])->get(['sku', 'parent'])
            ->keyBy(fn ($row) => $this->skuKey((string) $row->sku));
        $done = CategoryMaster::query()->whereIn('sku', $data['skus'])
            ->whereNotNull('category_shopify_synced_at')
            ->get(['sku'])
            ->keyBy(fn ($row) => $this->skuKey((string) $row->sku));

        $todo = [];
        foreach (array_values(array_unique($data['skus'])) as $sku) {
            $product = $products->get($this->skuKey($sku));
            if (! $product || $this->isParentSku((string) $product->sku) || $done->has($this->skuKey($sku))) {
                continue;
            }
            $todo[] = (string) $product->sku;
        }

        if ($todo === []) {
            return response()->json(['status' => 200, 'results' => []]);
        }

        $fetched = $fetcher->fetch($todo);
        if (! $fetched['ok']) {
            return response()->json(['status' => 502, 'message' => $fetched['message'], 'results' => []], 502);
        }

        $now = now();
        $results = [];
        foreach ($todo as $sku) {
            $hit = $fetched['found'][$sku] ?? null;
            $product = $products->get($this->skuKey($sku));
            $parent = trim((string) ($product->parent ?? ''));
            try {
                CategoryMaster::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'parent' => $parent !== '' ? $parent : null,
                        'category_shopify' => ($hit['category'] ?? '') !== '' ? $hit['category'] : null,
                        'category_shopify_id' => ($hit['id'] ?? '') !== '' ? $hit['id'] : null,
                        'category_shopify_synced_at' => $now,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('Category Masters Shopify fill failed: '.$e->getMessage(), ['sku' => $sku]);

                continue;
            }
            $results[] = [
                'sku' => $sku,
                'found' => $hit !== null,
                'category' => $hit['category'] ?? '',
                'category_id' => $hit['id'] ?? '',
            ];
        }

        return response()->json(['status' => 200, 'results' => $results]);
    }

    /**
     * Pull the live category of each SKU from one site and save it.
     * Parent rows have no listing, so they are skipped.
     */
    public function pull(Request $request, CategoryChannelSyncService $sync)
    {
        $data = $request->validate([
            'channel' => 'required|in:'.implode(',', CategoryChannelSyncService::CHANNELS),
            'skus' => 'required|array|min:1|max:25',
            'skus.*' => 'required|string|max:191',
        ]);

        if (! Schema::hasTable('category_masters')) {
            return response()->json(['message' => 'Category Masters table is missing. Run the migrations first.'], 500);
        }

        @set_time_limit(300);
        $channel = $data['channel'];
        $nameField = CategoryMaster::CHANNEL_FIELDS[$channel];
        $products = ProductMaster::query()->whereIn('sku', $data['skus'])->get(['sku', 'parent'])
            ->keyBy(fn ($row) => $this->skuKey((string) $row->sku));

        $results = [];
        foreach (array_values(array_unique($data['skus'])) as $sku) {
            $product = $products->get($this->skuKey($sku));
            if (! $product) {
                $results[] = $this->skippedResult($sku, 'SKU not found in CP Master.');

                continue;
            }
            if ($this->isParentSku((string) $product->sku)) {
                $results[] = $this->skippedResult($sku, 'Parent row: it has no listing to pull from.');

                continue;
            }

            try {
                $pulled = $sync->pull($channel, (string) $product->sku);
                if ($pulled['success']) {
                    CategoryMaster::query()->updateOrCreate(
                        ['sku' => (string) $product->sku],
                        [
                            'parent' => trim((string) ($product->parent ?? '')) !== '' ? trim((string) $product->parent) : null,
                            $nameField => $pulled['path'],
                            $nameField.'_id' => $pulled['id'] !== '' ? $pulled['id'] : null,
                            'updated_by' => $request->user()?->id,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::error('Category Masters pull failed: '.$e->getMessage(), ['sku' => $sku, 'channel' => $channel]);
                $pulled = ['success' => false, 'message' => 'Unexpected error: '.$e->getMessage(), 'id' => '', 'path' => '', 'accounts' => []];
            }

            $results[] = [
                'sku' => (string) $product->sku,
                'status' => $pulled['success'] ? 'pulled' : 'error',
                'success' => $pulled['success'],
                'message' => $pulled['message'],
                'category' => $pulled['path'],
                'category_id' => $pulled['id'],
                'accounts' => $pulled['accounts'],
            ];
        }

        return response()->json(['status' => 200, 'channel' => $channel, 'results' => $results]);
    }

    /**
     * Push the saved category of each SKU to one site.
     * dry_run=true only reports what would change. A live push must carry the target_id the dry run showed.
     */
    public function push(Request $request, CategoryChannelSyncService $sync)
    {
        $data = $request->validate([
            'channel' => 'required|in:'.implode(',', CategoryChannelSyncService::CHANNELS),
            'dry_run' => 'required|boolean',
            'items' => 'required|array|min:1|max:25',
            'items.*.sku' => 'required|string|max:191',
            'items.*.target_id' => 'nullable|string|regex:/^\d+$/|max:64',
        ]);

        $dryRun = (bool) $data['dry_run'];
        $channel = $data['channel'];
        if (! $dryRun) {
            foreach ($data['items'] as $item) {
                if (trim((string) ($item['target_id'] ?? '')) === '') {
                    return response()->json(['message' => 'A live push must be confirmed from a dry run first.'], 422);
                }
            }
        }

        @set_time_limit(300);
        $nameField = CategoryMaster::CHANNEL_FIELDS[$channel];
        $skus = array_values(array_unique(array_map(static fn ($i) => trim((string) $i['sku']), $data['items'])));
        $products = ProductMaster::query()->whereIn('sku', $skus)->get(['sku', 'parent'])
            ->keyBy(fn ($row) => $this->skuKey((string) $row->sku));
        $saved = Schema::hasTable('category_masters')
            ? CategoryMaster::query()->whereIn('sku', $skus)->get()->keyBy(fn ($row) => $this->skuKey((string) $row->sku))
            : collect();

        $results = [];
        $seen = [];
        foreach ($data['items'] as $item) {
            $sku = trim((string) $item['sku']);
            $key = $this->skuKey($sku);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $product = $products->get($key);
            if (! $product) {
                $results[] = $this->skippedResult($sku, 'SKU not found in CP Master.');

                continue;
            }
            if ($this->isParentSku((string) $product->sku)) {
                $results[] = $this->skippedResult($sku, 'Parent row: it has no listing to push to.');

                continue;
            }

            $row = $saved->get($key);
            try {
                $plan = $sync->push(
                    $channel,
                    (string) $product->sku,
                    (string) ($row->{$nameField} ?? ''),
                    (string) ($row->{$nameField.'_id'} ?? ''),
                    trim((string) ($item['target_id'] ?? '')),
                    $dryRun
                );
            } catch (\Throwable $e) {
                Log::error('Category Masters push failed: '.$e->getMessage(), ['sku' => $sku, 'channel' => $channel]);
                $plan = [
                    'status' => 'error', 'success' => false, 'message' => 'Unexpected error: '.$e->getMessage(),
                    'target_id' => '', 'target_path' => '', 'accounts' => [],
                ];
            }

            if (! $dryRun) {
                Log::info('Category Masters live push', [
                    'user_id' => $request->user()?->id,
                    'channel' => $channel,
                    'sku' => $product->sku,
                    'target_id' => $plan['target_id'],
                    'status' => $plan['status'],
                    'message' => $plan['message'],
                ]);
            }

            $results[] = ['sku' => (string) $product->sku, 'saved_category' => (string) ($row->{$nameField} ?? '')] + $plan;
        }

        return response()->json(['status' => 200, 'channel' => $channel, 'dry_run' => $dryRun, 'results' => $results]);
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedResult(string $sku, string $message): array
    {
        return [
            'sku' => $sku,
            'status' => 'skipped',
            'success' => false,
            'message' => $message,
            'category' => '',
            'category_id' => '',
            'target_id' => '',
            'target_path' => '',
            'accounts' => [],
        ];
    }

    private function cleanCategory(mixed $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', (string) ($value ?? ''))) ?? '');

        return $value === '' ? null : $value;
    }

    private function isParentSku(string $sku): bool
    {
        return stripos(trim($sku), 'PARENT') === 0;
    }

    private function skuKey(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', trim($sku));

        return strtolower(preg_replace('/\s+/u', ' ', $sku) ?? $sku);
    }

    /**
     * Same priority as CP Master: manually uploaded image, then Shopify, then any other local image.
     */
    private function resolveImage(ProductMaster $product, ?string $shopifyImage): ?string
    {
        $values = $product->Values;
        if (is_string($values)) {
            $values = json_decode($values, true);
        }
        $local = is_array($values) ? trim((string) ($values['image_path'] ?? '')) : '';

        if ($local !== '' && str_contains($local, 'storage/')) {
            return '/'.ltrim($local, '/');
        }
        if ($shopifyImage) {
            return $shopifyImage;
        }
        if ($local !== '') {
            return '/'.ltrim($local, '/');
        }

        return null;
    }
}
