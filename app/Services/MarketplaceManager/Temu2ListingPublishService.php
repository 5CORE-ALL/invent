<?php

namespace App\Services\MarketplaceManager;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Temu2DataView;
use App\Models\Temu2Metric;
use App\Models\Temu2Pricing;
use App\Services\Temu2ApiService;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Publish a Missing L SKU to Temu 2 via Open API (not Seller Center).
 */
class Temu2ListingPublishService
{
    public function __construct(private Temu2ApiService $api)
    {
    }

    /**
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string}
     */
    public function publish(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        if (stripos($sku, 'PARENT') !== false) {
            return ['success' => false, 'message' => 'Parent rows cannot be published.'];
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

        $existingGoodsId = $this->localGoodsId($sku);
        if ($existingGoodsId !== '') {
            return [
                'success' => true,
                'message' => 'Already listed on Temu 2.',
                'goods_id' => $existingGoodsId,
            ];
        }

        $product = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->first();
        if (! $product) {
            return ['success' => false, 'message' => 'SKU not found in product master.'];
        }

        $nrReq = $this->nrReqForSku($sku);
        if ($nrReq === 'NR') {
            return ['success' => false, 'message' => 'This SKU is NRL on Temu 2 Data View. Only REQ Missing L SKUs can be published.'];
        }

        $title = $this->resolveTitle($product, $sku);
        if ($title === '') {
            return ['success' => false, 'message' => 'No title found (product_master title80/title100/title150 or Shopify product_title).'];
        }

        $price = $this->resolvePrice($sku);
        if ($price === null || $price <= 0) {
            return ['success' => false, 'message' => 'No price found. Set temu2_pricing.base_price or Shopify b2c_price.'];
        }

        $sourceImages = $this->resolveSourceImages($product, $sku);
        if ($sourceImages === []) {
            return ['success' => false, 'message' => 'No images found on product master (main_image / image1–image7) or temu2_metrics.'];
        }

        $upload = $this->uploadImages($sourceImages);
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
        $hostedImages = array_slice($hostedImages, 0, 10);

        $catId = $this->resolveCatId($sku, $title, $hostedImages[0]);
        if ($catId === null) {
            return ['success' => false, 'message' => 'Could not resolve a Temu leaf category. Set temu2_pricing.category_id or check category recommend API access.'];
        }

        $costTemplateId = $this->resolveCostTemplateId();
        if ($costTemplateId === '') {
            return ['success' => false, 'message' => 'No Temu 2 shipping template found. Create one in Seller Center or set TEMU2_COST_TEMPLATE_ID.'];
        }

        $spec = $this->resolveSpecDetails($catId, $sku);
        if ($spec === []) {
            return ['success' => false, 'message' => 'Could not resolve a Temu variation (spec) for this category. Check temu.local.product.variation.get permission.'];
        }

        $qty = $this->resolveQty($sku);
        $currency = strtoupper(trim((string) config('services.temu2.currency', 'USD'))) ?: 'USD';
        $baseAmount = number_format($price, 2, '.', '');
        $listAmount = number_format(round($price * 1.2, 2), 2, '.', '');
        if ((float) $listAmount <= (float) $baseAmount) {
            $listAmount = number_format($price + 1, 2, '.', '');
        }

        $goodsProperty = $this->buildGoodsProperty($catId);
        $dimensions = $this->api->getProductDimensions($sku);
        $shipmentLimitDay = max(1, (int) config('services.temu2.shipment_limit_day', 2));
        $importDesignation = trim((string) config('services.temu2.import_designation', '4'));

        $payloadV2 = [
            'type' => (string) config('services.temu2.goods_add_type', 'temu.local.goods.v2.add'),
            'language' => 'en',
            'goodsBasic' => [
                'catId' => $catId,
                'goodsName' => $title,
                'externalGoodsId' => $sku,
                'outGoodsSn' => $sku,
                'goodsDesc' => $this->resolveDescription($product, $sku),
                'bulletPoints' => $this->resolveBullets($product, $sku),
                'brand' => ['noTrademark' => true],
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
            'goodsOriginInfo' => [
                'importDesignation' => is_numeric($importDesignation) ? (int) $importDesignation : $importDesignation,
            ],
            'skuList' => [[
                'externalSkuId' => $sku,
                'outSkuSn' => $sku,
                'quantity' => $qty,
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
            ]],
        ];

        if ($goodsProperty !== []) {
            $payloadV2['goodsProperty'] = $goodsProperty;
        }

        Log::info('Temu2 publish: sending add goods', [
            'sku' => $sku,
            'catId' => $catId,
            'title' => $title,
            'price' => $baseAmount,
            'qty' => $qty,
            'images' => count($hostedImages),
            'type' => $payloadV2['type'],
        ]);

        $data = $this->temuCallBody($payloadV2, 120);
        if (! ($data['success'] ?? false)) {
            $fallback = $this->addViaV1($payloadV2, $spec, $dimensions, $hostedImages, $sku, $qty, $baseAmount, $listAmount, $currency);
            if ($fallback['success'] ?? false) {
                $data = $fallback['data'];
            } else {
                $msg = $this->apiErrorMessage($data);
                $fallbackMsg = $this->apiErrorMessage($fallback['data'] ?? []);
                Log::warning('Temu2 publish add failed', [
                    'sku' => $sku,
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
        $skuId = (string) (($data['result']['skuInfoList'][0]['skuId'] ?? '') ?: '');
        if ($goodsId === '') {
            return ['success' => false, 'message' => 'Temu 2 add succeeded but returned no goodsId.'];
        }

        $this->api->persistNewListing($sku, $goodsId, $skuId !== '' ? $skuId : null);
        $this->persistExtraListingFields($sku, $price, $qty);
        $this->forgetListingCaches();

        Log::info('Temu2 publish: listed', [
            'sku' => $sku,
            'goods_id' => $goodsId,
            'sku_id' => $skuId,
        ]);

        return [
            'success' => true,
            'message' => 'Published to Temu 2.',
            'goods_id' => $goodsId,
            'sku_id' => $skuId !== '' ? $skuId : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payloadV2
     * @param  list<array<string, mixed>>  $spec
     * @param  array<string, string>  $dimensions
     * @param  list<string>  $hostedImages
     * @return array{success: bool, data?: array<string, mixed>}
     */
    private function addViaV1(
        array $payloadV2,
        array $spec,
        array $dimensions,
        array $hostedImages,
        string $sku,
        int $qty,
        string $baseAmount,
        string $listAmount,
        string $currency
    ): array {
        $specIds = [];
        foreach ($spec as $row) {
            if (isset($row['specId']) && $row['specId'] !== '' && $row['specId'] !== null) {
                $specIds[] = (int) $row['specId'];
            }
        }

        $v1 = $payloadV2;
        $v1['type'] = 'bg.local.goods.add';
        $v1['version'] = 'V1';
        if (! empty($payloadV2['goodsProperty']) && is_array($payloadV2['goodsProperty'])) {
            $first = $payloadV2['goodsProperty'][0] ?? null;
            if (is_array($first) && ! isset($payloadV2['goodsProperty']['goodsProperties'])) {
                $v1['goodsProperty'] = ['goodsProperties' => $payloadV2['goodsProperty']];
            }
        }

        $v1['skuList'] = [[
            'externalSkuId' => $sku,
            'outSkuSn' => $sku,
            'quantity' => $qty,
            'specIdList' => $specIds,
            'images' => $hostedImages,
            'weight' => (string) ($dimensions['weight'] ?? '1'),
            'length' => (string) ($dimensions['length'] ?? '1'),
            'width' => (string) ($dimensions['width'] ?? '1'),
            'height' => (string) ($dimensions['height'] ?? '1'),
            'weightUnit' => (string) ($dimensions['weightUnit'] ?? 'g'),
            'volumeUnit' => (string) ($dimensions['volumeUnit'] ?? 'cm'),
            'price' => [
                'listPriceType' => 0,
                'basePrice' => ['amount' => $baseAmount, 'currency' => $currency],
                'listPrice' => ['amount' => $listAmount, 'currency' => $currency],
            ],
        ]];

        $data = $this->temuCallBody($v1, 120);

        return [
            'success' => (bool) ($data['success'] ?? false),
            'data' => $data,
        ];
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
        $candidates = [
            $product->title80 ?? null,
            $product->title100 ?? null,
            $product->title150 ?? null,
            $product->title60 ?? null,
        ];
        $shopify = $this->shopifyRow($sku);
        if ($shopify) {
            $candidates[] = $shopify->product_title ?? null;
        }

        foreach ($candidates as $raw) {
            $clean = $this->sanitizeGoodsName((string) $raw);
            if ($clean !== '') {
                return $clean;
            }
        }

        return $this->sanitizeGoodsName($sku);
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
            $product->product_description ?? null,
            $product->description_800 ?? null,
            $product->description_600 ?? null,
            $product->description_1000 ?? null,
            $metric?->goods_desc,
            $metric?->description_master,
        ];
        foreach ($candidates as $raw) {
            $text = trim(strip_tags((string) $raw));
            if ($text !== '') {
                return mb_substr($text, 0, 2000);
            }
        }

        return $this->resolveTitle($product, $sku);
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
        if (Schema::hasTable('temu2_pricing')) {
            $p = Temu2Pricing::query()->where('sku', $sku)->value('base_price');
            if ($p !== null && (float) $p > 0) {
                return (float) $p;
            }
        }

        $metricPrice = $this->api->getProductPrice($sku);
        if ($metricPrice !== null && $metricPrice > 0) {
            return $metricPrice;
        }

        $shopify = $this->shopifyRow($sku);
        if ($shopify) {
            foreach (['b2c_price', 'price'] as $col) {
                $v = $shopify->{$col} ?? null;
                if ($v !== null && (float) $v > 0) {
                    return (float) $v;
                }
            }
        }

        return null;
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
        $cols = ['main_image', 'image1', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7'];
        foreach ($cols as $col) {
            $url = $this->toAbsoluteImageUrl((string) ($product->{$col} ?? ''));
            if ($url !== '' && ! isset($seen[$url])) {
                $urls[] = $url;
                $seen[$url] = true;
            }
        }

        if ($urls !== []) {
            return $urls;
        }

        $metric = $this->metricRow($sku);
        if (! $metric) {
            return [];
        }

        foreach ($this->decodeImageList($metric->image_master_json ?? null) as $url) {
            $url = $this->toAbsoluteImageUrl($url);
            if ($url !== '' && ! isset($seen[$url])) {
                $urls[] = $url;
                $seen[$url] = true;
            }
        }
        foreach ($this->decodeImageList($metric->image_urls ?? null) as $url) {
            $url = $this->toAbsoluteImageUrl($url);
            if ($url !== '' && ! isset($seen[$url])) {
                $urls[] = $url;
                $seen[$url] = true;
            }
        }

        return $urls;
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
        foreach (array_slice($sourceImages, 0, 5) as $url) {
            if ($this->isTemuHostedImageUrl($url)) {
                $hosted[] = $url;
                continue;
            }

            $res = $this->api->uploadTemuImageFromUrl($url);
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
     * @return list<array<string, mixed>>
     */
    private function buildGoodsProperty(int $catId): array
    {
        $data = $this->temuCallBody([
            'type' => 'temu.local.product.attributes.get',
            'catId' => $catId,
            'language' => 'en',
        ], 45);

        $props = $data['result']['properties']
            ?? $data['result']['propertyList']
            ?? $data['result']['goodsProperties']
            ?? $data['result']['inputPropertyList']
            ?? [];
        if (! is_array($props)) {
            return [];
        }

        $out = [];
        foreach ($props as $prop) {
            if (! is_array($prop)) {
                continue;
            }
            $required = $prop['required'] ?? $prop['isRequired'] ?? false;
            if (! $required) {
                continue;
            }
            $refPid = $prop['refPid'] ?? $prop['pid'] ?? $prop['templatePid'] ?? null;
            if ($refPid === null) {
                continue;
            }

            $entry = ['refPid' => (int) $refPid];
            if (isset($prop['pid'])) {
                $entry['pid'] = (int) $prop['pid'];
            }
            if (isset($prop['templatePid'])) {
                $entry['templatePid'] = (int) $prop['templatePid'];
            }

            $values = $prop['values'] ?? $prop['valueList'] ?? $prop['vidList'] ?? [];
            $picked = $this->pickAttributeValue($values);
            if ($picked !== null) {
                $entry['vid'] = (int) $picked['vid'];
                if ($picked['value'] !== '') {
                    $entry['value'] = $picked['value'];
                }
            } else {
                $controlType = (int) ($prop['controlType'] ?? $prop['control_type'] ?? 0);
                if (in_array($controlType, [0, 16, 19], true)) {
                    $entry['value'] = 'Generic';
                } else {
                    continue;
                }
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param  mixed  $values
     * @return array{vid: int, value: string}|null
     */
    private function pickAttributeValue(mixed $values): ?array
    {
        if (! is_array($values) || $values === []) {
            return null;
        }

        $preferred = null;
        foreach ($values as $row) {
            if (! is_array($row)) {
                continue;
            }
            $vid = (int) ($row['vid'] ?? $row['id'] ?? 0);
            if ($vid <= 0) {
                continue;
            }
            $value = trim((string) ($row['value'] ?? $row['specName'] ?? $row['name'] ?? ''));
            $lower = strtolower($value);
            if (in_array($lower, ['unbranded', 'generic', 'does not apply', 'none', 'other'], true)) {
                return ['vid' => $vid, 'value' => $value];
            }
            if ($preferred === null) {
                $preferred = ['vid' => $vid, 'value' => $value];
            }
        }

        return $preferred;
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
