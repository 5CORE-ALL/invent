<?php

namespace App\Services\Lqs;

use App\Models\AlibabaMetric;
use App\Models\LqsMarketplaceScore;
use App\Models\ProductMaster;
use App\Services\AlibabaApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Alibaba.com listings (LQS 0-100) from the live ICBU product (/icbu/product/get: subject, description, main
 * images, attributes, status/display, SKU price and stock) and stores the result in lqs_marketplace_scores
 * (marketplace = 'alibaba').
 *
 * SKU -> Alibaba product id comes from alibaba_metrics (and alibaba_sheet_prices for rows metrics lacks). One API
 * call scores every SKU of a product. Alibaba's API only answers from the server's whitelisted IP.
 *
 * @see AlibabaListingQualityScorer
 */
class AlibabaLqsApiSyncService
{
    public const MARKETPLACE = 'alibaba';

    public const LOCK_KEY = 'lqs-sync-alibaba-lock';

    public const STATUS_KEY = 'lqs-sync-alibaba-status';

    private const OFFLINE_STATES = ['offline', 'inactive', 'disabled', 'rejected', 'pending', 'auditing', 'editingrequired', 'deleted'];

    public function __construct(
        private readonly AlibabaApiService $api,
        private readonly AlibabaListingQualityScorer $scorer,
    ) {
    }

    public function isConfigured(): bool
    {
        return filled(config('services.alibaba.app_key')) && filled(config('services.alibaba.access_token'))
            && Schema::hasTable('alibaba_metrics') && Schema::hasTable((new ProductMaster)->getTable());
    }

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /**
     * Alibaba product id => [normalized SKU => SKU as stored in Product Master] for every Product Master SKU that has
     * a real Alibaba product id.
     *
     * @return array<string, array<string, string>>
     */
    public function productGroups(): array
    {
        $masters = [];
        foreach (ProductMaster::query()
            ->select(['id', 'sku', 'Values'])
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->get() as $pm) {
            $masters[self::normalizeSku((string) $pm->sku)] ??= trim((string) $pm->sku);
        }

        $bySku = []; // normalized SKU => product id (metrics wins over the sheet)
        $add = function (string $table, bool $overwrite) use (&$bySku, $masters) {
            if (! Schema::hasTable($table)) {
                return;
            }
            foreach (DB::table($table)->select('sku', 'product_id')->whereNotNull('sku')->where('sku', '!=', '')
                ->whereNotNull('product_id')->orderBy('id')->cursor() as $row) {
                $key = self::normalizeSku((string) $row->sku);
                $id = trim((string) $row->product_id);
                if (! isset($masters[$key]) || ! preg_match('/^\d{6,}$/', $id)) {
                    continue; // not a Product Master SKU, or a placeholder id (no real Alibaba product)
                }
                if ($overwrite || ! isset($bySku[$key])) {
                    $bySku[$key] = $id;
                }
            }
        };
        $add('alibaba_sheet_prices', false);
        $add('alibaba_metrics', true);

        $groups = [];
        foreach ($bySku as $key => $id) {
            $groups[$id][$key] = $masters[$key];
        }

        return $groups;
    }

    /** SKUs listed on Alibaba that exist in Product Master (the page's "of N" denominator). */
    public function catalogSkus(): array
    {
        if (! Schema::hasTable('alibaba_metrics')) {
            return [];
        }

        return Cache::remember('lqs-alibaba-catalog-skus', 60, function () {
            $skus = [];
            foreach ($this->productGroups() as $group) {
                foreach (array_keys($group) as $key) {
                    $skus[$key] = true;
                }
            }

            return array_keys($skus);
        });
    }

    /**
     * Fetch one live Alibaba product.
     *
     * @return array<string, mixed>
     */
    public function fetchProduct(string $productId): array
    {
        $res = $this->api->getProductInfo($productId);
        if (empty($res['success']) || empty($res['data']) || ! is_array($res['data'])) {
            throw new \RuntimeException('Alibaba product '.$productId.': '.(string) ($res['message'] ?? 'fetch failed'));
        }

        return $res['data'];
    }

    /**
     * Scoring contexts for every wanted SKU of a live Alibaba product, keyed by normalized SKU.
     * Content (subject, description, images, attributes, state) is product level; price and stock use the matching
     * variant when the API returns one, else the stored Alibaba price/stock.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, string>  $wanted  normalized SKU => Product Master SKU
     * @return array<string, array<string, mixed>>
     */
    public function contextsFromProduct(array $product, string $productId, array $wanted): array
    {
        $rows = [];
        foreach ($this->api->extractSkuRowsFromProductInfo($product, $productId) as $row) {
            $rows[self::normalizeSku((string) ($row['sku'] ?? ''))] = $row;
        }
        $onlyRow = count($rows) === 1 ? reset($rows) : null;

        $stored = $this->storedPriceStock(array_keys($wanted));
        $masters = $this->masterFacts(array_values($wanted));

        $description = (string) ($product['description'] ?? $product['detail'] ?? '');
        $images = $this->imageUrls($product);
        if ($images === []) {
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $m);
            $images = array_values(array_unique($m[1] ?? []));
        }

        $status = strtolower(trim((string) ($product['status'] ?? '')));
        $display = strtoupper(trim((string) ($product['display'] ?? '')));
        $displayed = $display !== 'N';
        $published = ! in_array(preg_replace('/[^a-z]/', '', $status), self::OFFLINE_STATES, true) && $displayed;

        $out = [];
        foreach ($wanted as $key => $sku) {
            $row = $rows[$key] ?? $onlyRow;
            $price = (float) ($row['price'] ?? 0);
            $stock = $row['stock'] ?? null;
            $price = $price > 0 ? $price : (float) ($stored[$key]['price'] ?? 0);
            $stock = $stock !== null ? (int) $stock : (int) ($stored[$key]['stock'] ?? 0);
            $pm = $masters[$sku] ?? [];

            $out[$key] = [
                'sku' => $sku,
                'listing_id' => $productId,
                'title' => (string) ($product['subject'] ?? $product['product_name'] ?? $product['title'] ?? ''),
                'description' => $description,
                'images' => $images,
                'attribute_count' => $this->attributeCount($product),
                'price' => $price,
                'stock' => $stock,
                'status' => $display === 'N' ? 'display off' : $status,
                'published' => $published,
                'displayed' => $displayed,
                'length' => $pm['length'] ?? 0,
                'width' => $pm['width'] ?? 0,
                'height' => $pm['height'] ?? 0,
                'weight' => $pm['weight'] ?? 0,
                'origin' => $pm['origin'] ?? '',
            ];
        }

        return $out;
    }

    /**
     * Main image URLs from the product payload (field names differ between ICBU API versions).
     *
     * @param  array<string, mixed>  $product
     * @return list<string>
     */
    private function imageUrls(array $product): array
    {
        $urls = [];
        $walk = function ($node) use (&$walk, &$urls): void {
            if (is_string($node)) {
                if (preg_match('#^(https?:)?//\S+$#i', trim($node))) {
                    $urls[trim($node)] = true;
                }
            } elseif (is_array($node)) {
                foreach ($node as $v) {
                    $walk($v);
                }
            }
        };
        foreach (['main_image', 'mainImage', 'main_images', 'image_list', 'imageList', 'images', 'image_urls'] as $key) {
            if (isset($product[$key])) {
                $walk($product[$key]);
            }
        }

        return array_keys($urls);
    }

    /**
     * Number of attributes with a value (product attributes, not SKU variant attributes).
     *
     * @param  array<string, mixed>  $product
     */
    private function attributeCount(array $product): int
    {
        $bag = $product['attributes'] ?? $product['product_attribute'] ?? $product['productAttribute'] ?? $product['attribute_list'] ?? [];
        if (! is_array($bag)) {
            return 0;
        }
        if (isset($bag['attribute']) && is_array($bag['attribute'])) {
            $bag = $bag['attribute'];
        }
        if ($bag !== [] && ! array_is_list($bag)) {
            $first = reset($bag);
            // {"Material": "Steel", ...} map, or a single attribute node
            if (! is_array($first)) {
                return count(array_filter($bag, fn ($v) => trim((string) $v) !== ''));
            }
            $bag = array_is_list($first) ? $bag : [$bag];
        }

        $n = 0;
        foreach ($bag as $attr) {
            if (! is_array($attr)) {
                $n += trim((string) $attr) !== '' ? 1 : 0;

                continue;
            }
            foreach (['attributeValue', 'attribute_value', 'value', 'values', 'valueName', 'value_name'] as $k) {
                $v = $attr[$k] ?? null;
                if ((is_array($v) && $v !== []) || (is_scalar($v) && trim((string) $v) !== '')) {
                    $n++;
                    break;
                }
            }
        }

        return $n;
    }

    /**
     * Stored Alibaba price/stock (fallback when the API variant has none).
     *
     * @param  list<string>  $keys  normalized SKUs
     * @return array<string, array{price: float, stock: int}>
     */
    private function storedPriceStock(array $keys): array
    {
        $want = array_flip($keys);
        $out = [];
        if (Schema::hasTable('alibaba_pricing_prices')) {
            foreach (DB::table('alibaba_pricing_prices')->select('sku', 'price', 'ab_stock')->whereNotNull('sku')->cursor() as $r) {
                $k = self::normalizeSku((string) $r->sku);
                if (isset($want[$k])) {
                    $out[$k] = ['price' => (float) $r->price, 'stock' => (int) ($r->ab_stock ?? 0)];
                }
            }
        }
        if (Schema::hasTable('alibaba_metrics')) {
            foreach (DB::table('alibaba_metrics')->select('sku', 'price')->whereNotNull('sku')->cursor() as $r) {
                $k = self::normalizeSku((string) $r->sku);
                if (isset($want[$k]) && (float) $r->price > 0 && (float) ($out[$k]['price'] ?? 0) <= 0) {
                    $out[$k] = ['price' => (float) $r->price, 'stock' => (int) ($out[$k]['stock'] ?? 0)];
                }
            }
        }
        if (Schema::hasTable('alibaba_sheet_prices')) {
            foreach (DB::table('alibaba_sheet_prices')->select('sku', 'sku_price', 'soh')->whereNotNull('sku')->cursor() as $r) {
                $k = self::normalizeSku((string) $r->sku);
                if (isset($want[$k])) {
                    $out[$k] = [
                        'price' => (float) ($out[$k]['price'] ?? 0) > 0 ? $out[$k]['price'] : (float) $r->sku_price,
                        'stock' => (int) ($out[$k]['stock'] ?? 0) > 0 ? $out[$k]['stock'] : (int) ($r->soh ?? 0),
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Weight/dimensions/origin from Product Master (Values JSON), keyed by Product Master SKU.
     *
     * @param  list<string>  $skus
     * @return array<string, array<string, mixed>>
     */
    private function masterFacts(array $skus): array
    {
        $out = [];
        foreach (ProductMaster::query()->select(['id', 'sku', 'Values'])->whereIn('sku', $skus)->get() as $pm) {
            $v = is_array($pm->Values ?? null) ? $pm->Values : [];
            $out[trim((string) $pm->sku)] = [
                'length' => $v['l'] ?? 0,
                'width' => $v['w'] ?? 0,
                'height' => $v['h'] ?? 0,
                'weight' => $v['wt_act'] ?? ($v['weight'] ?? 0),
                'origin' => $v['country_of_origin'] ?? '',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(array $context): array
    {
        return $this->scorer->score($context);
    }

    /**
     * Score a listing and save it.
     *
     * @param  array<string, mixed>  $context
     * @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>}
     */
    public function saveListing(array $context): array
    {
        $key = self::normalizeSku((string) ($context['sku'] ?? ''));
        if ($key === '') {
            throw new \RuntimeException('Alibaba listing has no SKU.');
        }

        $result = $this->assess($context);
        $row = ['lqs' => $result['score']];
        if (($context['listing_id'] ?? '') !== '') {
            $row['listing_id'] = (string) $context['listing_id'];
        }
        if ($result['facts']['price'] > 0) {
            $row['price'] = $result['facts']['price'];
        }
        if (Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
            $row['audit_findings'] = $result['findings'];
            $row['audit_suggestions'] = $result['suggestions'];
        }

        LqsMarketplaceScore::updateOrCreate(['marketplace' => self::MARKETPLACE, 'sku' => $key], $row);

        return [
            'sku' => $key,
            'lqs' => $result['score'],
            'findings' => $result['findings'],
            'suggestions' => $result['suggestions'],
            'checks' => $result['checks'],
        ];
    }

    /**
     * Context for one SKU from the live Alibaba product.
     *
     * @return array<string, mixed>
     */
    public function contextForSku(string $sku): array
    {
        $key = self::normalizeSku($sku);
        foreach ($this->productGroups() as $productId => $skus) {
            if (isset($skus[$key])) {
                return $this->contextsFromProduct($this->fetchProduct((string) $productId), (string) $productId, [$key => $skus[$key]])[$key];
            }
        }

        throw new \RuntimeException('SKU '.$sku.' has no real Alibaba product id (alibaba_metrics / alibaba_sheet_prices).');
    }

    /** @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>} */
    public function syncSku(string $sku): array
    {
        return $this->saveListing($this->contextForSku($sku));
    }
}
