<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonDataView;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Temu2DataView;
use App\Models\Temu2Metric;
use App\Models\Temu2Pricing;
use App\Models\TemuMetric;
use App\Services\LmpSkuGroupService;
use App\Services\Support\ProductMasterMarketplaceMaps;
use App\Services\Temu2ApiService;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Missing L SKUs to Temu 2 via Open API as parent variation listings.
 */
class Temu2ListingPublishService
{
    private const BRAND_NAME = '5 Core Inc';

    private const MAX_IMAGES = 10;

    /** @var array<int, int> */
    private array $parentSpecIdByCat = [];

    /** @var list<string> */
    private array $attributeHintCorpus = [];

    /** @var array<string, float>|null */
    private ?array $priceByLookupKey = null;

    /** @var array<string, float>|null */
    private ?array $stdPriceByLookupKey = null;

    /** @var Temu2ApiService|\App\Services\TemuApiService */
    protected $api;

    public function __construct(Temu2ApiService $api)
    {
        $this->api = $api;
    }

    protected function shopConfigKey(): string
    {
        return 'temu2';
    }

    protected function shopLabel(): string
    {
        return 'Temu 2';
    }

    protected function credentialsHelp(): string
    {
        return 'Set TEMU2_APP_KEY, TEMU2_SECRET_KEY, and TEMU2_ACCESS_TOKEN.';
    }

    protected function stdPriceHelp(): string
    {
        return 'Temu 2 Analytics (/temu2-decrease)';
    }

    protected function pricingTable(): string
    {
        return 'temu2_pricing';
    }

    protected function metricsTable(): string
    {
        return 'temu2_metrics';
    }

    protected function pricingClass(): string
    {
        return Temu2Pricing::class;
    }

    protected function metricClass(): string
    {
        return Temu2Metric::class;
    }

    protected function dataViewClass(): string
    {
        return Temu2DataView::class;
    }

    protected function costTemplateCacheKey(): string
    {
        return 'temu2_cost_template_id_v1';
    }

    /**
     * @return list<string>
     */
    protected function listingCountCacheKeys(): array
    {
        return ['listing_channel_counts_v1:temu2', 'listing_channel_counts_v1:temutwo'];
    }

    protected function shopConfig(string $key, mixed $default = null): mixed
    {
        return config('services.'.$this->shopConfigKey().'.'.$key, $default);
    }

    protected function shopCostTemplateEnv(): string
    {
        return strtoupper($this->shopConfigKey()).'_COST_TEMPLATE_ID';
    }

    /**
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publish(string $sku): array
    {
        return $this->publishSkus([$sku], true);
    }

    /**
     * Group seed SKUs by product-master parent and list siblings that can be published.
     *
     * @param  list<string>  $seedSkus
     * @param  array<string, string>  $skuParents  Optional listing-page parent keyed by SKU
     * @return array{success: bool, groups: list<array<string, mixed>>}
     */
    public function previewFromSkus(array $seedSkus, array $skuParents = [], string $mode = 'variation'): array
    {
        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        $seeds = $this->uniqueTrimmedSkus($seedSkus);
        $seedNorm = [];
        foreach ($seeds as $sku) {
            $norm = $this->skuNorm($sku);
            if ($norm !== '') {
                $seedNorm[$norm] = true;
            }
        }
        $groups = [];
        foreach ($this->expandSeedSkusToParentGroups($seeds, $skuParents) as $parent => $children) {
            if ($mode === 'single') {
                $children = $children->filter(function ($product) use ($seedNorm) {
                    return isset($seedNorm[$this->skuNorm((string) $product->sku)]);
                })->values();
            }
            if ($children->isEmpty()) {
                continue;
            }
            $groups[] = $this->formatPreviewGroup($parent, $children, $seeds);
        }

        return [
            'success' => true,
            'groups' => array_values($groups),
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publishSkus(array $skus, bool $expandSiblings = true, string $mode = 'variation', string $parentHint = ''): array
    {
        $skus = $this->uniqueTrimmedSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => $this->shopLabel().' API credentials missing. '.$this->credentialsHelp(),
            ];
        }

        $blocked = $this->temu2IpWhitelistError();
        if ($blocked !== null) {
            return ['success' => false, 'message' => $blocked];
        }

        if ($expandSiblings) {
            $preview = $this->previewFromSkus($skus);
            $groups = $preview['groups'] ?? [];
            if (count($groups) > 1) {
                return [
                    'success' => false,
                    'message' => 'Selected SKUs belong to more than one parent. Use Publish selected so each parent is listed as its own variation.',
                ];
            }
            $skus = $groups[0]['publish_skus'] ?? [];
        } else {
            $skus = $this->filterPublishableSkus($skus);
        }

        if ($skus === []) {
            return ['success' => false, 'message' => 'No child SKUs left to publish (NRL, missing masters, or live listings were skipped). Deleted Temu listings can be published again.'];
        }

        $mode = strtolower(trim($mode)) === 'single' ? 'single' : 'variation';
        $parentHint = trim($parentHint);
        if ($mode === 'single' && count($skus) > 1) {
            $ok = [];
            $fail = [];
            $listed = [];
            $lastGoods = null;
            foreach ($skus as $sku) {
                $one = $this->publishSkus([$sku], false, 'single', $parentHint);
                if ($one['success'] ?? false) {
                    $ok[] = $one['message'] ?? ('Published '.$sku);
                    foreach ($one['skus'] ?? [$sku] as $listedSku) {
                        $listed[] = $listedSku;
                    }
                    if (! empty($one['goods_id'])) {
                        $lastGoods = $one['goods_id'];
                    }
                } else {
                    $fail[] = $sku.': '.($one['message'] ?? 'Publish failed');
                }
            }

            return [
                'success' => $fail === [],
                'message' => trim(implode(' ', $ok).($fail !== [] ? ' '.implode(' ', $fail) : '')),
                'goods_id' => $lastGoods,
                'skus' => array_values(array_unique($listed)),
            ];
        }

        $products = $this->findProductsBySkus($skus)->keyBy(function ($row) {
            return (string) $row->sku;
        });

        $primarySku = $skus[0];
        $primary = $products->get($primarySku) ?? $this->productFromKeyed($products, $primarySku);
        if (! $primary) {
            return ['success' => false, 'message' => 'SKU not found in product master.'];
        }

        $parentKey = $parentHint !== '' ? $parentHint : $this->groupKeyForProduct($primary);
        $title = $this->resolveTitle($primary, $primarySku);
        if ($title === '') {
            return ['success' => false, 'message' => $primarySku.': Title missing in Title Master'];
        }
        $description = $this->resolveDescription($primary, $primarySku);
        if ($description === '') {
            return ['success' => false, 'message' => $primarySku.': Description missing in Description Master'];
        }

        $gallerySources = [];
        $prepared = [];
        foreach ($skus as $sku) {
            $product = $products->get($sku) ?? $this->productFromKeyed($products, $sku);
            if (! $product) {
                return ['success' => false, 'message' => 'SKU not found in product master: '.$sku];
            }
            $blocked = $this->classifyChildSku($sku);
            if (($blocked['status'] ?? '') !== 'will_publish') {
                return ['success' => false, 'message' => $sku.': '.($blocked['reason'] !== '' ? $blocked['reason'] : 'Masters are not ready')];
            }
            $price = $this->resolvePrice($sku);
            if ($price === null || $price <= 0) {
                return ['success' => false, 'message' => 'No Std Prc found for '.$sku.'. Set Std Prc on '.$this->stdPriceHelp().'.'];
            }
            $images = $this->resolveSourceImages($product, $sku);
            foreach ($images as $url) {
                $gallerySources[] = $url;
            }
            $prepared[] = [
                'sku' => $sku,
                'product' => $product,
                'price' => $price,
                'qty' => $this->resolveQty($sku),
                'dimensions' => $this->resolveDimensions($product, $sku),
            ];
        }

        $gallerySources = array_values(array_unique($gallerySources));
        if ($gallerySources === []) {
            return ['success' => false, 'message' => 'No Image Master photos found. Add images on Image Master before publishing.'];
        }
        $gallerySources = array_slice($gallerySources, 0, self::MAX_IMAGES);

        $upload = $this->uploadImages($gallerySources);
        if (($upload['error'] ?? null) !== null) {
            return ['success' => false, 'message' => $upload['error']];
        }
        $hostedImages = $upload['urls'] ?? [];
        if ($hostedImages === []) {
            return ['success' => false, 'message' => 'Failed to upload images to '.$this->shopLabel().'. Check API credentials and image URLs.'];
        }
        while (count($hostedImages) < 3) {
            $hostedImages[] = $hostedImages[0];
        }
        $hostedImages = array_slice($hostedImages, 0, self::MAX_IMAGES);

        $catId = $this->resolveCatId($primarySku, $title, $hostedImages[0]);
        if ($catId === null) {
            return ['success' => false, 'message' => 'Could not resolve a Temu leaf category. Set '.$this->pricingTable().'.category_id or check category recommend API access.'];
        }

        $costTemplateId = $this->resolveCostTemplateId();
        if ($costTemplateId === '') {
            return ['success' => false, 'message' => 'No '.$this->shopLabel().' shipping template found. Create one in Seller Center or set '.$this->shopCostTemplateEnv().'.'];
        }

        $currency = strtoupper(trim((string) $this->shopConfig('currency', 'USD'))) ?: 'USD';
        $skuList = [];
        foreach ($prepared as $row) {
            $spec = $this->uniqueSpecDetails($catId, $row['sku']);
            if ($spec === []) {
                return ['success' => false, 'message' => 'Could not resolve a Temu variation (spec) for '.$row['sku'].'. Check temu.local.product.variation.get permission.'];
            }
            $baseAmount = number_format($row['price'], 2, '.', '');
            $listAmount = number_format(round($row['price'] * 1.2, 2), 2, '.', '');
            if ((float) $listAmount <= (float) $baseAmount) {
                $listAmount = number_format($row['price'] + 1, 2, '.', '');
            }
            $dimensions = is_array($row['dimensions']) ? $row['dimensions'] : [];
            $skuList[] = [
                'externalSkuId' => $row['sku'],
                'outSkuSn' => $row['sku'],
                'quantity' => $row['qty'],
                'images' => $hostedImages,
                'specDetails' => $spec,
                'price' => [
                    'listPriceType' => 0,
                    'basePrice' => ['amount' => $baseAmount, 'currency' => $currency],
                    'listPrice' => ['amount' => $listAmount, 'currency' => $currency],
                ],
                'packageInfo' => [
                    'weight' => (string) ($dimensions['weight'] ?? '1'),
                    'length' => (string) ($dimensions['length'] ?? '1'),
                    'width' => (string) ($dimensions['width'] ?? '1'),
                    'height' => (string) ($dimensions['height'] ?? '1'),
                    'weightUnit' => (string) ($dimensions['weightUnit'] ?? 'g'),
                    'volumeUnit' => (string) ($dimensions['volumeUnit'] ?? 'cm'),
                ],
            ];
        }

        $goodsProperty = $this->buildGoodsProperty($catId, $costTemplateId, $primary, $primarySku);
        $shipmentLimitDay = max(1, (int) $this->shopConfig('shipment_limit_day', 2));
        $goodsOriginInfo = $this->buildGoodsOriginInfo();

        $stillPrepared = [];
        $stillSkuList = [];
        $restored = [];
        foreach ($prepared as $index => $row) {
            $remote = $this->remoteSkuDuplicate($row['sku']);
            if ($remote !== null) {
                $life = $this->remoteGoodsLifecycle($remote['goodsId']);
                if (in_array($life, ['deleted', 'draft', 'unknown'], true)) {
                    if (in_array($life, ['deleted', 'draft'], true) && $this->restoreDeletedGoods($remote['goodsId'])) {
                        $this->api->persistNewListing($row['sku'], $remote['goodsId'], $remote['skuId'] !== '' ? $remote['skuId'] : null);
                        $this->persistExtraListingFields($row['sku'], $row['price'], $row['qty']);
                        $this->markLocalListingStatus($row['sku'], 'active');
                        $restored[] = $row['sku'];
                        continue;
                    }
                    $this->freeDeletedOutSkuSn($row['sku'], $remote);
                    $stillPrepared[] = $row;
                    $stillSkuList[] = $skuList[$index];
                    continue;
                }
                $this->api->persistNewListing($row['sku'], $remote['goodsId'], $remote['skuId'] !== '' ? $remote['skuId'] : null);
                $this->persistExtraListingFields($row['sku'], $row['price'], $row['qty']);
                continue;
            }
            $stillPrepared[] = $row;
            $stillSkuList[] = $skuList[$index];
        }
        if ($stillPrepared === []) {
            $this->forgetListingCaches();

            return [
                'success' => true,
                'message' => $restored !== []
                    ? 'Restored '.count($restored).' deleted '.$this->shopLabel().' listing(s) and linked them.'
                    : 'SKU already exists on '.$this->shopLabel().' (active). Linked the existing listing.',
                'skus' => $skus,
            ];
        }
        $prepared = $stillPrepared;
        $skuList = $stillSkuList;

        $existingGoodsId = $mode === 'single' ? '' : $this->siblingGoodsId($parentKey);
        if ($existingGoodsId !== '') {
            $added = $this->addSkusToExistingGoods($existingGoodsId, $skuList);
            if ($added['success'] ?? false) {
                $firstSkuId = $this->persistPublishedRows($prepared, $existingGoodsId, $added['data'] ?? []);

                return [
                    'success' => true,
                    'message' => 'Added '.count($prepared).' variation(s) to the existing '.$this->shopLabel().' listing.',
                    'goods_id' => $existingGoodsId,
                    'sku_id' => $firstSkuId !== '' ? $firstSkuId : null,
                    'skus' => array_values(array_map(static fn ($row) => $row['sku'], $prepared)),
                ];
            }
            Log::warning('Temu2 publish: add SKU to existing goods failed, creating a new listing', [
                'parent' => $parentKey,
                'goods_id' => $existingGoodsId,
                'message' => $added['message'] ?? '',
            ]);
        }

        $outGoodsSn = $this->uniqueOutGoodsSn(
            count($prepared) === 1
                ? $primarySku
                : ($existingGoodsId !== '' ? $primarySku : ($parentKey !== '' ? $parentKey : $primarySku)),
            $primarySku
        );

        $payloadV2 = [
            'type' => (string) $this->shopConfig('goods_add_type', 'temu.local.goods.v2.add'),
            'language' => 'en',
            'goodsBasic' => [
                'catId' => $catId,
                'goodsName' => $title,
                'externalGoodsId' => $outGoodsSn,
                'outGoodsSn' => $outGoodsSn,
                'goodsDesc' => $description,
                'bulletPoints' => $this->resolveBullets($primary, $primarySku),
                'brand' => $this->resolveBrand(),
                'importDesignation' => $goodsOriginInfo['importDesignation'],
                'goodsGallery' => [
                    'goodsCarouselImage' => $hostedImages,
                    'detailImage' => $hostedImages,
                ],
            ],
            'goodsServicePromise' => [
                'shipmentLimitDay' => $shipmentLimitDay,
                'fulfillmentType' => 1,
                'costTemplateId' => $costTemplateId,
            ],
            'goodsOriginInfo' => $goodsOriginInfo,
            'skuList' => $skuList,
        ];

        if ($goodsProperty !== []) {
            $payloadV2['goodsProperty'] = $goodsProperty;
        }

        Log::info('Temu2 publish: sending add goods', [
            'parent' => $parentKey,
            'skus' => $skus,
            'catId' => $catId,
            'title' => $title,
            'images' => count($hostedImages),
            'type' => $payloadV2['type'],
        ]);

        $data = $this->temuCallBody($payloadV2, 120);
        if (! ($data['success'] ?? false)) {
            $fallback = $this->addViaV1($payloadV2);
            if ($fallback['success'] ?? false) {
                $data = $fallback['data'];
            } else {
                $msg = $this->apiErrorMessage($data);
                $retrySn = $this->uniqueOutGoodsSn($primarySku, $primarySku.'-'.substr(md5($primarySku), 0, 6));
                if ($this->isDuplicateSkuError($msg)) {
                    if ($retrySn !== '' && strcasecmp($retrySn, $outGoodsSn) !== 0) {
                        $payloadV2['goodsBasic']['outGoodsSn'] = $retrySn;
                        $payloadV2['goodsBasic']['externalGoodsId'] = $retrySn;
                    }
                    $payloadV2['skuList'] = $this->withUniqueOutSkuSns($payloadV2['skuList'] ?? []);
                    $retry = $this->temuCallBody($payloadV2, 120);
                    if ($retry['success'] ?? false) {
                        $data = $retry;
                    } else {
                        $retryFallback = $this->addViaV1($payloadV2);
                        if ($retryFallback['success'] ?? false) {
                            $data = $retryFallback['data'];
                        } else {
                            $msg = $this->apiErrorMessage($retry);
                        }
                    }
                }
                if (! ($data['success'] ?? false)) {
                    $fallbackMsg = $this->apiErrorMessage($fallback['data'] ?? []);
                    Log::warning('Temu2 publish add failed', [
                        'parent' => $parentKey,
                        'skus' => $skus,
                        'v2' => $msg,
                        'v1' => $fallbackMsg,
                    ]);

                    return [
                        'success' => false,
                        'message' => $msg !== '' ? $msg : $this->shopLabel().' add-goods API rejected the listing.',
                    ];
                }
            }
        }

        $goodsId = (string) ($data['result']['goodsId'] ?? '');
        if ($goodsId === '') {
            return ['success' => false, 'message' => $this->shopLabel().' add succeeded but returned no goodsId.'];
        }

        $firstSkuId = $this->persistPublishedRows($prepared, $goodsId, $data);

        Log::info('Temu2 publish: listed', [
            'parent' => $parentKey,
            'skus' => $skus,
            'goods_id' => $goodsId,
        ]);

        $count = count($skus);

        return [
            'success' => true,
            'message' => $count > 1
                ? 'Published '.$count.' variations of '.$parentKey.' to '.$this->shopLabel().'.'
                : 'Published '.$primarySku.' to '.$this->shopLabel().'.',
            'goods_id' => $goodsId,
            'sku_id' => $firstSkuId !== '' ? $firstSkuId : null,
            'skus' => $skus,
        ];
    }

    /**
     * @param  array<string, mixed>  $payloadV2
     * @return array{success: bool, data?: array<string, mixed>}
     */
    private function addViaV1(array $payloadV2): array
    {
        $v1 = $payloadV2;
        $v1['type'] = 'bg.local.goods.add';
        $v1['version'] = 'V1';
        if (! empty($payloadV2['goodsProperty']) && is_array($payloadV2['goodsProperty'])) {
            $first = $payloadV2['goodsProperty'][0] ?? null;
            if (is_array($first) && ! isset($payloadV2['goodsProperty']['goodsProperties'])) {
                $v1['goodsProperty'] = ['goodsProperties' => $payloadV2['goodsProperty']];
            }
        }

        $skuList = [];
        foreach ($payloadV2['skuList'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $specIds = [];
            foreach ($item['specDetails'] ?? [] as $row) {
                if (isset($row['specId']) && $row['specId'] !== '' && $row['specId'] !== null) {
                    $specIds[] = (int) $row['specId'];
                }
            }
            $pkg = is_array($item['packageInfo'] ?? null) ? $item['packageInfo'] : [];
            $skuList[] = [
                'externalSkuId' => $item['externalSkuId'] ?? '',
                'outSkuSn' => $item['outSkuSn'] ?? ($item['externalSkuId'] ?? ''),
                'quantity' => $item['quantity'] ?? 0,
                'specIdList' => $specIds,
                'images' => $item['images'] ?? [],
                'weight' => (string) ($pkg['weight'] ?? '1'),
                'length' => (string) ($pkg['length'] ?? '1'),
                'width' => (string) ($pkg['width'] ?? '1'),
                'height' => (string) ($pkg['height'] ?? '1'),
                'weightUnit' => (string) ($pkg['weightUnit'] ?? 'g'),
                'volumeUnit' => (string) ($pkg['volumeUnit'] ?? 'cm'),
                'price' => $item['price'] ?? [],
            ];
        }
        $v1['skuList'] = $skuList;

        $data = $this->temuCallBody($v1, 120);

        return [
            'success' => (bool) ($data['success'] ?? false),
            'data' => $data,
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function uniqueTrimmedSkus(array $skus): array
    {
        $out = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $key = $this->skuNorm($sku);
            if ($key === '' || isset($out[$key])) {
                continue;
            }
            $out[$key] = $sku;
        }

        return array_values($out);
    }

    /**
     * @param  list<string>  $seedSkus
     * @param  array<string, string>  $skuParents
     * @return array<string, \Illuminate\Support\Collection<int, ProductMaster>>
     */
    private function expandSeedSkusToParentGroups(array $seedSkus, array $skuParents = []): array
    {
        $seeds = $this->uniqueTrimmedSkus($seedSkus);
        if ($seeds === []) {
            return [];
        }

        $lookupSkus = $this->uniqueTrimmedSkus(array_merge($seeds, array_keys($skuParents)));
        $products = $this->findProductsBySkus($lookupSkus);
        $keyed = $products->keyBy(fn ($p) => (string) $p->sku);
        $parentKeys = [];
        $parentLabels = [];
        $seedsByParent = [];
        $orphanSkusByParent = [];

        foreach ($products as $product) {
            $sku = trim((string) $product->sku);
            $submitted = $this->submittedParentForSku($sku, $skuParents);
            $label = $submitted !== '' ? $submitted : $this->groupKeyForProduct($product);
            if ($label === '') {
                $label = $sku;
            }
            $parent = $this->skuNorm($label);
            $parentKeys[$parent] = true;
            $parentLabels[$parent] = $parentLabels[$parent] ?? $label;
            $seedsByParent[$parent][] = $product;
        }

        foreach ($seeds as $sku) {
            if ($this->productFromKeyed($keyed, $sku)) {
                continue;
            }
            $submitted = $this->submittedParentForSku($sku, $skuParents);
            $label = $submitted !== '' ? $submitted : $sku;
            $parent = $this->skuNorm($label);
            $parentKeys[$parent] = true;
            $parentLabels[$parent] = $parentLabels[$parent] ?? $label;
            $orphanSkusByParent[$parent][] = $sku;
        }

        $groups = [];
        foreach (array_keys($parentKeys) as $parent) {
            $label = $parentLabels[$parent] ?? $parent;
            $children = $this->childrenForParent($label);
            foreach ($seedsByParent[$parent] ?? [] as $seedProduct) {
                $this->pushChildIfMissing($children, $seedProduct);
            }
            foreach ($orphanSkusByParent[$parent] ?? [] as $orphanSku) {
                $found = $this->findProductLoose($orphanSku);
                if ($found) {
                    $this->pushChildIfMissing($children, $found);
                }
            }
            $children = $this->finalizeChildCollection($children);
            if ($children->isNotEmpty()) {
                $groups[$label] = $children;
            }
        }

        $this->ensureSeedsPresentInGroups($groups, $seeds, $skuParents);

        return $groups;
    }

    /**
     * @param  array<string, \Illuminate\Support\Collection<int, ProductMaster>>  $groups
     * @param  list<string>  $seeds
     * @param  array<string, string>  $skuParents
     */
    private function ensureSeedsPresentInGroups(array &$groups, array $seeds, array $skuParents): void
    {
        $included = [];
        foreach ($groups as $children) {
            foreach ($children as $row) {
                $included[$this->skuNorm((string) $row->sku)] = true;
            }
        }

        foreach ($seeds as $sku) {
            if ($this->isParentMasterSku($sku) || isset($included[$this->skuNorm($sku)])) {
                continue;
            }
            $found = $this->findProductLoose($sku);
            if (! $found || $this->isParentMasterSku((string) $found->sku)) {
                continue;
            }
            $label = $this->submittedParentForSku($sku, $skuParents);
            if ($label === '') {
                $label = $this->groupKeyForProduct($found);
            }
            if ($label === '') {
                $label = trim((string) $found->sku);
            }
            if (! isset($groups[$label])) {
                $groups[$label] = collect();
            }
            $this->pushChildIfMissing($groups[$label], $found);
            $groups[$label] = $this->finalizeChildCollection($groups[$label]);
            $included[$this->skuNorm((string) $found->sku)] = true;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductMaster>  $children
     */
    private function pushChildIfMissing($children, ProductMaster $product): void
    {
        $sku = trim((string) $product->sku);
        if ($sku === '' || $this->isParentMasterSku($sku)) {
            return;
        }
        $already = $children->contains(function ($row) use ($sku) {
            return $this->skuNorm((string) $row->sku) === $this->skuNorm($sku);
        });
        if (! $already) {
            $children->push($product);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductMaster>  $children
     * @return \Illuminate\Support\Collection<int, ProductMaster>
     */
    private function finalizeChildCollection($children)
    {
        return $children
            ->filter(fn ($p) => ! $this->isParentMasterSku((string) $p->sku))
            ->unique(fn ($p) => $this->skuNorm((string) $p->sku))
            ->sortBy(fn ($p) => strtoupper((string) $p->sku))
            ->values();
    }

    private function isParentMasterSku(?string $sku): bool
    {
        $sku = trim((string) $sku);

        return $sku !== '' && stripos($sku, 'PARENT') !== false;
    }

    /**
     * @param  array<string, string>  $skuParents
     */
    private function submittedParentForSku(string $sku, array $skuParents): string
    {
        $want = $this->skuNorm($sku);
        $wantCompact = $this->skuCompact($sku);
        foreach ($skuParents as $key => $parent) {
            if (is_array($parent)) {
                $key = $parent['sku'] ?? $key;
                $parent = $parent['parent'] ?? '';
            }
            if ($this->skuNorm((string) $key) === $want || ($wantCompact !== '' && $this->skuCompact((string) $key) === $wantCompact)) {
                return trim((string) $parent);
            }
        }

        return '';
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProductMaster>
     */
    private function childrenForParent(string $parent)
    {
        $parentNorm = $this->skuNorm($parent);
        $parentCompact = $this->skuCompact($parent);

        return $this->finalizeChildCollection(
            ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw("UPPER(TRIM(sku)) NOT LIKE 'PARENT%'")
                ->where(function ($q) use ($parent, $parentNorm, $parentCompact) {
                    $q->where('parent', $parent)
                        ->orWhereRaw('UPPER(TRIM(parent)) = ?', [$parentNorm]);
                    if ($parentCompact !== '') {
                        $q->orWhereRaw(
                            "UPPER(REPLACE(REPLACE(REPLACE(IFNULL(parent,''),' ',''),'-',''),'.','')) = ?",
                            [$parentCompact]
                        );
                    }
                })
                ->orderBy('sku')
                ->get()
        );
    }

    /**
     * @param  list<string>  $skus
     * @return \Illuminate\Support\Collection<int, ProductMaster>
     */
    private function findProductsBySkus(array $skus)
    {
        $skus = $this->uniqueTrimmedSkus($skus);
        if ($skus === []) {
            return ProductMaster::query()->whereRaw('1 = 0')->get();
        }

        $found = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('sku', $sku)
                        ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))]);
                }
            })
            ->get();

        $foundNorm = [];
        foreach ($found as $row) {
            $foundNorm[$this->skuNorm((string) $row->sku)] = true;
            $foundNorm[$this->skuCompact((string) $row->sku)] = true;
        }
        foreach ($skus as $sku) {
            if (isset($foundNorm[$this->skuNorm($sku)]) || isset($foundNorm[$this->skuCompact($sku)])) {
                continue;
            }
            $loose = $this->findProductLoose($sku);
            if ($loose) {
                $found->push($loose);
                $foundNorm[$this->skuNorm((string) $loose->sku)] = true;
            }
        }

        return $found->unique(fn ($p) => $p->id ?? $this->skuNorm((string) $p->sku))->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, ProductMaster>|array<string, ProductMaster>  $products
     */
    private function productFromKeyed($products, string $sku): ?ProductMaster
    {
        $want = $this->skuNorm($sku);
        foreach ($products as $row) {
            if ($row instanceof ProductMaster && $this->skuNorm((string) $row->sku) === $want) {
                return $row;
            }
        }

        return null;
    }

    private function skuNorm(string $sku): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $sku) ?? $sku));
    }

    private function skuCompact(string $sku): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $sku));
    }

    private function findProductLoose(string $sku): ?ProductMaster
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $exact = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
            })
            ->first();
        if ($exact) {
            return $exact;
        }

        $norm = $this->skuNorm($sku);
        $compact = $this->skuCompact($sku);
        $like = '%'.preg_replace('/\s+/', '%', $norm).'%';
        $candidates = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', 'like', $like)
            ->limit(40)
            ->get();
        foreach ($candidates as $row) {
            if ($this->skuNorm((string) $row->sku) === $norm || $this->skuCompact((string) $row->sku) === $compact) {
                return $row;
            }
        }

        if ($compact === '') {
            return null;
        }

        return ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw(
                "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(sku,' ',''),'-',''),'.',''),'/',''),'\\\\','')) = ?",
                [$compact]
            )
            ->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductMaster>  $children
     * @param  list<string>  $seedSkus
     * @return array<string, mixed>
     */
    private function formatPreviewGroup(string $parent, $children, array $seedSkus = []): array
    {
        $seedNorm = [];
        foreach ($seedSkus as $sku) {
            $norm = $this->skuNorm((string) $sku);
            if ($norm !== '') {
                $seedNorm[$norm] = true;
            }
        }

        $rows = [];
        $publishSkus = [];
        $selectedCount = 0;
        foreach ($children as $product) {
            $sku = trim((string) $product->sku);
            $classified = $this->classifyChildSku($sku);
            $publishable = ($classified['status'] ?? '') === 'will_publish';
            $selected = $publishable && isset($seedNorm[$this->skuNorm($sku)]);
            $rows[] = [
                'sku' => $sku,
                'spec' => $sku,
                'inv' => $this->resolveQty($sku),
                'status' => $classified['status'],
                'reason' => $classified['reason'],
                'selected' => $selected,
            ];
            if ($publishable) {
                $publishSkus[] = $sku;
            }
            if ($selected) {
                $selectedCount++;
            }
        }

        return [
            'parent' => $parent,
            'children' => $rows,
            'publish_skus' => $publishSkus,
            'publish_count' => $selectedCount > 0 ? $selectedCount : count($publishSkus),
            'single_child' => count($rows) === 1,
        ];
    }

    /**
     * @return array{status: string, reason: string}
     */
    private function classifyChildSku(string $sku): array
    {
        if ($this->isParentMasterSku($sku)) {
            return ['status' => 'skipped_parent', 'reason' => 'Parent row'];
        }
        if ($this->nrReqForSku($sku) === 'NR') {
            return ['status' => 'skipped_nrl', 'reason' => 'NRL'];
        }
        $localStatus = $this->localListingStatus($sku);
        if ($this->localGoodsId($sku) !== '' && ! in_array($localStatus, ['deleted', 'recycle', 'removed', 'draft'], true)) {
            return ['status' => 'skipped_listed', 'reason' => 'Already listed'];
        }
        $price = $this->resolvePrice($sku);
        if ($price === null || $price <= 0) {
            return ['status' => 'skipped_no_price', 'reason' => 'No Std Prc'];
        }
        $product = $this->findProductLoose($sku);
        if (! $product) {
            return ['status' => 'skipped_missing', 'reason' => 'Not in product master'];
        }

        $ready = $this->masterReadiness($product, $sku);
        if ($ready !== null) {
            return $ready;
        }
        if (in_array($localStatus, ['deleted', 'recycle', 'removed'], true)) {
            return ['status' => 'will_publish', 'reason' => 'In Temu Deleted — will republish'];
        }

        return ['status' => 'will_publish', 'reason' => ''];
    }

    /**
     * @return array{status: string, reason: string}|null
     */
    private function masterReadiness(ProductMaster $product, string $sku): ?array
    {
        if ($this->resolveTitle($product, $sku) === '') {
            return ['status' => 'skipped_no_title', 'reason' => 'Title missing in Title Master'];
        }
        if ($this->resolveDescription($product, $sku) === '') {
            return ['status' => 'skipped_no_description', 'reason' => 'Description missing in Description Master'];
        }
        if ($this->resolveSourceImages($product, $sku) === []) {
            return ['status' => 'skipped_no_image', 'reason' => 'Images missing in Image Master'];
        }
        if (! $this->hasDimWt($product, $sku)) {
            return ['status' => 'skipped_no_dim', 'reason' => 'Dimensions missing in Dim/Wt Master'];
        }

        return null;
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function filterPublishableSkus(array $skus): array
    {
        $out = [];
        foreach ($this->uniqueTrimmedSkus($skus) as $sku) {
            $status = $this->classifyChildSku($sku)['status'] ?? '';
            if ($status === 'will_publish') {
                $out[] = $sku;
            }
        }

        return $out;
    }

    private function groupKeyForProduct(ProductMaster $product): string
    {
        $parent = trim((string) ($product->parent ?? ''));
        if ($parent !== '') {
            return $parent;
        }

        return trim((string) $product->sku);
    }

    /**
     * @return list<array{parentSpecId: int|string, specId: int|string}>
     */
    private function uniqueSpecDetails(int $catId, string $sku): array
    {
        $parentSpecId = $this->parentSpecIdForCat($catId) ?? 1001;
        $specId = $this->createSpecId($catId, (int) $parentSpecId, $sku);
        if ($specId === null) {
            return [];
        }

        return [[
            'parentSpecId' => $parentSpecId,
            'specId' => $specId,
        ]];
    }

    private function parentSpecIdForCat(int $catId): ?int
    {
        if (isset($this->parentSpecIdByCat[$catId])) {
            return $this->parentSpecIdByCat[$catId];
        }

        $data = $this->temuCallBody([
            'type' => 'temu.local.product.variation.get',
            'catId' => $catId,
            'language' => 'en',
        ], 45);

        $parents = $data['result']['parentSpecList']
            ?? $data['result']['variationList']
            ?? $data['result']['specList']
            ?? [];
        if (! is_array($parents)) {
            $parents = [];
        }

        foreach ($parents as $parent) {
            if (! is_array($parent)) {
                continue;
            }
            $parentSpecId = $parent['parentSpecId'] ?? $parent['parentId'] ?? $parent['id'] ?? null;
            if ($parentSpecId === null || $parentSpecId === '') {
                continue;
            }
            $this->parentSpecIdByCat[$catId] = (int) $parentSpecId;

            return $this->parentSpecIdByCat[$catId];
        }

        return null;
    }

    private function localGoodsId(string $sku): string
    {
        $row = $this->metricRow($sku);
        if (! $row) {
            return '';
        }
        $status = strtolower(trim((string) ($row->listing_status ?? '')));
        if (in_array($status, ['deleted', 'recycle', 'removed', 'draft'], true)) {
            return '';
        }

        return trim((string) ($row->goods_id ?? ''));
    }

    private function localListingStatus(string $sku): string
    {
        $row = $this->metricRow($sku);

        return strtolower(trim((string) ($row?->listing_status ?? '')));
    }

    private function nrReqForSku(string $sku): string
    {
        try {
            $nrValues = ListingCountsEngine::loadNrValues($this->dataViewClass(), [$sku]);

            return ListingCountsEngine::nrReqFromDataView($nrValues->get(strtoupper($sku)));
        } catch (\Throwable) {
            return 'REQ';
        }
    }

    private function resolveTitle(ProductMaster $product, string $sku): string
    {
        foreach ($this->titleMasterCandidates($product) as $clean) {
            if ($clean !== '' && ! $this->looksLikeSku($clean, $sku)) {
                return $clean;
            }
        }

        $parent = $this->parentProductFor($product, $sku);
        if ($parent) {
            foreach ($this->titleMasterCandidates($parent) as $clean) {
                if ($clean !== '' && ! $this->looksLikeSku($clean, $sku)) {
                    return $clean;
                }
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function titleMasterCandidates(ProductMaster $product): array
    {
        $out = [];
        foreach (['title80', 'title100', 'title150', 'title60'] as $col) {
            $clean = $this->sanitizeGoodsName((string) ($product->{$col} ?? ''));
            if ($clean !== '') {
                $out[] = $clean;
            }
        }

        return $out;
    }

    private function looksLikeSku(string $text, string $sku): bool
    {
        $norm = static function (string $value): string {
            return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value))) ?? '';
        };

        $a = $norm($text);
        $b = $norm($sku);

        return $a !== '' && $b !== '' && $a === $b;
    }

    private function sanitizeGoodsName(string $name): string
    {
        $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/[®©™~!*$?_{}#<>|;^¬¦]/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name);

        return mb_substr($name, 0, 500);
    }

    private function resolveDescription(ProductMaster $product, string $sku): string
    {
        $rows = [$product];
        $parent = $this->parentProductFor($product, $sku);
        if ($parent) {
            $rows[] = $parent;
        }

        $marketplaceSkus = [$sku];
        foreach ($rows as $row) {
            $rowSku = trim((string) ($row->sku ?? ''));
            if ($rowSku !== '') {
                $marketplaceSkus[] = $rowSku;
            }
            $parentLabel = trim((string) ($row->parent ?? ''));
            if ($parentLabel !== '') {
                $marketplaceSkus[] = $parentLabel;
            }
            $metric = $rowSku !== '' ? $this->metricRow($rowSku) : null;
            foreach ([
                $metric?->description_master,
                $metric?->goods_desc ?? null,
                $metric?->goods_summary ?? null,
                $row->description_1500 ?? null,
                $row->product_description ?? null,
                $row->description_html ?? null,
                $row->description_1000 ?? null,
                $row->description_800 ?? null,
                $row->description_600 ?? null,
                $row->description_v2_description ?? null,
                $this->textFromMaybeList($row->description_v2_bullets ?? null),
                $this->textFromMaybeList($row->description_v2_features ?? null),
            ] as $raw) {
                $text = $this->cleanDescriptionText((string) $raw, $sku);
                if ($text !== '') {
                    return $text;
                }
            }

            $fromBullets = $this->cleanDescriptionText(implode(' ', $this->resolveBullets($row, $rowSku !== '' ? $rowSku : $sku)), $sku);
            if ($fromBullets !== '') {
                return $fromBullets;
            }
        }

        $fromMaps = $this->descriptionFromOtherMarketplaces($marketplaceSkus);
        if ($fromMaps !== '') {
            return $fromMaps;
        }

        return $this->cleanDescriptionText($this->resolveTitle($product, $sku), $sku);
    }

    private function textFromMaybeList(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            array_walk_recursive($value, function ($item) use (&$parts) {
                $text = trim(strip_tags((string) $item));
                if ($text !== '') {
                    $parts[] = $text;
                }
            });

            return implode(' ', $parts);
        }

        return trim((string) $value);
    }

    private function cleanDescriptionText(string $raw, string $sku): string
    {
        $text = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if ($text === '' || $this->looksLikeSku($text, $sku)) {
            return '';
        }

        return mb_substr($text, 0, 2000);
    }

    /**
     * @param  list<string>  $skus
     */
    private function descriptionFromOtherMarketplaces(array $skus): string
    {
        $keys = [];
        foreach ($skus as $sku) {
            foreach ($this->skuLookupKeys((string) $sku) as $key) {
                $keys[] = $key;
            }
        }
        $keys = array_values(array_unique(array_filter($keys)));
        if ($keys === []) {
            return '';
        }

        foreach (ProductMasterMarketplaceMaps::descriptionTableMap() as $table) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'description_master')) {
                    continue;
                }
                $raw = DB::table($table)
                    ->whereIn('sku', $keys)
                    ->whereNotNull('description_master')
                    ->where('description_master', '!=', '')
                    ->value('description_master');
                $text = $this->cleanDescriptionText((string) $raw, (string) ($skus[0] ?? ''));
                if ($text !== '') {
                    return $text;
                }
            } catch (\Throwable) {
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function resolveBullets(ProductMaster $product, string $sku): array
    {
        $bullets = $this->bulletLinesFromProduct($product);
        if ($bullets === []) {
            $parent = $this->parentProductFor($product, $sku);
            if ($parent) {
                $bullets = $this->bulletLinesFromProduct($parent);
            }
        }
        if ($bullets === []) {
            $metric = $this->metricRow($sku);
            $summary = trim((string) ($metric?->goods_summary ?? $metric?->bullet_points ?? ''));
            if ($summary !== '') {
                foreach (preg_split('/\r\n|\r|\n/', $summary) ?: [] as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $bullets[] = mb_substr($line, 0, 200);
                    }
                    if (count($bullets) >= 5) {
                        break;
                    }
                }
            }
        }

        return array_slice($bullets, 0, 5);
    }

    /**
     * @return list<string>
     */
    private function bulletLinesFromProduct(ProductMaster $product): array
    {
        $bullets = [];
        foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
            $line = trim(strip_tags((string) ($product->{$col} ?? '')));
            if ($line !== '') {
                $bullets[] = mb_substr($line, 0, 200);
            }
        }

        return $bullets;
    }

    private function resolvePrice(string $sku): ?float
    {
        $std = $this->standardPrice($sku);
        if ($this->positivePrice($std)) {
            return (float) $std;
        }

        foreach ($this->skuLookupKeys($sku) as $key) {
            $mapped = $this->priceLookupMap()[$key] ?? null;
            if ($this->positivePrice($mapped)) {
                return (float) $mapped;
            }
        }

        $metricPrice = $this->api->getProductPrice($sku);
        if ($this->positivePrice($metricPrice)) {
            return (float) $metricPrice;
        }

        $shopify = $this->shopifyRow($sku);
        if ($shopify && $this->positivePrice($shopify->price ?? null)) {
            return (float) $shopify->price;
        }

        $product = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first();
        if ($product) {
            $fromValues = $this->priceFromProductValues($product);
            if ($fromValues !== null) {
                return $fromValues;
            }

            $sibling = $this->siblingPrice($product);
            if ($sibling !== null) {
                return $sibling;
            }
        }

        return null;
    }

    /**
     * @return array<string, float>
     */
    private function priceLookupMap(): array
    {
        if ($this->priceByLookupKey !== null) {
            return $this->priceByLookupKey;
        }

        $this->priceByLookupKey = [];
        $remember = function (string $sku, $price): void {
            if (! $this->positivePrice($price)) {
                return;
            }
            $value = (float) $price;
            foreach ($this->skuLookupKeys($sku) as $key) {
                if (! isset($this->priceByLookupKey[$key])) {
                    $this->priceByLookupKey[$key] = $value;
                }
            }
        };

        if (Schema::hasTable($this->pricingTable())) {
            $this->pricingClass()::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['sku', 'base_price'])
                ->each(function ($row) use ($remember) {
                    $remember((string) $row->sku, $row->base_price);
                });
        }

        if (Schema::hasTable($this->metricsTable())) {
            $this->metricClass()::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['sku', 'base_price'])
                ->each(function ($row) use ($remember) {
                    $remember((string) $row->sku, $row->base_price);
                });
        }

        return $this->priceByLookupKey;
    }

    private function standardPrice(string $sku): ?float
    {
        $direct = $this->stdFromMap($sku);
        if ($direct !== null) {
            return $direct;
        }

        try {
            $lmp = app(LmpSkuGroupService::class);
            $lmp->prepareForSkus([$sku]);
            foreach ($lmp->groupContaining($sku) as $member) {
                $linked = $this->stdFromMap((string) $member);
                if ($linked !== null) {
                    return $linked;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function stdFromMap(string $sku): ?float
    {
        foreach ($this->skuLookupKeys($sku) as $key) {
            $mapped = $this->stdPriceMap()[$key] ?? null;
            if ($this->positivePrice($mapped)) {
                return (float) $mapped;
            }
        }

        return null;
    }

    /**
     * Std Prc from /temu2-decrease (amazon_data_view.STANDARD_PRICE).
     *
     * @return array<string, float>
     */
    private function stdPriceMap(): array
    {
        if ($this->stdPriceByLookupKey !== null) {
            return $this->stdPriceByLookupKey;
        }

        $this->stdPriceByLookupKey = [];
        if (! Schema::hasTable('amazon_data_view')) {
            return $this->stdPriceByLookupKey;
        }

        AmazonDataView::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'value'])
            ->each(function ($row) {
                $val = is_array($row->value)
                    ? $row->value
                    : (json_decode((string) ($row->value ?? ''), true) ?: []);
                $std = $val['STANDARD_PRICE'] ?? null;
                if (! $this->positivePrice($std)) {
                    return;
                }
                $value = round((float) $std, 2);
                foreach ($this->skuLookupKeys((string) $row->sku) as $key) {
                    if (! isset($this->stdPriceByLookupKey[$key])) {
                        $this->stdPriceByLookupKey[$key] = $value;
                    }
                }
            });

        return $this->stdPriceByLookupKey;
    }

    private function siblingPrice(ProductMaster $product): ?float
    {
        $parent = $this->groupKeyForProduct($product);
        if ($parent === '') {
            return null;
        }

        $siblings = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('parent', $parent)
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->pluck('sku');

        foreach ($siblings as $siblingSku) {
            foreach ($this->skuLookupKeys((string) $siblingSku) as $key) {
                $mapped = $this->priceLookupMap()[$key] ?? null;
                if ($this->positivePrice($mapped)) {
                    return (float) $mapped;
                }
            }
        }

        return null;
    }

    private function priceFromProductValues(ProductMaster $product): ?float
    {
        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['temu2_price', 'temu_price', 'base_price', 'sprice', 'SPRICE', 'lp'] as $key) {
            if ($this->positivePrice($values[$key] ?? null)) {
                return (float) $values[$key];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function skuLookupKeys(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $upper = strtoupper($sku);
        $upper = (string) preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $upper);
        $spaced = (string) preg_replace('/\s+/', ' ', $upper);
        $loose = (string) preg_replace('/[^A-Z0-9]/', '', $spaced);

        return array_values(array_unique(array_filter([$sku, $upper, $spaced, $loose], fn ($k) => $k !== '')));
    }

    private function positivePrice($price): bool
    {
        return $price !== null && $price !== '' && is_numeric($price) && (float) $price > 0;
    }

    private function resolveQty(string $sku): int
    {
        $shopify = $this->shopifyRow($sku);
        if (! $shopify) {
            return 0;
        }

        $qty = $shopify->available_to_sell ?? $shopify->inv ?? 0;

        return max(0, min(999999, (int) $qty));
    }

    /**
     * @return list<string>
     */
    private function resolveSourceImages(ProductMaster $product, string $sku): array
    {
        $urls = [];
        $seen = [];
        $push = function (string $raw) use (&$urls, &$seen): void {
            $url = $this->toAbsoluteImageUrl($raw);
            if ($url === '' || isset($seen[$url])) {
                return;
            }
            $urls[] = $url;
            $seen[$url] = true;
        };

        $this->pushProductMasterImages($product, $push);

        $parent = $this->parentProductFor($product, $sku);
        if ($parent) {
            $this->pushProductMasterImages($parent, $push);
        }

        $metric = $this->metricRow($sku);
        if ($metric) {
            foreach ($this->decodeImageList($metric->image_master_json ?? null) as $url) {
                $push($url);
            }
        }

        return array_slice($urls, 0, self::MAX_IMAGES);
    }

    /**
     * @param  callable(string): void  $push
     */
    private function pushProductMasterImages(ProductMaster $product, callable $push): void
    {
        $push((string) ($product->main_image ?? ''));
        $push((string) ($product->main_image_brand ?? ''));
        for ($i = 1; $i <= 20; $i++) {
            $col = 'image'.$i;
            $push((string) ($product->{$col} ?? ''));
        }

        $values = is_array($product->Values) ? $product->Values : [];
        foreach (['image_path', 'image', 'Image', 'main_image', 'Image Path', 'photo'] as $key) {
            $raw = $values[$key] ?? '';
            if (is_array($raw)) {
                foreach ($raw as $item) {
                    $push((string) (is_array($item) ? ($item['url'] ?? $item['src'] ?? '') : $item));
                }
            } else {
                $push((string) $raw);
            }
        }

        $v2 = $product->description_v2_images ?? null;
        if (is_array($v2)) {
            foreach ($v2 as $item) {
                $push((string) (is_array($item) ? ($item['url'] ?? $item['src'] ?? '') : $item));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function decodeImageList(mixed $raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $text = trim((string) $raw);
            if ($text === '') {
                return [];
            }
            $decoded = json_decode($text, true);
            $items = is_array($decoded) ? $decoded : (preg_split('/\s+/', $text) ?: []);
        }

        $out = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = $item['url'] ?? $item['src'] ?? $item['image'] ?? '';
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function toAbsoluteImageUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, '//')) {
            return 'https:'.$path;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }

    /**
     * @param  list<string>  $sourceImages
     * @return array{urls: list<string>, error: ?string}
     */
    private function uploadImages(array $sourceImages): array
    {
        $hosted = [];
        foreach (array_slice($sourceImages, 0, self::MAX_IMAGES) as $url) {
            if ($this->isTemuHostedImageUrl($url)) {
                $hosted[] = $url;
                continue;
            }

            $res = $this->uploadImageToTemu2($url);
            if (! empty($res['success']) && ! empty($res['url'])) {
                $hosted[] = (string) $res['url'];
                continue;
            }

            $msg = trim((string) ($res['message'] ?? ''));
            Log::warning('Temu2 publish image upload failed', [
                'url' => $url,
                'message' => $msg,
            ]);

            if ($this->isIpWhitelistMessage($msg)) {
                return ['urls' => [], 'error' => $this->ipWhitelistUserMessage()];
            }

            return [
                'urls' => [],
                'error' => $msg !== ''
                    ? $this->shopLabel().' image upload failed: '.$msg
                    : $this->shopLabel().' image upload failed.',
            ];
        }

        return ['urls' => array_values(array_unique($hosted)), 'error' => null];
    }

    /**
     * Temu 2 image API expects `fileUrl` on bg.local.goods.image.upload.
     *
     * @return array{success: bool, url?: string, message: string}
     */
    private function uploadImageToTemu2(string $imageUrl): array
    {
        $stripped = preg_replace('/\?.*$/', '', $imageUrl) ?: $imageUrl;
        $candidates = array_values(array_unique(array_filter([$imageUrl, $stripped])));

        $attempts = [];
        foreach ($candidates as $url) {
            $attempts[] = ['bg.local.goods.image.upload', ['fileUrl' => $url]];
            $attempts[] = ['bg.local.goods.image.upload', [
                'fileUrl' => $url,
                'scalingType' => 1,
                'compressionType' => 0,
                'formatConversionType' => 0,
            ]];
            $attempts[] = ['temu.local.goods.image.v2.upload', ['fileUrl' => $url, 'usage' => 1]];
        }

        $lastMsg = '';
        foreach ($attempts as [$type, $params]) {
            $data = $this->api->callOpenApi($type, $params, 60);
            $hosted = $this->extractUploadedImageUrl($data);
            if ($hosted !== null) {
                return ['success' => true, 'url' => $hosted, 'message' => 'OK'];
            }
            $lastMsg = trim((string) ($data['errorMsg'] ?? $data['message'] ?? ''));
            if ($this->isIpWhitelistMessage($lastMsg)) {
                return ['success' => false, 'message' => $lastMsg];
            }
        }

        $fallback = $this->api->uploadTemuImageFromUrl($imageUrl);
        if (! empty($fallback['success']) && ! empty($fallback['url'])) {
            return $fallback;
        }

        return [
            'success' => false,
            'message' => $lastMsg !== '' ? $lastMsg : (string) ($fallback['message'] ?? 'Image upload failed.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractUploadedImageUrl(array $data): ?string
    {
        $r = $data['result'] ?? null;
        if (! is_array($r) && isset($data['raw']['result']) && is_array($data['raw']['result'])) {
            $r = $data['raw']['result'];
        }
        if (! is_array($r)) {
            return null;
        }

        foreach (['url', 'imageUrl', 'image_url', 'fileUrl', 'cdnUrl', 'picUrl'] as $key) {
            $val = trim((string) ($r[$key] ?? ''));
            if ($val !== '' && preg_match('#^https?://#i', $val)) {
                return $val;
            }
        }

        $firstImage = $r['images'][0]['url'] ?? $r['autoCropUrls'][0] ?? null;
        $firstImage = trim((string) $firstImage);
        if ($firstImage !== '' && preg_match('#^https?://#i', $firstImage)) {
            return $firstImage;
        }

        return null;
    }

    private function isTemuHostedImageUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return $host !== '' && (
            str_contains($host, 'kwcdn.com')
            || str_contains($host, 'temu.com')
        );
    }

    private function temu2IpWhitelistError(): ?string
    {
        $data = $this->api->callOpenApi('bg.local.goods.list.query', [
            'goodsSearchType' => 1,
            'goodsStatusFilterType' => 1,
            'pageSize' => 1,
            'pageNumber' => 1,
        ], 20);

        if ($data['success'] ?? false) {
            return null;
        }

        $msg = trim((string) ($data['errorMsg'] ?? $data['message'] ?? ''));
        if ($this->isIpWhitelistMessage($msg)) {
            return $this->ipWhitelistUserMessage();
        }

        return null;
    }

    private function isIpWhitelistMessage(string $message): bool
    {
        return stripos($message, 'NOT_IN_IP_WHITE_LIST') !== false
            || stripos($message, 'IP_WHITE') !== false;
    }

    private function ipWhitelistUserMessage(): string
    {
        $ip = $this->detectPublicIp();
        $ipBit = $ip !== '' ? " Your public IP is {$ip}." : '';

        return $this->shopLabel().' blocked this computer (NOT_IN_IP_WHITE_LIST).'.$ipBit
            .' Open partner.temu.com → the '.$this->shopLabel().' app → IP whitelist, add that IP, wait about a minute, then click Publish again.'
            .' Production (inventory.5coremanagement.com) is a different IP from this PC.';
    }

    private function detectPublicIp(): string
    {
        try {
            $resp = Http::timeout(5)->withoutVerifying()->get('https://api.ipify.org');
            $ip = trim((string) $resp->body());
            if ($resp->successful() && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        } catch (\Throwable) {
        }

        return '';
    }

    private function resolveCatId(string $sku, string $title, string $imageUrl): ?int
    {
        if (Schema::hasTable($this->pricingTable())) {
            $raw = $this->pricingClass()::query()->where('sku', $sku)->value('category_id');
            $id = (int) preg_replace('/\D+/', '', (string) $raw);
            if ($id >= 1000) {
                return $id;
            }
        }

        $body = [
            'type' => 'bg.local.goods.category.recommend',
            'goodsName' => $title,
            'language' => 'en',
        ];
        if ($imageUrl !== '') {
            $body['imageUrl'] = $imageUrl;
        }

        $data = $this->temuCallBody($body, 45);
        $catId = (int) ($data['result']['catId'] ?? 0);
        if ($catId > 0) {
            return $catId;
        }
        $list = $data['result']['catIdList'] ?? [];
        if (is_array($list) && $list !== []) {
            $last = (int) end($list);
            if ($last > 0) {
                return $last;
            }
        }

        return null;
    }

    /**
     * US add-goods requires originRegion1 as an English country name (error 150011019).
     * ISO-2 codes like CN are rejected. originRegion2 is required when origin is China.
     *
     * @return array{importDesignation: string, originRegion1: string, originRegion2?: string}
     */
    private function buildGoodsOriginInfo(): array
    {
        $origin1 = $this->normalizeOriginRegion1((string) $this->shopConfig('origin_region1', 'China'));
        $isChina = strcasecmp($origin1, 'China') === 0;

        $info = [
            'importDesignation' => $this->normalizeImportDesignation((string) $this->shopConfig('import_designation', 'Imported')),
            'originRegion1' => $origin1,
        ];
        if ($isChina) {
            $origin2 = trim((string) $this->shopConfig('origin_region2', 'Guangdong'));
            $info['originRegion2'] = $origin2 !== '' ? $origin2 : 'Guangdong';
        }

        return $info;
    }

    private function normalizeOriginRegion1(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 'China';
        }

        $map = [
            'CN' => 'China',
            'CHN' => 'China',
            'CHINA' => 'China',
            'US' => 'United States',
            'USA' => 'United States',
            'UNITED STATES' => 'United States',
            'GB' => 'United Kingdom',
            'UK' => 'United Kingdom',
            'UNITED KINGDOM' => 'United Kingdom',
            'IN' => 'India',
            'VN' => 'Vietnam',
            'ID' => 'Indonesia',
            'TH' => 'Thailand',
            'MX' => 'Mexico',
        ];

        return $map[strtoupper($raw)] ?? $raw;
    }

    private function normalizeImportDesignation(string $raw): string
    {
        $raw = trim($raw);
        $map = [
            '1' => 'Made in the USA',
            '2' => 'Made in the USA and Imported',
            '3' => 'Made in the USA or Imported',
            '4' => 'Imported',
            'IMPORTED' => 'Imported',
            'MADE IN THE USA' => 'Made in the USA',
            'MADE IN THE USA AND IMPORTED' => 'Made in the USA and Imported',
            'MADE IN THE USA OR IMPORTED' => 'Made in the USA or Imported',
        ];

        return $map[strtoupper($raw)] ?? ($raw !== '' ? $raw : 'Imported');
    }

    private function resolveCostTemplateId(): string
    {
        $configured = trim((string) $this->shopConfig('cost_template_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $cached = trim((string) Cache::get($this->costTemplateCacheKey(), ''));
        if ($cached !== '') {
            return $cached;
        }

        $data = $this->temuCallBody([
            'type' => 'bg.freight.template.list.query',
        ], 45);
        $result = is_array($data['result'] ?? null) ? $data['result'] : [];
        $candidates = [];
        foreach (['freightTemplateList', 'templateList', 'costTemplateList'] as $key) {
            if (isset($result[$key]) && is_array($result[$key])) {
                $candidates = $result[$key];
                break;
            }
        }
        if ($candidates === []) {
            $candidates = array_values(array_filter($result, 'is_array'));
        }

        foreach ($candidates as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['costTemplateId', 'freightTemplateId', 'templateId'] as $key) {
                $id = trim((string) ($row[$key] ?? ''));
                if ($id !== '') {
                    Cache::put($this->costTemplateCacheKey(), $id, now()->addHour());

                    return $id;
                }
            }
        }

        return '';
    }

    /**
     * @return list<array{parentSpecId: int|string, specId: int|string}>
     */
    private function resolveSpecDetails(int $catId, string $sku): array
    {
        $data = $this->temuCallBody([
            'type' => 'temu.local.product.variation.get',
            'catId' => $catId,
            'language' => 'en',
        ], 45);

        $parents = $data['result']['parentSpecList']
            ?? $data['result']['variationList']
            ?? $data['result']['specList']
            ?? [];
        if (! is_array($parents)) {
            $parents = [];
        }

        $details = [];
        foreach ($parents as $parent) {
            if (! is_array($parent)) {
                continue;
            }
            $parentSpecId = $parent['parentSpecId'] ?? $parent['parentId'] ?? $parent['id'] ?? null;
            if ($parentSpecId === null || $parentSpecId === '') {
                continue;
            }

            $specId = null;
            $children = $parent['specList'] ?? $parent['childSpecList'] ?? $parent['values'] ?? [];
            if (is_array($children) && $children !== []) {
                $first = $children[0];
                if (is_array($first)) {
                    $specId = $first['specId'] ?? $first['id'] ?? null;
                }
            }

            $variationType = (int) ($parent['variationType'] ?? 2);
            if ($specId === null || $variationType === 1) {
                $created = $this->createSpecId($catId, (int) $parentSpecId, $sku);
                if ($created !== null) {
                    $specId = $created;
                }
            }

            if ($specId === null) {
                continue;
            }

            $details[] = [
                'parentSpecId' => $parentSpecId,
                'specId' => $specId,
            ];
            if (count($details) >= 1) {
                break;
            }
        }

        if ($details === []) {
            $created = $this->createSpecId($catId, 1001, $sku);
            if ($created !== null) {
                $details[] = [
                    'parentSpecId' => 1001,
                    'specId' => $created,
                ];
            }
        }

        return $details;
    }

    private function createSpecId(int $catId, int $parentSpecId, string $sku): ?int
    {
        $name = $this->sanitizeGoodsName($sku);
        if ($name === '') {
            $name = 'Default';
        }
        $data = $this->temuCallBody([
            'type' => 'bg.local.goods.spec.id.get',
            'catId' => $catId,
            'parentSpecId' => $parentSpecId,
            'childSpecName' => mb_substr($name, 0, 50),
        ], 45);
        $specId = (int) ($data['result']['specId'] ?? 0);

        return $specId > 0 ? $specId : null;
    }

    /**
     * Required category attributes, including Temu keyword attributes.
     * Child attributes (e.g. Operating Voltage) are only sent with values
     * allowed by the selected parent (Power Mode, Battery, etc.).
     *
     * @return list<array<string, mixed>>
     */
    private function buildGoodsProperty(int $catId, string $costTemplateId = '', ?ProductMaster $product = null, string $sku = ''): array
    {
        if ($product && $sku !== '') {
            $this->loadAttributeHintCorpus($product, $sku);
        } else {
            $this->attributeHintCorpus = [self::BRAND_NAME];
        }

        $props = $this->loadCategoryAttributes($catId, $costTemplateId);
        if ($props === []) {
            Log::warning('Temu2 publish: no category attributes returned', ['catId' => $catId]);

            return [];
        }

        usort($props, function (array $a, array $b): int {
            $sa = (int) ($a['showType'] ?? 0);
            $sb = (int) ($b['showType'] ?? 0);

            return $sa <=> $sb;
        });

        $selectedVidByRefPid = [];
        $out = [];
        $filled = [];

        foreach ($props as $prop) {
            if (! is_array($prop)) {
                continue;
            }
            $entry = $this->fillPropertyEntry($prop, $selectedVidByRefPid, $props);
            if ($entry === null) {
                continue;
            }
            $refPid = (int) $entry['refPid'];
            if (isset($entry['vid'])) {
                $selectedVidByRefPid[$refPid] = (int) $entry['vid'];
            }
            $out[] = $entry;
            $name = trim((string) ($prop['attributeName'] ?? $prop['name'] ?? ''));
            $filled[] = $name !== '' ? $name : ('refPid '.$refPid);
        }

        Log::info('Temu2 publish goodsProperty filled', [
            'catId' => $catId,
            'count' => count($out),
            'names' => $filled,
        ]);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $prop
     * @param  array<int, int>  $selectedVidByRefPid
     * @param  list<array<string, mixed>>  $allProps
     * @return array<string, mixed>|null
     */
    private function fillPropertyEntry(array $prop, array $selectedVidByRefPid, array $allProps): ?array
    {
        $name = trim((string) ($prop['attributeName'] ?? $prop['name'] ?? ''));
        $required = (bool) ($prop['required'] ?? $prop['isRequired'] ?? false);
        $isKeyword = $this->isKeywordAttribute($name, $prop);
        $isSale = ! empty($prop['isSale']);
        if ($isSale || (! $required && ! $isKeyword)) {
            return null;
        }

        $refPid = $prop['refPid'] ?? $prop['pid'] ?? $prop['templatePid'] ?? null;
        if ($refPid === null) {
            return null;
        }

        $showType = (int) ($prop['showType'] ?? 0);
        if ($showType === 1 && ! $this->childAttributeUnlocked($prop, $selectedVidByRefPid)) {
            return null;
        }

        $entry = ['refPid' => (int) $refPid];
        if (isset($prop['pid'])) {
            $entry['pid'] = (int) $prop['pid'];
        }
        if (isset($prop['templatePid'])) {
            $entry['templatePid'] = (int) $prop['templatePid'];
        }

        $values = $this->flattenAttributeValues($prop);
        $parentVid = $this->selectedParentVidForProp($prop, $selectedVidByRefPid);
        $preferParentVids = $showType !== 1
            ? $this->parentVidsThatUnlockChildren((int) $refPid, $allProps)
            : [];
        $picked = $this->pickAttributeValue($values, $name, $parentVid, $preferParentVids, $selectedVidByRefPid);
        $controlType = (int) (
            $prop['controlType']
            ?? $prop['attributeRules']['controlType']
            ?? 1
        );

        if ($picked !== null) {
            $entry['vid'] = (int) $picked['vid'];
            if ($picked['value'] !== '') {
                $entry['value'] = $picked['value'];
            }

            return $entry;
        }

        if ($showType === 1) {
            Log::info('Temu2 publish: skipped child attribute with no value matching parent', [
                'name' => $name,
                'refPid' => $refPid,
                'parentVid' => $parentVid,
            ]);

            return null;
        }

        if (in_array($controlType, [0, 16, 19], true)) {
            $typed = $this->inputValueFromHints($name);
            if ($typed === '') {
                Log::info('Temu2 publish: left input attribute for manual fill', [
                    'name' => $name,
                    'refPid' => $refPid,
                ]);

                return null;
            }
            $entry['value'] = $typed;
            $units = $prop['valueUnitList'] ?? $prop['attributeValueUnitList'] ?? [];
            if (is_array($units) && $units !== []) {
                $unitId = $units[0]['valueUnitId'] ?? null;
                if ($unitId !== null) {
                    $entry['valueUnitId'] = (int) $unitId;
                }
            }

            return $entry;
        }

        Log::warning('Temu2 publish: could not fill required attribute', [
            'name' => $name,
            'refPid' => $refPid,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $prop
     * @param  array<int, int>  $selectedVidByRefPid
     */
    private function childAttributeUnlocked(array $prop, array $selectedVidByRefPid): bool
    {
        $conditions = $prop['showCondition'] ?? [];
        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        foreach ($conditions as $cond) {
            if (! is_array($cond)) {
                continue;
            }
            $parentRef = (int) ($cond['parentRefPid'] ?? 0);
            $allowed = array_map('intval', $cond['parentVids'] ?? []);
            $selected = $selectedVidByRefPid[$parentRef] ?? null;
            if ($selected !== null && ($allowed === [] || in_array($selected, $allowed, true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $prop
     * @param  array<int, int>  $selectedVidByRefPid
     */
    private function selectedParentVidForProp(array $prop, array $selectedVidByRefPid): ?int
    {
        foreach ($prop['showCondition'] ?? [] as $cond) {
            if (! is_array($cond)) {
                continue;
            }
            $parentRef = (int) ($cond['parentRefPid'] ?? 0);
            if ($parentRef > 0 && isset($selectedVidByRefPid[$parentRef])) {
                return $selectedVidByRefPid[$parentRef];
            }
        }

        $parentRef = (int) ($prop['parentRefPid'] ?? $prop['parentTemplatePid'] ?? 0);
        if ($parentRef > 0 && isset($selectedVidByRefPid[$parentRef])) {
            return $selectedVidByRefPid[$parentRef];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $allProps
     * @return list<int>
     */
    private function parentVidsThatUnlockChildren(int $parentRefPid, array $allProps): array
    {
        $vids = [];
        foreach ($allProps as $child) {
            if ((int) ($child['showType'] ?? 0) !== 1) {
                continue;
            }
            foreach ($child['showCondition'] ?? [] as $cond) {
                if (! is_array($cond)) {
                    continue;
                }
                if ((int) ($cond['parentRefPid'] ?? 0) !== $parentRefPid) {
                    continue;
                }
                foreach ($cond['parentVids'] ?? [] as $pv) {
                    $vids[] = (int) $pv;
                }
            }
            foreach ($this->flattenAttributeValues($child) as $value) {
                foreach ($value['parentVids'] ?? [] as $pv) {
                    $vids[] = (int) $pv;
                }
            }
            foreach ($child['templatePropertyValueParentList'] ?? [] as $rel) {
                if (! is_array($rel)) {
                    continue;
                }
                foreach ($rel['parentVids'] ?? [] as $pv) {
                    $vids[] = (int) $pv;
                }
            }
        }

        return array_values(array_unique(array_filter($vids)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadCategoryAttributes(int $catId, string $costTemplateId): array
    {
        $attrsBody = [
            'type' => 'temu.local.product.attributes.get',
            'catId' => $catId,
            'language' => 'en',
        ];
        if ($costTemplateId !== '') {
            $attrsBody['costTemplateId'] = $costTemplateId;
        }
        $data = $this->temuCallBody($attrsBody, 45);
        $result = is_array($data['result'] ?? null) ? $data['result'] : [];

        $list = $result['attributeList']
            ?? $result['properties']
            ?? $result['propertyList']
            ?? $result['goodsProperties']
            ?? [];
        if (! is_array($list) || $list === []) {
            $list = [];
        }

        $template = $this->temuCallBody([
            'type' => 'bg.local.goods.template.get',
            'catId' => $catId,
            'language' => 'en',
        ], 45);
        $goodsProperties = $template['result']['templateInfo']['goodsProperties']
            ?? $template['result']['goodsProperties']
            ?? [];
        if (is_array($goodsProperties) && $goodsProperties !== []) {
            $list = array_merge($list, $goodsProperties);
        }

        $byRef = [];
        foreach ($list as $prop) {
            if (! is_array($prop)) {
                continue;
            }
            $refPid = (string) ($prop['refPid'] ?? $prop['pid'] ?? $prop['templatePid'] ?? '');
            if ($refPid === '') {
                continue;
            }
            if (! isset($byRef[$refPid])) {
                $byRef[$refPid] = $prop;
            } else {
                $byRef[$refPid] = array_merge($byRef[$refPid], $prop);
            }
        }

        return array_values($byRef);
    }

    /**
     * @param  array<string, mixed>  $prop
     * @return list<array<string, mixed>>
     */
    private function flattenAttributeValues(array $prop): array
    {
        foreach (['values', 'valueList', 'vidList'] as $key) {
            if (isset($prop[$key]) && is_array($prop[$key]) && $prop[$key] !== []) {
                return $prop[$key];
            }
        }

        $out = [];
        foreach ($prop['attributeValueDetail'] ?? [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            foreach ($detail['attributeValueList'] ?? [] as $value) {
                if (! is_array($value)) {
                    continue;
                }
                if (empty($value['parentVids']) && ! empty($detail['parentVids'])) {
                    $value['parentVids'] = $detail['parentVids'];
                }
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $prop
     */
    private function isKeywordAttribute(string $name, array $prop): bool
    {
        if (! empty($prop['isKeyword']) || (int) ($prop['feature'] ?? 0) === 1) {
            return true;
        }
        $lower = strtolower($name);

        return str_contains($lower, 'battery')
            || str_contains($lower, 'wireless')
            || str_contains($lower, 'power mode')
            || str_contains($lower, 'power supply')
            || str_contains($lower, 'operating voltage')
            || str_contains($lower, 'voltage');
    }

    private function inputValueFromHints(string $name): string
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'brand')) {
            return self::BRAND_NAME;
        }

        foreach ($this->attributeHintCorpus as $hint) {
            $hint = trim($hint);
            if ($hint === '' || $this->isGenericHint($hint)) {
                continue;
            }
            if ($this->hintRelatesToAttribute($hint, $lower)) {
                return mb_substr($hint, 0, 200);
            }
        }

        return '';
    }

    /**
     * @param  mixed  $values
     * @param  list<int>  $preferVids
     * @param  array<int, int>  $allSelectedVids
     * @return array{vid: int, value: string}|null
     */
    private function pickAttributeValue(
        mixed $values,
        string $attributeName = '',
        ?int $selectedParentVid = null,
        array $preferVids = [],
        array $allSelectedVids = []
    ): ?array {
        if (! is_array($values) || $values === []) {
            return null;
        }

        $name = strtolower($attributeName);
        $selectedSet = array_map('intval', array_values($allSelectedVids));
        $hintMatch = null;

        foreach ($values as $row) {
            if (! is_array($row)) {
                continue;
            }
            $vid = (int) ($row['vid'] ?? $row['id'] ?? 0);
            if ($vid <= 0) {
                continue;
            }

            $parentVids = array_map('intval', $row['parentVids'] ?? []);
            if ($parentVids !== []) {
                if ($selectedParentVid !== null && ! in_array($selectedParentVid, $parentVids, true)) {
                    continue;
                }
                if ($selectedParentVid === null && $selectedSet !== [] && array_intersect($parentVids, $selectedSet) === []) {
                    continue;
                }
            }

            $value = trim((string) ($row['value'] ?? $row['specName'] ?? $row['name'] ?? $row['attributeValue'] ?? ''));
            if ($value === '') {
                continue;
            }

            if (str_contains($name, 'brand') && $this->isFiveCoreBrand($value)) {
                return ['vid' => $vid, 'value' => $value];
            }
            if ($this->valueMatchesHints($value)) {
                return ['vid' => $vid, 'value' => $value];
            }
            if ($hintMatch === null && $preferVids !== [] && in_array($vid, $preferVids, true) && $this->valueMatchesHints($value)) {
                $hintMatch = ['vid' => $vid, 'value' => $value];
            }
        }

        return $hintMatch;
    }

    private function isFiveCoreBrand(string $value): bool
    {
        $norm = preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? '';

        return in_array($norm, ['5COREINC', '5CORE', 'FIVECOREINC', 'FIVECORE'], true);
    }

    private function valueMatchesHints(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || $this->isGenericHint($value)) {
            return false;
        }

        $needle = strtolower($value);
        foreach ($this->attributeHintCorpus as $hint) {
            $hay = strtolower(trim($hint));
            if ($hay === '') {
                continue;
            }
            if ($hay === $needle) {
                return true;
            }
            if (mb_strlen($needle) >= 3 && (str_contains($hay, $needle) || str_contains($needle, $hay))) {
                return true;
            }
        }

        return false;
    }

    private function isGenericHint(string $value): bool
    {
        return in_array(strtolower(trim($value)), [
            'yes', 'no', 'other', 'generic', 'none', 'n/a', 'na', 'unbranded',
            'does not apply', 'unknown',
        ], true);
    }

    private function hintRelatesToAttribute(string $hint, string $attributeName): bool
    {
        $hintLower = strtolower($hint);
        foreach (preg_split('/[\s\/,-]+/', $attributeName) ?: [] as $token) {
            $token = trim($token);
            if (mb_strlen($token) < 4) {
                continue;
            }
            if (str_contains($hintLower, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveBrand(): array
    {
        $name = self::BRAND_NAME;
        $types = [
            'bg.local.goods.brand.get',
            'temu.local.goods.brand.get',
            'bg.goods.brand.get',
        ];
        $paramSets = [
            ['brandName' => $name, 'pageSize' => 20, 'pageNo' => 1, 'language' => 'en'],
            ['brandNameList' => [$name], 'pageSize' => 20, 'pageNo' => 1, 'language' => 'en'],
        ];

        foreach ($types as $type) {
            foreach ($paramSets as $params) {
                $data = $this->temuCallBody(array_merge(['type' => $type], $params), 30);
                $result = is_array($data['result'] ?? null) ? $data['result'] : [];
                $list = $result['brandList'] ?? $result['list'] ?? $result['pageItems'] ?? [];
                if (! is_array($list)) {
                    continue;
                }
                foreach ($list as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $brandName = trim((string) ($row['brandName'] ?? $row['name'] ?? ''));
                    if ($brandName === '' || ! $this->isFiveCoreBrand($brandName)) {
                        continue;
                    }
                    $id = $row['brandId'] ?? $row['vid'] ?? $row['id'] ?? null;
                    if ($id) {
                        return ['brandId' => (int) $id, 'brandName' => $name];
                    }
                }
            }
        }

        return ['brandName' => $name];
    }

    /**
     * product_master.Values for a SKU — same source as /dim-wt-master.
     *
     * @return array<string, mixed>
     */
    private function dimWtMasterValues(?ProductMaster $product): array
    {
        if (! $product) {
            return [];
        }
        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        }

        return is_array($values) ? $values : [];
    }

    private function dimWtMasterNumber(array $values, string $key): ?float
    {
        if (! array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
            return null;
        }
        $raw = $values[$key];
        if (is_string($raw)) {
            $raw = trim(str_replace(',', '', $raw));
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $n = (float) $raw;

        return $n > 0 ? $n : null;
    }

    /**
     * Item package on /dim-wt-master: Item L/W/H IN + Itm wt GW, then Decl, then Item L/W/H CM.
     *
     * @return array{length_in: ?float, width_in: ?float, height_in: ?float, weight_lb: ?float, weight_kg: ?float, length_cm: ?float, width_cm: ?float, height_cm: ?float}
     */
    private function dimWtMasterItemPackage(array $values): array
    {
        $inch = function (string $actKey, string $declKey) use ($values): ?float {
            return $this->dimWtMasterNumber($values, $actKey)
                ?? $this->dimWtMasterNumber($values, $declKey);
        };

        return [
            'length_in' => $inch('l', 'l_decl'),
            'width_in' => $inch('w', 'w_decl'),
            'height_in' => $inch('h', 'h_decl'),
            'weight_lb' => $inch('wt_act', 'wt_decl'),
            'weight_kg' => $this->dimWtMasterNumber($values, 'wt_act_kg'),
            'length_cm' => $this->dimWtMasterNumber($values, 'l_cm'),
            'width_cm' => $this->dimWtMasterNumber($values, 'w_cm'),
            'height_cm' => $this->dimWtMasterNumber($values, 'h_cm'),
        ];
    }

    /**
     * @param  array{length_in: ?float, width_in: ?float, height_in: ?float, weight_lb: ?float, weight_kg: ?float, length_cm: ?float, width_cm: ?float, height_cm: ?float}  $base
     * @param  array{length_in: ?float, width_in: ?float, height_in: ?float, weight_lb: ?float, weight_kg: ?float, length_cm: ?float, width_cm: ?float, height_cm: ?float}  $fill
     * @return array{length_in: ?float, width_in: ?float, height_in: ?float, weight_lb: ?float, weight_kg: ?float, length_cm: ?float, width_cm: ?float, height_cm: ?float}
     */
    private function mergeDimWtMasterPackage(array $base, array $fill): array
    {
        foreach ($base as $key => $value) {
            if ($value === null && ($fill[$key] ?? null) !== null) {
                $base[$key] = $fill[$key];
            }
        }

        return $base;
    }

    /**
     * @param  array{length_in: ?float, width_in: ?float, height_in: ?float, weight_lb: ?float, weight_kg: ?float, length_cm: ?float, width_cm: ?float, height_cm: ?float}  $pkg
     */
    private function dimWtMasterPackageComplete(array $pkg): bool
    {
        $hasSize = (($pkg['length_cm'] ?? null) !== null || ($pkg['length_in'] ?? null) !== null)
            && (($pkg['width_cm'] ?? null) !== null || ($pkg['width_in'] ?? null) !== null)
            && (($pkg['height_cm'] ?? null) !== null || ($pkg['height_in'] ?? null) !== null);
        $hasWeight = ($pkg['weight_lb'] ?? null) !== null || ($pkg['weight_kg'] ?? null) !== null;

        return $hasSize && $hasWeight;
    }

    /**
     * Load /dim-wt-master item package for this SKU (not the parent row, unless a field is blank).
     *
     * @return array{length_in: ?float, width_in: ?float, height_in: ?float, weight_lb: ?float, weight_kg: ?float, length_cm: ?float, width_cm: ?float, height_cm: ?float}
     */
    private function dimWtMasterPackageForSku(string $sku, ?ProductMaster $hint = null): array
    {
        $sku = trim($sku);
        $row = $hint;
        if (! $row || strcasecmp(trim((string) $row->sku), $sku) !== 0) {
            $row = $this->findProductLoose($sku) ?? $hint;
        }
        $pkg = $this->dimWtMasterItemPackage($this->dimWtMasterValues($row));
        if ($this->dimWtMasterPackageComplete($pkg) || ! $row) {
            return $pkg;
        }
        $parent = $this->parentProductFor($row, $sku);
        if ($parent) {
            $pkg = $this->mergeDimWtMasterPackage(
                $pkg,
                $this->dimWtMasterItemPackage($this->dimWtMasterValues($parent))
            );
        }

        return $pkg;
    }

    private function hasDimWt(?ProductMaster $product, ?string $sku = null): bool
    {
        $sku = trim((string) ($sku ?: ($product->sku ?? '')));
        if ($sku === '' && ! $product) {
            return false;
        }

        return $this->dimWtMasterPackageComplete($this->dimWtMasterPackageForSku($sku, $product));
    }

    /**
     * Temu packageInfo from /dim-wt-master for this SKU (Item L/W/H IN + Itm wt GW, CM when stored).
     *
     * @return array{weight: string, length: string, width: string, height: string, weightUnit: string, volumeUnit: string}
     */
    private function resolveDimensions(ProductMaster $product, ?string $sku = null): array
    {
        $sku = trim((string) ($sku ?: $product->sku));
        $pkg = $this->dimWtMasterPackageForSku($sku, $product);

        $toCm = static function (?float $cm, ?float $inches): string {
            if ($cm !== null && $cm > 0) {
                return (string) max(1, round($cm, 2));
            }
            if ($inches !== null && $inches > 0) {
                return (string) max(1, round($inches * 2.54, 2));
            }

            return '1';
        };

        $weightG = '1';
        if (($pkg['weight_kg'] ?? null) !== null) {
            $weightG = (string) max(1, round((float) $pkg['weight_kg'] * 1000, 2));
        } elseif (($pkg['weight_lb'] ?? null) !== null) {
            $weightG = (string) max(1, round((float) $pkg['weight_lb'] * 453.592, 2));
        }

        return [
            'weight' => $weightG,
            'length' => $toCm($pkg['length_cm'] ?? null, $pkg['length_in'] ?? null),
            'width' => $toCm($pkg['width_cm'] ?? null, $pkg['width_in'] ?? null),
            'height' => $toCm($pkg['height_cm'] ?? null, $pkg['height_in'] ?? null),
            'weightUnit' => 'g',
            'volumeUnit' => 'cm',
        ];
    }

    private function parentProductFor(ProductMaster $product, string $sku): ?ProductMaster
    {
        $parentKey = $this->groupKeyForProduct($product);
        if ($parentKey === '' || strcasecmp($parentKey, trim($sku)) === 0) {
            return null;
        }

        return ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('parent', $parentKey)
            ->whereRaw('UPPER(TRIM(sku)) LIKE ?', ['PARENT%'])
            ->first()
            ?: ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('sku', $parentKey)
                ->first();
    }

    private function loadAttributeHintCorpus(ProductMaster $product, string $sku): void
    {
        $hints = [self::BRAND_NAME, '5Core', '5 Core'];
        $push = function (mixed $value) use (&$hints): void {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $this->flattenHintValues($item, $hints);
                }

                return;
            }
            $text = trim(strip_tags((string) $value));
            if ($text !== '' && mb_strlen($text) <= 240) {
                $hints[] = $text;
            }
        };

        $values = is_array($product->Values) ? $product->Values : [];
        $this->flattenHintValues($values, $hints);
        foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
            $push($product->{$col} ?? '');
        }

        try {
            $amazon = AmazonDataView::query()->whereIn('sku', $this->skuLookupKeys($sku))->first();
            if ($amazon && is_array($amazon->value)) {
                $this->flattenHintValues($amazon->value, $hints);
            }
        } catch (\Throwable) {
        }

        foreach (array_values(array_unique([$this->metricClass(), Temu2Metric::class, TemuMetric::class])) as $class) {
            try {
                $row = $class::query()->whereIn('sku', $this->skuLookupKeys($sku))->first();
                if ($row) {
                    foreach (['bullet_points', 'goods_summary', 'goods_desc'] as $col) {
                        $push($row->{$col} ?? '');
                    }
                }
            } catch (\Throwable) {
            }
        }

        try {
            $ebay = EbayMetric::query()->whereIn('sku', $this->skuLookupKeys($sku))->first();
            $push($ebay?->ebay_title);
        } catch (\Throwable) {
        }

        foreach (ProductMasterMarketplaceMaps::metricsTableMap() as $table) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                    continue;
                }
                $cols = [];
                foreach (['item_specifics', 'attributes', 'product_attributes', 'goods_property'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $cols[] = $col;
                    }
                }
                if ($cols === []) {
                    continue;
                }
                $row = DB::table($table)->whereIn('sku', $this->skuLookupKeys($sku))->first($cols);
                if (! $row) {
                    continue;
                }
                foreach ($cols as $col) {
                    $raw = $row->{$col} ?? null;
                    if (is_string($raw)) {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $this->flattenHintValues($decoded, $hints);
                            continue;
                        }
                    }
                    $push($raw);
                }
            } catch (\Throwable) {
            }
        }

        $this->attributeHintCorpus = array_values(array_unique(array_filter($hints, static fn ($h) => trim((string) $h) !== '')));
    }

    /**
     * @param  list<string>  $hints
     */
    private function flattenHintValues(mixed $value, array &$hints): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && ! is_numeric($key) && mb_strlen($key) <= 80) {
                    $hints[] = trim($key);
                }
                $this->flattenHintValues($item, $hints);
            }

            return;
        }

        $text = trim(strip_tags((string) $value));
        if ($text !== '' && mb_strlen($text) <= 240) {
            $hints[] = $text;
        }
    }

    /**
     * @param  list<array{sku: string, price: float, qty: int}>  $prepared
     * @param  array<string, mixed>  $data
     */
    private function persistPublishedRows(array $prepared, string $goodsId, array $data): string
    {
        $infoBySku = [];
        foreach ($data['result']['skuInfoList'] ?? [] as $info) {
            if (! is_array($info)) {
                continue;
            }
            foreach (['outSkuSn', 'externalSkuId', 'extCode'] as $key) {
                $code = strtoupper(trim((string) ($info[$key] ?? '')));
                if ($code !== '') {
                    $infoBySku[$code] = $info;
                }
            }
        }

        $firstSkuId = '';
        foreach ($prepared as $index => $row) {
            $info = $infoBySku[strtoupper((string) $row['sku'])] ?? (($data['result']['skuInfoList'][$index] ?? []) ?: []);
            $skuId = trim((string) ($info['skuId'] ?? ''));
            if ($firstSkuId === '' && $skuId !== '') {
                $firstSkuId = $skuId;
            }
            $this->api->persistNewListing((string) $row['sku'], $goodsId, $skuId !== '' ? $skuId : null);
            $this->persistExtraListingFields((string) $row['sku'], (float) $row['price'], (int) $row['qty']);
        }
        $this->forgetListingCaches();

        return $firstSkuId;
    }

    private function siblingGoodsId(string $parentKey): string
    {
        if ($parentKey === '') {
            return '';
        }

        $skus = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($parentKey) {
                $q->where('parent', $parentKey)->orWhere('sku', $parentKey);
            })
            ->pluck('sku')
            ->all();
        $skus[] = $parentKey;

        foreach (array_unique(array_map('strval', $skus)) as $sku) {
            $id = $this->localGoodsId($sku);
            if ($id === '') {
                continue;
            }
            $life = $this->remoteGoodsLifecycle($id);
            if (in_array($life, ['deleted', 'draft'], true)) {
                continue;
            }

            return $id;
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $skuList
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    private function addSkusToExistingGoods(string $goodsId, array $skuList): array
    {
        $attempts = [
            'bg.local.goods.sku.add',
            'temu.local.goods.sku.add',
            'bg.local.goods.update',
            'temu.local.goods.update',
            'bg.local.goods.partial.update',
        ];
        $lastMsg = '';
        foreach ($attempts as $type) {
            $data = $this->temuCallBody([
                'type' => $type,
                'language' => 'en',
                'goodsId' => (int) $goodsId,
                'skuList' => $skuList,
            ], 120);
            if ($data['success'] ?? false) {
                return ['success' => true, 'data' => $data];
            }
            $lastMsg = $this->apiErrorMessage($data);
            Log::info('Temu2 add SKU to existing goods failed', [
                'type' => $type,
                'goods_id' => $goodsId,
                'message' => $lastMsg,
            ]);
        }

        return ['success' => false, 'message' => $lastMsg];
    }

    private function uniqueOutGoodsSn(string $preferred, string $fallback): string
    {
        foreach ([$preferred, $fallback, $fallback.'-'.substr(md5($fallback), 0, 6)] as $raw) {
            $sn = mb_substr($this->sanitizeGoodsName((string) $raw), 0, 40);
            if ($sn === '') {
                continue;
            }
            if (! $this->outGoodsSnTaken($sn)) {
                return $sn;
            }
        }

        return mb_substr($this->sanitizeGoodsName($fallback).'-'.time(), 0, 40);
    }

    private function outGoodsSnTaken(string $sn): bool
    {
        foreach (['bg.local.goods.out.sn.check', 'temu.local.goods.out.sn.check'] as $type) {
            $data = $this->temuCallBody([
                'type' => $type,
                'outGoodsSnList' => [$sn],
                'language' => 'en',
            ], 30);
            $list = $data['result']['resultList'] ?? $data['result']['list'] ?? [];
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                if (is_array($row) && ! empty($row['isDuplicate'])) {
                    return true;
                }
            }
            if ($data['success'] ?? false) {
                return false;
            }
        }

        return $this->siblingGoodsId($sn) !== '';
    }

    /**
     * @return array{goodsId: string, skuId: string}|null
     */
    private function remoteSkuDuplicate(string $sku): ?array
    {
        foreach (['bg.local.goods.sku.out.sn.check', 'temu.local.goods.sku.out.sn.check'] as $type) {
            $data = $this->temuCallBody([
                'type' => $type,
                'outSkuSnList' => [$sku],
                'language' => 'en',
            ], 30);
            $list = $data['result']['resultList'] ?? $data['result']['list'] ?? [];
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                if (! is_array($row) || empty($row['isDuplicate'])) {
                    continue;
                }
                $goodsId = trim((string) ($row['duplicateGoodsId'] ?? $row['goodsId'] ?? ''));
                $skuId = trim((string) ($row['duplicateSkuId'] ?? $row['skuId'] ?? ''));
                if ($goodsId !== '') {
                    return ['goodsId' => $goodsId, 'skuId' => $skuId];
                }
            }
        }

        return null;
    }

    /**
     * @return 'active'|'inactive'|'incomplete'|'deleted'|'draft'|'unknown'
     */
    private function remoteGoodsLifecycle(string $goodsId): string
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return 'unknown';
        }

        foreach (['bg.local.goods.publish.status.get', 'temu.local.goods.publish.status.get'] as $type) {
            $data = $this->temuCallBody([
                'type' => $type,
                'goodsIdList' => [(int) $goodsId],
                'language' => 'en',
            ], 30);
            $list = $data['result']['goodsPublishStatusList']
                ?? $data['result']['publishStatusList']
                ?? $data['result']['list']
                ?? [];
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['goodsId'] ?? $row['productId'] ?? ''));
                if ($id !== '' && $id !== $goodsId) {
                    continue;
                }
                $life = $this->lifecycleFromStatusRow($row);
                if ($life !== 'unknown') {
                    return $life;
                }
            }
        }

        foreach ([6 => 'deleted', 5 => 'draft', 4 => 'incomplete', 1 => 'active'] as $searchType => $life) {
            $data = $this->temuCallBody([
                'type' => 'bg.local.goods.list.query',
                'goodsSearchType' => $searchType,
                'goodsStatusFilterType' => $searchType,
                'goodsIdList' => [(int) $goodsId],
                'pageSize' => 5,
                'pageNumber' => 1,
                'pageNo' => 1,
                'language' => 'en',
            ], 30);
            $goodsList = $data['result']['goodsList'] ?? [];
            if (! is_array($goodsList)) {
                continue;
            }
            foreach ($goodsList as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (trim((string) ($row['goodsId'] ?? '')) === $goodsId) {
                    return $life;
                }
            }
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'active'|'inactive'|'incomplete'|'deleted'|'draft'|'unknown'
     */
    private function lifecycleFromStatusRow(array $row): string
    {
        $status = (int) ($row['status'] ?? $row['publishStatus'] ?? $row['status4VO'] ?? 0);
        $sub = (int) ($row['subStatus'] ?? $row['subStatus4VO'] ?? 0);
        foreach ([$status, $sub] as $code) {
            if (in_array($code, [4401, 6], true)) {
                return 'deleted';
            }
            if (in_array($code, [1101, 1102], true) || $code === 1) {
                return 'active';
            }
            if ($code >= 2200 && $code < 2300) {
                return 'inactive';
            }
            if ($code >= 3300 && $code < 3400) {
                return 'incomplete';
            }
            if ($code === 5) {
                return 'draft';
            }
        }

        $label = strtolower(trim((string) ($row['statusName'] ?? $row['goodsStatus'] ?? $row['statusDesc'] ?? '')));
        if ($label === '') {
            return 'unknown';
        }
        if (str_contains($label, 'delet') || str_contains($label, 'recycle')) {
            return 'deleted';
        }
        if (str_contains($label, 'draft')) {
            return 'draft';
        }
        if (str_contains($label, 'incomplete')) {
            return 'incomplete';
        }
        if (str_contains($label, 'inactive') || str_contains($label, 'off sale') || str_contains($label, 'not on sale')) {
            return 'inactive';
        }
        if (str_contains($label, 'active') || str_contains($label, 'on sale')) {
            return 'active';
        }

        return 'unknown';
    }

    private function restoreDeletedGoods(string $goodsId): bool
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return false;
        }

        $attempts = [
            ['type' => 'temu.local.goods.recycle.recover', 'goodsIdList' => [(int) $goodsId], 'language' => 'en'],
            ['type' => 'bg.local.goods.recycle.recover', 'goodsIdList' => [(int) $goodsId], 'language' => 'en'],
            ['type' => 'temu.local.goods.recover', 'goodsId' => (int) $goodsId, 'language' => 'en'],
            ['type' => 'bg.local.goods.recover', 'goodsId' => (int) $goodsId, 'language' => 'en'],
            ['type' => 'bg.local.goods.sale.status.set', 'goodsId' => (int) $goodsId, 'onsale' => true, 'language' => 'en'],
            ['type' => 'bg.local.goods.sale.status.set', 'goodsIdList' => [(int) $goodsId], 'status' => 1, 'language' => 'en'],
        ];
        foreach ($attempts as $body) {
            $data = $this->temuCallBody($body, 45);
            if (! ($data['success'] ?? false)) {
                Log::info('Temu2 restore deleted goods attempt failed', [
                    'type' => $body['type'] ?? '',
                    'goods_id' => $goodsId,
                    'message' => $this->apiErrorMessage($data),
                ]);
                continue;
            }
            $life = $this->remoteGoodsLifecycle($goodsId);
            if ($life !== 'deleted') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{goodsId: string, skuId: string}  $remote
     */
    private function freeDeletedOutSkuSn(string $sku, array $remote): void
    {
        $skuId = trim((string) ($remote['skuId'] ?? ''));
        $newSn = mb_substr($this->skuNorm($sku).'-X'.substr(md5($sku.microtime(true)), 0, 6), 0, 40);
        $attempts = [];
        if ($skuId !== '') {
            $attempts[] = ['type' => 'bg.local.goods.sku.out.sn.set', 'skuId' => (int) $skuId, 'outSkuSn' => $newSn, 'language' => 'en'];
            $attempts[] = ['type' => 'temu.local.goods.sku.out.sn.set', 'skuId' => (int) $skuId, 'outSkuSn' => $newSn, 'language' => 'en'];
        }
        $attempts[] = ['type' => 'bg.local.goods.sku.out.sn.set', 'outSkuSn' => $sku, 'newOutSkuSn' => $newSn, 'language' => 'en'];
        $attempts[] = ['type' => 'temu.local.goods.sku.out.sn.set', 'outSkuSn' => $sku, 'newOutSkuSn' => $newSn, 'language' => 'en'];

        foreach ($attempts as $body) {
            $data = $this->temuCallBody($body, 30);
            if ($data['success'] ?? false) {
                Log::info('Temu2 freed deleted outSkuSn so the SKU can be republished', [
                    'sku' => $sku,
                    'new_sn' => $newSn,
                    'type' => $body['type'] ?? '',
                ]);

                return;
            }
            Log::info('Temu2 free deleted outSkuSn attempt failed', [
                'sku' => $sku,
                'type' => $body['type'] ?? '',
                'message' => $this->apiErrorMessage($data),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $skuList
     * @return list<array<string, mixed>>
     */
    private function withUniqueOutSkuSns(array $skuList): array
    {
        foreach ($skuList as $index => $item) {
            $current = trim((string) ($item['outSkuSn'] ?? $item['externalSkuId'] ?? ''));
            if ($current === '') {
                continue;
            }
            $unique = $this->uniqueOutSkuSn($current);
            $skuList[$index]['outSkuSn'] = $unique;
            $skuList[$index]['externalSkuId'] = $unique;
        }

        return $skuList;
    }

    private function uniqueOutSkuSn(string $sku): string
    {
        $base = mb_substr($this->sanitizeGoodsName($sku), 0, 32);
        if ($base === '') {
            $base = 'SKU';
        }
        foreach ([$sku, $base.'-'.substr(md5($sku.microtime(true)), 0, 6)] as $candidate) {
            $sn = mb_substr($this->sanitizeGoodsName((string) $candidate), 0, 40);
            if ($sn !== '' && ! $this->outSkuSnTaken($sn)) {
                return $sn;
            }
        }

        return mb_substr($base.'-'.time(), 0, 40);
    }

    private function outSkuSnTaken(string $sn): bool
    {
        $dup = $this->remoteSkuDuplicate($sn);

        return $dup !== null;
    }

    private function markLocalListingStatus(string $sku, string $status): void
    {
        try {
            if (! Schema::hasTable($this->metricsTable()) || ! Schema::hasColumn($this->metricsTable(), 'listing_status')) {
                return;
            }
            $keys = $this->skuLookupKeys($sku);
            if ($keys === []) {
                return;
            }
            $this->metricClass()::query()->whereIn('sku', $keys)->update(['listing_status' => $status]);
        } catch (\Throwable) {
        }
    }

    private function isDuplicateSkuError(string $message): bool
    {
        return str_contains($message, '100010050')
            || stripos($message, 'sku duplicated') !== false
            || (stripos($message, 'outskusn') !== false && stripos($message, 'duplicat') !== false);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function temuCallBody(array $body, int $timeout = 60): array
    {
        $type = (string) ($body['type'] ?? '');
        unset($body['type']);

        return $this->api->callOpenApi($type, $body, $timeout);
    }

    private function persistExtraListingFields(string $sku, float $price, int $qty): void
    {
        try {
            $update = [];
            if (Schema::hasColumn($this->metricsTable(), 'base_price')) {
                $update['base_price'] = $price;
            }
            if (Schema::hasColumn($this->metricsTable(), 'quantity')) {
                $update['quantity'] = $qty;
            }
            if ($update !== []) {
                $this->metricClass()::query()->where('sku', $sku)->update($update);
            }
        } catch (\Throwable $e) {
            Log::warning('Temu2 persist extra listing fields failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function forgetListingCaches(): void
    {
        try {
            Cache::forget(ListingChannelCounts::TOTAL_CACHE_KEY);
            foreach ($this->listingCountCacheKeys() as $key) {
                Cache::forget($key);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function apiErrorMessage(array $data): string
    {
        $code = trim((string) ($data['errorCode'] ?? ''));
        $msg = trim((string) ($data['errorMsg'] ?? $data['message'] ?? ''));
        if ($code === '' && $msg === '') {
            return '';
        }

        return trim($code.($code !== '' && $msg !== '' ? ': ' : '').$msg);
    }

    private function shopifyRow(string $sku): ?ShopifySku
    {
        return ShopifySku::mapByProductSkus([$sku])->get($sku);
    }

    private function metricRow(string $sku): ?object
    {
        if (! Schema::hasTable($this->metricsTable())) {
            return null;
        }

        $keys = $this->skuLookupKeys($sku);
        if ($keys === []) {
            return null;
        }

        return $this->metricClass()::query()->whereIn('sku', $keys)->first();
    }
}
