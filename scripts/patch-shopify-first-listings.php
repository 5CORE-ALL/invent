<?php

$path = __DIR__.'/../app/Http/Controllers/MarketPlace/ReverbSyncController.php';
$src = file_get_contents($path);
$start = strpos($src, '    public function syncProducts(Request $request): View');
$end = strpos($src, '    public function showProduct(int $shopifySkuId): View');
if ($start === false || $end === false) {
    fwrite(STDERR, "markers not found\n");
    exit(1);
}

$new = <<<'PHP'
    public function syncProducts(Request $request): View
    {
        $searchSku = trim((string) $request->input('search_sku', ''));
        $searchName = trim((string) $request->input('search_name', ''));
        $linkTab = strtolower((string) $request->input('link', 'linked'));
        if (! in_array($linkTab, ['all', 'linked', 'unlinked', 'not_in_shopify'], true)) {
            $linkTab = 'linked';
        }
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $apiError = null;
        $forceLive = $request->boolean('refresh_live');
        $liveQueued = 0;
        $liveMode = in_array($linkTab, ['linked', 'not_in_shopify'], true);

        if (! Schema::hasTable('shopify_skus')) {
            $apiError = 'shopify_skus table missing. Run Shopify inventory sync first.';
            $products = new LengthAwarePaginator([], 0, $perPage, $page);

            return view('marketplace.reverb.products', [
                'products' => $products,
                'title' => 'Reverb — Listings',
                'searchSku' => $searchSku,
                'searchName' => $searchName,
                'linkTab' => $linkTab,
                'counts' => ['all' => 0, 'linked' => 0, 'unlinked' => 0, 'not_in_shopify' => 0],
                'apiError' => $apiError,
                'connected' => $this->apiConfig->isConfigured('reverb'),
                'liveMode' => false,
                'liveQueued' => 0,
            ]);
        }

        if ($forceLive) {
            // Warm full Reverb catalog in background — never block page on full pull.
            \App\Jobs\WarmReverbLiveListingsCache::dispatch();
        }

        $linkedSkus = $this->linkedReverbSkus();
        $shopifyNormKeys = $this->shopifyNormalizedSkuKeys();
        $counts = $this->shopifyListingCounts($linkedSkus, $shopifyNormKeys);
        $liveService = app(ReverbLiveListingsService::class);

        // Linked = Shopify-first pagination, then live hydrate current page only.
        if ($linkTab === 'linked') {
            return $this->syncProductsShopifyFirstLinked(
                $request,
                $searchSku,
                $searchName,
                $page,
                $perPage,
                $linkedSkus,
                $counts,
                $liveService,
                $apiError
            );
        }

        if ($linkTab === 'not_in_shopify') {
            return $this->syncProductsNotInShopifyLivePage(
                $request,
                $searchSku,
                $searchName,
                $page,
                $perPage,
                $shopifyNormKeys,
                $counts,
                $liveService,
                $apiError
            );
        }

        $query = ShopifySku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if ($searchSku !== '') {
            $query->where('sku', 'like', '%'.$searchSku.'%');
        }
        if ($searchName !== '') {
            $query->where(function ($q) use ($searchName) {
                $q->where('product_title', 'like', '%'.$searchName.'%')
                    ->orWhere('variant_title', 'like', '%'.$searchName.'%')
                    ->orWhere('sku', 'like', '%'.$searchName.'%');
            });
        }

        if ($linkTab === 'unlinked' && $linkedSkus !== []) {
            $query->whereNotIn('sku', $linkedSkus);
        }

        $paginator = $query->orderBy('sku')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        $pageRows = collect($paginator->items())->all();
        $skus = collect($pageRows)->pluck('sku')->filter()->values()->all();
        $aeMap = $this->reverbMetricMapForSkus($skus);
        $liveShopifyQty = MarketplaceListingStockResolver::liveShopifyQtyMapForRows($pageRows, true);
        $listingIds = [];
        foreach ($aeMap as $metric) {
            if ($metric && ! empty($metric->product_id)) {
                $listingIds[] = (string) $metric->product_id;
            }
        }
        $liveReverb = $liveService->liveDetailsByListingIds($listingIds);

        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($aeMap, $liveShopifyQty, $liveReverb) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnReverb($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $shopifyPrice = $row->b2c_price ?? $row->price ?? null;
            $pid = $linked ? (string) ($metric->product_id ?? '') : '';
            $live = ($pid !== '' && isset($liveReverb[$pid])) ? $liveReverb[$pid] : null;
            $aeQty = $linked ? ($live['inventory'] ?? null) : null;

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $linked ? ($pid !== '' ? $pid : null) : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'reverb_title' => $live['title'] ?? ($metric->product_name ?? null),
                'image_src' => $row->image_src ?? null,
                'price' => $live['price'] ?? ($linked ? ($metric->price ?? null) : null),
                'shopify_price' => $shopifyPrice,
                'quantity' => $aeQty,
                'rv_quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $linked,
                'listing_status' => $linked ? 'linked' : 'unlinked',
                'reverb_state' => $live['state'] ?? null,
            ];
        });

        $paginator->setCollection($enriched);

        return view('marketplace.reverb.products', [
            'products' => $paginator,
            'title' => 'Reverb — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => $linkTab,
            'counts' => $counts,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('reverb'),
            'liveMode' => $liveMode,
            'liveQueued' => $liveQueued,
        ]);
    }

    /**
     * Shopify-first Linked tab: paginate Shopify SKUs that are linked, live-hydrate current page only.
     *
     * @param  array<int, string>  $linkedSkus
     * @param  array{all: int, linked: int, unlinked: int, not_in_shopify: int}  $counts
     */
    protected function syncProductsShopifyFirstLinked(
        Request $request,
        string $searchSku,
        string $searchName,
        int $page,
        int $perPage,
        array $linkedSkus,
        array $counts,
        ReverbLiveListingsService $liveService,
        ?string $apiError
    ): View {
        $query = ShopifySku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if ($linkedSkus === []) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('sku', $linkedSkus);
        }

        if ($searchSku !== '') {
            $query->where('sku', 'like', '%'.$searchSku.'%');
        }
        if ($searchName !== '') {
            $query->where(function ($q) use ($searchName) {
                $q->where('product_title', 'like', '%'.$searchName.'%')
                    ->orWhere('variant_title', 'like', '%'.$searchName.'%')
                    ->orWhere('sku', 'like', '%'.$searchName.'%');
            });
        }

        $paginator = $query->orderBy('sku')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        $pageRows = collect($paginator->items())->all();
        $skus = collect($pageRows)->pluck('sku')->filter()->values()->all();
        $aeMap = $this->reverbMetricMapForSkus($skus);

        // 1) Live Shopify qty for this page only
        $liveShopifyQty = MarketplaceListingStockResolver::liveShopifyQtyMapForRows($pageRows, true);

        // 2) Live Reverb qty/state for this page's listing IDs only (parallel)
        $listingIds = [];
        foreach ($skus as $sku) {
            $metric = $aeMap[$sku] ?? null;
            if ($metric && MarketplaceLiveInventoryRules::isLinked((string) $metric->product_id, (string) $metric->sku)) {
                $listingIds[] = (string) $metric->product_id;
            }
        }
        $liveReverb = $liveService->liveDetailsByListingIds($listingIds);

        $mismatchRows = [];
        $shopifyByUpper = [];
        foreach ($liveShopifyQty as $upper => $qty) {
            $shopifyByUpper[(string) $upper] = (int) $qty;
        }

        $enriched = collect($pageRows)->map(function (ShopifySku $row) use ($aeMap, $liveShopifyQty, $liveReverb, &$mismatchRows) {
            $sku = (string) $row->sku;
            $metric = $aeMap[$sku] ?? null;
            $linked = $this->isShopifySkuLinkedOnReverb($metric, $sku);
            $shopifyQty = MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $row, $sku);
            $pid = $linked ? (string) ($metric->product_id ?? '') : '';
            $live = ($pid !== '' && isset($liveReverb[$pid])) ? $liveReverb[$pid] : null;

            $state = (string) ($live['state'] ?? '');
            $rvQty = null;
            if ($linked && $live !== null && ! MarketplaceLiveInventoryRules::reverbIsDraftLike($state)) {
                $rvQty = (int) ($live['inventory'] ?? 0);
                if ($shopifyQty !== null) {
                    $mismatchRows[] = [
                        'sku' => $sku,
                        'inventory' => $rvQty,
                        'state' => $state,
                        'product_id' => $pid,
                    ];
                }
            }

            return (object) [
                'shopify_sku_id' => $row->id,
                'product_id' => $pid !== '' ? $pid : null,
                'sku' => $sku,
                'title' => trim(($row->product_title ?? '').($row->variant_title ? ' — '.$row->variant_title : '')) ?: $sku,
                'reverb_title' => $live['title'] ?? ($metric->product_name ?? null),
                'image_src' => $row->image_src ?? null,
                'price' => $live['price'] ?? ($metric->price ?? null),
                'shopify_price' => $row->b2c_price ?? $row->price ?? null,
                'quantity' => $rvQty,
                'rv_quantity' => $rvQty,
                'ae_quantity' => $rvQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => true,
                'listing_status' => 'linked',
                'reverb_state' => $state !== '' ? $state : null,
            ];
        });

        $liveQueued = $liveService->queueSyncForMismatches($mismatchRows, $shopifyByUpper);
        $paginator->setCollection($enriched);

        return view('marketplace.reverb.products', [
            'products' => $paginator,
            'title' => 'Reverb — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => 'linked',
            'counts' => $counts,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('reverb'),
            'liveMode' => true,
            'liveQueued' => $liveQueued,
        ]);
    }

    /**
     * Not-in-Shopify: paginate Reverb metrics missing from Shopify; live Reverb qty for page only.
     *
     * @param  array<string, true>  $shopifyNormKeys
     * @param  array{all: int, linked: int, unlinked: int, not_in_shopify: int}  $counts
     */
    protected function syncProductsNotInShopifyLivePage(
        Request $request,
        string $searchSku,
        string $searchName,
        int $page,
        int $perPage,
        array $shopifyNormKeys,
        array $counts,
        ReverbLiveListingsService $liveService,
        ?string $apiError
    ): View {
        $paginator = $this->paginateAeNotInShopify($searchSku, $searchName, $shopifyNormKeys, $page, $perPage);
        $items = collect($paginator->items());
        $listingIds = $items->pluck('product_id')->filter()->map(fn ($v) => (string) $v)->unique()->values()->all();
        $liveReverb = $liveService->liveDetailsByListingIds($listingIds);

        $enriched = $items->map(function ($p) use ($liveReverb) {
            $pid = (string) ($p->product_id ?? '');
            $live = ($pid !== '' && isset($liveReverb[$pid])) ? $liveReverb[$pid] : null;
            $p->rv_quantity = $live['inventory'] ?? ($p->quantity ?? null);
            $p->quantity = $p->rv_quantity;
            $p->shopify_quantity = null;
            $p->reverb_state = $live['state'] ?? ($p->reverb_state ?? null);
            $p->reverb_title = $live['title'] ?? ($p->reverb_title ?? $p->title ?? null);
            if ($live && isset($live['price'])) {
                $p->price = $live['price'];
            }

            return $p;
        });

        $paginator->setCollection($enriched);

        return view('marketplace.reverb.products', [
            'products' => $paginator,
            'title' => 'Reverb — Listings',
            'searchSku' => $searchSku,
            'searchName' => $searchName,
            'linkTab' => 'not_in_shopify',
            'counts' => $counts,
            'apiError' => $apiError,
            'connected' => $this->apiConfig->isConfigured('reverb'),
            'liveMode' => true,
            'liveQueued' => 0,
        ]);
    }

PHP;

$out = substr($src, 0, $start).$new.substr($src, $end);
file_put_contents($path, $out);
echo "OK patched\n";
