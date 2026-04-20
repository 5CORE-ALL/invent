<?php

namespace App\Http\Controllers;

use App\Jobs\PushImageJob;
use App\Models\ImageMarketplaceMap;
use App\Models\Marketplace;
use App\Models\MarketplacePercentage;
use App\Models\Product;
use App\Models\ShopifySku;
use App\Models\SkuImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SkuImageController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->select(['id', 'sku', 'parent'])
            ->withCount('skuImages as images_count')
            ->get();

        $parentInventory = $this->buildParentKeyInventoryMap();

        foreach ($products as $p) {
            $skuU = strtoupper((string) ($p->sku ?? ''));
            $p->is_parent_sku = str_starts_with(trim($skuU), 'PARENT');
            if ($p->is_parent_sku) {
                $k = $this->familyKeyForParentProduct($p);
                $p->parent_child_inventory = (int) round($k !== null && $k !== '' ? ($parentInventory[$k] ?? 0) : 0);
            } else {
                $p->parent_child_inventory = null;
            }
        }

        $products = $this->orderProductsChildGroupsThenParent($products);

        $this->attachLineInventory($products);

        $pushMarketplaceOptions = $this->marketplacePushSelectOptions();

        return view('sku_images.index', [
            'title' => 'SKU Image Manager',
            'products' => $products,
            'pushMarketplaceOptions' => $pushMarketplaceOptions,
        ]);
    }

    public function pushStatus(Request $request): View
    {
        $marketplaceId = $request->integer('marketplace_id') ?: null;
        $statusFilter = $request->get('status');
        $qSku = $request->get('sku');

        $validStatuses = [
            ImageMarketplaceMap::STATUS_PENDING,
            ImageMarketplaceMap::STATUS_SENT,
            ImageMarketplaceMap::STATUS_FAILED,
        ];
        if ($statusFilter !== null && $statusFilter !== '' && ! in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = null;
        } elseif ($statusFilter === '') {
            $statusFilter = null;
        }

        $summaryRows = ImageMarketplaceMap::query()
            ->whereHas('marketplace', static function ($mq): void {
                $mq->whereRaw('LOWER(TRIM(code)) = ?', ['reverb']);
            })
            ->select('marketplace_id', 'status', DB::raw('count(*) as c'))
            ->groupBy('marketplace_id', 'status')
            ->get();

        $marketplaces = Marketplace::query()
            ->whereRaw('LOWER(TRIM(code)) = ?', ['reverb'])
            ->orderBy('name')
            ->get();
        $summary = [];
        foreach ($marketplaces as $mp) {
            $summary[$mp->id] = [
                'marketplace' => $mp,
                'pending' => 0,
                'sent' => 0,
                'failed' => 0,
                'total' => 0,
            ];
        }
        foreach ($summaryRows as $row) {
            $mid = (int) $row->marketplace_id;
            if (! isset($summary[$mid])) {
                $summary[$mid] = [
                    'marketplace' => $marketplaces->firstWhere('id', $mid),
                    'pending' => 0,
                    'sent' => 0,
                    'failed' => 0,
                    'total' => 0,
                ];
            }
            $c = (int) $row->c;
            $st = (string) $row->status;
            $summary[$mid][$st] = $c;
            $summary[$mid]['total'] += $c;
        }
        uasort(
            $summary,
            static function (array $a, array $b) {
                $la = MarketplacePercentage::displayNameForMarketplace($a['marketplace'])
                    ?? $a['marketplace']?->name
                    ?? '';
                $lb = MarketplacePercentage::displayNameForMarketplace($b['marketplace'])
                    ?? $b['marketplace']?->name
                    ?? '';

                return strcasecmp((string) $la, (string) $lb);
            }
        );

        $maps = ImageMarketplaceMap::query()
            ->with(['marketplace', 'skuImage.product'])
            ->whereHas('marketplace', static function ($mq): void {
                $mq->whereRaw('LOWER(TRIM(code)) = ?', ['reverb']);
            })
            ->when($marketplaceId, static fn ($q) => $q->where('marketplace_id', $marketplaceId))
            ->when($statusFilter, static fn ($q) => $q->where('status', $statusFilter))
            ->when($qSku, function ($q) use ($qSku) {
                $s = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($qSku)).'%';
                $q->whereHas('skuImage.product', static function ($p) use ($s) {
                    $p->where('sku', 'like', $s);
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(50)
            ->withQueryString();

        return view('sku_images.push_status', [
            'title' => 'SKU image push status (Reverb)',
            'maps' => $maps,
            'summary' => $summary,
            'filterMarketplaceId' => $marketplaceId,
            'filterStatus' => $statusFilter,
            'filterSku' => $qSku ? (string) $qSku : '',
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:product_master,id'],
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $safePath = $this->sanitizePathSegment($product->sku);
        $base = 'skus/'.$safePath;

        $out = [];
        $disk = Storage::disk('public');

        foreach ($request->file('images', []) as $file) {
            if (! $file) {
                continue;
            }

            $originalName = (string) $file->getClientOriginalName();
            $nameKey = strtolower(trim($originalName));

            $existing = SkuImage::query()
                ->where('product_id', $product->id)
                ->whereRaw('LOWER(TRIM(file_name)) = ?', [$nameKey])
                ->first();

            if ($existing) {
                $oldPath = $existing->file_path;
                $path = $file->store($base, 'public');

                $existing->update([
                    'file_name' => $originalName,
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ]);

                if ($oldPath !== '' && $oldPath !== $path && $disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }

                $existing->imageMarketplaceMaps()->update([
                    'status' => ImageMarketplaceMap::STATUS_PENDING,
                    'response' => null,
                    'sent_at' => null,
                ]);

                $existing->refresh();
                $existing->load('imageMarketplaceMaps');
                $existing->setRelation('product', $product);
                $out[] = $this->imageToArray($existing);
            } else {
                $path = $file->store($base, 'public');
                $image = SkuImage::query()->create([
                    'product_id' => $product->id,
                    'file_name' => $originalName,
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ]);
                $image->setRelation('product', $product);
                $out[] = $this->imageToArray($image);
            }
        }

        return response()->json(['ok' => true, 'images' => $out]);
    }

    public function getImages(Product $product): JsonResponse
    {
        $images = SkuImage::query()
            ->where('product_id', $product->id)
            ->with('imageMarketplaceMaps')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'ok' => true,
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'label' => $product->title150 ?? $product->sku,
            ],
            'images' => $images->map(fn (SkuImage $i) => $this->imageToArray($i))->values(),
        ]);
    }

    public function pushImages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:product_master,id'],
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer', 'exists:sku_images,id'],
            'marketplace_ids' => ['required', 'array', 'min:1'],
            'marketplace_ids.*' => ['integer', 'exists:marketplaces,id'],
        ]);

        $imageIds = SkuImage::query()
            ->where('product_id', $data['product_id'])
            ->whereIn('id', $data['image_ids'])
            ->pluck('id')
            ->all();

        if (count($imageIds) !== count($data['image_ids'])) {
            return response()->json(['ok' => false, 'message' => 'Invalid image selection for this product.'], 422);
        }

        $reverbMarketplaceIds = Marketplace::query()
            ->where('status', true)
            ->whereRaw('LOWER(TRIM(code)) = ?', ['reverb'])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        if ($reverbMarketplaceIds === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Reverb is not configured as an active marketplace (code "reverb").',
            ], 422);
        }
        foreach ($data['marketplace_ids'] as $marketplaceId) {
            if (! in_array((int) $marketplaceId, $reverbMarketplaceIds, true)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Only Reverb image push is enabled.',
                ], 422);
            }
        }

        $dispatched = 0;
        DB::transaction(function () use ($imageIds, $data, &$dispatched) {
            foreach ($imageIds as $imageId) {
                foreach ($data['marketplace_ids'] as $marketplaceId) {
                    $map = ImageMarketplaceMap::query()->updateOrCreate(
                        [
                            'sku_image_id' => $imageId,
                            'marketplace_id' => $marketplaceId,
                        ],
                        [
                            'status' => ImageMarketplaceMap::STATUS_PENDING,
                            'response' => null,
                            'sent_at' => null,
                        ]
                    );
                    PushImageJob::dispatch($map->id);
                    $dispatched++;
                }
            }
        });

        return response()->json(['ok' => true, 'dispatched' => $dispatched]);
    }

    /**
     * Sum child SKU Shopify inventory (inv) by product_master.parent key (excludes "PARENT …" parent rows from the sum).
     * Matches aggregation used in ADVMasters / eBay parent–child flows.
     *
     * @return array<string, float|int>
     */
    private function buildParentKeyInventoryMap(): array
    {
        $all = Product::query()
            ->select(['sku', 'parent'])
            ->get();
        if ($all->isEmpty()) {
            return [];
        }
        $skus = $all->pluck('sku')->map(fn ($s) => (string) $s)->filter()->unique()->values()->all();
        $bySku = ShopifySku::query()
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(fn (ShopifySku $r) => strtoupper(trim((string) $r->sku)));
        $totals = [];
        foreach ($all as $row) {
            $s = (string) ($row->sku ?? '');
            if ($s === '' || str_starts_with(strtoupper(trim($s)), 'PARENT')) {
                continue;
            }
            $parent = trim((string) ($row->parent ?? ''));
            if ($parent === '') {
                continue;
            }
            $k = strtoupper($parent);
            $rec = $bySku[strtoupper(trim($s))] ?? null;
            $inv = (float) ($rec?->inv ?? 0);
            $totals[$k] = ($totals[$k] ?? 0) + $inv;
        }

        return $totals;
    }

    /**
     * Shared family key: child rows use product_master.parent (e.g. "04 CS").
     * Parent rows: use parent column if set, else strip a leading "PARENT " from sku (e.g. "PARENT 04 CS" → "04 CS").
     */
    private function familyKeyForParentProduct(object $p): ?string
    {
        $parent = trim((string) ($p->parent ?? ''));
        if ($parent !== '') {
            return strtoupper(trim($parent));
        }
        $s = trim((string) ($p->sku ?? ''));
        if ($s === '') {
            return null;
        }
        if (str_starts_with(strtoupper($s), 'PARENT ')) {
            $rest = trim(preg_replace('/^PARENT\s+/i', '', $s) ?? '');

            return $rest === '' ? null : strtoupper($rest);
        }

        return strtoupper($s);
    }

    private function familyKeyForChildProduct(object $p): ?string
    {
        $pField = trim((string) ($p->parent ?? ''));

        if ($pField === '') {
            return null;
        }

        return strtoupper($pField);
    }

    /**
     * For each family: list child SKUs (asc) then the PARENT row, like the product master table.
     * Unlinked rows (no family key) come last, sorted by sku.
     */
    private function orderProductsChildGroupsThenParent(Collection $rows): Collection
    {
        $parentByKey = [];
        $childrenByKey = [];
        $standalones = [];

        foreach ($rows as $p) {
            if ($p->is_parent_sku) {
                $k = $this->familyKeyForParentProduct($p);
                if ($k === null || $k === '') {
                    $standalones[] = $p;
                } elseif (isset($parentByKey[$k])) {
                    $standalones[] = $p;
                } else {
                    $parentByKey[$k] = $p;
                }
            } else {
                $k = $this->familyKeyForChildProduct($p);
                if ($k === null) {
                    $standalones[] = $p;
                } else {
                    $childrenByKey[$k] = $childrenByKey[$k] ?? [];
                    $childrenByKey[$k][] = $p;
                }
            }
        }

        foreach (array_keys($childrenByKey) as $k) {
            $list = $childrenByKey[$k];
            usort(
                $list,
                static fn (object $a, object $b) => strcasecmp(
                    (string) ($a->sku ?? ''),
                    (string) ($b->sku ?? '')
                )
            );
            $childrenByKey[$k] = $list;
        }

        $allKeys = array_unique(array_merge(
            array_keys($childrenByKey),
            array_keys($parentByKey)
        ));
        sort($allKeys, SORT_STRING);
        $orderedKeys = $allKeys;
        if ($orderedKeys === [] && $standalones === []) {
            return $rows;
        }
        if ($orderedKeys === [] && $standalones !== []) {
            $list = $standalones;
            usort(
                $list,
                static fn (object $a, object $b) => strcasecmp(
                    (string) ($a->sku ?? ''),
                    (string) ($b->sku ?? '')
                )
            );

            return collect($list);
        }

        $out = collect();
        foreach ($orderedKeys as $k) {
            foreach ($childrenByKey[$k] ?? [] as $c) {
                $out->push($c);
            }
            if (isset($parentByKey[$k])) {
                $out->push($parentByKey[$k]);
            }
        }
        if ($standalones !== []) {
            usort(
                $standalones,
                static fn (object $a, object $b) => strcasecmp(
                    (string) ($a->sku ?? ''),
                    (string) ($b->sku ?? '')
                )
            );
            foreach ($standalones as $s) {
                $out->push($s);
            }
        }

        return $out;
    }

    private function attachLineInventory(Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }
        $skus = $products->pluck('sku')->map(fn ($s) => (string) $s)->filter()->unique()->values()->all();
        $bySku = ShopifySku::query()
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(fn (ShopifySku $r) => strtoupper(trim((string) $r->sku)));
        foreach ($products as $p) {
            if ($p->is_parent_sku) {
                $p->inv = (int) ($p->parent_child_inventory ?? 0);
            } else {
                $k = strtoupper(trim((string) ($p->sku ?? '')));
                $rec = $bySku[$k] ?? null;
                $p->inv = (int) round($rec?->inv ?? 0);
            }
        }
    }

    private function imageToArray(SkuImage $image): array
    {
        return [
            'id' => $image->id,
            'file_name' => $image->file_name,
            'file_path' => $image->file_path,
            'url' => $image->url,
            'badges' => $image->pushStatusBadges(),
        ];
    }

    /**
     * Reverb only. Uses the same listing resolution as Title Master (see {@see \App\Services\ReverbApiService::getListingIdBySku}).
     * Title Master calls {@see \App\Services\ReverbApiService::updateTitle}; image push uses {@see \App\Services\ReverbApiService::appendImageUrlToListingBySku}.
     *
     * @return Collection<int, object{id: int, label: string}>
     */
    private function marketplacePushSelectOptions(): Collection
    {
        $mp = Marketplace::query()
            ->where('status', true)
            ->whereRaw('LOWER(TRIM(code)) = ?', ['reverb'])
            ->first();

        if (! $mp) {
            return collect();
        }

        $label = $mp->name;
        if (Schema::hasTable('marketplace_percentages')) {
            $fromPct = MarketplacePercentage::displayNameForMarketplace($mp);
            if ($fromPct !== null && $fromPct !== '') {
                $label = $fromPct;
            }
        }

        return collect([(object) ['id' => $mp->id, 'label' => (string) $label]]);
    }

    private function sanitizePathSegment(string $sku): string
    {
        $s = str_replace(['/', '\\', '..', "\0"], '-', $sku);
        if ($s === '' || $s === '.' || $s === '..') {
            return 'product-'.uniqid();
        }

        return $s;
    }
}
