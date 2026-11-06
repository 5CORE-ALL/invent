<?php

namespace App\Services\Lqs;

use App\Models\FaireMetric;
use App\Models\LqsMarketplaceScore;
use App\Models\ProductMaster;
use App\Services\FaireApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Faire listings (LQS 0-100) from the live Faire product (GET /products/{id}: name, description, images,
 * variants with price/stock/GTIN/size, lifecycle and sale state) and stores the result in lqs_marketplace_scores
 * (marketplace = 'faire').
 *
 * The SKU -> Faire product id list comes from faire_metric (the same table the Faire "Listed" count uses). One API
 * call scores every variant of a product.
 *
 * @see FaireListingQualityScorer
 */
class FaireLqsApiSyncService
{
    public const MARKETPLACE = 'faire';

    public const LOCK_KEY = 'lqs-sync-faire-lock';

    public const STATUS_KEY = 'lqs-sync-faire-status';

    public function __construct(
        private readonly FaireApiService $api,
        private readonly FaireListingQualityScorer $scorer,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->api->isConfigured() && $this->api->hasAccessToken()
            && Schema::hasTable('faire_metric') && Schema::hasTable((new ProductMaster)->getTable());
    }

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /**
     * Faire product id -> [normalized SKU => SKU as stored in Product Master] for every listed Product Master SKU.
     *
     * @return array<string, array<string, string>>
     */
    public function productGroups(): array
    {
        $master = [];
        foreach (ProductMaster::query()
            ->select(['id', 'sku'])
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->get() as $pm) {
            $master[self::normalizeSku((string) $pm->sku)] ??= trim((string) $pm->sku);
        }

        $groups = [];
        FaireMetric::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('product_id')->where('product_id', '!=', '')
            ->orderBy('id')
            ->get(['sku', 'product_id'])
            ->each(function ($row) use (&$groups, $master) {
                $key = self::normalizeSku((string) $row->sku);
                $id = trim((string) $row->product_id);
                if (! isset($master[$key]) || strcasecmp($id, trim((string) $row->sku)) === 0) {
                    return; // not a Product Master SKU, or a placeholder id (no real Faire product)
                }
                foreach ($groups as $pid => $skus) { // a SKU belongs to its newest product only
                    unset($groups[$pid][$key]);
                }
                $groups[$id][$key] = $master[$key];
            });

        return array_filter($groups);
    }

    /** SKUs listed on Faire that exist in Product Master (the page's "of N" denominator). */
    public function catalogSkus(): array
    {
        if (! Schema::hasTable('faire_metric')) {
            return [];
        }

        return Cache::remember('lqs-faire-catalog-skus', 60, function () {
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
     * Fetch one live Faire product.
     *
     * @return array<string, mixed>
     */
    public function fetchProduct(string $productId): array
    {
        $res = $this->api->getProductInfo($productId);
        if (empty($res['success']) || empty($res['data']) || ! is_array($res['data'])) {
            throw new \RuntimeException('Faire product '.$productId.': '.($res['message'] ?? 'fetch failed'));
        }

        return $res['data'];
    }

    /**
     * Scoring contexts for the wanted variants of a live Faire product, keyed by normalized SKU.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, string>  $wanted  normalized SKU => Product Master SKU
     * @return array<string, array<string, mixed>>
     */
    public function contextsFromProduct(array $product, array $wanted): array
    {
        $productImages = $this->images((array) ($product['images'] ?? []));
        $attrs = is_array($product['product_attributes'] ?? null) ? count($product['product_attributes']) : 0;
        $warnings = [];
        foreach ((array) ($product['publication_warnings'] ?? []) as $w) {
            $warnings[] = is_array($w) ? (string) ($w['message'] ?? $w['description'] ?? $w['type'] ?? json_encode($w)) : (string) $w;
        }

        $out = [];
        foreach ((array) ($product['variants'] ?? []) as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $key = self::normalizeSku((string) ($variant['sku'] ?? ''));
            if ($key === '' || ! isset($wanted[$key])) {
                continue;
            }

            $m = is_array($variant['measurements'] ?? null) ? $variant['measurements'] : [];
            $price = $variant['prices'][0] ?? [];
            $wholesale = $variant['wholesale_price_cents'] ?? ($price['wholesale_price']['amount_minor'] ?? 0);
            $retail = $variant['retail_price_cents'] ?? ($price['retail_price']['amount_minor'] ?? 0);

            $out[$key] = [
                'sku' => $wanted[$key],
                'listing_id' => (string) ($product['id'] ?? ''),
                'title' => (string) ($product['name'] ?? ''),
                'description' => (string) ($product['description'] ?? ''),
                'images' => array_merge($productImages, $this->images((array) ($variant['images'] ?? []))),
                'has_taxonomy' => ! empty($product['taxonomy_type']['id'] ?? $product['taxonomy_type']['name'] ?? null),
                'attribute_count' => $attrs,
                'warnings' => $warnings,
                'wholesale' => ((float) $wholesale) / 100,
                'retail' => ((float) $retail) / 100,
                'lifecycle' => (string) ($variant['lifecycle_state'] ?? $product['lifecycle_state'] ?? ''),
                'sale_state' => (string) ($variant['sale_state'] ?? $product['sale_state'] ?? ''),
                'stock' => (int) ($variant['available_quantity'] ?? 0),
                'gtin' => (string) ($variant['gtin'] ?? ''),
                'length' => $m['length'] ?? 0,
                'width' => $m['width'] ?? 0,
                'height' => $m['height'] ?? 0,
                'weight' => $m['weight'] ?? 0,
                'tariff' => (string) ($variant['tariff_code'] ?? ''),
                // product-level state decides whether the whole product is live
                'product_lifecycle' => (string) ($product['lifecycle_state'] ?? ''),
            ];
            // Not published if either the product or the variant is not PUBLISHED.
            if (strtoupper($out[$key]['product_lifecycle']) !== 'PUBLISHED' && $out[$key]['product_lifecycle'] !== '') {
                $out[$key]['lifecycle'] = $out[$key]['product_lifecycle'];
            }
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $images
     * @return list<array<string, mixed>>
     */
    private function images(array $images): array
    {
        $out = [];
        foreach ($images as $img) {
            if (is_array($img) && ! empty($img['url'])) {
                $out[] = ['url' => $img['url'], 'width' => $img['width'] ?? 0, 'height' => $img['height'] ?? 0];
            } elseif (is_string($img) && $img !== '') {
                $out[] = ['url' => $img];
            }
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
            throw new \RuntimeException('Faire listing has no SKU.');
        }

        $result = $this->assess($context);
        $row = ['lqs' => $result['score']];
        if (($context['listing_id'] ?? '') !== '') {
            $row['listing_id'] = (string) $context['listing_id'];
        }
        if ($result['facts']['wholesale'] > 0) {
            $row['price'] = $result['facts']['wholesale'];
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
     * Context for one SKU from the live Faire product.
     *
     * @return array<string, mixed>
     */
    public function contextForSku(string $sku): array
    {
        $key = self::normalizeSku($sku);
        foreach ($this->productGroups() as $productId => $skus) {
            if (isset($skus[$key])) {
                $contexts = $this->contextsFromProduct($this->fetchProduct($productId), [$key => $skus[$key]]);
                if (! isset($contexts[$key])) {
                    throw new \RuntimeException('SKU '.$sku.' is not a variant of Faire product '.$productId.'.');
                }

                return $contexts[$key];
            }
        }

        throw new \RuntimeException('SKU '.$sku.' is not listed on Faire (no real product id in faire_metric).');
    }

    /** @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>} */
    public function syncSku(string $sku): array
    {
        return $this->saveListing($this->contextForSku($sku));
    }
}
