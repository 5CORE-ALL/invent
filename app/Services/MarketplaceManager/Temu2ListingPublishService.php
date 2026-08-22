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

    public function __construct(private Temu2ApiService $api)
    {
    }

    /**
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, skus?: list<string>}
     */
    public function publish(string $sku): array
    {
        return $this->publishSkus([$sku], true);
    }

    /**
     * Group seed SKUs by product-master parent and list Missing L siblings that would be published.
     *
     * @param  list<string>  $seedSkus
     * @return array{success: bool, groups: list<array<string, mixed>>}
     */
    public function previewFromSkus(array $seedSkus): array
    {
        $groups = [];
        foreach ($this->expandSeedSkusToParentGroups($seedSkus) as $parent => $children) {
            $groups[] = $this->formatPreviewGroup($parent, $children);
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
    public function publishSkus(array $skus, bool $expandSiblings = true): array
    {
        $skus = $this->uniqueTrimmedSkus($skus);
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        if (! $this->api->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Temu 2 API credentials missing. Set TEMU2_APP_KEY, TEMU2_SECRET_KEY, and TEMU2_ACCESS_TOKEN.',
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
            return ['success' => false, 'message' => 'No Missing L child SKUs left to publish (already listed, NRL, or parent rows were skipped).'];
        }

        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(function ($row) {
                return (string) $row->sku;
            });

        $primarySku = $skus[0];
        $primary = $products->get($primarySku);
        if (! $primary) {
            return ['success' => false, 'message' => 'SKU not found in product master.'];
        }

        $parentKey = $this->groupKeyForProduct($primary);
        $title = $this->resolveTitle($primary, $primarySku);
        if ($title === '') {
            return ['success' => false, 'message' => 'No Title Master title found. Set title80/title100/title150 on Title Master (not the SKU).'];
        }
        $description = $this->resolveDescription($primary, $primarySku);
        if ($description === '') {
            return ['success' => false, 'message' => 'No Description Master text found. Set description_1500 or Description Master for this SKU (not the SKU code).'];
        }

        $gallerySources = [];
        $prepared = [];
        foreach ($skus as $sku) {
            $product = $products->get($sku);
            if (! $product) {
                return ['success' => false, 'message' => 'SKU not found in product master: '.$sku];
            }
            $price = $this->resolvePrice($sku);
            if ($price === null || $price <= 0) {
                return ['success' => false, 'message' => 'No Std Prc found for '.$sku.'. Set Std Prc on Temu 2 Analytics (/temu2-decrease).'];
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
                'dimensions' => $this->resolveDimensions($product),
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
            return ['success' => false, 'message' => 'Failed to upload images to Temu 2. Check TEMU2 credentials and image URLs.'];
        }
        while (count($hostedImages) < 3) {
            $hostedImages[] = $hostedImages[0];
        }
        $hostedImages = array_slice($hostedImages, 0, self::MAX_IMAGES);

        $catId = $this->resolveCatId($primarySku, $title, $hostedImages[0]);
        if ($catId === null) {
            return ['success' => false, 'message' => 'Could not resolve a Temu leaf category. Set temu2_pricing.category_id or check category recommend API access.'];
        }

        $costTemplateId = $this->resolveCostTemplateId();
        if ($costTemplateId === '') {
            return ['success' => false, 'message' => 'No Temu 2 shipping template found. Create one in Seller Center or set TEMU2_COST_TEMPLATE_ID.'];
        }

        $currency = strtoupper(trim((string) config('services.temu2.currency', 'USD'))) ?: 'USD';
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
        $shipmentLimitDay = max(1, (int) config('services.temu2.shipment_limit_day', 2));
        $goodsOriginInfo = $this->buildGoodsOriginInfo();
        $outGoodsSn = mb_substr($this->sanitizeGoodsName($parentKey !== '' ? $parentKey : $primarySku), 0, 50);
        if ($outGoodsSn === '') {
            $outGoodsSn = $primarySku;
        }

        $payloadV2 = [
            'type' => (string) config('services.temu2.goods_add_type', 'temu.local.goods.v2.add'),
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
                $fallbackMsg = $this->apiErrorMessage($fallback['data'] ?? []);
                Log::warning('Temu2 publish add failed', [
                    'parent' => $parentKey,
                    'skus' => $skus,
                    'v2' => $msg,
                    'v1' => $fallbackMsg,
                ]);

                return [
                    'success' => false,
                    'message' => $msg !== '' ? $msg : 'Temu 2 add-goods API rejected the listing.',
                ];
            }
        }

        $goodsId = (string) ($data['result']['goodsId'] ?? '');
        if ($goodsId === '') {
            return ['success' => false, 'message' => 'Temu 2 add succeeded but returned no goodsId.'];
        }

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
            $info = $infoBySku[strtoupper($row['sku'])] ?? (($data['result']['skuInfoList'][$index] ?? []) ?: []);
            $skuId = trim((string) ($info['skuId'] ?? ''));
            if ($firstSkuId === '' && $skuId !== '') {
                $firstSkuId = $skuId;
            }
            $this->api->persistNewListing($row['sku'], $goodsId, $skuId !== '' ? $skuId : null);
            $this->persistExtraListingFields($row['sku'], $row['price'], $row['qty']);
        }
        $this->forgetListingCaches();

        Log::info('Temu2 publish: listed', [
            'parent' => $parentKey,
            'skus' => $skus,
            'goods_id' => $goodsId,
        ]);

        $count = count($skus);

        return [
            'success' => true,
            'message' => $count > 1
                ? 'Published '.$count.' variations of '.$parentKey.' to Temu 2.'
                : 'Published to Temu 2.',
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
            $key = strtoupper($sku);
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = $sku;
        }

        return array_values($out);
    }

    /**
     * @param  list<string>  $seedSkus
     * @return array<string, \Illuminate\Support\Collection<int, ProductMaster>>
     */
    private function expandSeedSkusToParentGroups(array $seedSkus): array
    {
        $seeds = $this->uniqueTrimmedSkus($seedSkus);
        if ($seeds === []) {
            return [];
        }

        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $seeds)
            ->get();

        $parentKeys = [];
        foreach ($products as $product) {
            $parentKeys[$this->groupKeyForProduct($product)] = true;
        }

        $groups = [];
        foreach (array_keys($parentKeys) as $parent) {
            $children = ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('parent', $parent)
                ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
                ->orderBy('sku')
                ->get();

            if ($children->isEmpty()) {
                $children = $products
                    ->filter(function ($product) use ($parent) {
                        $sku = (string) $product->sku;

                        return $this->groupKeyForProduct($product) === $parent
                            && stripos($sku, 'PARENT') === false;
                    })
                    ->values();
            }

            if ($children->isNotEmpty()) {
                $groups[$parent] = $children;
            }
        }

        return $groups;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductMaster>  $children
     * @return array<string, mixed>
     */
    private function formatPreviewGroup(string $parent, $children): array
    {
        $rows = [];
        $publishSkus = [];
        foreach ($children as $product) {
            $sku = trim((string) $product->sku);
            $classified = $this->classifyChildSku($sku);
            $rows[] = [
                'sku' => $sku,
                'spec' => $sku,
                'inv' => $this->resolveQty($sku),
                'status' => $classified['status'],
                'reason' => $classified['reason'],
            ];
            if (in_array($classified['status'], ['will_publish', 'skipped_no_price'], true)) {
                $publishSkus[] = $sku;
            }
        }

        return [
            'parent' => $parent,
            'children' => $rows,
            'publish_skus' => $publishSkus,
            'publish_count' => count($publishSkus),
        ];
    }

    /**
     * @return array{status: string, reason: string}
     */
    private function classifyChildSku(string $sku): array
    {
        if (stripos($sku, 'PARENT') !== false) {
            return ['status' => 'skipped_parent', 'reason' => 'Parent row'];
        }
        if ($this->localGoodsId($sku) !== '') {
            return ['status' => 'skipped_listed', 'reason' => 'Already listed'];
        }
        if ($this->nrReqForSku($sku) === 'NR') {
            return ['status' => 'skipped_nrl', 'reason' => 'NRL'];
        }
        $price = $this->resolvePrice($sku);
        if ($price === null || $price <= 0) {
            return ['status' => 'skipped_no_price', 'reason' => 'No Std Prc'];
        }
        $product = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first();
        if (! $product) {
            return ['status' => 'skipped_missing', 'reason' => 'Not in product master'];
        }
        if ($this->resolveSourceImages($product, $sku) === []) {
            return ['status' => 'skipped_no_image', 'reason' => 'No Image Master photos'];
        }

        return ['status' => 'will_publish', 'reason' => ''];
    }

    /**
     * @param  list<string>  $skus
     * @return list<string>
     */
    private function filterPublishableSkus(array $skus): array
    {
        $out = [];
        $parent = null;
        foreach ($this->uniqueTrimmedSkus($skus) as $sku) {
            $status = $this->classifyChildSku($sku)['status'] ?? '';
            if (! in_array($status, ['will_publish', 'skipped_no_price'], true)) {
                continue;
            }
            $product = ProductMaster::query()
                ->whereNull('deleted_at')
                ->where('sku', $sku)
                ->first();
            if (! $product) {
                continue;
            }
            $key = $this->groupKeyForProduct($product);
            if ($parent === null) {
                $parent = $key;
            }
            if ($key !== $parent) {
                continue;
            }
            $out[] = $sku;
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
        if (! Schema::hasTable('temu2_metrics')) {
            return '';
        }

        $goodsId = Temu2Metric::query()
            ->where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->value('goods_id');

        return trim((string) $goodsId);
    }

    private function nrReqForSku(string $sku): string
    {
        try {
            $nrValues = ListingCountsEngine::loadNrValues(Temu2DataView::class, [$sku]);

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

        try {
            $ebay = EbayMetric::query()->whereIn('sku', $this->skuLookupKeys($sku))->first();
            $clean = $this->sanitizeGoodsName((string) ($ebay?->ebay_title ?? ''));
            if ($clean !== '' && ! $this->looksLikeSku($clean, $sku)) {
                return $clean;
            }
        } catch (\Throwable) {
        }

        $shopify = $this->shopifyRow($sku);
        $clean = $this->sanitizeGoodsName((string) ($shopify?->product_title ?? ''));
        if ($clean !== '' && ! $this->looksLikeSku($clean, $sku)) {
            return $clean;
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
        $metric = $this->metricRow($sku);
        $candidates = [
            $metric?->description_master,
            $product->description_1500 ?? null,
            $product->product_description ?? null,
            $product->description_1000 ?? null,
            $product->description_800 ?? null,
            $product->description_600 ?? null,
        ];

        $parent = $this->parentProductFor($product, $sku);
        if ($parent) {
            $candidates = array_merge($candidates, [
                $parent->description_1500 ?? null,
                $parent->product_description ?? null,
                $parent->description_1000 ?? null,
                $parent->description_800 ?? null,
                $parent->description_600 ?? null,
            ]);
        }

        foreach ($candidates as $raw) {
            $text = $this->cleanDescriptionText((string) $raw, $sku);
            if ($text !== '') {
                return $text;
            }
        }

        $fromOther = $this->descriptionFromOtherMarketplaces($sku);
        if ($fromOther !== '') {
            return $fromOther;
        }

        return '';
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

    private function descriptionFromOtherMarketplaces(string $sku): string
    {
        $keys = $this->skuLookupKeys($sku);
        if ($keys === []) {
            return '';
        }

        foreach (ProductMasterMarketplaceMaps::descriptionTableMap() as $table) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'description_master')) {
                    continue;
                }
                $raw = DB::table($table)->whereIn('sku', $keys)->value('description_master');
                $text = $this->cleanDescriptionText((string) $raw, $sku);
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
        $bullets = [];
        foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
            $line = trim(strip_tags((string) ($product->{$col} ?? '')));
            if ($line !== '') {
                $bullets[] = mb_substr($line, 0, 200);
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

        if (Schema::hasTable('temu2_pricing')) {
            Temu2Pricing::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['sku', 'base_price'])
                ->each(function ($row) use ($remember) {
                    $remember((string) $row->sku, $row->base_price);
                });
        }

        if (Schema::hasTable('temu2_metrics')) {
            Temu2Metric::query()
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
                    ? 'Temu 2 image upload failed: '.$msg
                    : 'Temu 2 image upload failed.',
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

        return 'Temu 2 blocked this computer (NOT_IN_IP_WHITE_LIST).'.$ipBit
            .' Open partner.temu.com → the Temu 2 app → IP whitelist, add that IP, wait about a minute, then click Publish again.'
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
        if (Schema::hasTable('temu2_pricing')) {
            $raw = Temu2Pricing::query()->where('sku', $sku)->value('category_id');
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
        $origin1 = $this->normalizeOriginRegion1((string) config('services.temu2.origin_region1', 'China'));
        $isChina = strcasecmp($origin1, 'China') === 0;

        $info = [
            'importDesignation' => $this->normalizeImportDesignation((string) config('services.temu2.import_designation', 'Imported')),
            'originRegion1' => $origin1,
        ];
        if ($isChina) {
            $origin2 = trim((string) config('services.temu2.origin_region2', 'Guangdong'));
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
        $configured = trim((string) config('services.temu2.cost_template_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $cached = trim((string) Cache::get('temu2_cost_template_id_v1', ''));
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
                    Cache::put('temu2_cost_template_id_v1', $id, now()->addHour());

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
     * Dim / Wt Items on product_master.Values (inches + lb) converted for Temu packageInfo.
     *
     * @return array{weight: string, length: string, width: string, height: string, weightUnit: string, volumeUnit: string}
     */
    private function resolveDimensions(ProductMaster $product): array
    {
        $values = is_array($product->Values) ? $product->Values : [];
        $num = static function (array $keys) use ($values): ?float {
            foreach ($keys as $key) {
                if (isset($values[$key]) && is_numeric($values[$key]) && (float) $values[$key] > 0) {
                    return (float) $values[$key];
                }
            }

            return null;
        };

        $lengthIn = $num(['l_decl', 'l', 'L', 'length']);
        $widthIn = $num(['w_decl', 'w', 'W', 'width']);
        $heightIn = $num(['h_decl', 'h', 'H', 'height']);
        $weightLb = $num(['wt_decl', 'wt_act', 'weight_lb', 'wt']);
        $weightKg = $num(['wt_act_kg', 'weight_kg', 'ctn_weight_kg']);

        $toCm = static function (?float $inches): string {
            if ($inches === null) {
                return '1';
            }

            return (string) max(1, round($inches * 2.54, 2));
        };

        $weightG = '1';
        if ($weightKg !== null) {
            $weightG = (string) max(1, round($weightKg * 1000, 2));
        } elseif ($weightLb !== null) {
            $weightG = (string) max(1, round($weightLb * 453.592, 2));
        }

        return [
            'weight' => $weightG,
            'length' => $toCm($lengthIn),
            'width' => $toCm($widthIn),
            'height' => $toCm($heightIn),
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

        foreach ([Temu2Metric::class, TemuMetric::class] as $class) {
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
            if (Schema::hasColumn('temu2_metrics', 'base_price')) {
                $update['base_price'] = $price;
            }
            if (Schema::hasColumn('temu2_metrics', 'quantity')) {
                $update['quantity'] = $qty;
            }
            if ($update !== []) {
                Temu2Metric::query()->where('sku', $sku)->update($update);
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
            Cache::forget('listing_channel_counts_v1:temu2');
            Cache::forget('listing_channel_counts_v1:temutwo');
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

    private function metricRow(string $sku): ?Temu2Metric
    {
        if (! Schema::hasTable('temu2_metrics')) {
            return null;
        }

        return Temu2Metric::query()
            ->where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->first();
    }
}
