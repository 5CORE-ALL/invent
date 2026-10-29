<?php

namespace App\Services\CategoryMasters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads the product category of SKUs from the main Shopify store.
 * The Shopify standard category (taxonomy path) wins; the product type is the fallback.
 */
class ShopifyCategoryFetcher
{
    private const API_VERSION = '2025-01';

    /**
     * @param  list<string>  $skus
     * @return array{ok: bool, message: string, found: array<string, array{category: string, id: string, source: string}>}
     */
    public function fetch(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            fn ($sku) => trim((string) $sku),
            $skus
        ), fn ($sku) => $sku !== '')));
        if ($skus === []) {
            return ['ok' => true, 'message' => '', 'found' => []];
        }

        $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
        $token = config('services.shopify.access_token') ?: config('services.shopify.password');
        if (! $domain || ! $token) {
            return ['ok' => false, 'message' => 'Shopify credentials are not configured.', 'found' => []];
        }
        $domain = rtrim((string) preg_replace('#^https?://#', '', (string) $domain), '/');

        $terms = array_map(
            fn (string $sku) => 'sku:"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $sku).'"',
            $skus
        );
        $query = 'query($q: String!) { productVariants(first: 100, query: $q) { edges { node { sku product { productType category { id fullName } } } } } }';

        try {
            $request = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(60)->connectTimeout(25);
            if (env('FILESYSTEM_DRIVER') === 'local') {
                $request = $request->withoutVerifying();
            }
            $response = $request->post(
                'https://'.$domain.'/admin/api/'.self::API_VERSION.'/graphql.json',
                ['query' => $query, 'variables' => ['q' => implode(' OR ', $terms)]]
            );
        } catch (\Throwable $e) {
            Log::warning('Category Masters: Shopify category request failed: '.$e->getMessage());

            return ['ok' => false, 'message' => 'Could not reach Shopify.', 'found' => []];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Shopify answered HTTP '.$response->status().'.', 'found' => []];
        }

        $errors = $response->json('errors');
        if (! empty($errors)) {
            $first = is_array($errors) ? ($errors[0]['message'] ?? json_encode($errors)) : (string) $errors;

            return ['ok' => false, 'message' => 'Shopify: '.mb_substr((string) $first, 0, 200), 'found' => []];
        }

        $wanted = [];
        foreach ($skus as $sku) {
            $wanted[$this->key($sku)] = $sku;
        }

        $found = [];
        foreach ($response->json('data.productVariants.edges') ?? [] as $edge) {
            $node = $edge['node'] ?? null;
            if (! is_array($node)) {
                continue;
            }
            $key = $this->key((string) ($node['sku'] ?? ''));
            if (! isset($wanted[$key]) || isset($found[$wanted[$key]])) {
                continue;
            }

            $product = is_array($node['product'] ?? null) ? $node['product'] : [];
            $category = is_array($product['category'] ?? null) ? $product['category'] : [];
            $name = trim((string) ($category['fullName'] ?? ''));
            $source = 'category';
            $id = '';
            if ($name !== '') {
                $id = $this->shortId((string) ($category['id'] ?? ''));
            } else {
                $name = trim((string) ($product['productType'] ?? ''));
                $source = 'product_type';
            }

            $found[$wanted[$key]] = [
                'category' => mb_substr($name, 0, 255),
                'id' => mb_substr($id, 0, 64),
                'source' => $name === '' ? 'none' : $source,
            ];
        }

        return ['ok' => true, 'message' => '', 'found' => $found];
    }

    /** "gid://shopify/TaxonomyCategory/el-4-2" => "el-4-2". */
    private function shortId(string $gid): string
    {
        $gid = trim($gid);
        if ($gid === '') {
            return '';
        }
        $pos = strrpos($gid, '/');

        return $pos === false ? $gid : substr($gid, $pos + 1);
    }

    private function key(string $sku): string
    {
        return strtolower(trim(str_replace("\u{00a0}", ' ', $sku)));
    }
}
