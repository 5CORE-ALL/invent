<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AliexpressDataView;
use App\Models\AliexpressMetric;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\SavesMarketplaceImageMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

/**
 * AliExpress dropshipping / Solution API — POST https://api-sg.aliexpress.com/sync
 *
 * Sign string: `/sync` + sorted `key`+`value` for all public params (HMAC-SHA256, uppercase hex).
 * Token is sent as `session` (not `access_token`) for this gateway.
 *
 * Test (tinker):
 *   app(\App\Services\AliExpressApiService::class)->getInventory(1, 5);
 *   app(\App\Services\AliExpressApiService::class)->updateTitle('1000005237852', 'New title');
 *
 * Artisan:
 *   php artisan aliexpress:test list
 *   php artisan aliexpress:test edit --product-id=ID --title="New title"
 */
class AliExpressApiService
{
    use SavesMarketplaceVideoMetrics;
    use SavesMarketplaceImageMetrics;
    use VideoMasterMarketplaceMethods;

    protected string $appKey;

    protected string $appSecret;

    protected ?string $accessToken;

    /** Full POST URL including path, e.g. https://api-sg.aliexpress.com/sync */
    protected string $apiBase;

    /** Leading path used as prefix in sign string (e.g. /sync); must match dropshipping API path. */
    protected string $signPath;

    /** `session` (dropshipping /sync) or `access_token` if your gateway requires it. */
    protected string $tokenParam;

    protected string $partnerId;

    protected string $format;

    /** String "true" / "false" for form + signature. */
    protected string $simplify;

    /** Official IOP: query + multipart. Legacy: single x-www-form-urlencoded body. */
    protected string $transport;

    /** Matches IOP SDK msectime() vs true millisecond timestamp. */
    protected string $timestampStyle;

    /** rest = business /rest gateway; sync = dropshipping /sync */
    protected string $gateway;

    protected string $restBase;

    protected string $topBase;

    protected string $restSignMethod;

    protected int $httpConnectTimeout;

    protected int $httpTimeout;

    protected ?string $httpProxy;

    protected bool $resolveIpv4;

    protected bool $gatewayFallback;

    /** sync signing: iop (/sync prefix) or legacy (secret sandwich + access_token) */
    protected string $syncSignStyle;

    /** Human label for OAuth token error messages (override in subclasses). */
    protected string $channelLabel = 'AliExpress';

    /** .env key hint in token error messages (override in subclasses). */
    protected string $tokenEnvKey = 'ALIEXPRESS_ACCESS_TOKEN';

    public function __construct()
    {
        $this->appKey = (string) (config('services.aliexpress.app_key') ?: env('ALIEXPRESS_APP_KEY', ''));
        $this->appSecret = (string) (config('services.aliexpress.app_secret') ?: env('ALIEXPRESS_APP_SECRET', ''));
        $this->accessToken = config('services.aliexpress.access_token') ?: env('ALIEXPRESS_ACCESS_TOKEN');
        $this->apiBase = $this->normalizeSyncApiBase(
            (string) (config('services.aliexpress.api_base') ?: env('ALIEXPRESS_API_BASE', 'https://api-sg.aliexpress.com/sync'))
        );
        $sp = (string) (config('services.aliexpress.sign_path') ?? env('ALIEXPRESS_SIGN_PATH', '/sync'));
        $this->signPath = ($sp !== '' && $sp[0] === '/') ? $sp : '/'.$sp;
        $tp = strtolower((string) (config('services.aliexpress.token_param') ?: env('ALIEXPRESS_TOKEN_PARAM', 'session')));
        $this->tokenParam = in_array($tp, ['session', 'access_token'], true) ? $tp : 'session';
        $this->partnerId = (string) (config('services.aliexpress.partner_id') ?: env('ALIEXPRESS_PARTNER_ID', 'iop-sdk-php'));
        $this->format = (string) (config('services.aliexpress.format') ?: env('ALIEXPRESS_FORMAT', 'json'));
        $sim = config('services.aliexpress.simplify') ?? env('ALIEXPRESS_SIMPLIFY', 'true');
        $this->simplify = is_bool($sim) ? ($sim ? 'true' : 'false') : (string) $sim;
        $tr = strtolower((string) (config('services.aliexpress.transport') ?: env('ALIEXPRESS_TRANSPORT', 'iop')));
        $this->transport = in_array($tr, ['iop', 'form'], true) ? $tr : 'iop';
        $ts = strtolower((string) (config('services.aliexpress.timestamp_style') ?: env('ALIEXPRESS_TIMESTAMP_STYLE', 'iop')));
        $this->timestampStyle = in_array($ts, ['iop', 'ms'], true) ? $ts : 'iop';

        $gw = strtolower((string) (config('services.aliexpress.gateway') ?: env('ALIEXPRESS_GATEWAY', 'rest')));
        $this->gateway = in_array($gw, ['sync', 'rest'], true) ? $gw : 'rest';
        $this->restBase = rtrim((string) (config('services.aliexpress.rest_base') ?: env('ALIEXPRESS_REST_BASE', 'https://api-sg.aliexpress.com/rest')), '/');
        $this->topBase = rtrim((string) (config('services.aliexpress.top_base') ?: env('ALIEXPRESS_TOP_BASE', 'https://eco.taobao.com/router/rest')), '/');
        $rsm = strtolower((string) (config('services.aliexpress.rest_sign_method') ?: env('ALIEXPRESS_REST_SIGN_METHOD', 'hmac')));
        $this->restSignMethod = in_array($rsm, ['hmac', 'md5'], true) ? $rsm : 'hmac';
        $this->httpConnectTimeout = max(5, (int) (config('services.aliexpress.connect_timeout') ?: env('ALIEXPRESS_CONNECT_TIMEOUT', 30)));
        $this->httpTimeout = max(10, (int) (config('services.aliexpress.timeout') ?: env('ALIEXPRESS_TIMEOUT', 60)));
        $proxy = config('services.aliexpress.http_proxy') ?: env('ALIEXPRESS_HTTP_PROXY');
        $this->httpProxy = is_string($proxy) && $proxy !== '' ? $proxy : null;
        $this->resolveIpv4 = filter_var(
            config('services.aliexpress.resolve_ipv4', env('ALIEXPRESS_RESOLVE_IPV4', true)),
            FILTER_VALIDATE_BOOL
        );
        $this->gatewayFallback = filter_var(
            config('services.aliexpress.gateway_fallback', env('ALIEXPRESS_GATEWAY_FALLBACK', true)),
            FILTER_VALIDATE_BOOL
        );
        $ss = strtolower((string) (config('services.aliexpress.sync_sign_style') ?: env('ALIEXPRESS_SYNC_SIGN_STYLE', 'legacy')));
        $this->syncSignStyle = in_array($ss, ['iop', 'legacy'], true) ? $ss : 'legacy';
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function isConfigured(): bool
    {
        return trim($this->appKey) !== ''
            && trim($this->appSecret) !== ''
            && trim((string) $this->accessToken) !== '';
    }

    /**
     * Create a new AliExpress listing — aliexpress.solution.product.post.
     *
     * @param  array<string, mixed>  $request
     * @return array{success: bool, message?: string, product_id?: string, data?: mixed}
     */
    public function postProduct(array $request): array
    {
        $brand = trim((string) ($request['brand_name'] ?? '')) ?: '5 Core Inc.';
        $request = $this->ensureAliExpressBrandAttribute($request, $brand);
        $weight = $this->aliexpressWeightNumber($request);
        $lb = $this->aliexpressWeightPounds($request, $weight);
        $categoryId = (int) ($request['aliexpress_category_id'] ?? 0);

        $schema = $this->getProductSchema($categoryId);
        $request = $this->applyCategorySkuAttributes($request, $schema);
        $weightFill = $this->fillWeightFromSchema($schema, $weight, $lb);
        $listedWeight = $this->remapWeightTemplate(
            is_array($request['listed_weight_template'] ?? null) ? $request['listed_weight_template'] : [],
            $weight,
            $lb
        );
        if ($listedWeight !== []) {
            $weightFill['fields'] = array_merge($listedWeight, $weightFill['fields']);
            if ($weightFill['keys'] === []) {
                $weightFill['keys'] = array_keys($listedWeight);
            }
        }
        $weightFill['fields'] = $this->ensureUsLogisticsWeightFields($weightFill['fields'], $weight, $lb, $weightFill['keys'], $weightFill['nodes']);
        $baseInstance = $this->buildOneSchemaInstance($request, $weight);
        $instance = array_merge($baseInstance, $weightFill['fields']);
        if (isset($baseInstance['category_attributes']) || isset($weightFill['fields']['category_attributes'])) {
            $instance['category_attributes'] = array_merge(
                is_array($baseInstance['category_attributes'] ?? null) ? $baseInstance['category_attributes'] : [],
                is_array($weightFill['fields']['category_attributes'] ?? null) ? $weightFill['fields']['category_attributes'] : []
            );
        }
        $instance = $this->forceUsLogisticsWeightObjects($instance, $weight, $lb);

        Log::info('AliExpress publish: schema weight fill', [
            'category_id' => $categoryId,
            'schema_ok' => $schema !== [],
            'schema_weight_keys' => $weightFill['keys'],
            'schema_weight_nodes' => $weightFill['nodes'],
            'weight_payload' => $weightFill['fields'],
            'weight_kg' => $weight,
            'weight_lb' => $lb,
            'us_package_weight_lb' => $this->usPackageWeightPounds($weight, $lb),
        ]);

        $last = [
            'success' => false,
            'message' => '',
            'data' => null,
        ];

        $official = $this->officialProductPostRequest($request, $weightFill['fields']);
        $encodedPost = $this->encodeRequestPayload($official);
        $postRes = $this->postProductCreateWithRetry('aliexpress.solution.product.post', [
            'post_product_request' => $encodedPost,
        ]);
        $productId = $this->extractPostedProductId($postRes['data'] ?? [])
            ?: $this->extractPostedProductId($postRes['result'] ?? [])
            ?: $this->extractPostedProductId($postRes)
            ?: (string) ($postRes['product_id'] ?? '');
        if ($productId !== '') {
            $postRes['success'] = true;
            $postRes['product_id'] = $productId;

            return $postRes;
        }
        $last = [
            'success' => false,
            'message' => $this->extractPostFailureMessage($postRes) ?: $last['message'],
            'data' => $postRes['data'] ?? $postRes['result'] ?? $last['data'] ?? null,
        ];

        if ($this->isAliExpressPackageSizeRequired((string) ($last['message'] ?? ''))) {
            $retried = $this->retryProductPostWithWeightShapes($instance, $official, $weight, $lb);
            if (! empty($retried['product_id'])) {
                return $retried;
            }
            if (trim((string) ($retried['message'] ?? '')) !== '') {
                $last['message'] = $retried['message'];
            }
            $instance = $retried['instance'] ?? $instance;
        }

        $skuCodes = [];
        foreach ($request['sku_info_list'] ?? [] as $row) {
            if (is_array($row)) {
                $skuCodes[] = (string) ($row['sku_code'] ?? '');
            }
        }
        $foundId = $this->findPostedProductIdBySkus($skuCodes);
        if ($foundId !== '') {
            return [
                'success' => true,
                'product_id' => $foundId,
                'message' => 'AliExpress created the product (found in seller catalog).',
            ];
        }

        if (str_contains(strtolower((string) ($last['message'] ?? '')), 'package weight')) {
            $schemaHint = [];
            foreach ($weightFill['nodes'] as $node) {
                $schemaHint[] = ($node['path'] ?? '').':'.($node['type'] ?? '?');
            }
            $last['message'] = trim((string) $last['message'])
                .' Sent usLogisticsWeight='.json_encode($instance['usLogisticsWeight'] ?? null)
                .' aeLogisticsWeight='.json_encode($instance['aeLogisticsWeight'] ?? null)
                .' package_weight='.json_encode($instance['package_weight'] ?? null)
                .' schema='.($schemaHint !== [] ? implode(',', $schemaHint) : implode(',', $weightFill['keys']));
        }

        return $last;
    }

    /**
     * Suggest a leaf category from title (and optional image).
     */
    public function suggestCategory(string $title, string $imageUrl = ''): ?int
    {
        $hit = $this->suggestCategoryMatch($title, $imageUrl);

        return ($hit['id'] ?? 0) > 0 ? (int) $hit['id'] : null;
    }

    /**
     * @param  list<string>  $hints
     * @return array{id: int, path: string}
     */
    public function suggestCategoryMatch(string $title, string $imageUrl = '', array $hints = []): array
    {
        $candidates = $this->suggestCategoryCandidates($title, $imageUrl);
        $extra = 0;
        foreach ($hints as $hint) {
            $hint = trim((string) $hint);
            if ($hint === '' || strcasecmp($hint, $title) === 0) {
                continue;
            }
            $candidates = array_merge($candidates, $this->suggestCategoryCandidates($hint, ''));
            $extra++;
            if ($extra >= 2) {
                break;
            }
        }

        return $this->pickBestSuggestedCategory($candidates, $hints !== [] ? $hints : [$title]);
    }

    /**
     * @return list<array{id: int, path: string}>
     */
    public function suggestCategoryCandidates(string $title, string $imageUrl = ''): array
    {
        $title = trim($title);
        if ($title === '') {
            return [];
        }

        $payloads = [
            ['title' => $title, 'language' => 'en'],
            ['subject' => $title, 'language' => 'en'],
            ['title' => $title, 'language' => 'en_US'],
        ];
        if ($imageUrl !== '') {
            $payloads[] = ['title' => $title, 'language' => 'en', 'image_url' => $imageUrl];
            $payloads[] = ['title' => $title, 'language' => 'en', 'imageUrl' => $imageUrl];
        }

        foreach ([
            'aliexpress.solution.product.category.suggest',
            'aliexpress.postproduct.redefining.categoryforecast',
        ] as $method) {
            foreach ($payloads as $params) {
                $res = $this->callApiFlexible($method, [
                    'rest' => $params,
                    'sync' => $params,
                ]);
                if (empty($res['success'])) {
                    continue;
                }
                $found = $this->extractSuggestedCategories($res['data'] ?? []);
                if ($found !== []) {
                    return $found;
                }
            }
        }

        return [];
    }

    /**
     * @param  list<array{id: int, path: string}>  $candidates
     * @param  list<string>  $hints
     * @return array{id: int, path: string}
     */
    private function pickBestSuggestedCategory(array $candidates, array $hints): array
    {
        $best = ['id' => 0, 'path' => '', 'score' => -1];
        $tokens = [];
        foreach ($hints as $hint) {
            foreach (preg_split('/[^a-z0-9]+/i', strtolower((string) $hint)) ?: [] as $token) {
                if (strlen($token) >= 3) {
                    $tokens[$token] = true;
                }
            }
        }
        foreach ($candidates as $row) {
            $id = (int) ($row['id'] ?? 0);
            $path = trim((string) ($row['path'] ?? ''));
            if ($id <= 0) {
                continue;
            }
            $hay = strtolower($path);
            $score = 1;
            foreach (array_keys($tokens) as $token) {
                if (str_contains($hay, $token)) {
                    $score += 4;
                }
            }
            if ($score > $best['score']) {
                $best = ['id' => $id, 'path' => $path !== '' ? $path : 'Category '.$id, 'score' => $score];
            }
        }

        return ['id' => (int) $best['id'], 'path' => (string) $best['path']];
    }

    /**
     * @return list<array{id: int, path: string}>
     */
    public function extractSuggestedCategories(mixed $data): array
    {
        $out = [];
        $seen = [];
        $walk = function ($node) use (&$out, &$seen, &$walk): void {
            if (! is_array($node)) {
                return;
            }
            $name = trim((string) ($node['name'] ?? $node['category_name'] ?? $node['categoryName'] ?? ''));
            $path = $node['name_path'] ?? $node['category_name_path'] ?? $node['path'] ?? $node['full_path'] ?? '';
            if (is_array($path)) {
                $path = implode(' / ', array_values(array_filter(array_map(static fn ($part) => trim((string) $part), $path))));
            }
            $path = trim((string) $path);
            $hasCategoryKey = isset($node['category_id']) || isset($node['leaf_category_id']) || isset($node['categoryId']);
            $id = (int) preg_replace('/\D+/', '', (string) (
                $node['category_id'] ?? $node['leaf_category_id'] ?? $node['categoryId'] ?? (($name !== '' || $path !== '' || $hasCategoryKey) ? ($node['id'] ?? '') : '')
            ));
            if ($id > 0 && ! isset($seen[$id])) {
                $seen[$id] = true;
                $out[] = [
                    'id' => $id,
                    'path' => $path !== '' ? $path : ($name !== '' ? $name : 'Category '.$id),
                ];
            }
            foreach ($node as $key => $child) {
                if (! is_array($child)) {
                    continue;
                }
                $keyLower = strtolower((string) $key);
                if (
                    str_contains($keyLower, 'categor')
                    || in_array($keyLower, ['result', 'data', 'list', 'suggest_category', 'suggest_category_list'], true)
                ) {
                    if ($child !== [] && array_is_list($child)) {
                        foreach ($child as $row) {
                            $walk($row);
                        }
                    } else {
                        $walk($child);
                    }
                }
            }
        };
        $walk(is_array($data) ? $data : []);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function extractCategoryId(mixed $data): ?int
    {
        if (! is_array($data)) {
            return null;
        }
        foreach ([
            'category_id', 'categoryId', 'leaf_category_id', 'leafCategoryId', 'cat_id',
        ] as $key) {
            $raw = $data[$key] ?? null;
            $id = (int) preg_replace('/\D+/', '', (string) $raw);
            if ($id > 0) {
                return $id;
            }
        }
        foreach (['result', 'category', 'data'] as $wrap) {
            if (isset($data[$wrap]) && is_array($data[$wrap])) {
                $nested = $this->extractCategoryId($data[$wrap]);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }
        $list = $data['category_list'] ?? $data['categorys'] ?? $data['forecast_category_list'] ?? [];
        if (is_array($list) && $list !== []) {
            $first = is_array($list[0] ?? null) ? $list[0] : (is_array(end($list)) ? end($list) : []);
            $nested = $this->extractCategoryId($first);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $data
     */
    private function extractPostedProductId(mixed $data, int $depth = 0): string
    {
        if ($depth > 8) {
            return '';
        }
        if (! is_array($data)) {
            $id = trim((string) $data);

            return preg_match('/^\d{8,}$/', $id) ? $id : '';
        }
        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);
            if (is_string($key) && (str_contains($keyLower, 'product_id') || in_array($keyLower, ['productid', 'item_id', 'itemid'], true))) {
                if (! is_array($value)) {
                    $id = trim((string) $value);
                    if (preg_match('/^\d{8,}$/', $id) && $id !== '0') {
                        return $id;
                    }
                }
            }
        }
        foreach ($data as $value) {
            if (! is_array($value)) {
                continue;
            }
            $nested = $this->extractPostedProductId($value, $depth + 1);
            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $res
     */
    private function extractPostFailureMessage(array $res): string
    {
        foreach ([$res['message'] ?? null, $this->extractBusinessResultError(is_array($res['data'] ?? null) ? $res['data'] : [])] as $msg) {
            $msg = trim((string) $msg);
            if ($msg !== '' && ! str_contains(strtolower($msg), 'did not return a product id')) {
                if (! str_contains(strtolower($msg), 'accepted the request')) {
                    return $msg;
                }
            }
        }

        $buckets = [
            $res['data'] ?? null,
            $res['result'] ?? null,
            $res['response'] ?? null,
        ];
        foreach ($buckets as $bucket) {
            if (! is_array($bucket)) {
                continue;
            }
            foreach (['error_message', 'error_msg', 'sub_msg', 'msg', 'message'] as $key) {
                $msg = trim((string) data_get($bucket, $key, ''));
                if ($msg !== '') {
                    return $msg;
                }
                $nested = trim((string) data_get($bucket, 'result.'.$key, ''));
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return 'AliExpress did not create a product. Check category ID, freight template, images, and brand permission, then try again.';
    }

    private function isRetryableProductPostError(string $message): bool
    {
        $m = strtolower($message);

        return $m === ''
            || str_contains($m, 'accepted the request')
            || str_contains($m, 'did not return a product')
            || $this->isTransientAliExpressError($message);
    }

    private function isTransientAliExpressError(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'rpc timeout')
            || str_contains($m, 'rpc time out')
            || str_contains($m, 'top-remote-connection-timeout')
            || str_contains($m, 'service timeout')
            || str_contains($m, 'timed out')
            || str_contains($m, 'try again later')
            || str_contains($m, 'system busy');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function postProductCreateWithRetry(string $method, array $params): array
    {
        $last = [];
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            if ($attempt > 1) {
                usleep(1_500_000);
                Log::info('AliExpress publish: retry create after transient error', [
                    'method' => $method,
                    'attempt' => $attempt,
                ]);
            }
            $res = $this->callApiFlexible($method, [
                'rest' => $params,
                'sync' => $params,
            ]);
            $last = $res;
            $productId = $this->extractPostedProductId($res['data'] ?? [])
                ?: $this->extractPostedProductId($res['result'] ?? [])
                ?: $this->extractPostedProductId($res);
            if ($productId !== '') {
                $res['success'] = true;
                $res['product_id'] = $productId;

                return $res;
            }
            if (! $this->isTransientAliExpressError($this->extractPostFailureMessage($res))) {
                return $res;
            }
        }

        return $last;
    }

    /**
     * AliExpress requires brand_name, or attribute_list with aliexpress_attribute_name_id = 2.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function ensureAliExpressBrandAttribute(array $request, string $brand): array
    {
        $brand = trim($brand) !== '' ? trim($brand) : '5 Core Inc.';
        $request['brand_name'] = $brand;
        $attrs = is_array($request['attribute_list'] ?? null) ? $request['attribute_list'] : [];
        $hasBrandId = false;
        foreach ($attrs as $row) {
            if (is_array($row) && (int) ($row['aliexpress_attribute_name_id'] ?? 0) === 2) {
                $hasBrandId = true;
                break;
            }
        }
        if (! $hasBrandId) {
            array_unshift($attrs, [
                'aliexpress_attribute_name_id' => 2,
                'attribute_name' => 'Brand Name',
                'attribute_value' => $brand,
            ]);
        }
        $request['attribute_list'] = $attrs;

        return $request;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function aliexpressWeightNumber(array $request): float
    {
        foreach (['package_weight', 'weight'] as $key) {
            $value = $request[$key] ?? null;
            if (is_numeric($value) && (float) $value > 0) {
                return round((float) $value, 3);
            }
        }
        $lb = $this->aliexpressWeightPounds($request, 0.0);
        if ($lb > 0) {
            return round($lb * 0.45359237, 3);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function aliexpressWeightPounds(array $request, float $kg): float
    {
        $lb = $request['weight_lb'] ?? null;
        if (is_numeric($lb) && (float) $lb > 0) {
            return round((float) $lb, 3);
        }
        foreach (['usLogisticsWeight', 'aeLogisticsWeight'] as $key) {
            $value = $request[$key] ?? null;
            if (is_array($value)) {
                $value = $value['Package weight'] ?? $value['weight'] ?? $value['value'] ?? null;
            }
            if (is_numeric($value) && (float) $value > 0) {
                return round((float) $value, 3);
            }
        }

        return $kg > 0 ? round($kg / 0.45359237, 3) : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProductSchema(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $cached = Cache::get('aliexpress.product_schema.'.$categoryId);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $last = [];
        foreach ([
            ['aliexpress_category_id' => $categoryId],
            ['category_id' => $categoryId],
        ] as $params) {
            $res = $this->callApiFlexible('aliexpress.solution.product.schema.get', [
                'rest' => $params,
                'sync' => $params,
            ]);
            $last = $res;
            $data = is_array($res['data'] ?? null) ? $res['data'] : [];
            $payload = $this->unwrapSolutionEnvelope($data);
            $schema = $this->extractSchemaJson($payload);
            if ($schema === []) {
                $schema = $this->extractSchemaJson($res);
            }
            if ($schema !== []) {
                Cache::put('aliexpress.product_schema.'.$categoryId, $schema, 1800);

                return $schema;
            }
        }

        Log::warning('AliExpress publish: schema.get returned no schema', [
            'category_id' => $categoryId,
            'message' => $last['message'] ?? null,
        ]);

        return [];
    }

    /**
     * Replace invalid names like "Specification" with a SKU attribute this category allows.
     *
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function applyCategorySkuAttributes(array $request, array $schema): array
    {
        $skus = $request['sku_info_list'] ?? [];
        if (! is_array($skus) || $skus === []) {
            return $request;
        }
        $categoryId = (int) ($request['aliexpress_category_id'] ?? 0);
        $attrs = $this->queryCategorySkuAttributes($categoryId);
        if ($attrs === []) {
            $attrs = $this->skuAttributesFromSchema($schema);
        }
        $diff = $this->pickVariationSkuAttribute($attrs) ?? [
            'name' => 'Color',
            'required' => false,
            'custom_name' => true,
            'custom_pic' => true,
            'values' => [],
        ];

        $toApply = [];
        $seen = [];
        foreach ($attrs as $attr) {
            $key = strtolower((string) ($attr['name'] ?? ''));
            if ($key === '' || empty($attr['required']) || isset($seen[$key])) {
                continue;
            }
            $toApply[] = $attr;
            $seen[$key] = true;
        }
        if (! isset($seen[strtolower((string) $diff['name'])])) {
            array_unshift($toApply, $diff);
        }

        $used = [];
        foreach ($skus as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $desired = $this->existingSkuAttributeValue($row) ?: (string) ($row['sku_code'] ?? '');
            $list = [];
            foreach ($toApply as $attr) {
                $isDiff = strcasecmp((string) $attr['name'], (string) $diff['name']) === 0;
                $value = $isDiff
                    ? $this->uniqueSkuAttributeValue($attr, $desired, $used)
                    : $this->fixedSkuAttributeValue($attr);
                if ($isDiff) {
                    $used[] = strtolower($value);
                }
                $item = [
                    'sku_attribute_name' => (string) $attr['name'],
                    'sku_attribute_value' => $value,
                ];
                $image = (string) ($row['sku_attributes_list'][0]['sku_image_url'] ?? $row['sku_image_url'] ?? '');
                if ($isDiff && $image !== '') {
                    $item['sku_image_url'] = $image;
                }
                $list[] = $item;
            }
            $skus[$i]['sku_attributes_list'] = $list;
        }
        $request['sku_info_list'] = $skus;

        Log::info('AliExpress publish: mapped category SKU attributes', [
            'category_id' => $categoryId,
            'attribute_names' => array_column($toApply, 'name'),
            'diff_attribute' => $diff['name'],
        ]);

        return $request;
    }

    /**
     * @return list<array{name: string, required: bool, custom_name: bool, custom_pic: bool, values: list<string>}>
     */
    public function queryCategorySkuAttributes(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $cached = Cache::get('aliexpress.sku_attributes.'.$categoryId);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $query = ['aliexpress_category_id' => $categoryId];
        $res = $this->callApiFlexible('aliexpress.solution.sku.attribute.query', [
            'rest' => ['query_sku_attribute_info_request' => $query],
            'sync' => ['query_sku_attribute_info_request' => $query],
        ]);
        $parsed = $this->parseSkuAttributeQuery($res);
        if ($parsed !== []) {
            Cache::put('aliexpress.sku_attributes.'.$categoryId, $parsed, 1800);

            return $parsed;
        }
        if (! empty($res['network_error'])) {
            return $this->queryChildCategorySkuAttributes($categoryId);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $res
     * @return list<array{name: string, required: bool, custom_name: bool, custom_pic: bool, values: list<string>}>
     */
    private function parseSkuAttributeQuery(array $res): array
    {
        $data = is_array($res['data'] ?? null) ? $res['data'] : (is_array($res['result'] ?? null) ? $res['result'] : []);
        $data = $this->unwrapSolutionEnvelope($data);
        $result = is_array($data['result'] ?? null) ? $data['result'] : $data;
        $list = $result['supporting_sku_attribute_list'] ?? [];
        if (isset($list['supported_sku_attribute_dto'])) {
            $list = $list['supported_sku_attribute_dto'];
        }
        if (! is_array($list)) {
            return [];
        }
        if ($list !== [] && ! isset($list[0]) && isset($list['aliexpress_sku_name'])) {
            $list = [$list];
        }

        $out = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['aliexpress_sku_name'] ?? $row['sku_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $values = $row['aliexpress_sku_value_list'] ?? [];
            if (isset($values['sku_value_simplified_info_dto'])) {
                $values = $values['sku_value_simplified_info_dto'];
            }
            $valueNames = [];
            foreach ((array) $values as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $valueNames[] = trim($value);
                } elseif (is_array($value)) {
                    $label = trim((string) ($value['aliexpress_sku_value_name'] ?? $value['sku_value_name'] ?? ''));
                    if ($label !== '') {
                        $valueNames[] = $label;
                    }
                }
            }
            $out[] = [
                'name' => $name,
                'required' => filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'custom_name' => filter_var($row['support_customized_name'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'custom_pic' => filter_var($row['support_customized_picture'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'values' => $valueNames,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{name: string, required: bool, custom_name: bool, custom_pic: bool, values: list<string>}>
     */
    private function queryChildCategorySkuAttributes(int $categoryId): array
    {
        foreach ([
            ['param0' => $categoryId],
            ['cate_id' => $categoryId],
            ['leaf_category_id' => $categoryId],
        ] as $params) {
            $res = $this->callApiFlexible('aliexpress.category.redefining.getchildattributesresultbypostcateidandpath', [
                'rest' => $params,
                'sync' => $params,
            ]);
            $parsed = $this->parseChildCategorySkuAttributes($res);
            if ($parsed !== []) {
                return $parsed;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $res
     * @return list<array{name: string, required: bool, custom_name: bool, custom_pic: bool, values: list<string>}>
     */
    private function parseChildCategorySkuAttributes(array $res): array
    {
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $data = $this->unwrapSolutionEnvelope($data);
        $result = is_array($data['result'] ?? null) ? $data['result'] : $data;
        $list = $result['attributes'] ?? $result['aeop_attribute_dto'] ?? $result['attribute_list'] ?? [];
        if (isset($list['aeop_attribute_dto'])) {
            $list = $list['aeop_attribute_dto'];
        }
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $isSku = filter_var($row['sku'] ?? $row['is_sku'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (! $isSku) {
                continue;
            }
            $names = $row['names'] ?? [];
            $name = trim((string) (
                $row['name']
                ?? (is_array($names) ? ($names['en'] ?? $names['EN'] ?? reset($names) ?: '') : '')
            ));
            if ($name === '') {
                continue;
            }
            $valueNames = [];
            $values = $row['values'] ?? $row['aeop_attr_value_dto'] ?? [];
            if (isset($values['aeop_attr_value_dto'])) {
                $values = $values['aeop_attr_value_dto'];
            }
            foreach ((array) $values as $value) {
                if (! is_array($value)) {
                    continue;
                }
                $labels = $value['names'] ?? [];
                $label = trim((string) (
                    $value['name']
                    ?? (is_array($labels) ? ($labels['en'] ?? $labels['EN'] ?? reset($labels) ?: '') : '')
                ));
                if ($label !== '') {
                    $valueNames[] = $label;
                }
            }
            $out[] = [
                'name' => $name,
                'required' => filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'custom_name' => filter_var($row['customized_name'] ?? $row['customizedName'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'custom_pic' => filter_var($row['customized_pic'] ?? $row['customizedPic'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'values' => $valueNames,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<array{name: string, required: bool, custom_name: bool, custom_pic: bool, values: list<string>}>
     */
    private function skuAttributesFromSchema(array $schema): array
    {
        $props = $this->findSchemaSkuAttributeProperties($schema);
        if ($props === []) {
            return [];
        }
        $out = [];
        foreach ($props as $name => $node) {
            if (! is_string($name) || $name === '' || ! is_array($node)) {
                continue;
            }
            $enum = $node['enum'] ?? [];
            $values = [];
            foreach ((array) $enum as $one) {
                if (is_scalar($one) && trim((string) $one) !== '') {
                    $values[] = trim((string) $one);
                }
            }
            $out[] = [
                'name' => $name,
                'required' => false,
                'custom_name' => true,
                'custom_pic' => true,
                'values' => $values,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function findSchemaSkuAttributeProperties(array $schema): array
    {
        $stack = [$schema];
        while ($stack !== []) {
            $node = array_pop($stack);
            if (! is_array($node)) {
                continue;
            }
            if (isset($node['sku_attributes']['properties']) && is_array($node['sku_attributes']['properties'])) {
                return $node['sku_attributes']['properties'];
            }
            if (isset($node['properties']['sku_attributes']['properties']) && is_array($node['properties']['sku_attributes']['properties'])) {
                return $node['properties']['sku_attributes']['properties'];
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return [];
    }

    /**
     * @param  list<array{name: string, required: bool, custom_name: bool, custom_pic: bool, values: list<string>}>  $attrs
     * @return array{name: string, required: bool, custom_name: bool, custom_pic: bool, values: list<string>}|null
     */
    private function pickVariationSkuAttribute(array $attrs): ?array
    {
        if ($attrs === []) {
            return null;
        }
        $ranked = $attrs;
        usort($ranked, function (array $a, array $b): int {
            return $this->skuAttributeScore($b) <=> $this->skuAttributeScore($a);
        });

        return $ranked[0];
    }

    /**
     * @param  array{name: string, required?: bool, custom_name?: bool, custom_pic?: bool, values?: list<string>}  $attr
     */
    private function skuAttributeScore(array $attr): int
    {
        $name = strtolower((string) ($attr['name'] ?? ''));
        $score = 0;
        if (! empty($attr['custom_name'])) {
            $score += 20;
        }
        if (! empty($attr['custom_pic'])) {
            $score += 5;
        }
        if (! empty($attr['required'])) {
            $score += 8;
        }
        if (in_array($name, ['color', 'colour'], true)) {
            $score += 15;
        } elseif (in_array($name, ['model', 'style', 'type', 'plug type'], true)) {
            $score += 8;
        }
        if (str_contains($name, 'ship') || str_contains($name, 'from') || str_contains($name, 'warehouse')) {
            $score -= 40;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function existingSkuAttributeValue(array $row): string
    {
        foreach ($row['sku_attributes_list'] ?? [] as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $value = trim((string) ($attr['sku_attribute_value'] ?? ''));
            if ($value !== '') {
                return mb_substr($value, 0, 70);
            }
        }

        return '';
    }

    /**
     * @param  array{name: string, custom_name?: bool, values?: list<string>}  $attr
     * @param  list<string>  $used
     */
    private function uniqueSkuAttributeValue(array $attr, string $desired, array $used): string
    {
        $desired = mb_substr(trim($desired), 0, 70);
        if ($desired === '') {
            $desired = 'Option';
        }
        if (! empty($attr['custom_name'])) {
            $value = $desired;
            $n = 2;
            while (in_array(strtolower($value), $used, true)) {
                $value = mb_substr($desired.' '.$n, 0, 70);
                $n++;
            }

            return $value;
        }

        foreach ($attr['values'] ?? [] as $value) {
            $value = trim((string) $value);
            if ($value !== '' && ! in_array(strtolower($value), $used, true)) {
                if (strcasecmp($value, $desired) === 0 || stripos($desired, $value) !== false) {
                    return $value;
                }
            }
        }
        foreach ($attr['values'] ?? [] as $value) {
            $value = trim((string) $value);
            if ($value !== '' && ! in_array(strtolower($value), $used, true)) {
                return $value;
            }
        }

        return $desired;
    }

    /**
     * @param  array{name: string, values?: list<string>}  $attr
     */
    private function fixedSkuAttributeValue(array $attr): string
    {
        $name = strtolower((string) ($attr['name'] ?? ''));
        $values = array_values(array_filter(array_map('strval', $attr['values'] ?? [])));
        if (str_contains($name, 'ship') || str_contains($name, 'from') || str_contains($name, 'warehouse')) {
            foreach (['United States', 'USA', 'US', 'China'] as $want) {
                foreach ($values as $value) {
                    if (strcasecmp($value, $want) === 0) {
                        return $value;
                    }
                }
            }
        }

        return $values[0] ?? 'Default';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSchemaJson(mixed $payload): array
    {
        if (is_string($payload)) {
            $trimmed = trim($payload);
            if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
                return [];
            }
            $decoded = json_decode($trimmed, true);

            return is_array($decoded) ? $this->extractSchemaJson($decoded) : [];
        }
        if (! is_array($payload) || $payload === []) {
            return [];
        }
        foreach (['schema', 'result', 'data'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $found = $this->extractSchemaJson($payload[$key]);
            if ($found !== []) {
                return $found;
            }
        }
        if (isset($payload['properties']) && is_array($payload['properties']) && $payload['properties'] !== []) {
            return $payload;
        }
        if (isset($payload['fields']) && is_array($payload['fields']) && $payload['fields'] !== []) {
            return $payload;
        }
        if (array_is_list($payload)) {
            $named = [];
            foreach ($payload as $i => $node) {
                if (! is_array($node)) {
                    continue;
                }
                $named[$this->schemaPropertyName((string) $i, $node)] = $node;
            }
            if (count($named) >= 3) {
                return ['properties' => $named];
            }
        }
        if ($this->payloadLooksLikeSchemaProperties($payload)) {
            return ['properties' => $this->schemaFieldsOnly($payload)];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadLooksLikeSchemaProperties(array $payload): bool
    {
        $nodes = 0;
        foreach ($payload as $key => $value) {
            if (in_array((string) $key, ['success', 'error', 'code', 'message', 'msg', 'request_id', 'requestId'], true)) {
                continue;
            }
            if (! is_array($value)) {
                return false;
            }
            if (isset($value['type']) || isset($value['title']) || isset($value['properties']) || isset($value['required']) || isset($value['id'])) {
                $nodes++;
            }
        }

        return $nodes >= 3;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function schemaFieldsOnly(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if (! is_array($value) || in_array((string) $key, ['success', 'error', 'code', 'message', 'msg', 'request_id', 'requestId'], true)) {
                continue;
            }
            $out[$this->schemaPropertyName((string) $key, $value)] = $value;
        }

        return $out;
    }

    /**
     * Fill every schema weight node from Dim/Wt, using that node's type and path.
     *
     * @param  array<string, mixed>  $schema
     * @return array{keys: list<string>, fields: array<string, mixed>, nodes: list<array<string, mixed>>}
     */
    private function fillWeightFromSchema(array $schema, float $kg, float $lb): array
    {
        $hits = $this->findSchemaWeightNodes($schema);
        $fields = [];
        $keys = [];
        $nodes = [];
        foreach ($hits as $hit) {
            $keys[] = $hit['path'];
            $nodes[] = [
                'path' => $hit['path'],
                'type' => $this->schemaNodeType($hit['node']),
                'required' => $hit['node']['required'] ?? null,
                'property_keys' => array_keys(is_array($hit['node']['properties'] ?? null) ? $hit['node']['properties'] : []),
            ];
            $this->setNestedField($fields, $hit['path'], $this->fillSchemaNode($hit['node'], $kg, $lb, $hit['path']));
        }
        if ($fields === [] && ($kg > 0 || $lb > 0)) {
            $keys = ['package_weight', 'aeLogisticsWeight', 'usLogisticsWeight'];
            $fields = [
                'package_weight' => $this->formatMarketplaceWeight($kg, $lb, 'kg', 'number'),
                'aeLogisticsWeight' => $this->usPackageWeightObject($kg, $lb),
                'usLogisticsWeight' => $this->usPackageWeightObject($kg, $lb),
            ];
        }

        return ['keys' => $keys, 'fields' => $fields, 'nodes' => $nodes];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<array{path: string, node: array<string, mixed>}>
     */
    public function debugSchemaWeightNodes(array $schema): array
    {
        return [
            'schema_top_keys' => array_slice(array_keys($schema), 0, 40),
            'weight_nodes' => $this->findSchemaWeightNodes($schema),
        ];
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<string, mixed>
     */
    public function debugProductWeightFields(array $info): array
    {
        $found = [];
        $walk = function ($node, string $path) use (&$walk, &$found): void {
            if (! is_array($node)) {
                if ($path !== '' && preg_match('/weight|package|logistic|usl/i', $path)) {
                    $found[$path] = $node;
                }

                return;
            }
            foreach ($node as $key => $value) {
                $child = $path === '' ? (string) $key : $path.'.'.$key;
                if (is_array($value)) {
                    $walk($value, $child);
                } elseif (preg_match('/weight|package|logistic|usl/i', $child)) {
                    $found[$child] = $value;
                }
            }
        };
        $walk($info, '');

        return ['success' => $info['success'] ?? null, 'message' => $info['message'] ?? null, 'weight_fields' => $found];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<array{path: string, node: array<string, mixed>}>
     */
    private function findSchemaWeightNodes(array $schema): array
    {
        $hits = [];
        $walk = function ($node, string $path) use (&$walk, &$hits): void {
            if (! is_array($node)) {
                return;
            }
            $name = $path !== '' ? (string) last(explode('.', $path)) : '';
            if ($name !== '' && $this->isSchemaWeightProperty($name, $node)) {
                $hits[] = ['path' => $path, 'node' => $node];
            }
            foreach (['properties', 'fields'] as $bag) {
                if (is_array($node[$bag] ?? null)) {
                    foreach ($node[$bag] as $key => $child) {
                        if (is_array($child)) {
                            $childName = $this->schemaPropertyName($key, $child);
                            $walk($child, $path === '' ? $childName : $path.'.'.$childName);
                        }
                    }
                }
            }
            foreach (['items', 'additionalProperties', 'oneOf', 'anyOf', 'allOf'] as $bag) {
                if (is_array($node[$bag] ?? null)) {
                    $walk($node[$bag], $path);
                }
            }
        };
        $walk($this->schemaPropertyMap($schema) ?: $schema, '');

        return $hits;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function setNestedField(array &$fields, string $path, mixed $value): void
    {
        $parts = array_values(array_filter(explode('.', $path), fn ($p) => $p !== ''));
        if ($parts === []) {
            return;
        }
        $ref =& $fields;
        $last = array_pop($parts);
        foreach ($parts as $part) {
            if (! isset($ref[$part]) || ! is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref =& $ref[$part];
        }
        $ref[$last] = $value;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function schemaPropertyMap(array $schema): array
    {
        if (is_array($schema['properties'] ?? null) && $schema['properties'] !== []) {
            return $schema['properties'];
        }
        if (is_array($schema['fields'] ?? null) && $schema['fields'] !== []) {
            return $schema['fields'];
        }
        $looksLikeProps = true;
        foreach ($schema as $value) {
            if (! is_array($value) || (! isset($value['type']) && ! isset($value['title']) && ! isset($value['properties']))) {
                $looksLikeProps = false;
                break;
            }
        }

        return $looksLikeProps ? $schema : [];
    }

    /**
     * AliExpress schema.get often returns properties as a list; the real name is id/name/title.
     *
     * @param  array<string, mixed>  $node
     */
    private function schemaPropertyName(string|int $key, array $node): string
    {
        foreach (['id', 'name', 'title', 'key'] as $field) {
            $value = trim((string) ($node[$field] ?? ''));
            if ($value !== '' && ! is_numeric($value)) {
                return $value;
            }
        }

        return (string) $key;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function isSchemaWeightProperty(string $name, array $node): bool
    {
        $hay = strtolower($name.' '.((string) ($node['title'] ?? '')).' '.((string) ($node['id'] ?? '')));
        if (str_contains($hay, 'unit') && ! str_contains($hay, 'weight')) {
            return false;
        }

        return str_contains($hay, 'weight')
            || str_contains($hay, 'logisticsweight')
            || $name === 'usl'
            || $name === 'usl.logisticsWeight';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function fillSchemaNode(array $node, float $kg, float $lb, string $path = ''): mixed
    {
        $type = $this->schemaNodeType($node);
        $props = is_array($node['properties'] ?? null) ? $node['properties'] : [];
        if ($props === [] && is_array($node['fields'] ?? null)) {
            $props = $node['fields'];
        }

        if ($type === 'object' || $props !== []) {
            $out = [];
            $required = is_array($node['required'] ?? null) ? $node['required'] : [];
            foreach ($props as $name => $child) {
                if (! is_array($child)) {
                    continue;
                }
                $childName = $this->schemaPropertyName($name, $child);
                $childPath = $path === '' ? $childName : $path.'.'.$childName;
                $needed = $required === []
                    || in_array($childName, $required, true)
                    || $this->isSchemaWeightProperty($childName, $child)
                    || $this->isSchemaUnitProperty($childName, $child);
                if (! $needed) {
                    continue;
                }
                $out[$childName] = $this->isSchemaUnitProperty($childName, $child)
                    ? $this->schemaUnitValue($child, $childPath)
                    : $this->fillSchemaNode($child, $kg, $lb, $childPath);
            }
            if ($out === [] || ($this->isUsLogisticsWeightParent($path) && ! $this->hasPackageWeightKey($out))) {
                $out = array_merge($out, $this->usPackageWeightObject($kg, $lb));
            }

            return $out;
        }

        if ($this->isPackageWeightLeaf($path)) {
            return $this->packageWeightLeafValue($node, $kg, $lb);
        }

        $unit = $this->inferWeightUnit($path, $node);

        return $this->formatMarketplaceWeight($kg, $lb, $unit, $this->marketplaceWeightKind($unit, $node, $type), $node);
    }

    private function isPackageWeightLeaf(string $path): bool
    {
        $name = strtolower((string) last(explode('.', $path)));

        return $name === 'package weight'
            || ($name === 'package_weight' && str_contains(strtolower($path), 'logistics'));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function packageWeightLeafValue(array $node, float $kg, float $lb): float|string
    {
        $pounds = $this->usPackageWeightPounds($kg, $lb);
        $type = $this->schemaNodeType($node);
        if ($type === 'integer') {
            return (int) max(1, round($pounds));
        }
        if ($type === 'number') {
            return $pounds;
        }

        return number_format($pounds, 2, '.', '');
    }

    private function isUsLogisticsWeightParent(string $path): bool
    {
        $hay = strtolower($path);

        return str_contains($hay, 'uslogisticsweight')
            || str_contains($hay, 'aelogisticsweight')
            || str_contains($hay, 'logisticsweight')
            || $hay === 'usl'
            || str_ends_with($hay, '.usl');
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function hasPackageWeightKey(array $fields): bool
    {
        foreach ($fields as $key => $value) {
            if (strcasecmp((string) $key, 'Package weight') === 0 && $value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * US overlay wants only {"Package weight":"3.35"}. Extra keys fail schema checks
     * and AliExpress then reports Package weight as CHK_BASIC_REQUIRED.
     *
     * @return array{Package weight: float|string}
     */
    private function usPackageWeightObject(float $kg, float $lb, string $kind = 'string'): array
    {
        $pounds = $this->usPackageWeightPounds($kg, $lb);
        if ($kind === 'integer') {
            return ['Package weight' => (int) max(1, round($pounds))];
        }
        if ($kind === 'number') {
            return ['Package weight' => $pounds];
        }

        return ['Package weight' => number_format($pounds, 2, '.', '')];
    }

    private function usPackageWeightPounds(float $kg, float $lb): float
    {
        $raw = $lb > 0 ? $lb : ($kg > 0 ? $kg / 0.45359237 : 0.0);

        return (float) number_format(max(0.01, abs($raw)), 2, '.', '');
    }

    /**
     * Fill schema weight paths. Leaf US fields are a pounds number; objects keep Package weight.
     *
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $schemaKeys
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    private function ensureUsLogisticsWeightFields(array $fields, float $kg, float $lb, array $schemaKeys = [], array $nodes = []): array
    {
        $kg = abs($kg);
        $lb = abs($lb);
        if ($kg <= 0 && $lb <= 0) {
            return $fields;
        }
        $kind = $this->packageWeightSchemaKind($nodes);

        return $this->applyLogisticsWeightShape($fields, $this->usPackageWeightObject($kg, $lb, $kind), $kg, $lb);
    }

    /**
     * AliExpress US checks usLogisticsWeight.Package weight. A bare "4.00" string
     * is treated as missing and returns CHK_BASIC_REQUIRED.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function forceUsLogisticsWeightObjects(array $fields, float $kg, float $lb, string $kind = 'string'): array
    {
        $kg = abs($kg);
        $lb = abs($lb);
        if ($kg <= 0 && $lb <= 0) {
            return $fields;
        }

        return $this->applyLogisticsWeightShape($fields, $this->usPackageWeightObject($kg, $lb, $kind), $kg, $lb);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array{Package weight: float|string}|string  $usObject
     * @return array<string, mixed>
     */
    private function applyLogisticsWeightShape(array $fields, mixed $usObject, float $kg, float $lb): array
    {
        foreach (['usLogisticsWeight', 'aeLogisticsWeight', 'LogisticsWeight', 'logisticsWeight'] as $key) {
            $fields[$key] = $usObject;
        }
        $fields['usl'] = ['logisticsWeight' => $usObject];
        unset($fields['Package weight']);
        $fields['package_weight'] = $this->formatMarketplaceWeight($kg, $lb, 'kg', 'number');
        if (is_array($usObject)) {
            $fields = $this->copyUsWeightOntoSkus($fields, $usObject);
        }

        return $fields;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function packageWeightSchemaKind(array $nodes): string
    {
        foreach ($nodes as $node) {
            $path = strtolower((string) ($node['path'] ?? ''));
            $type = strtolower((string) ($node['type'] ?? ''));
            if ($type === '' || ! str_contains($path, 'package weight')) {
                continue;
            }
            if ($type === 'number' || $type === 'integer') {
                return $type;
            }
            if ($type === 'string') {
                return 'string';
            }
        }

        return 'string';
    }

    private function hasValidLogisticsWeight(mixed $value): bool
    {
        if (is_array($value)) {
            if ($this->hasPackageWeightKey($value)) {
                return $this->hasValidLogisticsWeight($value['Package weight'] ?? $value['package_weight'] ?? null);
            }
            if (isset($value['logisticsWeight'])) {
                return $this->hasValidLogisticsWeight($value['logisticsWeight']);
            }

            return false;
        }
        if (is_numeric($value)) {
            return (float) $value > 0;
        }
        if (is_string($value)) {
            $trim = trim($value);

            return $trim !== '' && is_numeric($trim) && (float) $trim > 0;
        }

        return false;
    }

    /**
     * Keep a listed sibling's exact weight JSON shape, swapping in this SKU's Dim/Wt values.
     *
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public function weightFieldsFromListedProduct(array $info): array
    {
        $out = [];
        $bags = [$info];
        if (is_array($info['logistics_size'] ?? null)) {
            $bags[] = $info['logistics_size'];
        }
        if (is_array($info['product'] ?? null)) {
            $bags[] = $info['product'];
        }
        foreach ($bags as $bag) {
            foreach (['aeLogisticsWeight', 'usLogisticsWeight', 'package_weight', 'weight', 'usl', 'Package weight', 'gross_weight'] as $key) {
                if (! array_key_exists($key, $bag) || $bag[$key] === null || $bag[$key] === '' || array_key_exists($key, $out)) {
                    continue;
                }
                $value = $bag[$key];
                if (is_string($value)) {
                    $trim = trim($value);
                    if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                        $decoded = json_decode($trim, true);
                        if (is_array($decoded)) {
                            $value = $decoded;
                        }
                    }
                }
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function remapWeightTemplate(array $template, float $kg, float $lb): array
    {
        if ($template === []) {
            return [];
        }
        $pounds = $this->usPackageWeightPounds($kg, $lb);
        $kgValue = max(0.001, abs($kg));

        $walk = function ($value, string $key) use (&$walk, $pounds, $kgValue) {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $childKey => $child) {
                    $out[$childKey] = $walk($child, (string) $childKey);
                }

                return $out;
            }
            $useLb = str_contains(strtolower($key), 'package weight')
                || str_contains(strtolower($key), 'uslogistics')
                || str_contains(strtolower($key), 'aelogistics');
            $number = $useLb ? $pounds : $kgValue;

            return $this->weightValueSameType($value, $number);
        };

        $out = [];
        foreach ($template as $key => $value) {
            $out[$key] = $walk($value, (string) $key);
        }

        return $out;
    }

    private function weightValueSameType(mixed $sample, float $number): float|int|string
    {
        $text = number_format($number, is_int($sample) ? 0 : 2, '.', '');
        if (is_string($sample)) {
            return $text;
        }
        if (is_int($sample)) {
            return (int) max(1, round($number));
        }

        return (float) $text;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array{Package weight: string}  $usObject
     * @return array<string, mixed>
     */
    private function copyUsWeightOntoSkus(array $fields, array $usObject): array
    {
        $skus = $fields['sku_info_list'] ?? null;
        if (! is_array($skus) || $skus === []) {
            return $fields;
        }
        foreach ($skus as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $skus[$i]['aeLogisticsWeight'] = $usObject;
            $skus[$i]['usLogisticsWeight'] = $usObject;
            $skus[$i]['package_weight'] = $fields['package_weight'] ?? $row['package_weight'] ?? null;
        }
        $fields['sku_info_list'] = $skus;

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $instance
     * @param  array<string, mixed>  $official
     * @return array{success?: bool, product_id?: string, message?: string, instance?: array<string, mixed>}
     */
    private function retryProductPostWithWeightShapes(array $instance, array $official, float $kg, float $lb): array
    {
        $text = number_format($this->usPackageWeightPounds($kg, $lb), 2, '.', '');
        $stringObject = $this->usPackageWeightObject($kg, $lb, 'string');
        $jsonString = json_encode($stringObject, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"Package weight":"'.$text.'"}';
        $lastMessage = '';
        $lastInstance = $this->applyLogisticsWeightShape($instance, $stringObject, $kg, $lb);
        $tryOfficial = $this->applyLogisticsWeightShape($official, $jsonString, $kg, $lb);
        $postRes = $this->callApiFlexible('aliexpress.solution.product.post', [
            'rest' => ['post_product_request' => $this->encodeRequestPayload($tryOfficial)],
            'sync' => ['post_product_request' => $this->encodeRequestPayload($tryOfficial)],
        ]);
        $productId = $this->extractPostedProductId($postRes['data'] ?? [])
            ?: $this->extractPostedProductId($postRes['result'] ?? [])
            ?: $this->extractPostedProductId($postRes)
            ?: (string) ($postRes['product_id'] ?? '');
        if ($productId !== '') {
            return ['success' => true, 'product_id' => $productId, 'instance' => $lastInstance];
        }
        $lastMessage = $this->extractPostFailureMessage($postRes) ?: $lastMessage;
        $lastInstance = $this->applyLogisticsWeightShape($instance, $jsonString, $kg, $lb);

        return ['message' => $lastMessage, 'instance' => $lastInstance];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function schemaNodeIsObject(array $nodes, string $path): bool
    {
        foreach ($nodes as $node) {
            if (strcasecmp((string) ($node['path'] ?? ''), $path) !== 0) {
                continue;
            }

            return ($node['type'] ?? '') === 'object'
                || (is_array($node['property_keys'] ?? null) && $node['property_keys'] !== []);
        }

        return false;
    }

    private function normalizeLogisticsWeightValue(mixed $current, float $kg, float $lb, bool $asObject): mixed
    {
        if ($asObject) {
            $current = is_array($current) ? $current : [];

            return array_merge($current, $this->usPackageWeightObject($kg, $lb));
        }
        if (is_array($current)) {
            $current = $current['Package weight'] ?? $current['weight'] ?? $current['value'] ?? null;
        }
        if (is_numeric($current) && (float) $current > 0) {
            return (float) number_format((float) $current, 2, '.', '');
        }
        if (is_string($current) && is_numeric(trim($current)) && (float) $current > 0) {
            return $current;
        }

        return $this->usPackageWeightPounds($kg, $lb);
    }

    /**
     * @param  list<string>  $schemaKeys
     */
    private function schemaKeysMention(array $schemaKeys, string $needle): bool
    {
        $needle = strtolower($needle);
        foreach ($schemaKeys as $key) {
            if (str_contains(strtolower((string) $key), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * US package weight is a 2-decimal string. Official kg weight stays a number unless the schema says string.
     *
     * @param  array<string, mixed>  $node
     */
    private function marketplaceWeightKind(string $unit, array $node, string $type): string
    {
        if ($type === 'integer' || $this->schemaNodeType($node) === 'integer') {
            return 'integer';
        }
        if (in_array($unit, ['lb', 'lbs', 'pound', 'pounds', 'oz', 'ounce', 'ounces'], true)) {
            return 'string';
        }
        if ($type === 'string' || $this->schemaPrefersString($node)) {
            return 'string';
        }

        return 'number';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function schemaNodeType(array $node): string
    {
        $type = $node['type'] ?? null;
        if (is_array($type)) {
            foreach ($type as $one) {
                $one = strtolower((string) $one);
                if (in_array($one, ['object', 'number', 'integer', 'string'], true)) {
                    return $one;
                }
            }

            return 'number';
        }
        $type = strtolower((string) $type);
        if ($type !== '') {
            return $type;
        }

        return isset($node['properties']) ? 'object' : 'number';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function isSchemaUnitProperty(string $name, array $node): bool
    {
        $hay = strtolower($name.' '.((string) ($node['title'] ?? '')));

        return ($hay === 'unit' || str_contains($hay, 'unit')) && ! str_contains($hay, 'weight');
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function schemaUnitValue(array $node, string $path = ''): string
    {
        $preferLb = $this->inferWeightUnit($path, $node) === 'lb';
        $enum = $node['enum'] ?? [];
        if (is_array($enum) && $enum !== []) {
            $picked = null;
            foreach ($enum as $one) {
                $raw = trim((string) $one);
                $one = strtolower($raw);
                if ($preferLb && in_array($one, ['lb', 'lbs', 'pound', 'pounds'], true)) {
                    return $raw;
                }
                if (! $preferLb && in_array($one, ['kg', 'kilogram', 'kilograms'], true)) {
                    return $raw;
                }
                $picked ??= $raw;
            }

            return (string) $picked;
        }

        return $preferLb ? 'lb' : 'kg';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function inferWeightUnit(string $path, array $node = []): string
    {
        $hay = strtolower(
            $path.' '
            .(string) ($node['title'] ?? '').' '
            .(string) ($node['unit'] ?? '').' '
            .(string) ($node['id'] ?? '').' '
            .(string) ($node['description'] ?? '')
        );
        if (preg_match('/\b(oz|ounce|ounces)\b/', $hay)) {
            return 'oz';
        }
        if (preg_match('/\b(g|gram|grams)\b/', $hay) && ! str_contains($hay, 'kg') && ! str_contains($hay, 'logistics')) {
            return 'g';
        }
        if (
            preg_match('/\b(lb|lbs|pound|pounds)\b/', $hay)
            || str_contains($hay, 'uslogistics')
            || str_contains($hay, 'aelogistics')
            || str_contains($hay, 'usl.')
            || preg_match('/\busl\b/', $hay)
        ) {
            return 'lb';
        }

        return 'kg';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function schemaPrefersString(array $node): bool
    {
        return $this->schemaNodeType($node) === 'string' || isset($node['pattern']);
    }

    /**
     * Convert Dim/Wt kg+lb into the unit and JSON type AliExpress asks for.
     *
     * US logistics fields: pounds, usually a 2-decimal string ("0.60").
     * Official package_weight / weight: kilograms (number or "0.272").
     *
     * @param  array<string, mixed>  $node
     */
    private function formatMarketplaceWeight(float $kg, float $lb, string $unit, string $kind, array $node = []): float|int|string
    {
        $unit = strtolower(trim($unit));
        $kg = abs($kg);
        $lb = abs($lb);
        if (in_array($unit, ['lb', 'lbs', 'pound', 'pounds'], true)) {
            $raw = $lb > 0 ? $lb : ($kg > 0 ? $kg / 0.45359237 : 0.0);
            $decimals = 2;
            $min = 0.01;
        } elseif (in_array($unit, ['oz', 'ounce', 'ounces'], true)) {
            $pounds = $lb > 0 ? $lb : ($kg > 0 ? $kg / 0.45359237 : 0.0);
            $raw = $pounds * 16;
            $decimals = 1;
            $min = 0.1;
        } elseif (in_array($unit, ['g', 'gram', 'grams'], true)) {
            $raw = $kg * 1000;
            $decimals = 0;
            $min = 1;
        } else {
            $raw = $kg > 0 ? $kg : ($lb > 0 ? $lb * 0.45359237 : 0.0);
            $decimals = 3;
            $min = 0.001;
        }

        $decimals = $this->schemaWeightDecimals($node, $decimals);
        $raw = max($min, $raw);
        if ($kind === 'integer') {
            return (int) max(1, (int) round($raw));
        }

        $formatted = number_format($raw, $decimals, '.', '');

        return $kind === 'string' ? $formatted : (float) $formatted;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function schemaWeightDecimals(array $node, int $default): int
    {
        $pattern = (string) ($node['pattern'] ?? '');
        if ($pattern !== '' && preg_match('/\\\\d\{1,(\d+)\}/', $pattern, $match)) {
            return max(0, min(4, (int) $match[1]));
        }
        $multiple = $node['multipleOf'] ?? $node['multiple_of'] ?? null;
        if (is_numeric($multiple)) {
            $multiple = (float) $multiple;
            if ($multiple >= 1) {
                return 0;
            }
            $text = rtrim(rtrim(sprintf('%.10f', $multiple), '0'), '.');
            $dot = strpos($text, '.');

            return $dot === false ? 0 : max(0, min(4, strlen($text) - $dot - 1));
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function buildOneSchemaInstance(array $request, float $weight): array
    {
        $title = trim((string) ($request['multi_language_subject_list'][0]['subject'] ?? ''));
        $desc = $request['multi_language_description_list'][0] ?? [];
        $html = is_array($desc)
            ? (string) (data_get($desc, 'web_detail') ?: data_get($desc, 'html') ?: '')
            : '';
        if ($html !== '' && str_starts_with(trim($html), '{')) {
            $decoded = json_decode($html, true);
            $html = (string) (data_get($decoded, 'moduleList.0.html.content') ?: $html);
        }
        if (trim(strip_tags($html)) === '') {
            $html = '<p>'.e($title !== '' ? $title : 'Product details').'</p>';
        }

        $skus = [];
        $lb = $this->aliexpressWeightPounds($request, $weight);
        $usWeight = $this->usPackageWeightObject($weight, $lb);
        foreach ($request['sku_info_list'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $skuCode = trim((string) ($row['sku_code'] ?? ''));
            if ($skuCode === '') {
                continue;
            }
            $sku = [
                'sku_code' => $skuCode,
                'inventory' => max(1, (int) ($row['inventory'] ?? 1)),
                'price' => (float) ($row['price'] ?? 0),
            ];
            $attrs = [];
            foreach ($row['sku_attributes_list'] ?? [] as $attr) {
                if (! is_array($attr)) {
                    continue;
                }
                $name = trim((string) ($attr['sku_attribute_name'] ?? 'Color'));
                if ($name === '') {
                    continue;
                }
                $attrs[$name] = [
                    'alias' => mb_substr((string) ($attr['sku_attribute_value'] ?? ''), 0, 70),
                    'sku_image_url' => (string) ($attr['sku_image_url'] ?? ''),
                ];
            }
            if ($attrs === []) {
                $attrs['Color'] = [
                    'alias' => mb_substr($skuCode, 0, 70),
                    'sku_image_url' => (string) ($row['sku_image_url'] ?? ''),
                ];
            }
            $sku['sku_attributes'] = $attrs;
            $sku['aeLogisticsWeight'] = $usWeight;
            $sku['usLogisticsWeight'] = $usWeight;
            $skus[] = $sku;
        }

        return [
            'locale' => 'en_US',
            'category_id' => (int) ($request['aliexpress_category_id'] ?? 0),
            'product_units_type' => (string) ($request['product_unit'] ?? '100000015'),
            'title_multi_language_list' => [
                ['locale' => 'en_US', 'title' => $title],
            ],
            'description_multi_language_list' => [[
                'locale' => 'en_US',
                'module_list' => [
                    ['type' => 'html', 'html' => ['content' => $html]],
                ],
            ]],
            'image_url_list' => array_values($request['main_image_urls_list'] ?? []),
            'sku_info_list' => $skus,
            'inventory_deduction_strategy' => (string) ($request['inventory_deduction_strategy'] ?? 'place_order_withhold'),
            'package_weight' => $weight,
            'package_length' => (int) ($request['package_length'] ?? 10),
            'package_width' => (int) ($request['package_width'] ?? 10),
            'package_height' => (int) ($request['package_height'] ?? 10),
            'shipping_preparation_time' => max(1, (int) ($request['shipping_lead_time'] ?? 7)),
            'shipping_template_id' => (string) ($request['freight_template_id'] ?? ''),
            'service_template_id' => (string) ($request['service_policy_id'] ?? '0'),
            'category_attributes' => [
                'Brand Name' => ['value' => (string) ($request['brand_name'] ?? '5 Core Inc.')],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $schemaWeightFields
     * @return array<string, mixed>
     */
    private function officialProductPostRequest(array $request, array $schemaWeightFields): array
    {
        $keep = [
            'language',
            'aliexpress_category_id',
            'brand_name',
            'multi_language_subject_list',
            'multi_language_description_list',
            'main_image_urls_list',
            'sku_info_list',
            'product_unit',
            'inventory_deduction_strategy',
            'shipping_lead_time',
            'package_length',
            'package_width',
            'package_height',
            'freight_template_id',
            'service_policy_id',
            'attribute_list',
            'weight',
            'package_weight',
            'usLogisticsWeight',
            'aeLogisticsWeight',
            'usl',
        ];
        $out = [];
        foreach ($keep as $key) {
            if (array_key_exists($key, $request)) {
                $out[$key] = $request[$key];
            }
        }
        $skus = [];
        foreach ($out['sku_info_list'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $skuCode = trim((string) ($row['sku_code'] ?? ''));
            if ($skuCode === '') {
                continue;
            }
            $clean = array_intersect_key($row, array_flip([
                'sku_code',
                'price',
                'inventory',
                'sku_attributes_list',
                'weight',
                'package_weight',
                'aeLogisticsWeight',
                'usLogisticsWeight',
            ]));
            if (empty($clean['sku_attributes_list'])) {
                $clean['sku_attributes_list'] = [[
                    'sku_attribute_name' => 'Color',
                    'sku_attribute_value' => mb_substr($skuCode, 0, 70),
                ]];
            }
            $skus[] = $clean;
        }
        $out['sku_info_list'] = $skus;
        unset($schemaWeightFields['sku_info_list']);
        $out = array_merge($out, $schemaWeightFields);
        $kg = $this->aliexpressWeightNumber($request);
        $lb = $this->aliexpressWeightPounds($request, $kg);
        $out = $this->forceUsLogisticsWeightObjects($out, $kg, $lb);
        // Official product.post weight is a kg string (min 0.001). Do not overwrite it with a US object.
        $out['weight'] = number_format(max(0.001, $kg), 3, '.', '');
        $out['package_weight'] = (float) $out['weight'];

        return $out;
    }

    /**
     * @param  list<string>  $skuCodes
     */
    public function findPostedProductIdBySkus(array $skuCodes): string
    {
        $want = [];
        foreach ($skuCodes as $sku) {
            $sku = strtoupper(trim((string) $sku));
            if ($sku !== '') {
                $want[$sku] = true;
            }
        }
        if ($want === []) {
            return '';
        }

        foreach (['auditing', 'onSelling', 'offline', 'editingRequired'] as $status) {
            $res = $this->getInventory(1, 50, ['product_status_type' => $status]);
            if (empty($res['success'])) {
                continue;
            }
            foreach ($res['data']['products'] ?? [] as $product) {
                if (! is_array($product)) {
                    continue;
                }
                $id = $this->extractPostedProductId($product);
                if ($id === '') {
                    continue;
                }
                foreach ($this->extractSkuCodesFromListedProduct($product) as $code) {
                    if (isset($want[strtoupper($code)])) {
                        return $id;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $product
     * @return list<string>
     */
    private function extractSkuCodesFromListedProduct(array $product): array
    {
        $codes = [];
        $push = function (mixed $raw) use (&$codes): void {
            $sku = strtoupper(trim((string) $raw));
            if ($sku !== '') {
                $codes[] = $sku;
            }
        };
        $push($product['sku_code'] ?? $product['sku'] ?? null);
        foreach (['sku_info_list', 'aeop_ae_product_s_k_us', 'sku_list'] as $key) {
            $list = $product[$key] ?? null;
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                if (is_array($row)) {
                    $push($row['sku_code'] ?? $row['sku'] ?? $row['skuCode'] ?? null);
                }
            }
        }

        return $codes;
    }

    /**
     * First usable seller freight template (id 1000 is AliExpress default and is invalid for overseas).
     */
    public function firstFreightTemplateId(): string
    {
        foreach ([
            'aliexpress.freight.redefining.listfreighttemplate',
            'aliexpress.solution.seller.freight.list.get',
        ] as $method) {
            $res = $this->callApiFlexible($method, [
                'rest' => [],
                'sync' => [],
            ]);
            if (empty($res['success'])) {
                continue;
            }
            $id = $this->extractFreightTemplateId($res['data'] ?? []);
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }

    public function extractFreightTemplateId(mixed $data, int $depth = 0): string
    {
        if ($depth > 8) {
            return '';
        }
        if (! is_array($data)) {
            $id = trim((string) $data);

            return ($id !== '' && $id !== '1000' && ctype_digit($id)) ? $id : '';
        }
        foreach (['freight_template_id', 'freightTemplateId', 'template_id', 'templateId'] as $key) {
            if (! array_key_exists($key, $data) || is_array($data[$key])) {
                continue;
            }
            $id = $this->extractFreightTemplateId($data[$key], $depth + 1);
            if ($id !== '') {
                return $id;
            }
        }
        foreach (['result', 'data', 'aeop_freight_template_d_t_o_list', 'freight_template_list', 'template_list'] as $wrap) {
            if (! isset($data[$wrap])) {
                continue;
            }
            $nested = $this->extractFreightTemplateId($data[$wrap], $depth + 1);
            if ($nested !== '') {
                return $nested;
            }
        }
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $row) {
                $nested = $this->extractFreightTemplateId($row, $depth + 1);
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return '';
    }

    /**
     * Product list — method aliexpress.solution.product.list.get with product_list_get_request (JSON).
     */
    public function getInventory(int $page = 1, int $pageSize = 20, array $extraListParams = []): array
    {
        $listRequest = $this->buildProductListRequest(array_merge([
            'current_page' => $page,
            'page_size' => $pageSize,
        ], $extraListParams));

        $encoded = $this->encodeRequestPayload($listRequest);
        $raw = $this->callRestGateway('aliexpress.solution.product.list.get', [
            'aeop_a_e_product_list_query' => $encoded,
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $raw['data'] ?? [];
        $parsed = $this->parseSolutionProductListResponse($payload);

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => $parsed,
            'raw' => $payload,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Update title — method aliexpress.solution.product.edit with edit_product_request (JSON).
     */
    public function updateTitle(string $productId, string $title, ?string $language = 'en'): array
    {
        $title = trim($title);
        $resolved = $this->resolveProductIdBySku($productId) ?: trim($productId);
        if ($resolved === '' || $title === '') {
            return ['success' => false, 'message' => 'AliExpress product ID and title are required. Sync AliExpress listings first.'];
        }

        $last = ['success' => false, 'message' => 'AliExpress title update failed.'];

        // Single-field subject edit avoids US Pop Choice package-weight schema checks.
        foreach ([
            'aliexpress.postproduct.redefining.editsinglefiled',
            'aliexpress.postproduct.redefining.editSingleFiled',
        ] as $method) {
            foreach ([
                ['product_id' => $resolved, 'fied_name' => 'subject', 'fiedvalue' => $title],
                ['productId' => $resolved, 'fiedName' => 'subject', 'fiedValue' => $title],
            ] as $params) {
                $single = $this->callApiFlexible($method, [
                    'rest' => $params,
                    'sync' => $params,
                ]);
                if (! empty($single['success'])) {
                    return $single;
                }
                $last = $single;
            }
        }

        $pkg = $this->aliexpressPackageSizeFields($resolved);
        $weight = (string) ($pkg['weight'] ?? '0.5');
        $length = (string) ($pkg['package_length'] ?? '10');
        $width = (string) ($pkg['package_width'] ?? '10');
        $height = (string) ($pkg['package_height'] ?? '10');

        $payloads = [
            $this->buildEditProductRequest($resolved, $title, $language),
            array_merge($this->buildEditProductRequest($resolved, $title, $language), [
                'weight' => $weight,
                'package_length' => $length,
                'package_width' => $width,
                'package_height' => $height,
            ]),
            array_merge($this->buildEditProductRequest($resolved, $title, $language), [
                'weight' => $weight,
                'usLogisticsWeight' => $weight,
                'package_length' => $length,
                'package_width' => $width,
                'package_height' => $height,
                'gross_weight' => $weight,
            ]),
        ];
        foreach ($payloads as $payload) {
            $res = $this->callProductEditKeepTrying($payload);
            if (! empty($res['success'])) {
                return $res;
            }
            $last = $res;
        }

        return $last;
    }

    /**
     * Build body for edit_product_request (multi-language title per official docs).
     */
    public function buildEditProductRequest(string $productId, string $title, ?string $language = 'en'): array
    {
        return [
            'product_id' => (string) $productId,
            'multi_language_subject_list' => [
                [
                    'subject' => $title,
                    'language' => $language ?: 'en',
                ],
            ],
        ];
    }

    private function isAliExpressPackageSizeRequired(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'package size')
            || str_contains($m, 'package weight')
            || str_contains($m, 'logisticssize')
            || str_contains($m, 'uslogisticsweight')
            || str_contains($m, 'us_logistics_weight')
            || str_contains($m, 'usl.logisticsweight')
            || str_contains($m, 'logisticsweight')
            || str_contains($m, 'chk_basic_required');
    }

    /**
     * @return array<string, mixed>
     */
    private function aliexpressPackageSizeFields(string $productId): array
    {
        $info = $this->getProductInfo($productId);
        $data = is_array($info['data'] ?? null) ? $info['data'] : [];
        $length = $this->aliexpressPositiveNumber(
            $data['package_length'] ?? $data['packageLength'] ?? data_get($data, 'logistics_size.package_length')
        );
        $width = $this->aliexpressPositiveNumber(
            $data['package_width'] ?? $data['packageWidth'] ?? data_get($data, 'logistics_size.package_width')
        );
        $height = $this->aliexpressPositiveNumber(
            $data['package_height'] ?? $data['packageHeight'] ?? data_get($data, 'logistics_size.package_height')
        );
        $weight = $this->aliexpressPositiveNumber(
            $data['usLogisticsWeight']
                ?? $data['us_logistics_weight']
                ?? $data['package_weight']
                ?? $data['packageWeight']
                ?? $data['gross_weight']
                ?? $data['grossWeight']
                ?? data_get($data, 'logistics_size.gross_weight')
                ?? data_get($data, 'logistics_size.usLogisticsWeight')
        );

        if ($length === null) {
            $length = 10;
        }
        if ($width === null) {
            $width = 10;
        }
        if ($height === null) {
            $height = 10;
        }
        if ($weight === null) {
            $weight = 0.5;
        }

        return [
            'package_length' => $length,
            'package_width' => $width,
            'package_height' => $height,
            'weight' => $weight,
            'gross_weight' => $weight,
            'usLogisticsWeight' => $weight,
        ];
    }

    private function aliexpressPositiveNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return $n > 0 ? $n : null;
    }

    /**
     * Build body for product_list_get_request.
     *
     * @param  array<string, mixed>  $params  e.g. current_page, page_size, optional filters from docs
     */
    public function buildProductListRequest(array $params): array
    {
        return array_merge([
            'current_page' => 1,
            'page_size' => 20,
            'product_status_type' => 'onSelling',
        ], $params);
    }

    /**
     * Debug: same signing and URL as production /sync calls.
     *
     * @param  array<string, string|int>  $restParams  Already-encoded business params (e.g. product_list_get_request => json string)
     */
    public function debugCallRest(string $method, array $restParams = []): array
    {
        $system = $this->buildBaseParams($method);
        $api = [];
        foreach ($restParams as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $api[$k] = is_array($v) ? $this->encodeRequestPayload($v) : (string) $v;
        }

        $forSign = array_merge($api, $system);
        $signSource = $this->buildSignSource($forSign);
        $sign = $this->sign($signSource);
        $system['sign'] = $sign;

        $requestDebug = [
            'transport' => $this->transport,
            'sign_source' => $signSource,
            'sign' => $sign,
            'system_params' => $system,
            'api_params' => $api,
        ];

        if ($this->transport === 'iop') {
            $queryUrl = $this->apiBase.'?'.http_build_query($system, '', '&', PHP_QUERY_RFC3986);
            $requestDebug['request_url'] = $queryUrl;
            $requestDebug['api_multipart_keys'] = array_keys($api);

            $multipart = [];
            foreach ($api as $name => $contents) {
                $multipart[] = ['name' => $name, 'contents' => $contents];
            }

            $pending = $this->httpClient()->asMultipart();
            try {
                $response = $multipart === []
                    ? $pending->post($queryUrl)
                    : $pending->post($queryUrl, $multipart);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return [
                    'request' => $requestDebug,
                    'response' => [
                        'status' => 0,
                        'body' => $e->getMessage(),
                        'json' => null,
                    ],
                    'network_error' => true,
                ];
            }
        } else {
            $url = $this->apiBase;
            $merged = array_merge($api, $system);
            $requestDebug['request_url'] = $url;
            $requestDebug['raw_body'] = http_build_query($merged, '', '&', PHP_QUERY_RFC3986);
            try {
                $response = $this->httpClient()
                    ->asForm()
                    ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                    ->post($url, $merged);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return [
                    'request' => $requestDebug,
                    'response' => [
                        'status' => 0,
                        'body' => $e->getMessage(),
                        'json' => null,
                    ],
                    'network_error' => true,
                ];
            }
        }

        Log::debug('AliExpress sync debug', $requestDebug);

        return [
            'request' => $requestDebug,
            'response' => [
                'status' => $response->status(),
                'body' => $response->body(),
                'json' => $response->json(),
            ],
        ];
    }

    /**
     * Title/edit calls historically used /sync and failed with
     * "The request signature does not conform to platform standards".
     * Try REST (same as inventory) then Product Master-style flat /sync params.
     *
     * @param  array<string, mixed>  $editRequest
     * @return array<string, mixed>
     */
    private function callProductEdit(array $editRequest): array
    {
        $json = $this->encodeRequestPayload($editRequest);
        $productId = (string) ($editRequest['product_id'] ?? '');
        $subject = (string) ($editRequest['multi_language_subject_list'][0]['subject'] ?? '');

        $attempts = [
            ['gateway' => 'rest', 'params' => ['edit_product_request' => $json]],
            ['gateway' => 'rest', 'params' => ['product_id' => $productId, 'subject' => $subject]],
            ['gateway' => 'sync', 'params' => ['product_id' => $productId, 'subject' => $subject]],
            ['gateway' => 'sync', 'params' => ['edit_product_request' => $json]],
        ];

        $last = ['success' => false, 'message' => 'AliExpress product edit failed.'];
        foreach ($attempts as $attempt) {
            $last = $attempt['gateway'] === 'rest'
                ? $this->callRestGateway('aliexpress.solution.product.edit', $attempt['params'])
                : $this->callSync('aliexpress.solution.product.edit', $attempt['params']);

            if (! empty($last['success'])) {
                return $last;
            }
            if (! $this->isSignatureError($last) && empty($last['network_error'])) {
                return $last;
            }

            Log::warning('AliExpress product.edit attempt failed', [
                'gateway' => $attempt['gateway'],
                'param_keys' => array_keys($attempt['params']),
                'error' => $last['message'] ?? null,
            ]);
        }

        return $last;
    }

    /**
     * Keep trying gateway/param shapes even when a business validation error is returned.
     *
     * @param  array<string, mixed>  $editRequest
     * @return array<string, mixed>
     */
    private function callProductEditKeepTrying(array $editRequest): array
    {
        $json = $this->encodeRequestPayload($editRequest);
        $productId = (string) ($editRequest['product_id'] ?? '');
        $subject = (string) ($editRequest['multi_language_subject_list'][0]['subject'] ?? '');

        $attempts = [
            ['gateway' => 'rest', 'params' => ['product_id' => $productId, 'subject' => $subject]],
            ['gateway' => 'sync', 'params' => ['product_id' => $productId, 'subject' => $subject]],
            ['gateway' => 'rest', 'params' => ['edit_product_request' => $json]],
            ['gateway' => 'sync', 'params' => ['edit_product_request' => $json]],
        ];

        $last = ['success' => false, 'message' => 'AliExpress product edit failed.'];
        foreach ($attempts as $attempt) {
            $last = $attempt['gateway'] === 'rest'
                ? $this->callRestGateway('aliexpress.solution.product.edit', $attempt['params'])
                : $this->callSync('aliexpress.solution.product.edit', $attempt['params']);

            if (! empty($last['success'])) {
                return $last;
            }
        }

        return $last;
    }

    /**
     * Route to business /rest or legacy /sync gateway.
     *
     * @param  array<string, mixed>  $businessParams
     */
    protected function callApi(string $method, array $businessParams = []): array
    {
        return $this->gateway === 'rest'
            ? $this->callRestGateway($method, $businessParams)
            : $this->callSync($method, $businessParams);
    }

    /**
     * Try primary gateway, then fallback on network errors (with per-gateway param shapes).
     *
     * @param  array<string, array<string, mixed>>  $paramsByGateway
     */
    protected function callApiFlexible(string $method, array $paramsByGateway): array
    {
        $order = $this->gateway === 'rest' ? ['rest', 'sync'] : ['sync', 'rest'];
        if (! $this->gatewayFallback) {
            $order = [$this->gateway];
        }

        $last = null;
        foreach ($order as $gateway) {
            if (! isset($paramsByGateway[$gateway])) {
                continue;
            }

            $last = $gateway === 'rest'
                ? $this->callRestGateway($method, $paramsByGateway[$gateway])
                : $this->callSync($method, $paramsByGateway[$gateway]);

            if (empty($last['network_error'])) {
                return $last;
            }

            Log::warning('AliExpress gateway unreachable', [
                'gateway' => $gateway,
                'method' => $method,
                'message' => $last['message'] ?? null,
            ]);
        }

        return $last ?? $this->networkErrorResult('No AliExpress gateway configured.');
    }

    /**
     * Shared HTTP client: longer timeouts, optional IPv4 + proxy.
     */
    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $pending = Http::withoutVerifying()
            ->connectTimeout($this->httpConnectTimeout)
            ->timeout($this->httpTimeout);

        $options = [];
        $curl = [];
        if ($this->resolveIpv4 && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curl[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        if ($curl !== []) {
            $options['curl'] = $curl;
        }
        if ($this->httpProxy !== null) {
            $options['proxy'] = $this->httpProxy;
        }
        if ($options !== []) {
            $pending = $pending->withOptions($options);
        }

        return $pending;
    }

    /**
     * @return array<string, mixed>
     */
    private function networkErrorResult(string $message, ?\Throwable $e = null): array
    {
        return [
            'success' => false,
            'network_error' => true,
            'message' => $message,
            'detail' => $e?->getMessage(),
        ];
    }

    /**
     * Business REST gateway — POST https://api-sg.aliexpress.com/rest
     * Official sign: apiName + sorted key+value, HMAC-SHA256, ms timestamp, access_token.
     *
     * @param  array<string, mixed>  $businessParams  Flat business fields (e.g. current_page, page_size)
     */
    protected function callRestGateway(string $method, array $businessParams = []): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            return ['success' => false, 'message' => 'AliExpress app_key / app_secret are missing.'];
        }
        if (empty($this->accessToken)) {
            return [
                'success' => false,
                'message' => $this->channelLabel.' OAuth token is missing (set '.$this->tokenEnvKey.').',
            ];
        }

        $params = [
            'app_key' => $this->appKey,
            'method' => $method,
            'access_token' => $this->accessToken,
            'sign_method' => 'sha256',
            'timestamp' => (string) (int) round(microtime(true) * 1000),
        ];

        foreach ($businessParams as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $params[(string) $key] = is_array($value)
                ? $this->encodeRequestPayload($value)
                : (string) $value;
        }

        $apiNames = [$method, '/rest'];
        $last = null;

        foreach ($apiNames as $apiName) {
            $attempt = $params;
            $attempt['sign'] = $this->signBusinessApi($attempt, $apiName);

            try {
                $response = $this->httpClient()
                    ->asForm()
                    ->post($this->restBase, $attempt);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return $this->networkErrorResult(
                    'Could not reach AliExpress REST API (network timeout). Check firewall/VPN or run from your production server.',
                    $e
                );
            }

            $parsed = $this->parseHttpResponse($response, $method, 'rest');
            $last = $parsed;

            if (! empty($parsed['success'])) {
                return $parsed;
            }

            if (! $this->isSignatureError($parsed)) {
                return $parsed;
            }
        }

        return $last ?? ['success' => false, 'message' => 'AliExpress REST call failed.'];
    }

    /**
     * Business /rest file upload. The file part is omitted from the sign.
     *
     * @param  array<string, string>  $businessParams
     */
    private function callRestWithFile(string $method, array $businessParams, string $fileField, string $bytes, string $fileName): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            return ['success' => false, 'message' => 'AliExpress app_key / app_secret are missing.'];
        }
        if (empty($this->accessToken)) {
            return [
                'success' => false,
                'message' => $this->channelLabel.' OAuth token is missing (set '.$this->tokenEnvKey.').',
            ];
        }

        $params = [
            'app_key' => $this->appKey,
            'method' => $method,
            'access_token' => (string) $this->accessToken,
            'sign_method' => 'sha256',
            'timestamp' => (string) (int) round(microtime(true) * 1000),
        ];
        foreach ($businessParams as $key => $value) {
            if ($value === null || $value === '' || (string) $key === $fileField) {
                continue;
            }
            $params[(string) $key] = (string) $value;
        }

        $last = ['success' => false, 'message' => 'AliExpress photobank upload failed.'];
        foreach ([$method, '/rest'] as $apiName) {
            $attempt = $params;
            $attempt['sign'] = $this->signBusinessApi($attempt, $apiName);
            try {
                $response = $this->httpClient()
                    ->connectTimeout(10)
                    ->timeout(40)
                    ->asMultipart()
                    ->attach($fileField, $bytes, $fileName)
                    ->post($this->restBase, $attempt);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $last = $this->networkErrorResult('Could not reach AliExpress photobank (rest).', $e);
                continue;
            }

            $parsed = $this->parseHttpResponse($response, $method, 'rest-file');
            $last = $parsed;
            if ($this->extractPhotobankUrl($parsed) !== '' || ! empty($parsed['success'])) {
                return $parsed;
            }
            if (! $this->isSignatureError($parsed) && empty($parsed['network_error']) && ! $this->isRetryablePhotobankError($parsed)) {
                return $parsed;
            }
        }

        return $last;
    }

    /**
     * AliExpress business API sign — apiName prefix + sorted key+value, HMAC-SHA256 uppercase hex.
     *
     * @param  array<string, string>  $params
     */
    private function signBusinessApi(array $params, string $apiName): string
    {
        unset($params['sign']);
        ksort($params);

        $source = $apiName;
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $source .= (string) $key.(string) $value;
            }
        }

        return strtoupper(hash_hmac('sha256', $source, $this->appSecret));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function isSignatureError(array $result): bool
    {
        $code = strtolower((string) ($this->extractErrorCode($result) ?? ''));
        if (in_array($code, ['incompletesignature', 'illegaltimestamp', 'invalidsignature', 'sign-check-failure', '25'], true)) {
            return true;
        }

        $message = strtolower((string) ($result['message'] ?? ''));

        return $message !== '' && (str_contains($message, 'signature') || str_contains($message, 'sign'));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function isRetryablePhotobankError(array $result): bool
    {
        $message = strtolower((string) ($result['message'] ?? ''));
        if ($message === '') {
            return true;
        }

        return ! empty($result['invalid_json'])
            || str_contains($message, 'invalid json')
            || str_contains($message, 'web page')
            || str_contains($message, 'empty response')
            || str_contains($message, 'invalid method')
            || str_contains($message, 'isv.invalid-method')
            || str_contains($message, 'missing-parameter')
            || str_contains($message, 'missing parameter')
            || str_contains($message, 'image_bytes')
            || str_contains($message, 'service-unavailable')
            || str_contains($message, 'http request failed');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractErrorCode(array $result): ?string
    {
        $response = $result['response'] ?? null;
        if (! is_array($response)) {
            return null;
        }
        if (isset($response['error_response']['code'])) {
            return (string) $response['error_response']['code'];
        }
        if (isset($response['code'])) {
            return (string) $response['code'];
        }

        return null;
    }

    /**
     * TOP legacy sign for /rest (hmac-md5 / md5) — kept for optional fallback via env.
     *
     * @param  array<string, string>  $params
     */
    private function signTopRestParams(array $params): string
    {
        unset($params['sign']);
        ksort($params);

        if ($this->restSignMethod === 'md5') {
            $source = $this->appSecret;
            foreach ($params as $key => $value) {
                $source .= (string) $key.(string) $value;
            }
            $source .= $this->appSecret;

            return strtoupper(md5($source));
        }

        $source = '';
        foreach ($params as $key => $value) {
            $source .= (string) $key.(string) $value;
        }

        return strtoupper(hash_hmac('md5', $source, $this->appSecret));
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHttpResponse(\Illuminate\Http\Client\Response $response, string $method, string $gateway): array
    {
        $json = $response->json();
        $body = $response->body();

        Log::info('AliExpress API call', [
            'method' => $method,
            'gateway' => $gateway,
            'status' => $response->status(),
            'response_body' => mb_substr((string) $body, 0, 4000),
        ]);

        if ($response->failed()) {
            $message = 'AliExpress HTTP request failed.';
            if (is_array($json)) {
                $err = $json['error_response'] ?? $json;
                if (is_array($err)) {
                    $message = (string) ($err['sub_msg'] ?? $err['msg'] ?? $err['message'] ?? $message);
                }
            } elseif (is_string($body) && trim($body) !== '') {
                $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
                if ($plain !== '') {
                    $message = mb_substr($plain, 0, 240);
                }
            }

            return [
                'success' => false,
                'status' => $response->status(),
                'message' => $message,
                'response' => $json ?: $body,
            ];
        }

        if (! is_array($json)) {
            $raw = (string) $body;
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags($raw)) ?? '');
            $looksHtml = str_contains(strtolower($raw), '<html')
                || str_contains(strtolower($raw), '<!doctype');
            $message = $looksHtml
                ? 'AliExpress photobank returned a web page instead of JSON.'
                : ($plain !== ''
                    ? 'Invalid JSON response: '.mb_substr($plain, 0, 160)
                    : 'AliExpress photobank returned an empty response.');

            return [
                'success' => false,
                'status' => $response->status(),
                'invalid_json' => true,
                'network_error' => $looksHtml || $plain === '',
                'message' => $message,
                'response' => mb_substr($raw, 0, 400),
            ];
        }

        if (isset($json['error_response'])) {
            $err = $json['error_response'];

            return [
                'success' => false,
                'status' => $response->status(),
                'message' => is_array($err)
                    ? ($err['msg'] ?? $err['message'] ?? $err['sub_msg'] ?? json_encode($err))
                    : (string) $err,
                'response' => $json,
            ];
        }

        $json = $this->unwrapSolutionEnvelope($json);

        if (isset($json['type'], $json['code']) && ($json['type'] ?? '') === 'ISV') {
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => (string) ($json['msg'] ?? $json['message'] ?? 'ISV error'),
                'response' => $json,
            ];
        }

        if (array_key_exists('code', $json)) {
            $code = $json['code'];
            if ((string) $code !== '0' && $code !== 0) {
                return [
                    'success' => false,
                    'status' => $response->status(),
                    'message' => (string) ($json['message'] ?? $json['msg'] ?? 'AliExpress API error.'),
                    'code' => $code,
                    'response' => $json,
                ];
            }
        }

        $businessError = $this->extractBusinessResultError($json);
        if ($businessError !== null) {
            return [
                'success' => false,
                'status' => $response->status(),
                'message' => $businessError,
                'response' => $json,
            ];
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'data' => $json,
            'result' => $json['result'] ?? null,
            'request_id' => $json['request_id'] ?? null,
        ];
    }

    /**
     * Detect inner result.success=false from solution API envelopes.
     */
    private function extractBusinessResultError(array $json): ?string
    {
        $result = $json['result'] ?? null;
        if (! is_array($result)) {
            return null;
        }

        $success = $result['success'] ?? null;
        $error = trim((string) ($result['error_message'] ?? $result['error_msg'] ?? $result['sub_msg'] ?? $result['message'] ?? ''));
        if ($success === false || $success === 'false' || $success === 0 || $success === '0') {
            return $error !== '' ? $error : 'AliExpress API returned success=false.';
        }
        if ($error !== '' && ($result['error_code'] ?? $result['errorCode'] ?? null)) {
            return $error;
        }

        return null;
    }

    /**
     * POST to dropshipping `/sync` endpoint with IOP-style transport (query + multipart).
     *
     * @param  array<string, mixed>  $businessParams  Top-level API keys (e.g. edit_product_request => JSON string)
     */
    protected function callSync(string $method, array $businessParams = []): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            return [
                'success' => false,
                'message' => 'AliExpress app_key / app_secret are missing.',
            ];
        }

        if (empty($this->accessToken)) {
            return [
                'success' => false,
                'message' => $this->channelLabel.' OAuth token is missing (set '.$this->tokenEnvKey.'; sent as '.$this->tokenParam.').',
            ];
        }

        $system = $this->buildBaseParams($method);
        $api = [];
        foreach ($businessParams as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $api[$key] = is_array($value) ? $this->encodeRequestPayload($value) : (string) $value;
        }

        $forSign = array_merge($api, $system);
        $signSource = $this->buildSignSource($forSign);
        $sign = $this->sign($signSource);
        $system['sign'] = $sign;

        if ($this->transport === 'iop') {
            $queryUrl = $this->apiBase.'?'.http_build_query($system, '', '&', PHP_QUERY_RFC3986);
            $multipart = [];
            foreach ($api as $name => $contents) {
                $multipart[] = ['name' => $name, 'contents' => $contents];
            }
            $pending = $this->httpClient()->asMultipart();
            try {
                $response = $multipart === []
                    ? $pending->post($queryUrl)
                    : $pending->post($queryUrl, $multipart);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return $this->networkErrorResult(
                    'Could not reach AliExpress sync API (network timeout). Check firewall/VPN or run from your production server.',
                    $e
                );
            }
        } else {
            $merged = array_merge($api, $system);
            try {
                $response = $this->httpClient()
                    ->asForm()
                    ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                    ->post($this->apiBase, $merged);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return $this->networkErrorResult(
                    'Could not reach AliExpress sync API (network timeout). Check firewall/VPN or run from your production server.',
                    $e
                );
            }
        }

        return $this->parseHttpResponse($response, $method, 'sync');
    }

    /**
     * Official IopClient file upload. Method names without "/" are signed as key+value only (no /sync prefix).
     *
     * @param  array<string, string>  $businessParams
     */
    private function callIopFileUpload(string $method, array $businessParams, string $fileField, string $bytes, string $fileName): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            return ['success' => false, 'message' => 'AliExpress app_key / app_secret are missing.'];
        }
        if (empty($this->accessToken)) {
            return [
                'success' => false,
                'message' => $this->channelLabel.' OAuth token is missing (set '.$this->tokenEnvKey.').',
            ];
        }

        $sysParams = [
            'app_key' => $this->appKey,
            'sign_method' => 'sha256',
            'timestamp' => time().'000',
            'method' => $method,
            'partner_id' => $this->partnerId !== '' ? $this->partnerId : 'iop-sdk-php',
            'simplify' => 'true',
            'format' => 'json',
            'session' => (string) $this->accessToken,
        ];
        $apiParams = [];
        foreach ($businessParams as $key => $value) {
            if ($value === null || $value === '' || (string) $key === $fileField) {
                continue;
            }
            $apiParams[(string) $key] = (string) $value;
        }

        $signParams = array_merge($apiParams, $sysParams);
        ksort($signParams);
        $stringToBeSigned = str_contains($method, '/') ? $method : '';
        foreach ($signParams as $key => $value) {
            $stringToBeSigned .= (string) $key.(string) $value;
        }
        $sysParams['sign'] = strtoupper(hash_hmac('sha256', $stringToBeSigned, $this->appSecret));

        $requestUrl = rtrim($this->apiBase, '/').'?';
        foreach ($sysParams as $key => $value) {
            $requestUrl .= $key.'='.urlencode((string) $value).'&';
        }
        $requestUrl = rtrim($requestUrl, '&');

        $delimiter = '-------------'.uniqid();
        $data = '';
        foreach ($apiParams as $name => $content) {
            $data .= '--'.$delimiter."\r\n";
            $data .= 'Content-Disposition: form-data; name="'.$name.'"';
            $data .= "\r\n\r\n".$content."\r\n";
        }
        $data .= '--'.$delimiter."\r\n";
        $data .= 'Content-Disposition: form-data; name="'.$fileField.'"; filename="'.$fileName."\" \r\n";
        $data .= 'Content-Type: '.$this->imageMimeType($bytes, $fileName)."\r\n\r\n";
        $data .= $bytes."\r\n";
        $data .= '--'.$delimiter.'--';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        curl_setopt($ch, CURLOPT_USERAGENT, $sysParams['partner_id']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: multipart/form-data; boundary='.$delimiter,
            'Content-Length: '.strlen($data),
        ]);
        if ($this->resolveIpv4 && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        if ($this->httpProxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->httpProxy);
        }

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errno) {
            return $this->networkErrorResult(
                'Could not reach AliExpress photobank (sync).',
                new \RuntimeException($error !== '' ? $error : 'curl error '.$errno)
            );
        }

        $response = new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response($status > 0 ? $status : 502, [], (string) $body)
        );

        return $this->parseHttpResponse($response, $method, 'iop-file');
    }

    private function imageMimeType(string $bytes, string $fileName): string
    {
        if (str_starts_with($bytes, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, 'GIF8')) {
            return 'image/gif';
        }
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        $ext = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * IOP SDK {@see IopClient::msectime()} uses second + "000", not true ms; must match what you send.
     */
    private function buildTimestampForSign(): string
    {
        if ($this->timestampStyle === 'ms') {
            return (string) (int) round(microtime(true) * 1000);
        }

        return time().'000';
    }

    private function buildBaseParams(string $method): array
    {
        if ($this->syncSignStyle === 'legacy') {
            return [
                'app_key' => $this->appKey,
                'timestamp' => (string) (int) round(microtime(true) * 1000),
                'access_token' => $this->accessToken,
                'sign_method' => 'sha256',
                'method' => $method,
            ];
        }

        $params = [
            'app_key' => $this->appKey,
            'format' => $this->format,
            'method' => $method,
            'partner_id' => $this->partnerId,
            'sign_method' => 'sha256',
            'simplify' => $this->simplify,
            'timestamp' => $this->buildTimestampForSign(),
        ];

        if ($this->tokenParam === 'access_token') {
            $params['access_token'] = $this->accessToken;
        } else {
            $params['session'] = $this->accessToken;
        }

        return $params;
    }

    /**
     * JSON for *_request parameters; compact and stable for signing.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function encodeRequestPayload(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Dropshipping /sync: sign string = API path + sorted key1val1key2val2... then HMAC-SHA256 (uppercase).
     */
    private function buildSignSource(array $params): string
    {
        unset($params['sign']);
        ksort($params);

        if ($this->syncSignStyle === 'legacy') {
            $source = $this->appSecret;
            foreach ($params as $key => $value) {
                if ($value !== null && $value !== '') {
                    $source .= (string) $key.(string) $value;
                }
            }

            return $source.$this->appSecret;
        }

        $source = $this->signPath;
        foreach ($params as $key => $value) {
            $source .= (string) $key.(string) $value;
        }

        return $source;
    }

    /**
     * Ensure POST URL is the sync endpoint (migrate host-only or legacy /rest URLs).
     */
    private function normalizeSyncApiBase(string $raw): string
    {
        $raw = rtrim($raw, '/');
        $lower = strtolower($raw);
        if (str_ends_with($lower, '/rest')) {
            return substr($raw, 0, -strlen('/rest')).'/sync';
        }
        if (! str_ends_with($lower, '/sync')) {
            return $raw.'/sync';
        }

        return $raw;
    }

    private function sign(string $source): string
    {
        return strtoupper(hash_hmac('sha256', $source, $this->appSecret));
    }

    /**
     * Unwrap single-key nested response like aliexpress_solution_product_edit_response.
     */
    protected function unwrapSolutionEnvelope(array $json): array
    {
        if (count($json) !== 1) {
            return $json;
        }
        $first = reset($json);
        if (!is_array($first)) {
            return $json;
        }
        $key = key($json);
        if (!is_string($key)) {
            return $json;
        }
        if (
            str_contains(strtolower($key), 'response')
            || str_contains(strtolower($key), 'aliexpress_')
            || str_contains(strtolower($key), 'alibaba_')
        ) {
            return $first;
        }

        return $json;
    }

    /**
     * Normalize product list JSON after successful REST call.
     */
    protected function parseSolutionProductListResponse($payload): array
    {
        if (!is_array($payload)) {
            return [
                'products' => [],
                'total_count' => null,
                'current_page' => null,
                'page_size' => null,
            ];
        }

        $payload = $this->unwrapSolutionEnvelope($payload);

        $result = $payload['result'] ?? $payload;

        if (!is_array($result)) {
            $result = [];
        }

        $products = $result['aeop_ae_product_display_dto_list']
            ?? $result['aeop_a_e_product_display_d_t_o_list']
            ?? $result['aeop_ae_product_display_d_t_o_list']
            ?? $result['product_list']
            ?? $result['products']
            ?? [];

        if (! is_array($products)) {
            $products = [];
        }

        if ($products !== [] && ! isset($products[0]) && (isset($products['item_display_dto']) || isset($products['aeop_ae_product_display_dto']))) {
            $products = $products['item_display_dto'] ?? $products['aeop_ae_product_display_dto'] ?? [$products];
        }

        $products = $this->normalizeList($products);

        return [
            'products' => $products,
            'total_count' => $result['product_count'] ?? $result['total_count'] ?? $result['total_item'] ?? $result['totalCount'] ?? null,
            'total_page' => $result['total_page'] ?? $result['totalPage'] ?? null,
            'current_page' => $result['current_page'] ?? $result['currentPage'] ?? null,
            'page_size' => $result['page_size'] ?? $result['pageSize'] ?? null,
        ];
    }

    /**
     * Push detail / mobile description via solution.product.edit (no truncation).
     *
     * @return array{success: bool, message?: string, status?: int, data?: mixed}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints, ?string $language = 'en'): array
    {
        $bulletPoints = trim($bulletPoints);
        if (trim($identifier) === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU (or AliExpress product_id) and bullet points are required.'];
        }

        $trim = trim($identifier);
        $row = AliexpressMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if (! $row) {
            $row = AliexpressMetric::query()->where('product_id', $trim)->first();
        }
        $productId = $row && $row->product_id ? (string) $row->product_id : $trim;

        $html = '<ul>';
        foreach (preg_split('/\r\n|\r|\n/', $bulletPoints) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $html .= '<li>'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
        }
        $html .= '</ul>';

        $editRequest = [
            'product_id' => (string) $productId,
            'multi_language_description_list' => [
                [
                    'language' => $language ?: 'en',
                    'mobile_detail' => $html,
                    'web_detail' => $html,
                ],
            ],
        ];

        $res = $this->callSync('aliexpress.solution.product.edit', [
            'edit_product_request' => $this->encodeRequestPayload($editRequest),
        ]);

        if (! empty($res['success'])) {
            return [
                'success' => true,
                'message' => 'AliExpress product detail updated.',
                'data' => $res['data'] ?? $res['result'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => (string) ($res['message'] ?? 'AliExpress product edit failed.'),
            'response' => $res['response'] ?? $res,
        ];
    }

    /**
     * Long-form product detail (prose HTML, not bullet list).
     *
     * @return array{success: bool, message?: string, status?: int, data?: mixed}
     */
    public function updateProductDescription(string $identifier, string $description, ?string $language = 'en'): array
    {
        $description = trim($description);
        if (trim($identifier) === '' || $description === '') {
            return ['success' => false, 'message' => 'SKU (or AliExpress product_id) and description are required.'];
        }

        $trim = trim($identifier);
        $row = AliexpressMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if (! $row) {
            $row = AliexpressMetric::query()->where('product_id', $trim)->first();
        }
        $productId = $row && $row->product_id ? (string) $row->product_id : $trim;

        $html = '<div class="product-description">'.nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</div>';

        $editRequest = [
            'product_id' => (string) $productId,
            'multi_language_description_list' => [
                [
                    'language' => $language ?: 'en',
                    'mobile_detail' => $html,
                    'web_detail' => $html,
                ],
            ],
        ];

        $res = $this->callSync('aliexpress.solution.product.edit', [
            'edit_product_request' => $this->encodeRequestPayload($editRequest),
        ]);

        if (! empty($res['success'])) {
            return [
                'success' => true,
                'message' => 'AliExpress product description updated.',
                'data' => $res['data'] ?? $res['result'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => (string) ($res['message'] ?? 'AliExpress product edit failed.'),
            'response' => $res['response'] ?? $res,
        ];
    }

    /**
     * Single product detail — aliexpress.solution.product.info.get (SKU list + prices).
     */
    public function getProductInfo(string $productId): array
    {
        $raw = $this->callRestGateway('aliexpress.solution.product.info.get', [
            'product_id' => (string) $productId,
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => $result,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Order list — aliexpress.solution.order.get (param0 = OrderQuery JSON).
     *
     * @param  array<string, mixed>  $query  create_date_start/end, modified_date_*, current_page, page_size, order_status, …
     */
    public function getOrders(int $page = 1, int $pageSize = 20, array $query = []): array
    {
        $orderQuery = array_merge([
            'current_page' => $page,
            'page_size' => $pageSize,
        ], $query);

        $raw = $this->callRestGateway('aliexpress.solution.order.get', $orderQuery);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $parsed = $this->parseSolutionOrderListResponse($payload);

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => $parsed,
            'raw' => $payload,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Unprocessed buyer / order messages that still need a seller reply.
     *
     * @return array{success: bool, count: int, message?: string}
     */
    public function getPendingMessageCount(): array
    {
        $methods = [
            'aliexpress.message.redefining.versionlist.queryMsgRelationList',
            'api.queryMsgRelationList',
        ];
        $sources = ['message_center', 'order_msg'];
        $lastMessage = 'AliExpress message API returned no count.';

        foreach ($methods as $method) {
            $sum = 0;
            $ok = 0;
            foreach ($sources as $source) {
                $raw = $this->callRestGateway($method, [
                    'currentPage' => 1,
                    'pageSize' => 1,
                    'msgSources' => $source,
                    'filter' => 'dealStat',
                ]);
                if (empty($raw['success'])) {
                    $raw = $this->callRestGateway($method, [
                        'current_page' => 1,
                        'page_size' => 1,
                        'msg_sources' => $source,
                        'filter' => 'dealStat',
                    ]);
                }
                if (empty($raw['success'])) {
                    $lastMessage = (string) ($raw['message'] ?? $lastMessage);
                    continue;
                }
                $count = $this->extractPendingMessageTotal($raw['data'] ?? $raw['response'] ?? []);
                if ($count === null) {
                    continue;
                }
                $sum += $count;
                $ok++;
            }
            if ($ok > 0) {
                return ['success' => true, 'count' => $sum];
            }
        }

        return ['success' => false, 'count' => 0, 'message' => $lastMessage];
    }

    /**
     * @param  array<string, mixed>|mixed  $data
     */
    protected function extractPendingMessageTotal($data): ?int
    {
        if (! is_array($data)) {
            return null;
        }

        foreach (['totalItem', 'total_item', 'totRecordCount', 'unread_count', 'unreadCount', 'unReadCount', 'unDealCount', 'undeal_count', 'total'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return max(0, (int) $data[$key]);
            }
        }

        foreach (['result', 'data', 'response', 'result_obj'] as $wrap) {
            if (isset($data[$wrap]) && is_array($data[$wrap])) {
                $found = $this->extractPendingMessageTotal($data[$wrap]);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Single order detail — aliexpress.solution.order.info.get (buyer, address, logistics, funds).
     */
    public function getOrderInfo(string $orderId): array
    {
        $raw = $this->callRestGateway('aliexpress.solution.order.info.get', [
            'param1' => $this->encodeRequestPayload(['order_id' => (string) $orderId]),
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
        if (isset($result['data']) && is_array($result['data'])) {
            $result = $result['data'];
        }

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => is_array($result) ? $result : [],
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Shipping receipt address — aliexpress.solution.order.receiptinfo.get.
     */
    public function getOrderReceiptInfo(string $orderId): array
    {
        $raw = $this->callRestGateway('aliexpress.solution.order.receiptinfo.get', [
            'param1' => $this->encodeRequestPayload(['order_id' => (string) $orderId]),
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => is_array($result) ? $result : [],
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Trade order detail — aliexpress.trade.new.redefining.findorderbyid.
     * Includes loan_info, escrow_fee, pay_amount_by_settlement_cur after payment.
     *
     * @return array{success: bool, message?: string, data?: array<string, mixed>, request_id?: string|null}
     */
    public function getOrderTradeDetail(string $orderId): array
    {
        $raw = $this->callRestGateway('aliexpress.trade.new.redefining.findorderbyid', [
            'param1' => $this->encodeRequestPayload([
                'order_id' => (string) $orderId,
                'ext_info_bit_flag' => 31,
            ]),
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => $this->parseTradeOrderDetailResponse($payload),
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Order loan / settlement fund rows — aliexpress.trade.redefining.findloanlistquery.
     * Returns escrow_fee, affiliate_commission, real_loan_amount when AE has released funds.
     *
     * @return array{success: bool, message?: string, data?: array<string, mixed>, request_id?: string|null}
     */
    public function getOrderLoanFundList(string $orderId, int $page = 1, int $pageSize = 20): array
    {
        $raw = $this->callRestGateway('aliexpress.trade.redefining.findloanlistquery', [
            'param1' => $this->encodeRequestPayload([
                'order_id' => (int) $orderId,
                'page' => max(1, $page),
                'page_size' => min(200, max(1, $pageSize)),
            ]),
        ]);

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);

        return [
            'success' => true,
            'status' => $raw['status'] ?? 200,
            'data' => $this->parseOrderLoanFundResponse($payload),
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function parseTradeOrderDetailResponse(array $payload): array
    {
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
        $target = $result['target'] ?? $result['data'] ?? $result;

        return is_array($target) ? $target : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function parseOrderLoanFundResponse(array $payload): array
    {
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : $payload;
        $orderList = $result['order_list']['order_loan_item_vo']
            ?? $result['order_list']
            ?? [];
        $orders = $this->normalizeList($orderList);
        $sonOrders = [];

        foreach ($orders as $orderRow) {
            if (! is_array($orderRow)) {
                continue;
            }
            $sons = $this->normalizeList($orderRow['son_order_list']['son_order_loan_vo'] ?? $orderRow['son_order_list'] ?? []);
            foreach ($sons as $son) {
                if (is_array($son)) {
                    $sonOrders[] = $son;
                }
            }
        }

        return [
            'total_item' => $result['total_item'] ?? count($orders),
            'orders' => $orders,
            'first' => $orders[0] ?? null,
            'son_orders' => $sonOrders,
        ];
    }

    /**
     * Daily sales for one product (last 30 days) — aliexpress.data.redefining.queryproductsalesinfoeverydaybyid.
     */
    public function getProductDailySales(string $productId, string $startDate, string $endDate, int $page = 1, int $pageSize = 50): array
    {
        return $this->callDataRedefining(
            'aliexpress.data.redefining.queryproductsalesinfoeverydaybyid',
            [
                'product_id' => (string) $productId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'current_page' => $page,
                'page_size' => $pageSize,
            ]
        );
    }

    /**
     * Daily page views for one product (last 30 days) — aliexpress.data.redefining.queryproductviewedinfoeverydaybyid.
     * TOP router params: product_id, start_date, end_date, current_page, page_size.
     */
    public function getProductDailyViews(string $productId, string $startDate, string $endDate, int $page = 1, int $pageSize = 50): array
    {
        return $this->callDataRedefining(
            'aliexpress.data.redefining.queryproductviewedinfoeverydaybyid',
            [
                'product_id' => (string) $productId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'current_page' => (string) $page,
                'page_size' => (string) $pageSize,
            ]
        );
    }

    /**
     * Product L30 business stats — aliexpress.data.redefining.queryproductbusinessinfobyid.
     * Official TOP param is param_string (= product id). Includes viewedCount + outputOrder.
     */
    public function getProductBusinessInfo(string $productId): array
    {
        return $this->callDataRedefining(
            'aliexpress.data.redefining.queryproductbusinessinfobyid',
            ['param_string' => (string) $productId]
        );
    }

    /**
     * L30 traffic for one product: views + CVR from AliExpress data APIs.
     * Prefers queryproductbusinessinfobyid (viewedCount, outputOrder),
     * then sums daily page views from queryproductviewedinfoeverydaybyid.
     * CVR% = outputOrder ÷ viewedCount × 100.
     *
     * @return array{success: bool, views: int, output_order: int, cvr: float, source?: string, message?: string, request_id?: mixed}
     */
    public function getProductPageViewsL30(string $productId): array
    {
        $biz = $this->getProductBusinessInfo($productId);
        $outputOrder = 0;
        $lastId = $biz['request_id'] ?? null;
        $lastMessage = $biz['message'] ?? null;

        if (! empty($biz['success'])) {
            $counts = $this->extractTrafficCounts($biz['data'] ?? []);
            $outputOrder = $counts['orders'];
            if ($counts['viewed'] !== null) {
                return $this->trafficResult($counts['viewed'], $outputOrder, 'business', $lastId);
            }
            // Official AE empty result: {"itemList":[],"success":true,"totalItem":0} — 0 views, not a failure.
            if ($counts['empty']) {
                return $this->trafficResult(0, $outputOrder, 'business', $lastId);
            }
            if ($this->trafficPayloadHasMetrics($counts['parsed'])) {
                $lastMessage = $lastMessage ?: 'Business info returned no viewedCount.';
            }
        }

        $end = now('Asia/Shanghai')->format('Y-m-d');
        $start = now('Asia/Shanghai')->subDays(29)->format('Y-m-d');
        $page = 1;
        $sum = 0;
        $gotDailyPayload = false;

        while ($page <= 5) {
            $daily = $this->getProductDailyViews($productId, $start, $end, $page, 50);
            $lastId = $daily['request_id'] ?? $lastId;
            if (empty($daily['success'])) {
                $lastMessage = $daily['message'] ?? $lastMessage;
                break;
            }

            $counts = $this->extractTrafficCounts($daily['data'] ?? []);
            $parsed = $counts['parsed'];
            $items = $parsed['itemList'] ?? $parsed['item_list'] ?? [];
            $gotDailyPayload = $gotDailyPayload || $this->trafficPayloadHasMetrics($parsed) || is_array($items);
            $pageSum = 0;
            foreach ($this->normalizeList($items) as $item) {
                $item = $this->normalizeApiRow($item);
                $pageSum += (int) ($item['count'] ?? $item['viewed_count'] ?? $item['viewCount'] ?? 0);
            }
            $sum += $pageSum;

            $total = (int) ($parsed['totalItem'] ?? $parsed['total_item'] ?? 0);
            if ($counts['empty'] || ($pageSum === 0 && $total === 0)) {
                return $this->trafficResult($sum, $outputOrder, 'daily', $lastId);
            }
            if ($total > 0 && $page * 50 >= $total) {
                return $this->trafficResult(max(0, $sum), $outputOrder, 'daily', $lastId);
            }
            if ($pageSum === 0) {
                return $this->trafficResult($sum, $outputOrder, 'daily', $lastId);
            }
            $page++;
            usleep(80000);
        }

        if ($sum > 0 || $outputOrder > 0 || $gotDailyPayload) {
            return $this->trafficResult($sum, $outputOrder, $sum > 0 ? 'daily' : 'business', $lastId);
        }

        return [
            'success' => false,
            'views' => 0,
            'output_order' => 0,
            'cvr' => 0.0,
            'message' => $lastMessage ?: 'AliExpress page-view API returned no views data (check TOP/AE-数据 permission).',
            'request_id' => $lastId,
        ];
    }

    /**
     * @return array{success: bool, views: int, output_order: int, cvr: float, source: string, request_id?: mixed}
     */
    private function trafficResult(int $views, int $outputOrder, string $source, mixed $requestId): array
    {
        $views = max(0, $views);
        $outputOrder = max(0, $outputOrder);

        return [
            'success' => true,
            'views' => $views,
            'output_order' => $outputOrder,
            'cvr' => $views > 0 ? round(($outputOrder / $views) * 100, 2) : 0.0,
            'source' => $source,
            'request_id' => $requestId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{viewed: int|null, orders: int, empty: bool, parsed: array<string, mixed>}
     */
    private function extractTrafficCounts(array $payload): array
    {
        $parsed = $this->normalizeTrafficPayload($payload);
        $viewed = $parsed['viewedCount'] ?? $parsed['viewed_count'] ?? $parsed['viewCount'] ?? null;
        $orders = $parsed['outputOrder'] ?? $parsed['output_order'] ?? $parsed['orderCount'] ?? null;

        return [
            'viewed' => ($viewed !== null && $viewed !== '') ? max(0, (int) $viewed) : null,
            'orders' => max(0, (int) ($orders ?? 0)),
            'empty' => $this->isOfficialEmptyTraffic($parsed),
            'parsed' => $parsed,
        ];
    }

    /**
     * Official AE-数据 empty body: {"itemList":[],"success":true,"totalItem":0}
     *
     * @param  array<string, mixed>  $parsed
     */
    private function isOfficialEmptyTraffic(array $parsed): bool
    {
        $success = $parsed['success'] ?? null;
        $ok = $success === true || $success === 'true' || $success === 1 || $success === '1';
        if (! $ok) {
            return false;
        }

        $items = $parsed['itemList'] ?? $parsed['item_list'] ?? null;
        if (is_array($items) && $this->normalizeList($items) === []) {
            return true;
        }

        $total = $parsed['totalItem'] ?? $parsed['total_item'] ?? null;

        return $total !== null && (int) $total === 0
            && ! isset($parsed['viewedCount'])
            && ! isset($parsed['viewed_count'])
            && ! isset($parsed['viewCount']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeTrafficPayload(array $payload, int $depth = 0): array
    {
        $payload = $this->normalizeApiRow($payload);
        if ($depth > 6) {
            return $payload;
        }

        foreach (['result', 'data'] as $key) {
            if (! isset($payload[$key])) {
                continue;
            }
            $inner = $payload[$key];
            if (is_string($inner)) {
                $decoded = json_decode($inner, true);
                if (is_array($decoded)) {
                    return $this->normalizeTrafficPayload($decoded, $depth + 1);
                }
            }
            if (is_array($inner)) {
                $nested = $this->normalizeTrafficPayload($inner, $depth + 1);
                if ($this->trafficPayloadHasMetrics($nested)) {
                    return array_merge($payload, $nested);
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function trafficPayloadHasMetrics(array $parsed): bool
    {
        foreach (['viewedCount', 'viewed_count', 'viewCount', 'outputOrder', 'output_order', 'itemList', 'item_list', 'totalItem', 'total_item', 'success'] as $key) {
            if (array_key_exists($key, $parsed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * AE-数据 APIs use classic TOP protocol (eco.taobao.com), not SG /rest HMAC-SHA256.
     *
     * @return array{success: bool, data?: array, message?: string, request_id?: mixed}
     */
    private function callDataRedefining(string $method, array $params): array
    {
        // 1) Classic TOP router (datetime + session + hmac) — required by AE-数据 docs.
        $raw = $this->callTopRouter($method, $params);

        // 2) SG /rest with same business params (some AE apps only work here).
        if (empty($raw['success'])) {
            $rest = $this->callRestGateway($method, $params);
            if (! empty($rest['success'])) {
                $raw = $rest;
            } elseif (isset($params['param_string']) && ! isset($params['product_id'])) {
                // Business-info docs use param_string; some gateways still expect product_id.
                $alt = $params;
                $alt['product_id'] = $params['param_string'];
                unset($alt['param_string']);
                $rest2 = $this->callRestGateway($method, $alt);
                if (! empty($rest2['success'])) {
                    $raw = $rest2;
                } else {
                    $raw['message'] = trim(
                        ($raw['message'] ?? '').
                        ' | rest: '.($rest['message'] ?? '').
                        ' | rest+product_id: '.($rest2['message'] ?? '')
                    );
                }
            } else {
                $raw['message'] = trim(($raw['message'] ?? '').' | rest: '.($rest['message'] ?? ''));
            }
        }

        if (empty($raw['success'])) {
            return $raw;
        }

        $payload = $this->unwrapSolutionEnvelope($raw['data'] ?? []);
        $parsed = $this->normalizeTrafficPayload($payload);

        // SG /rest often returns {code:0,request_id} with no result — treat as empty failure.
        if (! $this->trafficPayloadHasMetrics($parsed) && ! isset($payload['result'])) {
            $keys = array_keys($payload);
            sort($keys);
            if (
                $keys === ['code', 'request_id']
                || $keys === ['_trace_id_', 'code', 'request_id']
                || $keys === ['code', 'request_id', '_trace_id_']
            ) {
                return [
                    'success' => false,
                    'message' => 'AliExpress data API returned empty result (no viewedCount). Check AE-数据 / TOP permission (param_string).',
                    'request_id' => $raw['request_id'] ?? $payload['request_id'] ?? null,
                    'data' => $payload,
                ];
            }
        }

        return [
            'success' => true,
            'data' => is_array($parsed) ? $parsed : [],
            'request_id' => $raw['request_id'] ?? $payload['request_id'] ?? null,
        ];
    }

    /**
     * Classic TOP / Taobao Open Platform router (datetime timestamp, session, hmac-md5).
     * Required for aliexpress.data.redefining.* (AE-数据).
     *
     * @param  array<string, mixed>  $businessParams
     * @return array<string, mixed>
     */
    private function callTopRouter(string $method, array $businessParams = []): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            return ['success' => false, 'message' => 'AliExpress app_key / app_secret are missing.'];
        }
        if (empty($this->accessToken)) {
            return [
                'success' => false,
                'message' => $this->channelLabel.' OAuth token is missing (set '.$this->tokenEnvKey.').',
            ];
        }

        $params = [
            'method' => $method,
            'app_key' => $this->appKey,
            'session' => $this->accessToken,
            'timestamp' => now('Asia/Shanghai')->format('Y-m-d H:i:s'),
            'format' => 'json',
            'v' => '2.0',
            'sign_method' => $this->restSignMethod === 'md5' ? 'md5' : 'hmac',
            'partner_id' => $this->partnerId !== '' ? $this->partnerId : 'invent-php',
        ];

        foreach ($businessParams as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $params[(string) $key] = is_array($value)
                ? $this->encodeRequestPayload($value)
                : (string) $value;
        }

        $params['sign'] = $this->signTopRestParams($params);

        $bases = array_values(array_unique(array_filter([
            $this->topBase,
            'https://api-sg.aliexpress.com/rest',
            'https://api-sg.aliexpress.com/sync',
            'https://eco.taobao.com/router/rest',
            'https://gw.api.taobao.com/router/rest',
        ])));

        $last = null;
        $attemptIndex = 0;
        foreach ($bases as $base) {
            // Try TOP hmac/md5 first, then also sha256 TOP-style sorted key+value (no /sync prefix)
            // for AliExpress SG hosts that reject Taobao TOP but accept datetime+session.
            $signVariants = [
                ['sign_method' => $this->restSignMethod === 'md5' ? 'md5' : 'hmac', 'style' => 'top'],
                ['sign_method' => 'sha256', 'style' => 'sha256'],
            ];
            foreach ($signVariants as $variant) {
                $attempt = $params;
                $attempt['sign_method'] = $variant['sign_method'];
                unset($attempt['sign']);
                if ($variant['style'] === 'sha256') {
                    $attempt['sign'] = $this->signTopSha256($attempt);
                } else {
                    $attempt['sign'] = $this->signTopRestParams($attempt);
                }

                $client = $this->httpClient();
                // Dead TOP hosts (taobao.com from some networks) used to stall 60s each and kill the job.
                if ($attemptIndex > 0) {
                    $client = $client->connectTimeout(8)->timeout(15);
                }
                $attemptIndex++;

                try {
                    $response = $client
                        ->asForm()
                        ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8'])
                        ->post($base, $attempt);
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    $last = $this->networkErrorResult(
                        'Could not reach AliExpress TOP router ('.$base.').',
                        $e
                    );
                    continue;
                }

                $parsed = $this->parseHttpResponse($response, $method, 'top');
                $last = $parsed;

                if (! empty($parsed['success'])) {
                    return $parsed;
                }

                // Keep trying other hosts/sign styles on invalid-key / signature / empty.
                $msg = strtolower((string) ($parsed['message'] ?? ''));
                if (
                    str_contains($msg, 'invalid app')
                    || $this->isSignatureError($parsed)
                    || str_contains($msg, 'empty result')
                ) {
                    continue;
                }

                // Non-retryable business error for this host.
                if (! ($parsed['network_error'] ?? false)) {
                    // Still try next host; AE-数据 may only work on one gateway.
                    continue;
                }
            }
        }

        return $last ?? ['success' => false, 'message' => 'AliExpress TOP router call failed.'];
    }

    /**
     * TOP file upload. Binary fields are attached as multipart and omitted from the sign.
     *
     * @param  array<string, string>  $businessParams
     * @return array<string, mixed>
     */
    private function callTopRouterWithFile(string $method, array $businessParams, string $fileField, string $bytes, string $fileName): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            return ['success' => false, 'message' => 'AliExpress app_key / app_secret are missing.'];
        }
        if (empty($this->accessToken)) {
            return [
                'success' => false,
                'message' => $this->channelLabel.' OAuth token is missing (set '.$this->tokenEnvKey.').',
            ];
        }

        $params = [
            'method' => $method,
            'app_key' => $this->appKey,
            'session' => $this->accessToken,
            'timestamp' => now('Asia/Shanghai')->format('Y-m-d H:i:s'),
            'format' => 'json',
            'v' => '2.0',
            'sign_method' => $this->restSignMethod === 'md5' ? 'md5' : 'hmac',
            'partner_id' => $this->partnerId !== '' ? $this->partnerId : 'invent-php',
        ];
        foreach ($businessParams as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $params[(string) $key] = (string) $value;
        }

        $bases = array_values(array_unique(array_filter([
            $this->topBase,
            'https://eco.taobao.com/router/rest',
            'https://gw.api.taobao.com/router/rest',
        ])));

        $last = ['success' => false, 'message' => 'AliExpress photobank upload failed.'];
        foreach ($bases as $index => $base) {
            if ($index > 1) {
                break;
            }
            $signVariants = [
                ['sign_method' => 'hmac', 'style' => 'top'],
                ['sign_method' => 'md5', 'style' => 'top'],
            ];
            foreach ($signVariants as $variant) {
                $attempt = $params;
                $attempt['sign_method'] = $variant['sign_method'];
                unset($attempt['sign']);
                $attempt['sign'] = $variant['style'] === 'sha256'
                    ? $this->signTopSha256($attempt)
                    : $this->signTopRestParams($attempt);

                try {
                    $response = $this->httpClient()
                        ->connectTimeout(8)
                        ->timeout(25)
                        ->asMultipart()
                        ->attach($fileField, $bytes, $fileName)
                        ->post($base, $attempt);
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    $last = $this->networkErrorResult('Could not reach AliExpress photobank ('.$base.').', $e);
                    continue;
                }

                $parsed = $this->parseHttpResponse($response, $method, 'top-file');
                $last = $parsed;
                if ($this->extractPhotobankUrl($parsed) !== '' || ! empty($parsed['success'])) {
                    return $parsed;
                }
                if (! $this->isSignatureError($parsed) && empty($parsed['network_error'])) {
                    return $parsed;
                }
            }
        }

        return $last;
    }

    /**
     * TOP-style sorted key+value HMAC-SHA256 (no /sync or method-name prefix).
     *
     * @param  array<string, string>  $params
     */
    private function signTopSha256(array $params): string
    {
        unset($params['sign']);
        ksort($params);
        $source = '';
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $source .= (string) $key.(string) $value;
            }
        }

        return strtoupper(hash_hmac('sha256', $source, $this->appSecret));
    }

    /**
     * Product review count + average rating.
     * Primary: aliexpress.social.product.evaluation.query (total_number).
     * Fallback: aliexpress.solution.product.info.get (evaluation_count / avg_evaluation_rating).
     *
     * @return array{success: bool, reviews: int, avg_rating: float, source?: string, message?: string, request_id?: mixed}
     */
    public function getProductReviews(string $productId): array
    {
        $stats = ['reviews' => null, 'avg_rating' => null];
        $lastId = null;
        $lastMessage = null;
        $source = null;

        $eval = $this->callDataRedefining('aliexpress.social.product.evaluation.query', [
            'product_id' => (string) $productId,
            'page' => 1,
            'page_size' => 20,
        ]);
        $lastId = $eval['request_id'] ?? $lastId;
        $lastMessage = $eval['message'] ?? $lastMessage;
        if (! empty($eval['success'])) {
            $stats = $this->mergeReviewStats($stats, $this->extractReviewStats($eval['data'] ?? []));
            $source = 'evaluation.query';
        }

        if ($stats['reviews'] === null || $stats['avg_rating'] === null) {
            $info = $this->getProductInfo($productId);
            $lastId = $info['request_id'] ?? $lastId;
            $lastMessage = $info['message'] ?? $lastMessage;
            if (! empty($info['success'])) {
                $stats = $this->mergeReviewStats($stats, $this->extractReviewStats($info['data'] ?? []));
                $source = $source ?: 'product.info';
            }
        }

        if ($stats['reviews'] === null && $stats['avg_rating'] === null) {
            return [
                'success' => false,
                'reviews' => 0,
                'avg_rating' => 0.0,
                'message' => $lastMessage ?: 'AliExpress review API returned no reviews.',
                'request_id' => $lastId,
            ];
        }

        return [
            'success' => true,
            'reviews' => max(0, (int) ($stats['reviews'] ?? 0)),
            'avg_rating' => round((float) ($stats['avg_rating'] ?? 0), 2),
            'source' => $source ?: 'api',
            'request_id' => $lastId,
        ];
    }

    /**
     * @param  array{reviews: int|null, avg_rating: float|null}  $current
     * @param  array{reviews: int|null, avg_rating: float|null}  $incoming
     * @return array{reviews: int|null, avg_rating: float|null}
     */
    private function mergeReviewStats(array $current, array $incoming): array
    {
        if ($current['reviews'] === null && $incoming['reviews'] !== null) {
            $current['reviews'] = $incoming['reviews'];
        }
        if ($current['avg_rating'] === null && $incoming['avg_rating'] !== null) {
            $current['avg_rating'] = $incoming['avg_rating'];
        }

        return $current;
    }

    /**
     * @return array{reviews: int|null, avg_rating: float|null}
     */
    private function extractReviewStats(array $data): array
    {
        $data = $this->normalizeApiRow($data);
        $reviews = $data['total_number']
            ?? $data['totalNumber']
            ?? $data['evaluation_count']
            ?? $data['evaluationCount']
            ?? $data['review_count']
            ?? $data['reviewCount']
            ?? $data['total_item']
            ?? $data['totalItem']
            ?? null;
        $rating = $data['avg_evaluation_rating']
            ?? $data['avgEvaluationRating']
            ?? $data['avg_evaluation']
            ?? $data['average_star']
            ?? $data['averageStar']
            ?? $data['product_star_rating']
            ?? $data['evaluation_rating']
            ?? null;

        if (isset($data['result']) && (is_array($data['result']) || is_string($data['result']))) {
            $nestedRaw = is_string($data['result']) ? json_decode($data['result'], true) : $data['result'];
            if (is_array($nestedRaw)) {
                $nested = $this->extractReviewStats($nestedRaw);
                if ($reviews === null) {
                    $reviews = $nested['reviews'];
                }
                if ($rating === null) {
                    $rating = $nested['avg_rating'];
                }
            }
        }

        if ($rating === null) {
            $evals = $data['evaluations'] ?? $data['buyer_evaluation'] ?? $data['evaluation_list'] ?? [];
            $stars = [];
            foreach ($this->normalizeList($evals) as $row) {
                $row = $this->normalizeApiRow($row);
                $star = $row['evaluation'] ?? $row['buyer_eval'] ?? $row['buyerEval'] ?? null;
                if (is_numeric($star)) {
                    $stars[] = (float) $star;
                }
            }
            if ($stars !== []) {
                $rating = array_sum($stars) / count($stars);
            }
        }

        return [
            'reviews' => $reviews !== null && $reviews !== '' ? max(0, (int) $reviews) : null,
            'avg_rating' => $this->normalizeStarRating($rating),
        ];
    }

    private function normalizeStarRating(mixed $rating): ?float
    {
        if ($rating === null || $rating === '' || ! is_numeric($rating)) {
            return null;
        }
        $value = (float) $rating;
        if ($value > 5 && $value <= 100) {
            $value = $value / 20;
        }

        return round(max(0, min(5, $value)), 2);
    }

    /**
     * @return array<int, array{sku: string, price: float, stock: int|null, product_id: string, product_name: string|null}>
     */
    public function extractSkuRowsFromListItem(array $item, bool $fetchDetail = false): array
    {
        $item = $this->normalizeApiRow($item);
        $productId = (string) ($item['product_id'] ?? $item['productId'] ?? $item['id'] ?? '');
        if ($productId === '') {
            return [];
        }

        $productName = $this->extractProductName($item);
        $rows = [];

        if ($fetchDetail) {
            $info = $this->getProductInfo($productId);
            if (! empty($info['success']) && is_array($info['data'] ?? null)) {
                $rows = $this->extractSkuRowsFromProductInfo($info['data'], $productId, $productName);
                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        $nested = $item['aeop_ae_product_sku_list']
            ?? $item['aeop_ae_product_s_k_us']
            ?? $item['aeop_aeop_product_skus']
            ?? $item['skus']
            ?? $item['product_skus']
            ?? $item['sku_list']
            ?? $item['product_sku_list']
            ?? $item['sku_info_list']
            ?? null;

        if (is_array($nested) && $nested !== []) {
            foreach ($this->normalizeList($nested) as $skuRow) {
                $skuRow = $this->normalizeApiRow($skuRow);
                $sku = trim((string) ($skuRow['sku_code'] ?? $skuRow['sku'] ?? $skuRow['seller_sku'] ?? $skuRow['cargo_number'] ?? ''));
                $price = $this->extractPriceFromRow($skuRow);
                if ($sku === '' && $price <= 0) {
                    continue;
                }
                $rows[] = [
                    'product_id' => $productId,
                    'sku' => $sku !== '' ? $sku : $productId,
                    'price' => $price > 0 ? $price : $this->extractListPrice($item),
                    'stock' => $this->extractStockFromRow($skuRow),
                    'product_name' => $productName,
                ];
            }

            if ($rows !== []) {
                return $rows;
            }
        }

        $price = $this->extractListPrice($item);
        $stock = $this->extractStockFromRow($item);
        if ($price > 0 || $productName !== null || $stock !== null) {
            $rows[] = [
                'product_id' => $productId,
                'sku' => $productId,
                'price' => $price,
                'stock' => $stock,
                'product_name' => $productName,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{sku: string, price: float, stock: int|null, product_id: string, product_name: string|null}>
     */
    public function extractSkuRowsFromProductInfo(array $info, string $productId, ?string $productName = null): array
    {
        $info = $this->normalizeApiRow($info);
        $productName = $productName ?? $this->extractProductName($info);
        $rows = [];

        if (isset($info['result']) && is_array($info['result']) && ! isset($info['aeop_ae_product_sku_list'])) {
            $nested = $this->extractSkuRowsFromProductInfo($info['result'], $productId, $productName);
            if ($nested !== []) {
                return $nested;
            }
        }

        $skus = $info['aeop_ae_product_sku_list']
            ?? $info['aeop_ae_product_s_k_us']
            ?? $info['aeop_a_e_product_s_k_u_list']
            ?? $info['aeop_ae_product_s_k_u_s']
            ?? $info['product_sku_list']
            ?? $info['sku_info_list']
            ?? $info['sku_list']
            ?? $info['skus']
            ?? [];
        if (is_string($skus)) {
            $decoded = json_decode($skus, true);
            $skus = is_array($decoded) ? $decoded : [];
        }

        foreach ($this->normalizeList($skus) as $skuRow) {
            $skuRow = $this->normalizeApiRow($skuRow);
            $sku = trim((string) ($skuRow['sku_code'] ?? $skuRow['sku'] ?? $skuRow['seller_sku'] ?? $skuRow['cargo_number'] ?? ''));
            $price = $this->extractPriceFromRow($skuRow);
            if ($sku === '' && $price <= 0) {
                continue;
            }
            $rows[] = [
                'product_id' => $productId,
                'sku' => $sku !== '' ? $sku : $productId,
                'price' => $price,
                'stock' => $this->extractStockFromRow($skuRow),
                'product_name' => $productName,
            ];
        }

        if ($rows === []) {
            $price = $this->extractListPrice($info);
            $stock = $this->extractStockFromRow($info);
            if ($price > 0 || $stock !== null) {
                $rows[] = [
                    'product_id' => $productId,
                    'sku' => $productId,
                    'price' => $price,
                    'stock' => $stock,
                    'product_name' => $productName,
                ];
            }
        }

        return $rows;
    }

    /**
     * Normalize order list payload to a flat list of orders with product lines.
     */
    protected function parseSolutionOrderListResponse(array $payload): array
    {
        $result = $payload['result'] ?? $payload;
        if (! is_array($result)) {
            $result = [];
        }

        $orders = $result['target_list']
            ?? $result['order_list']
            ?? $result['orders']
            ?? [];

        $orders = $this->normalizeList($orders);

        return [
            'orders' => $orders,
            'total_count' => $result['total_count'] ?? $result['totalCount'] ?? null,
            'total_page' => $result['total_page'] ?? $result['totalPage'] ?? null,
            'current_page' => $result['current_page'] ?? $result['currentPage'] ?? null,
            'page_size' => $result['page_size'] ?? $result['pageSize'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractOrderProductLines(array $order): array
    {
        $order = $this->normalizeApiRow($order);
        $products = $order['product_list']['order_product_dto']
            ?? $order['product_list']['aeop_order_product_dto']
            ?? $order['product_list']
            ?? $order['child_order_list']
            ?? $order['product_items']
            ?? $order['order_entries']
            ?? $order['entry_list']
            ?? $order['sku_list']
            ?? [];

        $lines = [];
        foreach ($this->normalizeList($products) as $product) {
            $product = $this->normalizeApiRow($product);
            $lines[] = [
                'product_id' => (string) ($product['product_id'] ?? ''),
                'sku_code' => (string) ($product['sku_code'] ?? $product['sku'] ?? $product['seller_sku'] ?? $product['cargo_number'] ?? ''),
                'product_count' => (int) ($product['product_count'] ?? $product['quantity'] ?? 1),
                'product_unit_price' => [
                    'amount' => $this->extractPriceFromRow($product),
                ],
                'product_name' => $product['product_name'] ?? $product['subject'] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * Build order query date range (US Pacific) for the last N days.
     *
     * @return array{create_date_start: string, create_date_end: string}
     */
    public function buildOrderDateRange(int $days): array
    {
        $end = Carbon::now('America/Los_Angeles');
        $start = $end->copy()->subDays(max(1, $days));

        return [
            'create_date_start' => $start->format('Y-m-d H:i:s'),
            'create_date_end' => $end->format('Y-m-d H:i:s'),
        ];
    }

    private function extractListPrice(array $item): float
    {
        $item = $this->normalizeApiRow($item);
        foreach (['product_min_price', 'product_max_price', 'item_offer_min_price', 'price', 'sale_price'] as $key) {
            $parsed = $this->parseMoney($item[$key] ?? null);
            if ($parsed > 0) {
                return $parsed;
            }
        }

        return $this->extractPriceFromRow($item);
    }

    private function extractStockFromRow(array $row): ?int
    {
        $row = $this->normalizeApiRow($row);
        foreach (['ipm_sku_stock', 'sku_stock', 'stock', 'inventory', 'available_stock', 'sku_inventory', 'quantity'] as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $val = $row[$key];
            if ($val === true || $val === 'true') {
                return 1;
            }
            if ($val === false || $val === 'false' || $val === null || $val === '') {
                continue;
            }
            if (is_numeric($val)) {
                return max(0, (int) $val);
            }
        }

        return null;
    }

    private function extractPriceFromRow(array $row): float
    {
        $row = $this->normalizeApiRow($row);
        foreach ([
            'sku_price', 'offer_sale_price', 'sku_discount_price', 'sale_price',
            'price', 'product_min_price', 'product_max_price', 'unit_price',
            'supply_price', 'retail_price', 'discount_price', 'original_price',
            'offer_bulk_sale_price', 's_sku_price',
        ] as $key) {
            $parsed = $this->parseMoney($row[$key] ?? null);
            if ($parsed > 0) {
                return $parsed;
            }
        }
        if (isset($row['product_unit_price']) && is_array($row['product_unit_price'])) {
            $parsed = $this->parseMoney($row['product_unit_price']['amount'] ?? null);
            if ($parsed > 0) {
                return $parsed;
            }
        }

        return 0.0;
    }

    private function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '' || $value === false) {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return 0.0;
        }
        $n = preg_replace('/[^0-9.\-]/', '', $value);

        return is_numeric($n) ? (float) $n : 0.0;
    }

    private function extractProductName(array $item): ?string
    {
        $item = $this->normalizeApiRow($item);
        foreach (['subject', 'product_name', 'title', 'product_title', 'name'] as $key) {
            if (! empty($item[$key]) && is_string($item[$key])) {
                return trim($item[$key]);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeApiRow(mixed $row): array
    {
        if (is_array($row)) {
            return $row;
        }
        if (is_object($row)) {
            return json_decode(json_encode($row), true) ?: [];
        }

        return [];
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeList(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }
        if ($list === []) {
            return [];
        }
        if (! isset($list[0]) && (isset($list['product_id']) || isset($list['sku_code']) || isset($list['order_id']))) {
            return [$list];
        }

        return array_values($list);
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message?: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 5);
        if (trim($identifier) === '' || $videos === []) {
            return ['success' => false, 'message' => 'SKU (or AliExpress product_id) and at least one video URL are required.'];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }

        $trim = trim($identifier);
        $row = AliexpressMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if (! $row) {
            $row = AliexpressMetric::query()->where('product_id', $trim)->first();
        }
        $productId = $row && $row->product_id ? (string) $row->product_id : $trim;
        $primary = $videos[0];

        $attempts = [
            ['product_id' => $productId, 'video_url' => $primary, 'product_video_url' => $primary],
            ['product_id' => $productId, 'multimedia' => ['video_url' => $primary]],
            ['product_id' => $productId, 'aeop_a_e_multimedia' => ['aeop_a_e_videos' => [['video_url' => $primary]]]],
        ];

        $lastMessage = 'AliExpress video update failed.';
        foreach ($attempts as $editRequest) {
            $res = $this->callSync('aliexpress.solution.product.edit', [
                'edit_product_request' => $this->encodeRequestPayload($editRequest),
            ]);
            if (! empty($res['success'])) {
                $sku = $row && $row->sku ? (string) $row->sku : $trim;
                $table = app(\App\Services\Support\MarketplaceMetricsTableResolver::class)->table('aliexpress') ?? 'aliexpress_metric';
                $this->saveVideoUrlsToMetricsRow($table, $sku, $videos);

                return [
                    'success' => true,
                    'message' => 'AliExpress product video updated.',
                    'normalized_urls' => $videos,
                ];
            }
            $lastMessage = (string) ($res['message'] ?? $lastMessage);
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    /**
     * @param  list<string>  $urls
     * @return array{success: bool, message: string, urls: list<string>}
     */
    public function uploadImagesToPhotobank(array $urls): array
    {
        $hosted = [];
        $map = [];
        $lastFail = 'No Image Master photos to upload.';
        foreach (array_slice(array_values(array_unique(array_filter(array_map('trim', $urls)))), 0, 6) as $url) {
            if ($this->isAliExpressCdnUrl($url)) {
                $hosted[] = $url;
                $map[$url] = $url;
                continue;
            }
            $res = $this->uploadPhotobankImage($url);
            if (empty($res['success']) || trim((string) ($res['url'] ?? '')) === '') {
                $lastFail = (string) ($res['message'] ?? $lastFail);
                Log::warning('AliExpress photobank: skipped Image Master photo', [
                    'source' => mb_substr($url, 0, 200),
                    'message' => $lastFail,
                ]);
                continue;
            }
            $hosted[] = (string) $res['url'];
            $map[$url] = (string) $res['url'];
        }

        return [
            'success' => $hosted !== [],
            'message' => $hosted === [] ? $lastFail : '',
            'urls' => $hosted,
            'map' => $map,
        ];
    }

    /**
     * @return array{success: bool, url?: string, message: string}
     */
    public function uploadPhotobankImage(string $sourceUrl): array
    {
        $sourceUrl = trim($sourceUrl);
        if ($sourceUrl === '') {
            return ['success' => false, 'message' => 'Image URL is empty.'];
        }
        if ($this->isAliExpressCdnUrl($sourceUrl)) {
            return ['success' => true, 'url' => $sourceUrl, 'message' => ''];
        }

        $downloaded = $this->downloadImageBytes($sourceUrl);
        $bytes = (string) ($downloaded['bytes'] ?? '');
        if ($bytes === '') {
            return ['success' => false, 'message' => $downloaded['message'] ?? 'Could not download the Image Master photo.'];
        }
        $prepared = $this->preparePhotobankImage($bytes, (string) ($downloaded['filename'] ?? 'image.jpg'));
        $bytes = (string) ($prepared['bytes'] ?? '');
        if ($bytes === '') {
            return ['success' => false, 'message' => $prepared['message'] ?? 'Could not prepare the Image Master photo for AliExpress.'];
        }
        $fileName = (string) ($prepared['filename'] ?? 'image.jpg');
        $business = [
            'file_name' => $fileName,
            'group_id' => '0',
        ];
        $last = 'AliExpress photobank upload failed.';

        foreach ([
            'aliexpress.photobank.redefining.uploadimageforsdk',
            'aliexpress.photobank.redefining.uploadimage',
        ] as $method) {
            foreach ([
                fn () => $this->callIopFileUpload($method, $business, 'image_bytes', $bytes, $fileName),
                fn () => $this->callRestWithFile($method, $business, 'image_bytes', $bytes, $fileName),
                fn () => $this->callTopRouterWithFile($method, $business, 'image_bytes', $bytes, $fileName),
            ] as $sender) {
                $parsed = $sender();
                $url = $this->extractPhotobankUrl($parsed);
                if ($url !== '') {
                    return ['success' => true, 'url' => $url, 'message' => ''];
                }
                $last = trim((string) ($parsed['message'] ?? $last));
                if (! $this->isSignatureError($parsed) && empty($parsed['network_error']) && ! $this->isRetryablePhotobankError($parsed)) {
                    break 2;
                }
            }
        }

        Log::warning('AliExpress photobank upload failed', [
            'source' => mb_substr($sourceUrl, 0, 200),
            'file' => $fileName,
            'message' => $last,
        ]);

        return [
            'success' => false,
            'message' => $last !== '' ? $last : 'AliExpress photobank upload failed.',
        ];
    }

    public function isAliExpressCdnUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return $host !== '' && (str_contains($host, 'alicdn.com') || str_contains($host, 'aliexpress-media.com'));
    }

    /**
     * @param  array<string, mixed>  $res
     */
    private function extractPhotobankUrl(array $res): string
    {
        $found = '';
        $walk = function ($node) use (&$found, &$walk): void {
            if ($found !== '' || ! is_array($node)) {
                return;
            }
            foreach ($node as $key => $value) {
                $keyLower = strtolower((string) $key);
                if (! is_array($value) && in_array($keyLower, ['photobank_url', 'photobankurl', 'file_url', 'fileurl', 'image_url', 'imageurl', 'url'], true)) {
                    $url = trim((string) $value);
                    if (str_starts_with($url, '//')) {
                        $url = 'https:'.$url;
                    }
                    if (str_starts_with($url, 'http://')) {
                        $url = 'https://'.substr($url, 7);
                    }
                    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
                    $looksHosted = $host !== '' && (
                        str_contains($host, 'alicdn.com')
                        || str_contains($host, 'aliexpress-media.com')
                        || $keyLower === 'photobank_url'
                        || $keyLower === 'photobankurl'
                    );
                    if (preg_match('#^https?://#i', $url) && $looksHosted) {
                        $found = $url;

                        return;
                    }
                }
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($res);

        return $found;
    }

    /**
     * @return array{bytes: string, filename: string, message?: string}
     */
    private function downloadImageBytes(string $url): array
    {
        $url = trim($url);
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (str_starts_with($url, '/')) {
            $base = rtrim((string) config('app.url'), '/');
            $url = $base !== '' ? $base.$url : $url;
        }
        if (! preg_match('#^https?://#i', $url)) {
            return ['bytes' => '', 'filename' => 'image.jpg', 'message' => 'Image Master photo is not a public URL.'];
        }

        $candidates = [$url];
        $stripped = preg_replace('/\?.*$/', '', $url);
        if (is_string($stripped) && $stripped !== $url) {
            $candidates[] = $stripped;
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === 'cdn.shopify.com') {
            $alt = preg_replace('#://cdn\.shopify\.com#i', '://cdn.shopifycdn.com', $url);
            if (is_string($alt) && $alt !== $url) {
                $candidates[] = $alt;
            }
        }

        $last = 'Could not download the Image Master photo.';
        foreach (array_values(array_unique($candidates)) as $try) {
            $got = $this->httpGetImageBytes($try);
            if (($got['bytes'] ?? '') !== '') {
                return $got;
            }
            $last = (string) ($got['message'] ?? $last);
        }

        return ['bytes' => '', 'filename' => 'image.jpg', 'message' => $last];
    }

    /**
     * @return array{bytes: string, filename: string, message?: string}
     */
    private function httpGetImageBytes(string $url): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (compatible; 5CORE-ImageMaster/1.0)',
            'Accept' => 'image/jpeg,image/jpg,image/png,image/gif,image/*,*/*;q=0.8',
        ];
        $clients = [
            $this->httpClient(),
            $this->imageDownloadClient($url),
        ];
        $last = 'Could not download the Image Master photo.';
        foreach ($clients as $client) {
            try {
                $response = $client->withHeaders($headers)->get($url);
            } catch (\Throwable $e) {
                $last = 'Could not download Image Master photo: '.$e->getMessage();
                continue;
            }
            if (! $response->successful()) {
                $last = 'Image Master photo returned HTTP '.$response->status().'.';
                continue;
            }
            $bytes = (string) $response->body();
            if ($bytes === '') {
                $last = 'Image Master photo is empty.';
                continue;
            }
            if (strlen($bytes) > 3 * 1024 * 1024) {
                return ['bytes' => '', 'filename' => 'image.jpg', 'message' => 'Image Master photo is larger than 3MB.'];
            }
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($path)) ?: 'image.jpg';
            if (! str_contains($name, '.')) {
                $name .= '.jpg';
            }

            return ['bytes' => $bytes, 'filename' => $name];
        }

        return ['bytes' => '', 'filename' => 'image.jpg', 'message' => $last];
    }

    /**
     * When the server DNS cannot resolve cdn.shopify.com, use public DNS and pin the IP.
     */
    private function imageDownloadClient(string $url): \Illuminate\Http\Client\PendingRequest
    {
        $pending = $this->httpClient();
        $curl = [];
        if (defined('CURLOPT_DNS_SERVERS')) {
            $curl[CURLOPT_DNS_SERVERS] = '8.8.8.8,1.1.1.1';
        }
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curl[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?: 'https'));
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));
        $ip = $host !== '' ? $this->resolveHostWithPublicDns($host) : null;
        if ($ip !== null && defined('CURLOPT_RESOLVE')) {
            $curl[CURLOPT_RESOLVE] = [$host.':'.$port.':'.$ip];
        }
        if ($curl !== []) {
            $pending = $pending->withOptions(['curl' => $curl]);
        }

        return $pending;
    }

    private function resolveHostWithPublicDns(string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host !== '' ? $host : null;
        }
        $cacheKey = 'aliexpress.dns.'.$host;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && filter_var($cached, FILTER_VALIDATE_IP)) {
            return $cached;
        }

        $lookups = [
            ['https://1.1.1.1/dns-query', ['name' => $host, 'type' => 'A']],
            ['https://dns.google/resolve', ['name' => $host, 'type' => 'A']],
        ];
        foreach ($lookups as [$endpoint, $query]) {
            try {
                $response = Http::withoutVerifying()
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->withHeaders(['Accept' => 'application/dns-json'])
                    ->get($endpoint, $query);
            } catch (\Throwable $e) {
                continue;
            }
            if (! $response->successful()) {
                continue;
            }
            foreach ((array) $response->json('Answer') as $answer) {
                if (! is_array($answer)) {
                    continue;
                }
                $ip = trim((string) ($answer['data'] ?? ''));
                $type = (int) ($answer['type'] ?? 0);
                if ($type === 1 && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    Cache::put($cacheKey, $ip, 600);

                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * AliExpress photobank accepts JPEG/PNG/GIF only (not WebP/AVIF).
     *
     * @return array{bytes: string, filename: string, message?: string}
     */
    private function preparePhotobankImage(string $bytes, string $fileName): array
    {
        if ($this->isWebpImage($bytes) || $this->isAvifImage($bytes)) {
            $converted = $this->convertImageToJpeg($bytes);
            if ($converted === '') {
                return [
                    'bytes' => '',
                    'filename' => $fileName,
                    'message' => 'AliExpress does not accept WebP/AVIF. Could not convert the Image Master photo to JPEG.',
                ];
            }
            $bytes = $converted;
            $fileName = preg_replace('/\.[a-z0-9]+$/i', '', $fileName) ?: 'image';
            $fileName .= '.jpg';
        }

        if (strlen($bytes) > 3 * 1024 * 1024) {
            return ['bytes' => '', 'filename' => $fileName, 'message' => 'Image Master photo is larger than 3MB.'];
        }

        return ['bytes' => $bytes, 'filename' => $fileName];
    }

    private function isWebpImage(string $bytes): bool
    {
        return strlen($bytes) >= 12
            && str_starts_with($bytes, 'RIFF')
            && substr($bytes, 8, 4) === 'WEBP';
    }

    private function isAvifImage(string $bytes): bool
    {
        $brand = strtolower(substr($bytes, 4, 12));

        return strlen($bytes) >= 16 && (str_contains($brand, 'ftypavif') || str_contains($brand, 'ftypavis'));
    }

    private function convertImageToJpeg(string $bytes): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return '';
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return '';
        }
        if (function_exists('imagepalettetotruecolor') && ! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        if (function_exists('imagealphablending') && function_exists('imagesavealpha')) {
            imagealphablending($image, true);
            imagesavealpha($image, false);
        }
        ob_start();
        $ok = imagejpeg($image, null, 90);
        imagedestroy($image);
        $jpeg = (string) ob_get_clean();

        return $ok && $jpeg !== '' ? $jpeg : '';
    }

    /**
     * @param  list<string>  $images
     * @return array{success: bool, message?: string, normalized_urls?: list<string>}
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 12);
        if (trim($identifier) === '' || $images === []) {
            return ['success' => false, 'message' => 'SKU (or '.$this->channelLabel.' product_id) and at least one image URL are required.'];
        }

        foreach ($images as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid image URL (must be http/https).'];
            }
        }

        $trim = trim($identifier);
        $row = $this->findChannelMetricRow($trim);
        $productId = $this->resolveProductIdBySku($trim);
        if ($productId === null || $productId === '') {
            $productId = ($row && $row->product_id) ? (string) $row->product_id : '';
        }
        if ($productId === '' && preg_match('/^\d{6,}$/', $trim)) {
            $productId = $trim;
        }
        if ($productId === '') {
            return [
                'success' => false,
                'message' => $this->channelLabel.' product_id not found for this SKU. Sync '.$this->channelLabel.' listings first.',
            ];
        }

        $primary = $images[0];
        $joined = implode(';', $images);
        $skuCode = $row && $row->sku ? (string) $row->sku : $trim;

        foreach ([
            ['product_id' => $productId, 'fied_name' => 'image_u_r_ls', 'fiedvalue' => $joined],
            ['productId' => $productId, 'fiedName' => 'image_u_r_ls', 'fiedValue' => $joined],
            ['product_id' => $productId, 'fied_name' => 'imageURLs', 'fiedvalue' => $joined],
        ] as $params) {
            foreach ([
                'aliexpress.postproduct.redefining.editsinglefiled',
                'aliexpress.postproduct.redefining.editSingleFiled',
            ] as $method) {
                $single = $this->callApiFlexible($method, [
                    'rest' => $params,
                    'sync' => $params,
                ]);
                if (! empty($single['success'])) {
                    return $this->finishChannelImageUpdate($row, $trim, $images, $single);
                }
            }
        }

        $attempts = [
            ['product_id' => $productId, 'image_u_r_ls' => $joined, 'main_image_url' => $primary],
            ['product_id' => $productId, 'image_urls' => $images, 'main_image_url' => $primary],
            ['product_id' => $productId, 'aeop_a_e_product_s_k_us' => ['sku_code' => $skuCode, 'sku_image' => $primary]],
        ];

        $lastMessage = $this->channelLabel.' image update failed.';
        foreach ($attempts as $editRequest) {
            $encoded = $this->encodeRequestPayload($editRequest);
            $res = $this->callApiFlexible('aliexpress.solution.product.edit', [
                'rest' => ['edit_product_request' => $encoded],
                'sync' => ['edit_product_request' => $encoded],
            ]);
            if (! empty($res['success'])) {
                return $this->finishChannelImageUpdate($row, $trim, $images, $res);
            }
            $lastMessage = (string) ($res['message'] ?? $lastMessage);
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    /**
     * @param  object{sku?: mixed}|null  $row
     * @param  list<string>  $images
     * @param  array<string, mixed>  $res
     * @return array{success: bool, message: string, normalized_urls: list<string>}
     */
    protected function finishChannelImageUpdate(?object $row, string $identifier, array $images, array $res): array
    {
        $sku = $row && ! empty($row->sku) ? (string) $row->sku : $identifier;
        $table = app(\App\Services\Support\MarketplaceMetricsTableResolver::class)
            ->table($this->channelImageMetricsMarketplaceKey())
            ?? ($this->channelImageMetricsMarketplaceKey() === 'alibaba' ? 'alibaba_metrics' : 'aliexpress_metric');
        $this->saveImageUrlsToMetricsRow($table, $sku, $images);

        return [
            'success' => true,
            'message' => (string) ($res['message'] ?? $this->channelLabel.' product images updated.'),
            'normalized_urls' => $images,
        ];
    }

    protected function channelImageMetricsMarketplaceKey(): string
    {
        return 'aliexpress';
    }

    /**
     * @return object{sku?: mixed, product_id?: mixed}|null
     */
    protected function findChannelMetricRow(string $trim): ?object
    {
        $row = AliexpressMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if ($row) {
            return $row;
        }

        return AliexpressMetric::query()->where('product_id', $trim)->first();
    }

    /**
     * Resolve AliExpress product_id from local metrics by merchant SKU.
     */
    public function resolveProductIdBySku(string $sku): ?string
    {
        $trim = trim($sku);
        if ($trim === '') {
            return null;
        }

        $row = $this->findChannelMetricRow($trim);
        if ($row && $row->product_id) {
            return (string) $row->product_id;
        }

        return $this->findChannelProductIdFromDataView($trim);
    }

    protected function findChannelProductIdFromDataView(string $trim): ?string
    {
        try {
            $view = AliexpressDataView::query()
                ->where('sku', $trim)
                ->orWhere('sku', strtoupper($trim))
                ->orWhere('sku', strtolower($trim))
                ->first();
            $value = is_array($view?->value ?? null) ? $view->value : [];
            $id = trim((string) ($value['product_id'] ?? $value['productId'] ?? $value['id'] ?? ''));
            if ($id !== '') {
                return $id;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Batch inventory update — aliexpress.solution.batch.product.inventory.update
     *
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int}>  $rows
     */
    public function batchUpdateInventory(array $rows): array
    {
        if ($rows === []) {
            return ['success' => true, 'message' => 'No rows to update.', 'updated' => 0];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $productId = (string) ($row['product_id'] ?? '');
            $skuCode = trim((string) ($row['sku_code'] ?? $row['sku'] ?? ''));
            // Rule 1: never update unlinked SKUs.
            if ($productId === '' || $skuCode === ''
                || ! \App\Services\MarketplaceManager\MarketplaceLiveInventoryRules::isLinked($productId, $skuCode)) {
                continue;
            }
            $inventory = max(0, min(999999, (int) ($row['inventory'] ?? $row['stock'] ?? 0)));
            // Rule 4: Shopify 0 => marketplace 0.
            if (array_key_exists('shopify_qty', $row)) {
                $inventory = \App\Services\MarketplaceManager\MarketplaceLiveInventoryRules::clampPushQty(
                    $inventory,
                    (int) $row['shopify_qty']
                );
            }
            $grouped[$productId][] = [
                'sku_code' => $skuCode,
                'inventory' => $inventory,
            ];
        }

        if ($grouped === []) {
            return ['success' => false, 'message' => 'No valid inventory rows.', 'updated' => 0];
        }

        $updated = 0;
        $errors = [];
        $chunks = array_chunk($grouped, 20, true);

        foreach ($chunks as $chunk) {
            $payload = [];
            foreach ($chunk as $productId => $skus) {
                $payload[] = [
                    'product_id' => ctype_digit((string) $productId) ? (int) $productId : (string) $productId,
                    'multiple_sku_update_list' => array_values($skus),
                ];
            }

            // Prefer REST gateway (same signing as order APIs). Sync gateway returns IncompleteSignature.
            $raw = $this->callRestGateway('aliexpress.solution.batch.product.inventory.update', [
                'mutiple_product_update_list' => $this->encodeRequestPayload($payload),
            ]);
            if (empty($raw['success'])) {
                $raw = $this->callSync('aliexpress.solution.batch.product.inventory.update', [
                    'mutiple_product_update_list' => $this->encodeRequestPayload($payload),
                ]);
            }

            if (empty($raw['success'])) {
                $errors[] = $raw['message'] ?? 'Batch inventory update failed.';
                continue;
            }

            $updated += count($payload);
            usleep(200000);
        }

        return [
            'success' => $errors === [],
            'message' => $errors === [] ? "Inventory updated for {$updated} product group(s)." : implode(' | ', $errors),
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Batch price update — aliexpress.solution.batch.product.price.update
     *
     * @param  array<int, array{product_id: string, sku_code: string, price: float|string}>  $rows
     */
    public function batchUpdatePrice(array $rows): array
    {
        if ($rows === []) {
            return ['success' => true, 'message' => 'No rows to update.', 'updated' => 0];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $productId = (string) ($row['product_id'] ?? '');
            $skuCode = trim((string) ($row['sku_code'] ?? $row['sku'] ?? ''));
            $price = (float) ($row['price'] ?? 0);
            if ($productId === '' || $skuCode === '' || $price <= 0) {
                continue;
            }
            $grouped[$productId][] = [
                'sku_code' => $skuCode,
                'price' => number_format($price, 2, '.', ''),
            ];
        }

        if ($grouped === []) {
            return ['success' => false, 'message' => 'No valid price rows.', 'updated' => 0];
        }

        $updated = 0;
        $errors = [];
        $chunks = array_chunk($grouped, 20, true);

        foreach ($chunks as $chunk) {
            $payload = [];
            foreach ($chunk as $productId => $skus) {
                $payload[] = [
                    'product_id' => ctype_digit((string) $productId) ? (int) $productId : (string) $productId,
                    'multiple_sku_update_list' => array_values($skus),
                ];
            }

            $raw = $this->callRestGateway('aliexpress.solution.batch.product.price.update', [
                'mutiple_product_update_list' => $this->encodeRequestPayload($payload),
            ]);
            if (empty($raw['success'])) {
                $raw = $this->callSync('aliexpress.solution.batch.product.price.update', [
                    'mutiple_product_update_list' => $this->encodeRequestPayload($payload),
                ]);
            }

            if (empty($raw['success'])) {
                $errors[] = $raw['message'] ?? 'Batch price update failed.';
                continue;
            }

            $updated += count($payload);
            usleep(200000);
        }

        return [
            'success' => $errors === [],
            'message' => $errors === [] ? "Price updated for {$updated} product group(s)." : implode(' | ', $errors),
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Put offline products back on selling — aliexpress.postproduct.redefining.onlineaeproduct
     * product_ids semicolon-separated, max 50 per call.
     *
     * @param  array<int, string|int>  $productIds
     * @return array{success: bool, message: string, online: int, errors: array<int, string>}
     */
    public function onlineProducts(array $productIds): array
    {
        $ids = [];
        foreach ($productIds as $id) {
            $id = trim((string) $id);
            if ($id !== '' && ctype_digit($id)) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return ['success' => true, 'message' => 'No product IDs to online.', 'online' => 0, 'errors' => []];
        }

        $online = 0;
        $errors = [];

        foreach (array_chunk($ids, 50) as $chunk) {
            $productIdsParam = implode(';', $chunk);
            // Business /rest + method-name sign only (sync fallback returns IncompleteSignature).
            $raw = $this->callRestGateway('aliexpress.postproduct.redefining.onlineaeproduct', [
                'product_ids' => $productIdsParam,
            ]);

            $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
            $result = is_array($raw['result'] ?? null)
                ? $raw['result']
                : (is_array($data['result'] ?? null) ? $data['result'] : $data);

            $errorDetails = $result['error_details']['error_detail']
                ?? $result['error_details']
                ?? $result['errorDetails']
                ?? null;
            if (is_array($errorDetails)) {
                $list = isset($errorDetails[0]) ? $errorDetails : [$errorDetails];
                foreach ($list as $detail) {
                    if (! is_array($detail)) {
                        continue;
                    }
                    $code = trim((string) ($detail['error_code'] ?? ''));
                    $msg = trim((string) ($detail['error_message'] ?? ''));
                    $pids = $detail['product_ids'] ?? null;
                    $pidHint = is_array($pids) ? implode(',', array_slice($pids, 0, 5)) : '';
                    $errors[] = trim(($code !== '' ? "{$code}: " : '').($msg !== '' ? $msg : 'online failed').($pidHint !== '' ? " [{$pidHint}]" : ''));
                }
            }

            $bizSuccess = ($result['success'] ?? null) === true || ($result['success'] ?? null) === 'true';
            $modify = (int) ($result['modify_count'] ?? $result['modifyCount'] ?? 0);
            if ($bizSuccess || $modify > 0) {
                $online += max($modify, 0);
            } elseif (empty($raw['success'])) {
                $errors[] = $raw['message'] ?? ('Online failed for: '.$productIdsParam);
            } elseif ($modify === 0 && $errorDetails === null) {
                $errors[] = 'Online returned modify_count=0 for: '.$productIdsParam;
            }

            usleep(250000);
        }

        return [
            'success' => $errors === [],
            'message' => $errors === []
                ? "Onlined {$online} product(s)."
                : ("Onlined {$online}; errors: ".implode(' | ', array_slice(array_filter($errors), 0, 5))),
            'online' => $online,
            'errors' => array_values(array_filter($errors)),
        ];
    }

    /**
     * Declare shipment / fill tracking — aliexpress.logistics.sellershipmentfortop
     *
     * @param  array{
     *   out_ref: string,
     *   logistics_no: string,
     *   service_name: string,
     *   send_type?: string,
     *   tracking_website?: string|null,
     *   description?: string|null,
     *   actual_carrier?: string|null
     * }  $params
     * @return array{success: bool, message?: string, data?: mixed, request_id?: string|null}
     */
    public function declareSellerShipment(array $params): array
    {
        $outRef = trim((string) ($params['out_ref'] ?? ''));
        $logisticsNo = trim((string) ($params['logistics_no'] ?? ''));
        $serviceName = trim((string) ($params['service_name'] ?? ''));
        $sendType = strtolower(trim((string) ($params['send_type'] ?? 'all')));
        if (! in_array($sendType, ['all', 'part'], true)) {
            $sendType = 'all';
        }

        if ($outRef === '' || $logisticsNo === '' || $serviceName === '') {
            return [
                'success' => false,
                'message' => 'out_ref, logistics_no, and service_name are required to declare shipment.',
            ];
        }

        $business = [
            'out_ref' => $outRef,
            'logistics_no' => $logisticsNo,
            'service_name' => $serviceName,
            'send_type' => $sendType,
        ];

        foreach (['tracking_website', 'description', 'actual_carrier', 'package_type', 'ioss', 'locale'] as $optional) {
            $value = trim((string) ($params[$optional] ?? ''));
            if ($value !== '') {
                $business[$optional] = $value;
            }
        }

        $raw = $this->callRestGateway('aliexpress.logistics.sellershipmentfortop', $business);
        if (empty($raw['success']) && ! empty($this->gatewayFallback)) {
            $fallback = $this->callSync('aliexpress.logistics.sellershipmentfortop', $business);
            if (! empty($fallback['success']) || empty($raw['network_error'])) {
                $raw = ! empty($fallback['success']) ? $fallback : $raw;
            }
        }

        if (empty($raw['success'])) {
            return [
                'success' => false,
                'message' => $raw['message'] ?? 'AliExpress declare shipment failed.',
                'response' => $raw['response'] ?? $raw['data'] ?? null,
                'request_id' => $raw['request_id'] ?? null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Shipment declared on AliExpress.',
            'data' => $raw['data'] ?? $raw['result'] ?? null,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Modify previously declared tracking — aliexpress.logistics.sellermodifiedshipmentfortop
     *
     * @param  array{
     *   out_ref: string,
     *   old_logistics_no: string,
     *   new_logistics_no: string,
     *   old_service_name: string,
     *   new_service_name: string,
     *   send_type?: string,
     *   tracking_website?: string|null,
     *   description?: string|null,
     *   actual_carrier?: string|null
     * }  $params
     * @return array{success: bool, message?: string, data?: mixed, request_id?: string|null}
     */
    public function modifySellerShipment(array $params): array
    {
        $outRef = trim((string) ($params['out_ref'] ?? ''));
        $oldNo = trim((string) ($params['old_logistics_no'] ?? ''));
        $newNo = trim((string) ($params['new_logistics_no'] ?? ''));
        $oldService = trim((string) ($params['old_service_name'] ?? ''));
        $newService = trim((string) ($params['new_service_name'] ?? ''));
        $sendType = strtolower(trim((string) ($params['send_type'] ?? 'all')));
        if (! in_array($sendType, ['all', 'part'], true)) {
            $sendType = 'all';
        }

        if ($outRef === '' || $oldNo === '' || $newNo === '' || $oldService === '' || $newService === '') {
            return [
                'success' => false,
                'message' => 'out_ref, old/new logistics_no, and old/new service_name are required to modify shipment.',
            ];
        }

        $business = [
            'out_ref' => $outRef,
            'old_logistics_no' => $oldNo,
            'new_logistics_no' => $newNo,
            'old_service_name' => $oldService,
            'new_service_name' => $newService,
            'send_type' => $sendType,
        ];

        foreach (['tracking_website', 'description', 'actual_carrier', 'package_type', 'locale'] as $optional) {
            $value = trim((string) ($params[$optional] ?? ''));
            if ($value !== '') {
                $business[$optional] = $value;
            }
        }

        $raw = $this->callRestGateway('aliexpress.logistics.sellermodifiedshipmentfortop', $business);
        if (empty($raw['success']) && ! empty($this->gatewayFallback)) {
            $fallback = $this->callSync('aliexpress.logistics.sellermodifiedshipmentfortop', $business);
            if (! empty($fallback['success']) || empty($raw['network_error'])) {
                $raw = ! empty($fallback['success']) ? $fallback : $raw;
            }
        }

        if (empty($raw['success'])) {
            return [
                'success' => false,
                'message' => $raw['message'] ?? 'AliExpress modify shipment failed.',
                'response' => $raw['response'] ?? $raw['data'] ?? null,
                'request_id' => $raw['request_id'] ?? null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Shipment tracking updated on AliExpress.',
            'data' => $raw['data'] ?? $raw['result'] ?? null,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }

    /**
     * Logistics services supported for seller shipment declare.
     * aliexpress.logistics.redefining.listlogisticsservice
     *
     * @return array{success: bool, message?: string, services?: list<array{service_name: string, display_name?: string}>}
     */
    public function listLogisticsServices(): array
    {
        $raw = $this->callRestGateway('aliexpress.logistics.redefining.listlogisticsservice', []);
        if (empty($raw['success']) && ! empty($this->gatewayFallback)) {
            $fallback = $this->callSync('aliexpress.logistics.redefining.listlogisticsservice', []);
            if (! empty($fallback['success']) || empty($raw['network_error'])) {
                $raw = ! empty($fallback['success']) ? $fallback : $raw;
            }
        }

        if (empty($raw['success'])) {
            return [
                'success' => false,
                'message' => $raw['message'] ?? 'Could not list AliExpress logistics services.',
                'services' => [],
            ];
        }

        $payload = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $result = is_array($raw['result'] ?? null) ? $raw['result'] : ($payload['result'] ?? $payload);
        $list = $result['result_list']
            ?? $result['resultList']
            ?? $result['aeop_logistics_service_result']
            ?? $result['aeopLogisticsServiceResult']
            ?? $result['logistics_service_list']
            ?? $result;

        if (is_array($list) && isset($list['aeop_logistics_service_result'])) {
            $list = $list['aeop_logistics_service_result'];
        }
        if (is_array($list) && isset($list['aeopLogisticsServiceResult'])) {
            $list = $list['aeopLogisticsServiceResult'];
        }
        if (! is_array($list)) {
            $list = [];
        }
        if (isset($list['service_name']) || isset($list['serviceName'])) {
            $list = [$list];
        }

        $services = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['service_name'] ?? $row['serviceName'] ?? ''));
            if ($name === '') {
                continue;
            }
            $services[] = [
                'service_name' => $name,
                'display_name' => trim((string) ($row['display_name'] ?? $row['displayName'] ?? $name)),
            ];
        }

        return [
            'success' => true,
            'services' => $services,
            'request_id' => $raw['request_id'] ?? null,
        ];
    }
}
