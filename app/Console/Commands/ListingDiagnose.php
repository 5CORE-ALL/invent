<?php

namespace App\Console\Commands;

use App\Models\AliexpressMetric;
use Illuminate\Console\Command;

/**
 * Dumps what a marketplace actually expects for one SKU (live product payload, category
 * schema / attributes, required Mirakl attributes with their value lists) so listing-update
 * failures can be fixed against real data. Read-only: nothing is written to the marketplace.
 */
class ListingDiagnose extends Command
{
    protected $signature = 'listing:diagnose
        {channel : aliexpress | tiktok | tiktok2 | bestbuy | purchasingpower | wayfair}
        {sku : Our SKU}
        {--full : Print complete JSON instead of trimmed output}';

    protected $description = 'Read-only dump of marketplace listing data/schema for one SKU (for debugging Push Updates)';

    public function handle(): int
    {
        $channel = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $this->argument('channel')));
        $sku = trim((string) $this->argument('sku'));

        try {
            return match ($channel) {
                'aliexpress' => $this->aliexpress($sku),
                'tiktok', 'tiktokshop' => $this->tiktok(\App\Services\TikTokShopService::class, $sku),
                'tiktok2' => $this->tiktok(\App\Services\TikTok2ShopService::class, $sku),
                'bestbuy', 'bestbuyusa' => $this->mirakl(\App\Services\BestBuyApiService::class, $sku),
                'purchasingpower', 'pp' => $this->mirakl(\App\Services\PurchasingPowerApiService::class, $sku),
                'wayfair' => $this->wayfair($sku),
                default => $this->unknown($channel),
            };
        } catch (\Throwable $e) {
            $this->error(get_class($e).': '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }
    }

    private function unknown(string $channel): int
    {
        $this->error('Unknown channel "'.$channel.'". Use aliexpress, tiktok, tiktok2, bestbuy, purchasingpower or wayfair.');

        return self::INVALID;
    }

    private function aliexpress(string $sku): int
    {
        $svc = app(\App\Services\AliExpressApiService::class);
        $row = AliexpressMetric::query()->where('sku', $sku)->first();
        $productId = $row && $row->product_id ? (string) $row->product_id : (string) ($svc->resolveProductIdBySku($sku) ?? '');
        $this->info('AliExpress product_id: '.($productId ?: '(not found)'));
        if ($productId === '') {
            return self::FAILURE;
        }

        $info = $svc->getProductInfo($productId);
        $this->section('product.info.get', $info);

        $data = is_array($info['data'] ?? null) ? $info['data'] : [];
        $this->section('weight / package / logistics fields in product', $this->findKeys($data, '/weight|package|logistic|length|width|height|size/i'));

        $categoryId = (string) ($data['category_id'] ?? data_get($data, 'aeop_ae_product.category_id') ?? '');
        $this->info('category_id: '.($categoryId ?: '(none)'));
        if ($categoryId !== '') {
            $schema = $this->invoke($svc, 'callRestGateway', 'aliexpress.solution.product.schema.get', ['aliexpress_category_id' => $categoryId]);
            $raw = (string) json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->info('product.schema.get: '.(empty($schema['success']) ? 'FAILED '.($schema['message'] ?? '') : 'ok ('.strlen($raw).' bytes)'));
            foreach (['usLogisticsWeight', 'Package weight', 'aeLogisticsWeight', 'package_weight', 'gross_weight'] as $needle) {
                $this->snippets($raw, $needle);
            }
        }

        return self::SUCCESS;
    }

    private function tiktok(string $class, string $sku): int
    {
        $svc = app($class);
        $productId = (string) ($this->invoke($svc, 'findTikTokProductIdBySku', $sku) ?? '');
        $this->info('TikTok product_id: '.($productId ?: '(not found)'));
        if ($productId === '') {
            return self::FAILURE;
        }
        \Closure::bind(function () {
            $this->client->setAccessToken($this->accessToken);
            $this->ensureShopCipher();
            if (is_string($this->shopCipher) && $this->shopCipher !== '') {
                $this->client->setShopCipher($this->shopCipher);
            }
        }, $svc, \App\Services\TikTokShopService::class)();

        $data = $this->invoke($svc, 'fetchProductData', $productId);
        $this->info('Top-level product keys: '.implode(', ', array_keys(is_array($data) ? $data : [])));
        $this->section('product detail', $this->trimStrings($data));
        $this->section('highlight-like keys anywhere in product', $this->findKeys(is_array($data) ? $data : [], '/highlight|feature|selling|key_?point/i'));

        $categoryId = (string) $this->invoke($svc, 'tiktokCategoryIdFromProduct', is_array($data) ? $data : []);
        $this->info('category_id: '.($categoryId ?: '(none)'));
        if ($categoryId === '') {
            return self::SUCCESS;
        }

        foreach (['202309', '202407', '202509'] as $version) {
            try {
                $attrs = $this->invoke($svc, 'tiktokOpenApi', 'GET', "/product/{$version}/categories/{$categoryId}/attributes", ['locale' => 'en-US']);
                $list = $attrs['attributes'] ?? $attrs['category_attributes'] ?? [];
                $this->info("Category attributes ({$version}): ".count((array) $list));
                $rows = [];
                foreach ((array) $list as $a) {
                    if (is_array($a)) {
                        $rows[] = [
                            $a['id'] ?? '',
                            mb_substr((string) ($a['name'] ?? ''), 0, 50),
                            $a['type'] ?? '',
                            json_encode($a['is_requried'] ?? $a['is_required'] ?? null),
                            json_encode($a['is_multiple_selection'] ?? null),
                            json_encode($a['is_customizable'] ?? null),
                        ];
                    }
                }
                $this->table(['id', 'name', 'type', 'required', 'multiple', 'custom'], $rows);
            } catch (\Throwable $e) {
                $this->warn("Category attributes ({$version}) failed: ".$e->getMessage());
            }
        }

        foreach (['202309', '202509'] as $version) {
            try {
                $rules = $this->invoke($svc, 'tiktokOpenApi', 'GET', "/product/{$version}/categories/{$categoryId}/rules", ['locale' => 'en-US']);
                $this->section("Category rules ({$version})", $rules);
            } catch (\Throwable $e) {
                $this->warn("Category rules ({$version}) failed: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function mirakl(string $class, string $sku): int
    {
        $svc = app($class);
        $hierarchy = $this->invoke($svc, 'resolveMiraklMcmHierarchyForP41', $sku);
        $this->info('Hierarchy: '.($hierarchy ?: '(none)'));

        $attrs = (array) $this->invoke($svc, 'fetchMiraklMcmPm11Attributes', $hierarchy);
        $this->info('PM11 attributes: '.count($attrs));
        $explicit = (array) $this->invoke($svc, 'miraklMcmP41AttributeSemanticMap');
        $master = (array) $this->invoke($svc, 'miraklMcmMasterData', $sku);
        $haystack = (string) $this->invoke($svc, 'miraklMcmGuessHaystack', $master, []);
        $existing = [];
        try {
            $existing = (array) $this->invoke($svc, 'fetchMiraklMcmProductBySku', $sku);
        } catch (\Throwable $e) {
            $this->warn('Live product lookup failed: '.$e->getMessage());
        }
        $this->info('Live Mirakl product found: '.($existing !== [] ? 'yes' : 'no'));

        $rows = [];
        foreach ($attrs as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $code = (string) ($attr['code'] ?? '');
            if (($attr['requirement_level'] ?? '') !== 'REQUIRED' && ! isset($explicit[$code])) {
                continue;
            }
            $semantic = $explicit[$code] ?? (string) $this->invoke($svc, 'miraklMcmAttributeSemantic', $attr);
            $value = (string) $this->invoke($svc, 'miraklMcmMasterValueForSemantic', $semantic, $attr, $master, $sku, []);
            $coerced = $value !== '' ? (string) $this->invoke($svc, 'miraklMcmCoerceP41AttributeValue', $attr, $value, $semantic, $haystack) : '';

            $listCode = (string) ($attr['values_list'] ?? '');
            foreach ((array) ($attr['type_parameters'] ?? []) as $p) {
                if (is_array($p) && in_array(strtoupper((string) ($p['name'] ?? '')), ['LIST_CODE', 'VALUES_LIST', 'VALUE_LIST'], true)) {
                    $listCode = (string) ($p['value'] ?? '') ?: $listCode;
                }
            }
            $list = $listCode !== '' ? (array) $this->invoke($svc, 'fetchMiraklMcmValuesList', $listCode) : [];
            $sample = implode(' | ', array_slice(array_map(fn ($c, $l) => $c === $l ? $c : $c.'='.$l, array_keys($list), $list), 0, 12));

            $rows[] = [
                mb_substr($code, 0, 55),
                mb_substr((string) ($attr['label'] ?? ''), 0, 30),
                (string) ($attr['type'] ?? ''),
                $semantic,
                mb_substr($value, 0, 30),
                mb_substr($coerced, 0, 30),
                $listCode !== '' ? $listCode.' ('.count($list).'): '.mb_substr($sample, 0, 160) : '',
            ];
        }
        $this->table(['code', 'label', 'type', 'semantic', 'master value', 'sent value', 'values list'], $rows);
        $this->line('Master data keys: '.implode(', ', array_keys(array_filter($master, fn ($v) => $v !== '' && $v !== null && $v !== []))));

        return self::SUCCESS;
    }

    private function wayfair(string $sku): int
    {
        $svc = app(\App\Services\WayfairApiService::class);
        $token = (string) $this->invoke($svc, 'getTokenForCatalog');
        $this->info('Catalog token: '.($token !== '' ? 'ok' : 'FAILED'));
        if ($token !== '') {
            $parts = explode('.', $token);
            $claims = isset($parts[1]) ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true) : null;
            $this->section('token claims (scopes / permissions)', is_array($claims)
                ? array_intersect_key($claims, array_flip(['scope', 'scp', 'permissions', 'aud', 'azp', 'gty', 'exp']))
                : '(not a JWT)');
        }
        $this->info('Supplier id: '.$this->invoke($svc, 'liveSupplierId'));
        $this->info('Item group id via lookup: '.((string) $this->invoke($svc, 'wayfairItemGroupId', $sku) ?: '(none)'));

        $query = 'query ($input: SupplierCatalogItemsInput!) { supplierCatalogItems(input: $input) {'
            .' ... on SupplierCatalogItems { catalogItems { supplierPartNumber listings { listingId isLive } } }'
            .' ... on SupplierCatalogItemsError { httpError { code message } internalError { code message } } } }';
        $json = $this->invoke($svc, 'catalogGraphqlRequest',
            (string) config('services.wayfair.product_catalog_graphql_url', 'https://api.wayfair.io/v1/product-catalog-api/graphql'),
            $query,
            ['input' => ['filter' => ['supplierPartNumbers' => [$sku]], 'paginationOptions' => ['page' => 1, 'pageSize' => 5]]]
        );
        $this->section('raw supplierCatalogItems response', $json);

        return self::SUCCESS;
    }

    private function invoke(object $obj, string $method, mixed ...$args): mixed
    {
        for ($class = new \ReflectionClass($obj); $class; $class = $class->getParentClass()) {
            if ($class->hasMethod($method)) {
                $m = $class->getMethod($method);
                $m->setAccessible(true);

                return $m->invoke($obj, ...$args);
            }
        }
        throw new \BadMethodCallException(get_class($obj).'::'.$method.' does not exist');
    }

    private function section(string $title, mixed $data): void
    {
        $this->newLine();
        $this->line('<comment>== '.$title.' ==</comment>');
        $json = is_string($data) ? $data : (string) json_encode($this->option('full') ? $data : $this->trimStrings($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->line($this->option('full') ? $json : mb_substr($json, 0, 12000));
    }

    private function trimStrings(mixed $data): mixed
    {
        if (is_string($data)) {
            return mb_strlen($data) > 300 ? mb_substr($data, 0, 300).'…['.mb_strlen($data).' chars]' : $data;
        }
        if (is_array($data)) {
            return array_map(fn ($v) => $this->trimStrings($v), $data);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function findKeys(array $data, string $pattern, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $path = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            if (is_string($k) && preg_match($pattern, $k)) {
                $out[$path] = $this->trimStrings($v);
            }
            if (is_array($v)) {
                $out += $this->findKeys($v, $pattern, $path);
            }
        }

        return $out;
    }

    private function snippets(string $raw, string $needle): void
    {
        $offset = 0;
        $n = 0;
        while (($pos = stripos($raw, $needle, $offset)) !== false && $n < 3) {
            $this->line('<comment>['.$needle.']</comment> …'.substr($raw, max(0, $pos - 300), 900).'…');
            $offset = $pos + strlen($needle);
            $n++;
        }
        if ($n === 0) {
            $this->line('['.$needle.'] not found in schema');
        }
    }
}
