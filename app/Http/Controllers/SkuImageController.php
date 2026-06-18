<?php

namespace App\Http\Controllers;

use App\Models\ImageMarketplaceMap;
use App\Models\Marketplace;
use App\Models\MarketplacePercentage;
use App\Models\Product;
use App\Models\ShopifySku;
use App\Models\SkuImage;
use App\Services\AmazonSpApiService;
use App\Services\BestBuyApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\MacysApiService;
use App\Services\ReverbApiService;
use App\Services\ShopifyApiService;
use App\Services\ShopifyPLSApiService;
use App\Services\TemuApiService;
use App\Services\WayfairApiService;
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

        $codes = array_keys($this->marketplacePushDefinitions());

        $summaryRows = ImageMarketplaceMap::query()
            ->whereHas('marketplace', static function ($mq) use ($codes): void {
                $mq->whereIn(DB::raw('LOWER(TRIM(code))'), $codes);
            })
            ->select('marketplace_id', 'status', DB::raw('count(*) as c'))
            ->groupBy('marketplace_id', 'status')
            ->get();

        $marketplaces = Marketplace::query()
            ->whereIn(DB::raw('LOWER(TRIM(code))'), $codes)
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
            ->whereHas('marketplace', static function ($mq) use ($codes): void {
                $mq->whereIn(DB::raw('LOWER(TRIM(code))'), $codes);
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
            'title' => 'SKU image push status',
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
            'marketplace_codes' => ['required', 'array', 'min:1'],
            'marketplace_codes.*' => ['string'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $requestedImageIds = array_values(array_map('intval', $data['image_ids']));
        $images = SkuImage::query()
            ->where('product_id', $data['product_id'])
            ->whereIn('id', $requestedImageIds)
            ->get()
            ->sortBy(static fn (SkuImage $image) => array_search((int) $image->id, $requestedImageIds, true))
            ->values();

        if ($images->count() !== count($requestedImageIds)) {
            return response()->json(['ok' => false, 'message' => 'Invalid image selection for this product.'], 422);
        }

        $definitions = $this->marketplacePushDefinitions();
        $codes = collect($data['marketplace_codes'])
            ->map(static fn ($code) => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        foreach ($codes as $code) {
            if (! isset($definitions[$code]) || ! ($definitions[$code]['enabled'] ?? false)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Image push is not implemented for '.($definitions[$code]['label'] ?? $code).'.',
                ], 422);
            }
        }

        if ($codes->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Select at least one marketplace.'], 422);
        }

        $marketplacesByCode = $this->ensureMarketplaceRows($codes->all());
        foreach ($codes as $code) {
            $mp = $marketplacesByCode[$code] ?? null;
            if (! $mp || ! $mp->status) {
                return response()->json([
                    'ok' => false,
                    'message' => ($definitions[$code]['label'] ?? $code).' is not configured as an active marketplace.',
                ], 422);
            }
        }

        $mapIdsByCode = [];
        DB::transaction(function () use ($images, $codes, $marketplacesByCode, &$mapIdsByCode) {
            foreach ($images as $image) {
                foreach ($codes as $code) {
                    $marketplaceId = (int) $marketplacesByCode[$code]->id;
                    $map = ImageMarketplaceMap::query()->updateOrCreate(
                        [
                            'sku_image_id' => $image->id,
                            'marketplace_id' => $marketplaceId,
                        ],
                        [
                            'status' => ImageMarketplaceMap::STATUS_PENDING,
                            'response' => null,
                            'sent_at' => null,
                        ]
                    );
                    $mapIdsByCode[$code][] = (int) $map->id;
                }
            }
        });

        $results = [];
        $marketplaceResults = [];
        $imageUrls = $this->publicUrlsForSkuImages($images);
        foreach ($codes as $code) {
            $remote = $this->pushImagesToRemote($code, (string) $product->sku, $imageUrls);
            $success = (bool) ($remote['success'] ?? false);
            $status = $success ? ImageMarketplaceMap::STATUS_SENT : ImageMarketplaceMap::STATUS_FAILED;
            $payload = [
                'message' => (string) ($remote['message'] ?? ''),
                'data' => [
                    'sku' => $product->sku,
                    'marketplace' => $code,
                    'image_urls' => $remote['normalized_urls'] ?? $imageUrls,
                ],
            ];
            ImageMarketplaceMap::query()
                ->whereIn('id', $mapIdsByCode[$code] ?? [])
                ->get()
                ->each(static function (ImageMarketplaceMap $map) use ($status, $payload, $success): void {
                    $map->update([
                        'status' => $status,
                        'response' => $payload,
                        'sent_at' => $success ? now() : null,
                    ]);
                });

            $marketplaceResults[$code] = [
                'status' => $status,
                'success' => $success,
                'message' => $payload['message'],
            ];

            foreach ($mapIdsByCode[$code] ?? [] as $mapId) {
                $results[] = [
                    'map_id' => $mapId,
                    'marketplace' => $code,
                    'status' => $status,
                    'response' => $payload,
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'dispatched' => count($results),
            'results' => $results,
            'marketplace_results' => $marketplaceResults,
        ]);
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
     * Mirrors the image-capable marketplaces from Image Master. Wayfair/Best Buy are shown disabled
     * until those services expose image update methods.
     *
     * @return Collection<int, object{code: string, label: string, short: string, class: string, enabled: bool}>
     */
    private function marketplacePushSelectOptions(): Collection
    {
        $existing = Marketplace::query()
            ->whereIn(DB::raw('LOWER(TRIM(code))'), array_keys($this->marketplacePushDefinitions()))
            ->get()
            ->keyBy(static fn (Marketplace $marketplace) => strtolower(trim((string) $marketplace->code)));

        return collect($this->marketplacePushDefinitions())
            ->map(function (array $definition, string $code) use ($existing) {
                $mp = $existing[$code] ?? null;
                $label = $definition['label'];
                if ($mp && Schema::hasTable('marketplace_percentages')) {
                    $fromPct = MarketplacePercentage::displayNameForMarketplace($mp);
                    if ($fromPct !== null && $fromPct !== '') {
                        $label = $fromPct;
                    }
                }

                return (object) [
                    'code' => $code,
                    'label' => (string) $label,
                    'short' => (string) $definition['short'],
                    'class' => (string) $definition['class'],
                    'enabled' => (bool) ($definition['enabled'] ?? false),
                ];
            })
            ->values();
    }

    /**
     * @return array<string, array{label: string, short: string, class: string, enabled: bool}>
     */
    private function marketplacePushDefinitions(): array
    {
        return [
            'ebay' => ['label' => 'eBay 1', 'short' => 'E1', 'class' => 'btn-ebay1', 'enabled' => true],
            'ebay2' => ['label' => 'eBay 2', 'short' => 'E2', 'class' => 'btn-ebay2', 'enabled' => true],
            'ebay3' => ['label' => 'eBay 3', 'short' => 'E3', 'class' => 'btn-ebay3', 'enabled' => true],
            'macy' => ['label' => "Macy's", 'short' => 'M', 'class' => 'btn-macy', 'enabled' => true],
            'amazon' => ['label' => 'Amazon', 'short' => 'A', 'class' => 'btn-amazon', 'enabled' => true],
            'temu' => ['label' => 'Temu', 'short' => 'T', 'class' => 'btn-temu', 'enabled' => true],
            'reverb' => ['label' => 'Reverb', 'short' => 'R', 'class' => 'btn-reverb', 'enabled' => true],
            'wayfair' => ['label' => 'Wayfair', 'short' => 'W', 'class' => 'btn-wayfair', 'enabled' => true],
            'bestbuy' => ['label' => 'Best Buy', 'short' => 'B', 'class' => 'btn-bestbuy', 'enabled' => true],
            'shopify_main' => ['label' => 'Shopify Main', 'short' => 'SM', 'class' => 'btn-shopify', 'enabled' => true],
            'shopify_pls' => ['label' => 'Shopify PLS', 'short' => 'PLS', 'class' => 'btn-shopify-pls', 'enabled' => true],
        ];
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, Marketplace>
     */
    private function ensureMarketplaceRows(array $codes): array
    {
        $definitions = $this->marketplacePushDefinitions();
        $out = [];
        foreach ($codes as $code) {
            if (! isset($definitions[$code])) {
                continue;
            }
            $out[$code] = Marketplace::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $definitions[$code]['label'],
                    'status' => true,
                ]
            );
        }

        return $out;
    }

    /**
     * @param  Collection<int, SkuImage>  $images
     * @return list<string>
     */
    private function publicUrlsForSkuImages(Collection $images): array
    {
        return $images
            ->map(fn (SkuImage $image) => $this->publicUrlForStoragePath((string) $image->file_path))
            ->filter()
            ->values()
            ->all();
    }

    private function publicUrlForStoragePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $base = rtrim((string) (
            config('services.reverb.sku_image_public_base_url')
            ?: config('app.asset_url')
            ?: config('app.url')
        ), '/');
        if ($base !== '' && ! preg_match('#^https?://#i', $base)) {
            $base = 'https://'.$base;
        }

        $segments = array_values(array_filter(explode('/', $path), fn ($segment) => $segment !== ''));

        return $base.'/storage/'.implode('/', array_map('rawurlencode', $segments));
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    private function pushImagesToRemote(string $marketplace, string $sku, array $imageUrls): array
    {
        try {
            return match ($marketplace) {
                'ebay' => app(EbayApiService::class)->updateImages($sku, $imageUrls),
                'ebay2' => app(Ebay2ApiService::class)->updateImages($sku, $imageUrls),
                'ebay3' => app(EbayThreeApiService::class)->updateImages($sku, $imageUrls),
                'amazon' => app(AmazonSpApiService::class)->updateImages($sku, $imageUrls),
                'temu' => app(TemuApiService::class)->updateImages($sku, $imageUrls),
                'macy' => app(MacysApiService::class)->updateImages($sku, $imageUrls),
                'wayfair' => app(WayfairApiService::class)->updateImages($sku, $imageUrls),
                'bestbuy' => app(BestBuyApiService::class)->updateImages($sku, $imageUrls),
                'reverb' => app(ReverbApiService::class)->updateImages($sku, $imageUrls, 'add'),
                'shopify_main' => app(ShopifyApiService::class)->updateImages($sku, $imageUrls, 'add'),
                'shopify_pls' => app(ShopifyPLSApiService::class)->updateImages($sku, $imageUrls, 'add'),
                default => ['success' => false, 'message' => 'Image push is not implemented for '.$marketplace.'.'],
            };
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
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
