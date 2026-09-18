<?php

namespace App\Services\Lqs;

use App\Models\LqsShopifySeoScore;
use App\Models\ShopifyCatalogProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LqsShopifySeoService
{
    public function __construct(protected LqsShopifySeoScorer $scorer)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function scoresBySku(): array
    {
        if (! Schema::hasTable('lqs_shopify_seo_scores')) {
            return [];
        }

        if (! LqsShopifySeoScore::query()->exists()) {
            $this->sync(false);
        }

        return $this->mapScoresBySku();
    }

    /**
     * @return array{
     *     products: int,
     *     source: string,
     *     seo: array{good: int, ok: int, bad: int, na: int},
     *     readability: array{good: int, ok: int, bad: int, na: int}
     * }
     */
    public function sync(bool $live = true): array
    {
        if (! Schema::hasTable('lqs_shopify_seo_scores')) {
            throw new \RuntimeException('lqs_shopify_seo_scores table is missing. Run migrations.');
        }

        $products = [];
        $source = 'catalog';
        if ($live) {
            $products = $this->fetchShopifyProducts();
            if ($products !== []) {
                $source = 'shopify';
            }
        }
        if ($products === []) {
            $products = $this->localCatalogProducts();
            $source = 'catalog';
        }

        $now = now();
        $seen = [];
        foreach ($products as $product) {
            $productId = (int) ($product['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $seen[$productId] = true;
            $this->persistProduct($product, $source, $now);
        }

        if ($seen !== []) {
            LqsShopifySeoScore::query()
                ->whereNotIn('shopify_product_id', array_keys($seen))
                ->delete();
        }

        return [
            'products' => count($seen),
            'source' => $source,
            'seo' => $this->countByRating('seo_rating'),
            'readability' => $this->countByRating('readability_rating'),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function mapScoresBySku(): array
    {
        $map = [];
        $normalize = static fn ($value) => strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));

        foreach (LqsShopifySeoScore::query()->get() as $row) {
            $payload = $this->rowPayload($row);
            foreach ((array) ($row->skus ?? []) as $sku) {
                $key = $normalize($sku);
                if ($key !== '' && ! isset($map[$key])) {
                    $map[$key] = $payload;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowPayload(LqsShopifySeoScore $row): array
    {
        return [
            'shopify_product_id' => $row->shopify_product_id,
            'handle' => $row->handle,
            'seo_title' => $row->seo_title,
            'seo_description' => $row->seo_description,
            'focus_keyphrase' => $row->focus_keyphrase,
            'seo_score' => $row->seo_score,
            'seo_rating' => $row->seo_rating ?: LqsShopifySeoScorer::NA,
            'readability_score' => $row->readability_score,
            'readability_rating' => $row->readability_rating ?: LqsShopifySeoScorer::NA,
            'findings' => $row->findings ?? [],
            'listing_url' => $this->storefrontUrl($row->handle),
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function persistProduct(array $product, string $source, $now): void
    {
        $yoast = is_array($product['yoast'] ?? null) ? $product['yoast'] : [];
        $keyphrase = $this->scorer->extractKeyphrase($yoast);
        if ($keyphrase === '') {
            $keyphrase = trim((string) ($product['keyphrase'] ?? ''));
        }

        $seoTitle = trim((string) ($yoast['title'] ?? $product['seo_title'] ?? ''));
        $seoDescription = trim((string) ($yoast['description'] ?? $product['seo_description'] ?? ''));
        $scored = $this->scorer->score([
            'title' => $product['title'] ?? '',
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'body_html' => $product['body_html'] ?? '',
            'keyphrase' => $keyphrase,
            'image_alts' => $product['image_alts'] ?? [],
        ]);

        LqsShopifySeoScore::updateOrCreate(
            ['shopify_product_id' => (int) $product['id']],
            [
                'handle' => $product['handle'] ?? null,
                'title' => $product['title'] ?? null,
                'seo_title' => $seoTitle !== '' ? $seoTitle : null,
                'seo_description' => $seoDescription !== '' ? $seoDescription : null,
                'focus_keyphrase' => $keyphrase !== '' ? $keyphrase : null,
                'skus' => array_values(array_unique(array_filter(array_map('strval', $product['skus'] ?? [])))),
                'seo_score' => $scored['seo_score'],
                'seo_rating' => $scored['seo_rating'],
                'readability_score' => $scored['readability_score'],
                'readability_rating' => $scored['readability_rating'],
                'findings' => $scored['findings'],
                'yoast_payload' => $yoast !== [] ? $yoast : null,
                'source' => $source,
                'synced_at' => $now,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchShopifyProducts(): array
    {
        [$domain, $token] = $this->credentials();
        if ($domain === '' || $token === '') {
            Log::warning('LqsShopifySeoService: missing Shopify credentials');

            return [];
        }

        $products = $this->fetchViaGraphql($domain, $token);
        if ($products !== []) {
            return $products;
        }

        return $this->fetchViaRest($domain, $token);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchViaGraphql(string $domain, string $token): array
    {
        $queries = [
            $this->graphqlQuery(true),
            $this->graphqlQuery(false),
        ];

        foreach ($queries as $query) {
            $cursor = null;
            $out = [];
            $ok = true;
            for ($page = 0; $page < 80; $page++) {
                $response = $this->shopifyRequest($domain, $token)
                    ->timeout(90)
                    ->post("https://{$domain}/admin/api/2025-01/graphql.json", [
                        'query' => $query,
                        'variables' => ['cursor' => $cursor],
                    ]);

                if (! $response->successful()) {
                    Log::warning('LqsShopifySeoService: GraphQL page failed', [
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 400),
                    ]);
                    $ok = false;
                    break;
                }

                $json = $response->json();
                if (! empty($json['errors'])) {
                    Log::warning('LqsShopifySeoService: GraphQL errors', [
                        'errors' => $json['errors'],
                    ]);
                    $ok = false;
                    break;
                }

                $connection = data_get($json, 'data.products', []);
                foreach ((array) data_get($connection, 'nodes', []) as $node) {
                    $mapped = $this->mapGraphqlProduct(is_array($node) ? $node : []);
                    if ($mapped !== null) {
                        $out[] = $mapped;
                    }
                }

                $hasNext = (bool) data_get($connection, 'pageInfo.hasNextPage', false);
                $cursor = data_get($connection, 'pageInfo.endCursor');
                if (! $hasNext || ! is_string($cursor) || $cursor === '') {
                    break;
                }
                usleep(350000);
            }

            if ($ok && $out !== []) {
                return $out;
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchViaRest(string $domain, string $token): array
    {
        $out = [];
        $pageInfo = null;
        for ($page = 0; $page < 80; $page++) {
            $query = [
                'limit' => 250,
                'fields' => 'id,title,handle,status,body_html,variants,image,images',
            ];
            if ($pageInfo) {
                $query['page_info'] = $pageInfo;
            }

            $response = $this->shopifyRequest($domain, $token)
                ->timeout(90)
                ->get("https://{$domain}/admin/api/2025-01/products.json", $query);

            if (! $response->successful()) {
                Log::warning('LqsShopifySeoService: REST products failed', [
                    'status' => $response->status(),
                ]);
                break;
            }

            foreach ((array) ($response->json('products') ?? []) as $product) {
                if (! is_array($product)) {
                    continue;
                }
                $skus = [];
                foreach ((array) ($product['variants'] ?? []) as $variant) {
                    $sku = trim((string) ($variant['sku'] ?? ''));
                    if ($sku !== '') {
                        $skus[] = $sku;
                    }
                }
                $out[] = [
                    'id' => (int) ($product['id'] ?? 0),
                    'title' => $product['title'] ?? '',
                    'handle' => $product['handle'] ?? '',
                    'body_html' => $product['body_html'] ?? '',
                    'seo_title' => '',
                    'seo_description' => '',
                    'keyphrase' => '',
                    'yoast' => [],
                    'skus' => $skus,
                    'image_alts' => $this->imageAltsFromRest($product),
                ];
            }

            $pageInfo = $this->nextPageInfo($response);
            if (! $pageInfo) {
                break;
            }
            usleep(700000);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function localCatalogProducts(): array
    {
        if (! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }

        $out = [];
        $query = ShopifyCatalogProduct::query()->where('store', 'main')->with('catalogVariants');
        foreach ($query->get() as $product) {
            $skus = [];
            foreach ($product->catalogVariants as $variant) {
                $sku = trim((string) ($variant->sku ?? ''));
                if ($sku !== '') {
                    $skus[] = $sku;
                }
            }
            $out[] = [
                'id' => (int) $product->shopify_id,
                'title' => $product->title ?? '',
                'handle' => $product->handle ?? '',
                'body_html' => $product->body_html ?? '',
                'seo_title' => '',
                'seo_description' => '',
                'keyphrase' => '',
                'yoast' => [],
                'skus' => $skus,
                'image_alts' => [],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function mapGraphqlProduct(array $node): ?array
    {
        $id = $this->numericGid((string) ($node['id'] ?? ''));
        if ($id <= 0) {
            return null;
        }

        $yoast = $this->collectYoastPayload($node);
        $skus = [];
        foreach ((array) data_get($node, 'variants.nodes', []) as $variant) {
            $sku = trim((string) ($variant['sku'] ?? ''));
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }

        return [
            'id' => $id,
            'title' => $node['title'] ?? '',
            'handle' => $node['handle'] ?? '',
            'body_html' => $node['descriptionHtml'] ?? '',
            'seo_title' => data_get($node, 'seo.title') ?? '',
            'seo_description' => data_get($node, 'seo.description') ?? '',
            'keyphrase' => $this->scorer->extractKeyphrase($yoast),
            'yoast' => $yoast,
            'skus' => $skus,
            'image_alts' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function collectYoastPayload(array $node): array
    {
        $payload = [];
        foreach (['yoastIndexable', 'yoastIndexableAlt'] as $alias) {
            $raw = data_get($node, $alias.'.value');
            $decoded = $this->decodeJson($raw);
            if ($decoded !== []) {
                $payload = array_merge($payload, $decoded);
            }
        }
        foreach (['yoastKeyword', 'yoastKeywordAlt'] as $alias) {
            $value = trim((string) data_get($node, $alias.'.value', ''));
            if ($value !== '') {
                $payload['focus_keyphrase'] = $payload['focus_keyphrase'] ?? $value;
            }
        }
        foreach ((array) data_get($node, 'metafields.nodes', []) as $field) {
            $key = (string) ($field['key'] ?? '');
            $value = $field['value'] ?? null;
            if ($key === '') {
                continue;
            }
            $decoded = $this->decodeJson($value);
            if ($decoded !== []) {
                $payload = array_merge($payload, $decoded);
                continue;
            }
            if (is_string($value) && trim($value) !== '') {
                $payload[$key] = trim($value);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '' || ($value[0] ?? '') !== '{') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function graphqlQuery(bool $withMetafields): string
    {
        $metafields = $withMetafields ? '
          yoastIndexable: metafield(namespace: "yoast_seo", key: "indexable") { value }
          yoastIndexableAlt: metafield(namespace: "yoast-seo", key: "indexable") { value }
          yoastKeyword: metafield(namespace: "yoast_seo", key: "focus_keyphrase") { value }
          yoastKeywordAlt: metafield(namespace: "yoast_seo", key: "focus_keyword") { value }
          metafields(first: 20, namespace: "yoast_seo") { nodes { key value } }' : '';

        return <<<GQL
query LqsYoastProducts(\$cursor: String) {
  products(first: 50, after: \$cursor) {
    pageInfo { hasNextPage endCursor }
    nodes {
      id
      title
      handle
      descriptionHtml
      seo { title description }
      {$metafields}
      variants(first: 80) { nodes { sku } }
    }
  }
}
GQL;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(): array
    {
        $domain = (string) (config('services.shopify.store_url') ?: config('services.shopify.domain') ?: '');
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim((string) $domain, '/');
        $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');

        return [$domain, $token];
    }

    private function shopifyRequest(string $domain, string $token)
    {
        $request = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ]);

        if (config('filesystems.default') === 'local' || env('FILESYSTEM_DRIVER') === 'local') {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    private function numericGid(string $gid): int
    {
        if (preg_match('/(\d+)$/', $gid, $matches)) {
            return (int) $matches[1];
        }

        return (int) $gid;
    }

    private function storefrontUrl(?string $handle): ?string
    {
        $handle = trim((string) $handle);
        if ($handle === '') {
            return null;
        }
        [$domain] = $this->credentials();
        if ($domain === '') {
            return null;
        }

        return 'https://'.$domain.'/products/'.$handle;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return list<string>
     */
    private function imageAltsFromRest(array $product): array
    {
        $alts = [];
        foreach (array_merge([$product['image'] ?? []], (array) ($product['images'] ?? [])) as $image) {
            $alt = trim((string) (is_array($image) ? ($image['alt'] ?? '') : ''));
            if ($alt !== '') {
                $alts[] = $alt;
            }
        }

        return $alts;
    }

    private function nextPageInfo(\Illuminate\Http\Client\Response $response): ?string
    {
        if (! $response->hasHeader('Link') || ! str_contains($response->header('Link'), 'rel="next"')) {
            return null;
        }
        foreach (explode(',', $response->header('Link')) as $link) {
            if (str_contains($link, 'rel="next"') && preg_match('/<(.*)>; rel="next"/', $link, $matches)) {
                parse_str((string) parse_url($matches[1], PHP_URL_QUERY), $query);

                return $query['page_info'] ?? null;
            }
        }

        return null;
    }

    /**
     * @return array{good: int, ok: int, bad: int, na: int}
     */
    private function countByRating(string $column): array
    {
        $counts = ['good' => 0, 'ok' => 0, 'bad' => 0, 'na' => 0];
        foreach (LqsShopifySeoScore::query()->select($column)->get() as $row) {
            $rating = (string) ($row->{$column} ?? 'na');
            if (! isset($counts[$rating])) {
                $rating = 'na';
            }
            $counts[$rating]++;
        }

        return $counts;
    }
}
