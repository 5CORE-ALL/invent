<?php

namespace App\Services\MarketplaceManager;

use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Models\TemuPricing;
use App\Services\TemuApiService;
use App\Support\Marketplace\ListingManagerFamily;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Publish Missing L SKUs to Temu 1 via Open API, including independent (single) listings.
 */
class TemuListingPublishService extends Temu2ListingPublishService
{
    private const T1_MAX_IMAGES = 10;

    public function __construct(TemuApiService $api)
    {
        $this->api = $api;
    }

    protected function shopConfigKey(): string
    {
        return 'temu';
    }

    protected function shopLabel(): string
    {
        return 'Temu';
    }

    protected function credentialsHelp(): string
    {
        return 'Set TEMU_APP_KEY, TEMU_SECRET_KEY, and TEMU_ACCESS_TOKEN.';
    }

    protected function stdPriceHelp(): string
    {
        return 'Temu 1 Analytics (/new-temuone)';
    }

    protected function pricingTable(): string
    {
        return 'temu_pricing';
    }

    protected function metricsTable(): string
    {
        return 'temu_metrics';
    }

    protected function pricingClass(): string
    {
        return TemuPricing::class;
    }

    protected function metricClass(): string
    {
        return TemuMetric::class;
    }

    protected function dataViewClass(): string
    {
        return TemuDataView::class;
    }

    protected function costTemplateCacheKey(): string
    {
        return 'temu_cost_template_id_v1';
    }

    /**
     * @return list<string>
     */
    protected function listingCountCacheKeys(): array
    {
        return ['listing_channel_counts_v1:temu'];
    }

    // ---- Temu 1 only: add a variation to an existing parent goods. Temu 2 and Temu 3 do not use any of this. ----

    /**
     * Upload each source once and keep the source URL mapped to the hosted URL.
     *
     * @param  list<string>  $sourceImages
     * @return array{map: array<string, string>, error: ?string}
     */
    protected function uploadImageMap(array $sourceImages): array
    {
        // The browser/web server gives up on a request after about two minutes. Photos are uploaded one by one,
        // so a big variation group can run past that. Every uploaded photo is remembered, and when the time
        // budget is used up the publish stops with a clear "click Publish again" message instead of a 504.
        // The next click skips the photos that are already on Temu and carries on.
        $budgetSeconds = 70;
        $startedAt = microtime(true);
        $total = count(array_unique($sourceImages));

        $map = [];
        foreach ($sourceImages as $url) {
            if (isset($map[$url])) {
                continue;
            }
            if ($this->isTemuGoodsImageUrl($url)) {
                $map[$url] = $url;
                continue;
            }

            $cacheKey = 'temu1:image_upload:'.sha1($url);
            $cached = '';
            try {
                $cached = trim((string) \Illuminate\Support\Facades\Cache::get($cacheKey, ''));
            } catch (\Throwable $e) {
                $cached = '';
            }
            if ($cached !== '' && $this->isTemuGoodsImageUrl($cached)) {
                $map[$url] = $cached;
                continue;
            }

            if ((microtime(true) - $startedAt) > $budgetSeconds) {
                $done = count($map);
                Log::info('Temu publish: image upload paused, click Publish again to continue', ['uploaded' => $done, 'total' => $total]);

                return [
                    'map' => [],
                    'error' => 'Temu photo upload is taking long: '.$done.' of '.$total.' photos are uploaded and saved. '
                        .'Click Publish again - it continues from where it stopped and does not upload them twice.',
                ];
            }

            $res = $this->uploadImageToTemu2($url);
            $hosted = trim((string) ($res['url'] ?? ''));
            if (! empty($res['success']) && $hosted !== '' && $this->isTemuGoodsImageUrl($hosted) && ! $this->sameImageUrl($hosted, $url)) {
                $map[$url] = $hosted;
                try {
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $hosted, now()->addHours(12));
                } catch (\Throwable $e) {
                    // the cache only saves time
                }
                continue;
            }

            $msg = trim((string) ($res['message'] ?? ''));
            Log::warning('Temu2 publish image upload failed', [
                'url' => $url,
                'message' => $msg,
            ]);

            if ($this->isIpWhitelistMessage($msg)) {
                return ['map' => [], 'error' => $this->ipWhitelistUserMessage()];
            }

            // One photo Temu will not take is left out. The rest of this SKU's photos still go up.
        }

        if ($map === []) {
            return ['map' => [], 'error' => $this->shopLabel().' refused every image for this SKU.'];
        }

        return ['map' => $map, 'error' => null];
    }

    /**
     * goods.update only loads a direct kwcdn jpeg or png. A page link, a WebP, or our own site is not an image Temu can fetch.
     */
    protected function isTemuGoodsImageUrl(string $url): bool
    {
        if (! preg_match('#^https://#i', $url)) {
            return false;
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if (! str_contains($host, 'kwcdn.com')) {
            return false;
        }
        $path = strtolower((string) (parse_url(strtok($url, '?') ?: $url, PHP_URL_PATH) ?? ''));

        return (bool) preg_match('/\.(jpe?g|png)$/', $path);
    }

    /**
     * A picture already stored on a Temu goods. goods.update can load these when a newly uploaded link is refused.
     */
    private function isTemuCdnUrl(string $url): bool
    {
        if (! preg_match('#^https://#i', $url)) {
            return false;
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return str_contains($host, 'kwcdn.com');
    }

    protected function sameImageUrl(string $left, string $right): bool
    {
        $normalize = static function (string $url): string {
            return strtolower(rtrim(strtok($url, '?') ?: $url, '/'));
        };

        return $normalize($left) === $normalize($right);
    }

    /**
     * This SKU's own pictures for a variation update, up to the gallery limit.
     *
     * @param  list<mixed>  $preferred
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private function temuUpdateImages(array $preferred, array $fallback): array
    {
        $out = [];
        foreach ([$preferred, $fallback] as $list) {
            foreach ($list as $url) {
                $url = trim((string) $url);
                if ($url === '' || ! $this->isTemuGoodsImageUrl($url) || in_array($url, $out, true)) {
                    continue;
                }
                $out[] = $url;
                if (count($out) >= self::T1_MAX_IMAGES) {
                    return $out;
                }
            }
            if ($out !== []) {
                break;
            }
        }

        return $out;
    }

    /**
     * Temu wants at least three picture slots. Fewer than three real photos are repeated; a full gallery is left as it is.
     *
     * @param  list<string>  $images
     * @return list<string>
     */
    private function padTemuUpdateImages(array $images): array
    {
        $images = array_values(array_filter($images, static fn ($url) => trim((string) $url) !== ''));
        if ($images === []) {
            return [];
        }
        $unique = array_values(array_unique($images));
        while (count($images) < 3) {
            $images[] = $unique[0];
        }

        return array_slice($images, 0, self::T1_MAX_IMAGES);
    }

    private function t1CreateSpecId(int $catId, int $parentSpecId, string $sku, string $nameOverride = ''): ?int
    {
        if ($catId <= 0 || $parentSpecId <= 0) {
            return null;
        }
        $storedName = $nameOverride !== '' ? $nameOverride : ListingManagerFamily::attributesForSku($sku)['name'];
        $name = $this->sanitizeGoodsName($storedName !== '' ? $storedName : $sku);
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
        // A parent spec id is not a child spec. Sending it in specIdList is rejected (150011018).
        if ($specId <= 0 || $specId === $parentSpecId) {
            return null;
        }

        return $specId;
    }

    /**
     * Temu's own rows (bg.local.goods.sku.list.query) for every SKU under a goods, keyed by skuId.
     *
     * @return array<string, array<string, mixed>>
     */
    private function t1RemoteSkuRows(string $goodsId): array
    {
        $found = [];
        // 2 and 3 are the live lists. 1 and 4 cover a SKU Temu has accepted but not put on sale yet.
        foreach ([2, 3, 1, 4] as $searchType) {
            for ($page = 1; $page <= 10; $page++) {
                $res = $this->temuCallBody([
                    'type' => 'bg.local.goods.sku.list.query',
                    'searchText' => $goodsId,
                    'skuSearchType' => $searchType,
                    'pageNo' => $page,
                    'pageSize' => 100,
                ], 45);
                if (! ($res['success'] ?? false)) {
                    break;
                }
                $rows = (array) ($res['result']['skuList'] ?? []);
                foreach ($rows as $row) {
                    if (! is_array($row) || (string) ($row['goodsId'] ?? '') !== $goodsId) {
                        continue;
                    }
                    $skuId = (string) ($row['skuId'] ?? '');
                    if ($skuId === '' || isset($found[$skuId])) {
                        continue;
                    }
                    $found[$skuId] = $row;
                }
                if (count($rows) < 100) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * The variation dimension (parent spec) and category the goods already uses. A SKU can only join a goods
     * through the same parent spec as its siblings; a spec made from this SKU's own variation type (Size / Color)
     * under another parent was accepted by Temu's update call and then dropped, so the SKU never appeared.
     *
     * @return array{parentSpecIds: list<int>, parentSpecNames: list<string>, catId: int}
     */
    private function t1ExistingGoodsSpecContext(string $goodsId): array
    {
        // Read once per request: a group of new SKUs asks for this for every SKU, and each read is several Temu calls.
        if (isset($this->t1SpecContextMemo[$goodsId])) {
            return $this->t1SpecContextMemo[$goodsId];
        }

        return $this->t1SpecContextMemo[$goodsId] = $this->t1ReadGoodsSpecContext($goodsId);
    }

    /** @var array<string, array{parentSpecIds: list<int>, parentSpecNames: list<string>, catId: int}> */
    private array $t1SpecContextMemo = [];

    /** @var array<int, array<string, mixed>> */
    private array $t1TemplateMemo = [];

    /**
     * @return array{parentSpecIds: list<int>, parentSpecNames: list<string>, catId: int}
     */
    private function t1ReadGoodsSpecContext(string $goodsId): array
    {
        $parents = [];
        $names = [];
        // The goods record carries the category its variation specs belong to (SKU rows do not).
        $catId = $this->t1RemoteGoodsCatId($goodsId);
        foreach (array_slice($this->t1RemoteSkuRows($goodsId), 0, 25) as $row) {
            foreach ((array) ($row['specList'] ?? []) as $spec) {
                if (! is_array($spec)) {
                    continue;
                }
                $pid = (int) ($spec['parentSpecId'] ?? $spec['parentId'] ?? 0);
                if ($pid > 0) {
                    $parents[$pid] = true;
                }
                $label = trim((string) ($spec['parentSpecName'] ?? ''));
                if ($label !== '') {
                    $names[$label] = true;
                }
            }
            if ($catId === 0) {
                foreach (['leafCatId', 'catId', 'leafCategoryId', 'categoryId'] as $key) {
                    if (is_numeric($row[$key] ?? null) && (int) $row[$key] > 0) {
                        $catId = (int) $row[$key];
                        break;
                    }
                }
            }
        }

        return [
            'parentSpecIds' => array_map('intval', array_keys($parents)),
            'parentSpecNames' => array_keys($names),
            'catId' => $catId,
        ];
    }

    /**
     * Rebuild each new SKU's spec under the goods' own parent spec (and category) before it is added.
     *
     * @param  list<array<string, mixed>>  $skuList  v2 SKU rows
     * @return list<array<string, mixed>>
     */
    protected function alignSpecsToGoods(string $goodsId, array $skuList, int $fallbackCatId): array
    {
        $ctx = $this->t1ExistingGoodsSpecContext($goodsId);
        Log::info('Temu add SKU to existing goods: goods spec context', ['goods_id' => $goodsId] + $ctx);
        // A spec minted under the recommended category is rejected on this goods (150011018).
        // Only the category Temu already stored on the parent is valid. $fallbackCatId is unused.
        $catId = $ctx['catId'];
        unset($fallbackCatId);
        $parentSpecIds = $ctx['parentSpecIds'];
        $parents = [];
        if ($catId > 0) {
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
            $valid = [];
            foreach ($parents as $parent) {
                if (! is_array($parent)) {
                    continue;
                }
                $id = (int) ($parent['parentSpecId'] ?? $parent['parentId'] ?? $parent['id'] ?? 0);
                if ($id > 0) {
                    $valid[$id] = true;
                }
            }
            if ($valid !== []) {
                $parentSpecIds = array_values(array_filter(
                    $parentSpecIds,
                    static fn ($id) => isset($valid[(int) $id])
                ));
            }
            if ($parentSpecIds === [] && $ctx['parentSpecNames'] !== []) {
                foreach ($ctx['parentSpecNames'] as $name) {
                    $id = $this->matchParentSpecId($parents, strtolower($name));
                    if ($id !== null && $id > 0) {
                        $parentSpecIds[] = $id;
                    }
                }
                $parentSpecIds = array_values(array_unique($parentSpecIds));
            }
        }
        if ($parentSpecIds === [] || $catId <= 0) {
            return $skuList;
        }
        foreach ($skuList as $i => $item) {
            $sn = (string) ($item['outSkuSn'] ?? $item['externalSkuId'] ?? '');
            if ($sn === '') {
                continue;
            }
            // A spec minted with spec.id.get (370004488) is rejected on this goods. Use the color
            // Temu already publishes for the category (Green for GRN) when that value exists.
            $catalog = $this->matchVariationChild($parents, $parentSpecIds, $sn)
                ?? $this->catalogSpecForSku($catId, $parentSpecIds, $sn);
            if ($catalog !== null) {
                $skuList[$i]['specDetails'] = [[
                    'parentSpecId' => $catalog['parentSpecId'] > 0 ? $catalog['parentSpecId'] : (int) $parentSpecIds[0],
                    'specId' => $catalog['specId'],
                    'property' => $catalog['property'],
                ]];
                continue;
            }
            $details = [];
            foreach ($parentSpecIds as $parentSpecId) {
                $specId = $this->t1CreateSpecId($catId, (int) $parentSpecId, $sn, $sn);
                if ($specId === null) {
                    continue;
                }
                $details[] = ['parentSpecId' => (int) $parentSpecId, 'specId' => $specId];
            }
            if ($details !== []) {
                $skuList[$i]['specDetails'] = $details;
            }
        }

        return $skuList;
    }

    /**
     * The leaf category Temu holds for a goods (bg.local.goods.list.query), 0 when it cannot be read.
     */
    private function t1RemoteGoodsCatId(string $goodsId): int
    {
        // Status filter is optional. A filtered query can miss an on-sale goods and leave the category at 0,
        // which is how a spec from the wrong category (370004488) kept being sent.
        foreach ([[1, false], [1, true], [4, false], [2, false], [3, false], [5, false], [6, false]] as [$searchType, $withStatus]) {
            $body = [
                'type' => 'bg.local.goods.list.query',
                'goodsSearchType' => $searchType,
                'goodsIdList' => [(int) $goodsId],
                'searchText' => $goodsId,
                'pageSize' => 5,
                'pageNumber' => 1,
                'pageNo' => 1,
                'language' => 'en',
            ];
            if ($withStatus) {
                $body['goodsStatusFilterType'] = $searchType;
            }
            $catId = $this->catIdFromGoodsPayload($this->temuCallBody($body, 30), $goodsId);
            if ($catId > 0) {
                return $catId;
            }
        }

        return $this->catIdFromGoodsPayload($this->temuCallBody([
            'type' => 'temu.local.goods.list.retrieve',
            'goodsIdList' => [(int) $goodsId],
            'pageSize' => 5,
            'pageNo' => 1,
            'language' => 'en',
        ], 30), $goodsId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function catIdFromGoodsPayload(array $data, string $goodsId): int
    {
        $lists = [
            $data['result']['goodsList'] ?? null,
            $data['result']['goods'] ?? null,
            $data['result'] ?? null,
        ];
        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }
            $rows = array_is_list($list) ? $list : [$list];
            foreach ($rows as $row) {
                if (! is_array($row) || trim((string) ($row['goodsId'] ?? '')) !== $goodsId) {
                    continue;
                }
                foreach (['catId', 'leafCatId', 'categoryId'] as $key) {
                    if ((int) ($row[$key] ?? 0) > 0) {
                        return (int) $row[$key];
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Add SKUs to a goods that is already live, so they end up as variations of that same goods id.
     * The new SKU is sent in the add-listing shape (nested price). Live SKUs are sent by id only.
     * When Temu accepts the update, that is success: the app shows the green tick, and Temu shows the new version
     * on the parent. bg.local.goods.partial.update is not used.
     *
     * @param  list<array<string, mixed>>  $skuList  v2 SKU rows
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    protected function addSkusToExistingGoods(string $goodsId, array $skuList): array
    {
        $newSkus = [];
        foreach ($skuList as $item) {
            if (is_array($item)) {
                $newSkus[] = $this->updateSkuFromV2($item);
            }
        }
        if ($newSkus === []) {
            return ['success' => false, 'message' => 'There is no variation to add.'];
        }

        $messages = [];
        $unknownTypes = [];
        // Temu applies an accepted add asynchronously and locks the goods meanwhile (150010815 "You can edit the
        // product after the processing is complete"). Either signal means "pending", not "failed": sending the
        // heavier full update on top of a goods that is still processing only produces misleading errors.
        $pending = '';
        $attempt = function (string $type, array $skus, string $label = '', int $wait = 3, array $extra = [], int $timeout = 120) use ($goodsId, $newSkus, &$messages, &$unknownTypes, &$pending): ?array {
            if (isset($unknownTypes[$type])) {
                return null;
            }
            $label = $label !== '' ? $label : $type;
            $prices = [];
            foreach ($skus as $s) {
                $prices[(string) ($s['outSkuSn'] ?? '')] = $s['basePrice']['amount'] ?? ($s['price']['basePrice']['amount'] ?? null);
            }
            $data = $this->temuCallBody(array_merge([
                'type' => $type,
                'language' => 'en',
                'goodsId' => (int) $goodsId,
                'skuList' => $skus,
            ], $extra), $timeout);
            $innerFailed = is_array($data['result'] ?? null) && array_key_exists('success', $data['result']) && empty($data['result']['success']);
            if (($data['success'] ?? false) && ! $innerFailed) {
                // Same as a single listing: Temu accepted the request, so the app shows the green tick now.
                // The new version appears on Temu after that, while Temu finishes processing.
                if (! is_array($data['result'] ?? null)) {
                    $data['result'] = [];
                }
                $data['result']['goodsId'] = $data['result']['goodsId'] ?? (int) $goodsId;
                Log::info('Temu add SKU to existing goods: Temu accepted', [
                    'type' => $label,
                    'goods_id' => $goodsId,
                    'sku_count' => count($skus),
                ]);

                return $data;
            }
            $msg = $this->apiErrorMessage($data);
            if (str_contains($msg, '150010815') || stripos($msg, 'after the processing is complete') !== false) {
                $pending = 'Temu is still processing an earlier change to goods '.$goodsId.' and locks it until that is done.';
            }
            if ($this->isUnknownApiType($data)) {
                $unknownTypes[$type] = true;
            } elseif ($msg !== '') {
                $messages[$label] = $msg;
            }
            Log::warning('Temu add SKU to existing goods failed', [
                'type' => $label,
                'goods_id' => $goodsId,
                'sku_count' => count($skus),
                'message' => $msg,
                'sku_base_prices' => $prices,
            ]);

            return null;
        };

        $existing = $this->t1ExistingSkusForUpdate($goodsId);
        $existingSns = [];
        foreach ($existing as $row) {
            $existingSns[strtoupper((string) ($row['outSkuSn'] ?? ''))] = true;
        }
        $fresh = [];
        foreach ($newSkus as $sku) {
            if (! isset($existingSns[strtoupper((string) ($sku['outSkuSn'] ?? ''))])) {
                $fresh[] = $sku;
            }
        }
        if ($existing !== [] && $fresh === [] && $this->newSkusAreOnGoods($goodsId, $newSkus)) {
            return ['success' => true, 'data' => ['success' => true, 'result' => ['goodsId' => (int) $goodsId]]];
        }

        $pendingResult = function () use (&$pending, $goodsId): ?array {
            if ($pending === '') {
                return null;
            }
            Log::info('Temu add SKU to existing goods: pending', ['goods_id' => $goodsId, 'note' => $pending]);

            return [
                'success' => false,
                'pending' => true,
                'message' => $pending.' It is NOT marked listed yet. Wait a few minutes, then click Refresh listings: it links the SKU as soon as Temu shows it under this goods. '
                    .'If it never appears, Temu rejected it during processing - see the goods in Seller Center.',
            ];
        };

        // bg.local.goods.update reads a flat basePrice on the SKU (see the update API), not the nested
        // price object that bg.local.goods.add uses. Live SKUs stay as sku id + their own spec only:
        // sending their price or quantity back is rejected.
        $freshUpdate = $fresh;
        $freshV1 = [];
        foreach ($skuList as $item) {
            if (! is_array($item)) {
                continue;
            }
            $converted = $this->v1SkuFromV2($item);
            $sn = strtoupper(trim((string) ($converted['sku']['outSkuSn'] ?? '')));
            if ($sn === '' || isset($existingSns[$sn])) {
                continue;
            }
            $freshV1[] = $converted['sku'];
        }
        if ($freshUpdate === []) {
            $freshUpdate = $freshV1;
        }

        $tryAdd = function (string $type, array $skus, string $label, array $extra = [], int $timeout = 120) use ($attempt, $pendingResult): ?array {
            if (($data = $attempt($type, $skus, $label, 30, $extra, $timeout)) !== null) {
                return ['success' => true, 'data' => $data];
            }

            return $pendingResult();
        };

        if ($existing !== []) {
            // Live rows are identified by sku id. Their spec ids are not sent back: the only spec Temu
            // has to accept is the new variation's, and echoing the old ones was rejected as well.
            $identityKeys = array_flip(['skuId', 'outSkuSn']);
            // The update checks the pictures of every SKU it carries. A live SKU sent with no picture is
            // reported as 150010022 as well, so each one keeps the photo Temu already stores for it.
            // Temu also checks package size on every carried SKU (150011019 "volumeUnit:null"). Each live SKU keeps
            // the weight and size Temu holds; where Temu holds none, the new SKU's package size fills the gap.
            $measureKeys = ['weight', 'weightUnit', 'length', 'width', 'height', 'volumeUnit'];
            $measureFallback = [];
            foreach ($freshUpdate as $freshRow) {
                foreach ($measureKeys as $key) {
                    if (isset($freshRow[$key]) && $freshRow[$key] !== '' && ! isset($measureFallback[$key])) {
                        $measureFallback[$key] = $freshRow[$key];
                    }
                }
            }
            $kept = [];
            $keptPlain = [];
            foreach ($existing as $row) {
                $identity = array_intersect_key($row, $identityKeys);
                $keptPlain[] = $identity;
                // Package size comes from our masters (Dim & Wt) for that SKU. Temu's own value is used only
                // when the masters hold no complete package for it.
                $fromMasters = [];
                $liveSku = trim((string) ($row['outSkuSn'] ?? ''));
                if ($liveSku !== '') {
                    $liveProduct = $this->findProductLoose($liveSku);
                    if ($liveProduct && $this->hasDimWt($liveProduct, $liveSku)) {
                        $pkg = $this->resolveDimensions($liveProduct, $liveSku);
                        $fromMasters = [
                            'weight' => $this->v1Measure($pkg['weight'] ?? '1', 4),
                            'length' => $this->v1Measure($pkg['length'] ?? '1', 3),
                            'width' => $this->v1Measure($pkg['width'] ?? '1', 3),
                            'height' => $this->v1Measure($pkg['height'] ?? '1', 3),
                            'weightUnit' => $this->v1WeightUnit($pkg['weightUnit'] ?? 2),
                            'volumeUnit' => $this->v1VolumeUnit($pkg['volumeUnit'] ?? 2),
                        ];
                    }
                }
                foreach ($measureKeys as $key) {
                    if (isset($fromMasters[$key])) {
                        $identity[$key] = $fromMasters[$key];
                    } elseif (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
                        $identity[$key] = $row[$key];
                    } elseif (isset($measureFallback[$key])) {
                        $identity[$key] = $measureFallback[$key];
                    }
                }
                $own = [];
                foreach ((array) ($row['images'] ?? []) as $url) {
                    $url = trim((string) $url);
                    if ($url !== '' && $this->isTemuCdnUrl($url) && ! in_array($url, $own, true)) {
                        $own[] = $url;
                    }
                }
                if ($own !== []) {
                    $identity['images'] = $this->padTemuUpdateImages($own);
                }
                $kept[] = $identity;
            }
            $firstSn = '';
            $currentSpecIds = [];
            $siblingImages = [];
            foreach ($existing as $row) {
                foreach ((array) ($row['images'] ?? []) as $url) {
                    $url = trim((string) $url);
                    if ($this->isTemuCdnUrl($url) && ! in_array($url, $siblingImages, true)) {
                        $siblingImages[] = $url;
                    }
                }
            }
            foreach ($freshUpdate as $i => $row) {
                if ($firstSn === '') {
                    $firstSn = (string) ($row['outSkuSn'] ?? '');
                }
                foreach ((array) ($row['specIdList'] ?? []) as $specId) {
                    if ((int) $specId > 0) {
                        $currentSpecIds[] = (int) $specId;
                    }
                }
                $rawImages = [];
                foreach ((array) ($row['images'] ?? []) as $url) {
                    $url = trim((string) $url);
                    if ($url !== '' && preg_match('#^https://#i', $url) && ! in_array($url, $rawImages, true)) {
                        $rawImages[] = $url;
                    }
                }
                $rawImages = array_slice($rawImages, 0, self::T1_MAX_IMAGES);
                $own = $this->temuUpdateImages($rawImages, []);
                $freshUpdate[$i]['images'] = $own !== []
                    ? $own
                    : $this->temuUpdateImages($siblingImages, []);
            }
            // bg.local.goods.update replaces the whole goods and rejects a call without the product name
            // (150010009). The parent's own name, photos, description and service promise are read from Temu
            // and sent back unchanged, so the parent keeps its title.
            $parentFields = $this->t1ParentUpdateFields($goodsId);
            $parentExtra = $parentFields['extra'];
            $parentProps = $parentFields['properties'];
            $lastMsg = '';
            // One spec value per new SKU. Temu rejects a goods whose SKUs share a spec value (150011028
            // "Specification information repeated"), so a group of new SKUs must never all get the same spec id.
            $specPlans = [];
            if (count($freshUpdate) <= 1) {
                foreach ($this->specIdsToTry($goodsId, $firstSn, $currentSpecIds) as $planSpecId) {
                    $specPlans[] = [0 => $planSpecId];
                }
            } else {
                $liveSpecIds = [];
                foreach ($existing as $row) {
                    foreach ((array) ($row['specIdList'] ?? []) as $liveSpecId) {
                        $liveSpecIds[(int) $liveSpecId] = true;
                    }
                }
                $rowCandidates = [];
                foreach ($freshUpdate as $i => $row) {
                    $rowCandidates[$i] = $this->specIdsToTry(
                        $goodsId,
                        (string) ($row['outSkuSn'] ?? ''),
                        array_map('intval', (array) ($row['specIdList'] ?? []))
                    );
                }
                $seenPlans = [];
                for ($round = 0; $round < 4; $round++) {
                    $used = $liveSpecIds;
                    $plan = [];
                    foreach ($rowCandidates as $i => $candidates) {
                        $pick = null;
                        $count = count($candidates);
                        // Round 0 takes each SKU's best spec that is still free; later rounds start further down the list.
                        for ($offset = 0; $offset < $count; $offset++) {
                            $candidate = (int) $candidates[($round + $offset) % $count];
                            if ($candidate > 0 && ! isset($used[$candidate])) {
                                $pick = $candidate;
                                break;
                            }
                        }
                        if ($pick === null) {
                            continue 2;
                        }
                        $used[$pick] = true;
                        $plan[$i] = $pick;
                    }
                    $signature = json_encode($plan);
                    if (! isset($seenPlans[$signature])) {
                        $seenPlans[$signature] = true;
                        $specPlans[] = $plan;
                    }
                }
            }
            foreach ($specPlans as $specMap) {
                $specId = (int) (reset($specMap) ?: 0);
                $send = function (array $rows, bool $withLivePhotos, array $extra = []) use ($tryAdd, $kept, $keptPlain, $specMap): ?array {
                    $freshForSpec = [];
                    $imageCount = 0;
                    $firstImage = '';
                    foreach ($rows as $rowIndex => $row) {
                        $row['specIdList'] = [(int) ($specMap[$rowIndex] ?? reset($specMap))];
                        $row['images'] = $this->padTemuUpdateImages((array) ($row['images'] ?? []));
                        $imageCount = max($imageCount, count($row['images']));
                        if ($firstImage === '' && isset($row['images'][0])) {
                            $firstImage = (string) $row['images'][0];
                        }
                        $freshForSpec[] = $row;
                    }
                    $tail = $firstImage !== '' ? substr(parse_url($firstImage, PHP_URL_PATH) ?: $firstImage, -42) : 'none';

                    return $tryAdd(
                        'bg.local.goods.update',
                        array_merge($withLivePhotos ? $kept : $keptPlain, $freshForSpec),
                        'bg.local.goods.update (spec '.implode('/', array_values($specMap)).', '.$imageCount.' images'
                            .($withLivePhotos ? ', live photos' : ', no live photos').', '.$tail.')',
                        $extra,
                        40
                    );
                };
                $plans = [];
                $ownImages = array_values((array) ($freshUpdate[0]['images'] ?? []));
                if ($ownImages !== []) {
                    $plans[] = $ownImages;
                }
                if ($siblingImages !== []) {
                    $plans[] = array_slice($siblingImages, 0, 3);
                }
                if ($plans === []) {
                    $plans = [[]];
                }
                $imageRejected = false;
                foreach ($plans as $imageSet) {
                    $attemptRows = [];
                    foreach ($freshUpdate as $index => $candidate) {
                        if ($index === 0) {
                            $candidate['images'] = $imageSet;
                        }
                        $attemptRows[] = $candidate;
                    }
                    if (($done = $send($attemptRows, true, $parentExtra)) !== null) {
                        return $done;
                    }
                    $lastMsg = '';
                    foreach ($messages as $msg) {
                        $lastMsg = $msg;
                    }
                    // The update replaces the whole goods, so Temu may also want the parent's attributes and the
                    // new color listed among them (150010237). Sent only when Temu says so.
                    if ($parentProps !== [] && preg_match('/propert|150010237|150011025/i', $lastMsg)) {
                        $propsExtra = $parentExtra;
                        $entries = $parentProps;
                        $cat = (int) ($this->t1ExistingGoodsSpecContext($goodsId)['catId'] ?? 0);
                        $catalog = $cat > 0 ? $this->catalogSpecForSku($cat, [], $firstSn) : null;
                        if ($catalog !== null && (int) $catalog['specId'] === (int) $specId) {
                            $entries[] = $catalog['property'];
                        }
                        $propsExtra['goodsProperty'] = ['goodsProperties' => $entries];
                        if (($done = $send($attemptRows, true, $propsExtra)) !== null) {
                            return $done;
                        }
                        $lastMsg = '';
                        foreach ($messages as $msg) {
                            $lastMsg = $msg;
                        }
                    }
                    $imageRejected = str_contains(strtolower($lastMsg), 'image') || str_contains($lastMsg, '150010022');
                    if (! $imageRejected) {
                        break;
                    }
                }
                if ($imageRejected || ($lastMsg !== '' && ! str_contains(strtolower($lastMsg), 'spec'))) {
                    break;
                }
            }
        }

        if (($done = $tryAdd('bg.local.goods.sku.add', $freshV1, 'bg.local.goods.sku.add')) !== null) {
            return $done;
        }
        if (($done = $tryAdd('temu.local.goods.sku.add', $freshV1, 'temu.local.goods.sku.add')) !== null) {
            return $done;
        }

        $parts = [];
        foreach (array_slice($messages, 0, 5, true) as $label => $msg) {
            $parts[] = $label.': '.$msg;
        }
        if ($existing === []) {
            $parts[] = 'The existing variations of this goods could not be read from Temu.';
        }

        return ['success' => false, 'message' => implode(' | ', $parts)];
    }

    /**
     * Spec ids to offer Temu for one new variation, best first. The generated id 370004488 is last because
     * this parent already rejected it. Temu 1, Temu 2, and Temu 3 all add variations this way.
     *
     * @param  list<int>  $fallbackIds
     * @return list<int>
     */
    protected function specIdsToTry(string $goodsId, string $sku, array $fallbackIds): array
    {
        $ids = [];
        $push = function (?int $id) use (&$ids): void {
            if ($id !== null && $id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        };
        $ctx = $this->t1ExistingGoodsSpecContext($goodsId);
        $catId = (int) ($ctx['catId'] ?? 0);
        $parentIds = array_values(array_map('intval', $ctx['parentSpecIds'] ?? []));
        if ($catId > 0 && $parentIds !== []) {
            $catalog = $this->catalogSpecForSku($catId, [], $sku)
                ?? $this->catalogSpecForSku($catId, $parentIds, $sku);
            if ($catalog !== null) {
                $push($catalog['specId']);
            }
            foreach ($this->specLabelsForCreate($sku) as $label) {
                $push($this->t1CreateSpecId($catId, (int) $parentIds[0], $sku, $label));
            }
        }
        foreach ($fallbackIds as $id) {
            $push((int) $id);
        }

        return array_slice($ids, 0, 4);
    }

    /**
     * Names used to create a spec under the parent. Color words come first (GRN -> Green) because a spec
     * named after the whole SKU was rejected.
     *
     * @return list<string>
     */
    protected function specLabelsForCreate(string $sku): array
    {
        $labels = [];
        $token = strtoupper((string) preg_replace('/^.*\s+/', '', trim($sku)));
        $colors = [
            'GRN' => 'Green', 'GREEN' => 'Green',
            'BLK' => 'Black', 'BLACK' => 'Black',
            'BLU' => 'Blue', 'BLUE' => 'Blue',
            'GREY' => 'Grey', 'GRAY' => 'Gray', 'GRY' => 'Grey',
            'ORG' => 'Orange', 'ORANGE' => 'Orange',
            'RED' => 'Red',
            'YLW' => 'Yellow', 'YELLOW' => 'Yellow',
            'PNK' => 'Pink', 'PINK' => 'Pink',
            'WHT' => 'White', 'WHITE' => 'White',
            'BRN' => 'Brown', 'BROWN' => 'Brown',
            'PUR' => 'Purple', 'PURPLE' => 'Purple',
            'GLD' => 'Gold', 'GOLD' => 'Gold',
            'SLV' => 'Silver', 'SILVER' => 'Silver',
        ];
        if (isset($colors[$token])) {
            $labels[] = $colors[$token];
        }
        $variation = trim(ListingManagerFamily::attributesForSku($sku)['name']);
        if ($variation !== '') {
            $labels[] = $variation;
        }
        if (trim($sku) !== '') {
            $labels[] = trim($sku);
        }

        return array_values(array_unique($labels));
    }

    /**
     * Names that can identify this SKU's variation on Temu: the SKU, its variation name, and the color word.
     * DPAD COMBO GRN matches the catalog value Green.
     *
     * @return list<string>
     */
    private function specNameCandidates(string $sku): array
    {
        $names = [];
        $add = function (string $name) use (&$names): void {
            $name = strtolower(trim($name));
            if ($name !== '') {
                $names[$name] = true;
            }
        };
        $add($sku);
        $add(ListingManagerFamily::attributesForSku($sku)['name']);
        $token = strtoupper((string) preg_replace('/^.*\s+/', '', trim($sku)));
        $colors = [
            'GRN' => 'green', 'GREEN' => 'green',
            'BLK' => 'black', 'BLACK' => 'black',
            'BLU' => 'blue', 'BLUE' => 'blue',
            'GREY' => 'grey', 'GRAY' => 'gray', 'GRY' => 'grey',
            'ORG' => 'orange', 'ORANGE' => 'orange',
            'RED' => 'red',
            'YLW' => 'yellow', 'YELLOW' => 'yellow',
            'PNK' => 'pink', 'PINK' => 'pink',
            'WHT' => 'white', 'WHITE' => 'white',
            'BRN' => 'brown', 'BROWN' => 'brown',
            'PUR' => 'purple', 'PURPLE' => 'purple',
            'GLD' => 'gold', 'GOLD' => 'gold',
            'SLV' => 'silver', 'SILVER' => 'silver',
        ];
        if (isset($colors[$token])) {
            $add($colors[$token]);
            $add($token);
        }

        return array_keys($names);
    }

    /**
     * A predefined child spec under the goods' parent (variation.get), when its name is this SKU's color.
     *
     * @param  list<mixed>  $parents
     * @param  list<int>  $parentSpecIds
     * @return array{specId: int, parentSpecId: int, property: array<string, mixed>}|null
     */
    private function matchVariationChild(array $parents, array $parentSpecIds, string $sku): ?array
    {
        $want = array_flip($this->specNameCandidates($sku));
        $allowed = array_flip(array_map('intval', $parentSpecIds));
        foreach ($parents as $parent) {
            if (! is_array($parent)) {
                continue;
            }
            $parentSpecId = (int) ($parent['parentSpecId'] ?? $parent['parentId'] ?? $parent['id'] ?? 0);
            if ($parentSpecId <= 0 || ! isset($allowed[$parentSpecId])) {
                continue;
            }
            $children = $parent['specList'] ?? $parent['childSpecList'] ?? $parent['values'] ?? [];
            if (! is_array($children)) {
                continue;
            }
            foreach ($children as $child) {
                if (! is_array($child)) {
                    continue;
                }
                $specId = (int) ($child['specId'] ?? $child['id'] ?? 0);
                $label = strtolower(trim((string) ($child['specName'] ?? $child['value'] ?? $child['name'] ?? '')));
                if ($specId <= 0 || $label === '' || ! isset($want[$label])) {
                    continue;
                }

                return [
                    'specId' => $specId,
                    'parentSpecId' => $parentSpecId,
                    'property' => [
                        'parentSpecId' => $parentSpecId,
                        'specId' => $specId,
                        'value' => (string) ($child['specName'] ?? $child['value'] ?? $child['name'] ?? $label),
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * The sale-spec value Temu's category template already has for this SKU (bg.local.goods.template.get).
     * That specId is what goods.update accepts. A spec.id.get id is not.
     *
     * @param  list<int>  $parentSpecIds
     * @return array{specId: int, parentSpecId: int, property: array<string, mixed>}|null
     */
    private function catalogSpecForSku(int $catId, array $parentSpecIds, string $sku): ?array
    {
        if ($catId <= 0) {
            return null;
        }
        if (! isset($this->t1TemplateMemo[$catId])) {
            $this->t1TemplateMemo[$catId] = $this->temuCallBody([
                'type' => 'bg.local.goods.template.get',
                'catId' => $catId,
                'language' => 'en',
            ], 45);
        }
        $template = $this->t1TemplateMemo[$catId];
        $info = $template['result']['templateInfo'] ?? $template['result'] ?? [];
        if (! is_array($info)) {
            return null;
        }
        $groups = [];
        if (is_array($info['goodsSpecProperties'] ?? null)) {
            $groups[] = $info['goodsSpecProperties'];
        }
        if (is_array($info['goodsProperties'] ?? null)) {
            $groups[] = $info['goodsProperties'];
        }
        $want = array_flip($this->specNameCandidates($sku));
        $allowed = array_flip(array_map('intval', $parentSpecIds));
        foreach ($groups as $props) {
            foreach ($props as $prop) {
                if (! is_array($prop)) {
                    continue;
                }
                $propParent = (int) ($prop['parentSpecId'] ?? $prop['parentId'] ?? 0);
                if ($allowed !== [] && $propParent > 0 && ! isset($allowed[$propParent])) {
                    continue;
                }
                $values = $prop['values'] ?? $prop['valueList'] ?? $prop['specList'] ?? [];
                if (! is_array($values)) {
                    continue;
                }
                foreach ($values as $value) {
                    if (! is_array($value)) {
                        continue;
                    }
                    $specId = (int) ($value['specId'] ?? 0);
                    $label = strtolower(trim((string) ($value['value'] ?? $value['specName'] ?? $value['name'] ?? '')));
                    if ($specId <= 0 || $label === '' || ! isset($want[$label])) {
                        continue;
                    }
                    $entry = [
                        'specId' => $specId,
                        'value' => (string) ($value['value'] ?? $value['specName'] ?? $label),
                    ];
                    if ($propParent > 0) {
                        $entry['parentSpecId'] = $propParent;
                    }
                    foreach (['templatePid', 'refPid', 'pid'] as $key) {
                        if (isset($prop[$key]) && $prop[$key] !== '' && $prop[$key] !== null) {
                            $entry[$key] = (int) $prop[$key];
                        }
                    }
                    if (isset($value['vid']) && $value['vid'] !== '' && $value['vid'] !== null) {
                        $entry['vid'] = (int) $value['vid'];
                    }

                    return [
                        'specId' => $specId,
                        'parentSpecId' => $propParent,
                        'property' => $entry,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * What Temu already holds for the parent goods, shaped for bg.local.goods.update: name, photos, description,
     * bullets, service promise, and the attribute list (kept apart, used only when Temu asks for it).
     *
     * @return array{extra: array<string, mixed>, properties: list<array<string, mixed>>}
     */
    private function t1ParentUpdateFields(string $goodsId): array
    {
        $result = [];
        foreach ([2, 1] as $versionType) {
            $data = $this->temuCallBody([
                'type' => 'bg.local.goods.detail.query',
                'goodsId' => (int) $goodsId,
                'language' => 'en',
                'versionQueryType' => $versionType,
            ], 45);
            $candidate = is_array($data['result'] ?? null) ? $data['result'] : [];
            if (($data['success'] ?? false) && trim((string) ($candidate['goodsName'] ?? '')) !== '') {
                $result = $candidate;
                break;
            }
        }
        if ($result === []) {
            Log::warning('Temu 1 variation add: could not read the parent goods', ['goods_id' => $goodsId]);

            return ['extra' => [], 'properties' => []];
        }

        $urls = static function (mixed $list): array {
            return array_values(array_filter(array_map(
                static fn ($url) => trim((string) $url),
                is_array($list) ? $list : []
            ), static fn ($url) => $url !== ''));
        };

        $basic = ['goodsName' => trim((string) $result['goodsName'])];
        $gallery = [];
        $carousel = $urls($result['goodsGallery']['goodsCarouselImage'] ?? []);
        $detail = $urls($result['goodsGallery']['detailImage'] ?? []);
        if ($carousel !== []) {
            $gallery['goodsCarouselImage'] = $carousel;
        }
        if ($detail !== []) {
            $gallery['detailImage'] = $detail;
        }
        if ($gallery !== []) {
            $basic['goodsGallery'] = $gallery;
        }
        $import = trim((string) ($result['importDesignation'] ?? ''));
        if ($import !== '') {
            $basic['importDesignation'] = $import;
        }
        if ((int) ($result['productType'] ?? 0) > 0) {
            $basic['productType'] = (int) $result['productType'];
        }

        $extra = ['goodsBasic' => $basic];
        $desc = trim((string) ($result['goodsDesc'] ?? ''));
        if ($desc !== '') {
            $extra['goodsDesc'] = $desc;
        }
        $bullets = $urls($result['bulletPoints'] ?? []);
        if ($bullets !== []) {
            $extra['bulletPoints'] = $bullets;
        }
        $promise = is_array($result['goodsServicePromise'] ?? null) ? $result['goodsServicePromise'] : [];
        $service = [];
        if (trim((string) ($promise['costTemplateId'] ?? '')) !== '') {
            $service['costTemplateId'] = (string) $promise['costTemplateId'];
        }
        if ((int) ($promise['shipmentLimitDay'] ?? 0) > 0) {
            $service['shipmentLimitDay'] = (int) $promise['shipmentLimitDay'];
        }
        if ((int) ($promise['fulfillmentType'] ?? 0) > 0) {
            $service['fulfillmentType'] = (int) $promise['fulfillmentType'];
        }
        if ($service !== []) {
            $extra['goodsServicePromise'] = $service;
        }

        // Country of origin is required too (150011019 "originRegion1:null"). Our products are made in China,
        // so that is what is sent, with the province this shop already uses on new listings.
        $listingOrigin = $this->buildGoodsOriginInfo();
        $province = trim((string) ($listingOrigin['originRegion2'] ?? ''));
        $extra['goodsOriginInfo'] = [
            'originRegion1' => 'China',
            'originRegion2' => $province !== '' ? $province : 'Guangdong',
        ];

        $allowed = array_flip(['vid', 'specId', 'imgUrl', 'note', 'parentSpecId', 'valueUnitId', 'groupId', 'valueUnit',
            'templatePid', 'numberInputValue', 'value', 'refPid', 'pid']);
        $properties = [];
        foreach ((array) ($result['goodsProperties'] ?? []) as $prop) {
            if (! is_array($prop)) {
                continue;
            }
            $entry = array_intersect_key($prop, $allowed);
            foreach ($entry as $key => $value) {
                if ($value === null || $value === '') {
                    unset($entry[$key]);
                }
            }
            if ($entry !== []) {
                $properties[] = $entry;
            }
        }

        return ['extra' => $extra, 'properties' => $properties];
    }

    /**
     * The SKUs Temu already holds under a goods, in the update shape (with skuId). Temu 1 also reads SKUs that
     * are accepted but not on sale yet.
     *
     * @return list<array<string, mixed>>
     */
    private function t1ExistingSkusForUpdate(string $goodsId): array
    {
        $found = [];
        foreach ($this->t1RemoteSkuRows($goodsId) as $skuId => $row) {
            $found[$skuId] = $this->updateSkuFromRemote($row);
        }

        return array_values($found);
    }

    /**
     * Temu 1's seller token is authorized for the older local add API.
     * temu.local.goods.v2.add returns 3000032 on this shop.
     *
     * @return list<string>
     */
    protected function goodsAddTypes(): array
    {
        return [
            'bg.local.goods.add',
            'temu.local.goods.add',
            'temu.local.goods.v2.add',
        ];
    }

    /**
     * Temu 1 rejects WebP file links. Convert the photo to JPEG and upload the bytes.
     * Temu 2 keeps the fileUrl upload.
     *
     * @return array{success: bool, url?: string, message: string}
     */
    protected function uploadImageToTemu2(string $imageUrl): array
    {
        $prepared = $this->prepareTemuImage($imageUrl);
        if ($prepared['bytes'] === null) {
            return [
                'success' => false,
                'message' => 'Could not read the listing photo as a JPEG or PNG. Temu rejects WebP links.',
            ];
        }

        $lastMsg = '';
        $remember = function (array $data) use (&$lastMsg): bool {
            $msg = trim((string) ($data['errorMsg'] ?? $data['message'] ?? ''));
            if ($this->isIpWhitelistMessage($msg)) {
                $lastMsg = $msg;

                return true;
            }
            if ($msg !== '' && ! $this->isSkippableImageError($msg) && ! $this->isApiAccessDenied($data)) {
                $lastMsg = $msg;
            }

            return false;
        };

        foreach ($prepared['file_urls'] as $url) {
            foreach ([
                ['fileUrl' => $url, 'scalingType' => 1, 'compressionType' => 0, 'formatConversionType' => 1],
                ['fileUrl' => $url],
            ] as $params) {
                $data = $this->api->callOpenApi('bg.local.goods.image.upload', $params, 60);
                $hosted = $this->extractUploadedImageUrl($data);
                if ($hosted !== null && $this->isTemuGoodsImageUrl($hosted) && ! $this->sameImageUrl($hosted, $url)) {
                    return ['success' => true, 'url' => $hosted, 'message' => 'OK'];
                }
                if ($remember($data)) {
                    return ['success' => false, 'message' => $lastMsg];
                }
            }
        }

        if (is_string($prepared['bytes']) && $prepared['bytes'] !== '') {
            $b64 = base64_encode($prepared['bytes']);
            foreach ([
                ['image' => 'data:image/jpeg;base64,'.$b64, 'formatConversionType' => 1],
                ['fileBase64' => $b64, 'formatConversionType' => 1],
                ['imageBase64' => $b64],
            ] as $params) {
                $data = $this->api->callOpenApi('bg.local.goods.image.upload', $params, 90);
                $hosted = $this->extractUploadedImageUrl($data);
                if ($hosted !== null && $this->isTemuGoodsImageUrl($hosted)) {
                    return ['success' => true, 'url' => $hosted, 'message' => 'OK'];
                }
                if ($remember($data)) {
                    return ['success' => false, 'message' => $lastMsg];
                }
            }
        }

        if ($prepared['file_urls'] === []) {
            return [
                'success' => false,
                'message' => 'Temu needs a public JPEG or PNG link for this photo. Add the photo in Image Master as JPEG or PNG.',
            ];
        }

        return [
            'success' => false,
            'message' => $lastMsg !== '' ? $lastMsg : 'Temu image upload failed.',
        ];
    }

    /**
     * @return array{bytes: ?string, file_urls: list<string>}
     */
    private function prepareTemuImage(string $imageUrl): array
    {
        $raw = $this->readImageBytes($imageUrl);
        if ($raw === null) {
            return ['bytes' => null, 'file_urls' => []];
        }
        $jpeg = $this->binaryToJpeg($raw);
        if ($jpeg === null) {
            return ['bytes' => null, 'file_urls' => []];
        }

        $fileUrls = [];
        if ($this->isTemuFetchableImageUrl($imageUrl) && $this->bytesAreJpegOrPng($raw)) {
            $fileUrls[] = $imageUrl;
        }
        $saved = $this->storeJpegForTemu($imageUrl, $jpeg);
        if ($saved !== '') {
            $fileUrls[] = $saved;
        }

        return ['bytes' => $jpeg, 'file_urls' => array_values(array_unique($fileUrls))];
    }

    private function isSkippableImageError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'type not exists')
            || str_contains($message, 'type not exist')
            || str_contains($message, '.webp');
    }

    private function isTemuFetchableImageUrl(string $url): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));

        return (bool) preg_match('/\.(jpe?g|png)$/', $path);
    }

    private function bytesAreJpegOrPng(string $raw): bool
    {
        return str_starts_with($raw, "\xFF\xD8\xFF") || str_starts_with($raw, "\x89PNG\r\n\x1a\n");
    }

    private function readImageBytes(string $imageUrl): ?string
    {
        $file = $this->localImageFile($imageUrl);
        if ($file !== null) {
            $raw = @file_get_contents($file);
            if (is_string($raw) && strlen($raw) > 32) {
                return $raw;
            }
        }

        try {
            $response = Http::timeout(60)->withoutVerifying()->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; 5CoreListingManager/1.0)',
                'Accept' => 'image/jpeg,image/png,image/webp,image/*',
            ])->get($imageUrl);
            if ($response->successful()) {
                $raw = $response->body();
                if (strlen($raw) > 32) {
                    return $raw;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function localImageFile(string $imageUrl): ?string
    {
        $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
        if (preg_match('#/listing-manager/media/([^/]+)$#', $path, $match)) {
            $relative = 'listing-manager/images/'.basename(rawurldecode($match[1]));
        } elseif (preg_match('#/storage/(.+)$#', $path, $match)) {
            $relative = ltrim(rawurldecode($match[1]), '/');
        } else {
            return null;
        }

        $candidates = [
            Storage::disk('public')->path($relative),
            storage_path('app/public/'.$relative),
            public_path('storage/'.$relative),
        ];
        foreach ($candidates as $full) {
            if (is_file($full) && is_readable($full)) {
                return $full;
            }
        }

        return null;
    }

    private function storeJpegForTemu(string $imageUrl, string $jpeg): string
    {
        $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
        $stem = '';
        if (preg_match('#/listing-manager/media/([^/]+)$#', $path, $match)) {
            $stem = pathinfo(basename(rawurldecode($match[1])), PATHINFO_FILENAME);
        }
        if (! preg_match('/^[a-f0-9]{40}$/', $stem)) {
            $stem = sha1($jpeg);
        }
        $jpgName = $stem.'.jpg';
        $relative = 'listing-manager/images/'.$jpgName;
        if (! Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->put($relative, $jpeg);
        }

        return rtrim((string) config('app.url'), '/').'/listing-manager/media/'.$jpgName;
    }

    private function binaryToJpeg(string $raw): ?string
    {
        if (str_starts_with($raw, "\xFF\xD8\xFF")) {
            return $raw;
        }
        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $image = @imagecreatefromstring($raw);
            if ($image !== false) {
                return $this->gdToJpeg($image);
            }
        }
        if (function_exists('imagecreatefromwebp') && function_exists('imagejpeg') && str_starts_with($raw, 'RIFF')) {
            $tmp = tempnam(sys_get_temp_dir(), 'temuimg');
            if (is_string($tmp)) {
                file_put_contents($tmp, $raw);
                $image = @imagecreatefromwebp($tmp);
                @unlink($tmp);
                if ($image !== false) {
                    return $this->gdToJpeg($image);
                }
            }
        }
        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick();
                $im->readImageBlob($raw);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(90);
                $blob = $im->getImageBlob();
                $im->clear();

                return is_string($blob) && $blob !== '' ? $blob : null;
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * @param  \GdImage|resource  $image
     */
    private function gdToJpeg($image): ?string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);

            return null;
        }
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($image);

            return null;
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($image);
        imagedestroy($canvas);

        return is_string($jpeg) && str_starts_with($jpeg, "\xFF\xD8") ? $jpeg : null;
    }
}
