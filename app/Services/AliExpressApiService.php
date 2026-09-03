<?php

namespace App\Services;

use Carbon\Carbon;
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
        $attempts = [$request];
        if (trim((string) ($request['brand_name'] ?? '')) !== '') {
            $withoutBrand = $request;
            unset($withoutBrand['brand_name']);
            $attempts[] = $withoutBrand;
        }

        $last = ['success' => false, 'message' => 'AliExpress product post failed.'];
        foreach ($attempts as $payload) {
            $encoded = $this->encodeRequestPayload($payload);
            $res = $this->callApiFlexible('aliexpress.solution.product.post', [
                'rest' => ['post_product_request' => $encoded],
                'sync' => ['post_product_request' => $encoded],
            ]);
            $productId = $this->extractPostedProductId($res['data'] ?? [])
                ?: $this->extractPostedProductId($res['result'] ?? [])
                ?: $this->extractPostedProductId($res);
            if ($productId !== '') {
                $res['success'] = true;
                $res['product_id'] = $productId;

                return $res;
            }

            $bizError = $this->extractPostFailureMessage($res);
            $last = [
                'success' => false,
                'message' => $bizError,
                'data' => $res['data'] ?? $res['result'] ?? $res['response'] ?? null,
            ];
            if (empty($res['success']) && ! $this->isRetryableProductPostError($bizError)) {
                break;
            }
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
            || str_contains($m, 'brand')
            || str_contains($m, 'accepted the request')
            || str_contains($m, 'did not return a product');
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
     * Official IOP file upload: /sync HMAC-SHA256, system params on the query, file omitted from the sign.
     *
     * @param  array<string, string>  $businessParams
     */
    private function callIopFileUpload(string $method, array $businessParams, string $fileField, string $bytes, string $fileName, string $tokenKey = 'session'): array
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

        $tokenKey = $tokenKey === 'access_token' ? 'access_token' : 'session';
        $system = [
            'app_key' => $this->appKey,
            'format' => $this->format !== '' ? $this->format : 'json',
            'method' => $method,
            'partner_id' => $this->partnerId !== '' ? $this->partnerId : 'iop-sdk-php',
            'sign_method' => 'sha256',
            'simplify' => $this->simplify !== '' ? (string) $this->simplify : 'true',
            'timestamp' => time().'000',
            $tokenKey => (string) $this->accessToken,
        ];
        $api = [];
        foreach ($businessParams as $key => $value) {
            if ($value === null || $value === '' || (string) $key === $fileField) {
                continue;
            }
            $api[$key] = (string) $value;
        }

        $forSign = array_merge($api, $system);
        unset($forSign['sign']);
        ksort($forSign);
        $source = ($this->signPath !== '' ? $this->signPath : '/sync');
        foreach ($forSign as $key => $value) {
            $source .= (string) $key.(string) $value;
        }
        $system['sign'] = strtoupper(hash_hmac('sha256', $source, $this->appSecret));

        $queryUrl = $this->apiBase.'?'.http_build_query($system, '', '&', PHP_QUERY_RFC3986);
        $mime = $this->imageMimeType($bytes, $fileName);
        $multipart = [];
        foreach ($api as $name => $contents) {
            $multipart[] = ['name' => $name, 'contents' => $contents];
        }
        $multipart[] = [
            'name' => $fileField,
            'contents' => $bytes,
            'filename' => $fileName,
            'headers' => ['Content-Type' => $mime],
        ];

        try {
            $response = $this->httpClient()
                ->connectTimeout(10)
                ->timeout(40)
                ->asMultipart()
                ->post($queryUrl, $multipart);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return $this->networkErrorResult('Could not reach AliExpress photobank (sync).', $e);
        }

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
        foreach (array_slice(array_values(array_unique(array_filter(array_map('trim', $urls)))), 0, 6) as $url) {
            if ($this->isAliExpressCdnUrl($url)) {
                $hosted[] = $url;
                continue;
            }
            $res = $this->uploadPhotobankImage($url);
            if (empty($res['success']) || trim((string) ($res['url'] ?? '')) === '') {
                return [
                    'success' => false,
                    'message' => $res['message'] ?? 'AliExpress photobank upload failed for an Image Master photo.',
                    'urls' => [],
                ];
            }
            $hosted[] = (string) $res['url'];
        }

        return [
            'success' => $hosted !== [],
            'message' => $hosted === [] ? 'No Image Master photos to upload.' : '',
            'urls' => $hosted,
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
        $fileName = (string) ($downloaded['filename'] ?? 'image.jpg');
        $business = [
            'file_name' => $fileName,
            'group_id' => '0',
        ];

        $parsed = $this->callTopRouterWithFile(
            'aliexpress.photobank.redefining.uploadimageforsdk',
            $business,
            'image_bytes',
            $bytes,
            $fileName
        );
        $url = $this->extractPhotobankUrl($parsed);
        if ($url !== '') {
            return ['success' => true, 'url' => $url, 'message' => ''];
        }

        $publicUrl = $this->storePublicImage($bytes, $fileName);
        if ($publicUrl !== '') {
            Log::warning('AliExpress photobank unavailable, hosting Image Master photo on app URL', [
                'source' => mb_substr($sourceUrl, 0, 200),
                'public' => $publicUrl,
                'photobank' => $parsed['message'] ?? '',
            ]);

            return ['success' => true, 'url' => $publicUrl, 'message' => ''];
        }

        $last = trim((string) ($parsed['message'] ?? 'AliExpress photobank upload failed.'));
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

    private function storePublicImage(string $bytes, string $fileName): string
    {
        $ext = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = str_starts_with($bytes, "\x89PNG") ? 'png' : 'jpg';
        }
        $dir = public_path('aliexpress-publish');
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return '';
        }
        $name = sha1($bytes).'.'.$ext;
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        if (! is_file($path) && file_put_contents($path, $bytes) === false) {
            return '';
        }
        $base = rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            return '';
        }

        return $base.'/aliexpress-publish/'.$name;
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

        $last = 'Could not download the Image Master photo.';
        foreach ($candidates as $try) {
            try {
                $response = $this->httpClient()
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; 5CORE-ImageMaster/1.0)',
                        'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    ])
                    ->get($try);
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
            $path = (string) (parse_url($try, PHP_URL_PATH) ?? '');
            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($path)) ?: 'image.jpg';
            if (! str_contains($name, '.')) {
                $name .= '.jpg';
            }

            return ['bytes' => $bytes, 'filename' => $name];
        }

        return ['bytes' => '', 'filename' => 'image.jpg', 'message' => $last];
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
