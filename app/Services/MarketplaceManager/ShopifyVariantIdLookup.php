<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve a Shopify variant id for an order-line SKU.
 *
 * GET /variants.json?sku= does not filter. The first variant in the store
 * (HISE 4X10) was attached to every line whose SKU was not already in shopify_skus.
 */
class ShopifyVariantIdLookup
{
    public static function idForSku(string $storeUrl, string $token, string $sku): ?int
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $row = ShopifySku::firstForProductSku($sku);
        if ($row && trim((string) ($row->variant_id ?? '')) !== '') {
            return (int) $row->variant_id;
        }

        $storeUrl = trim($storeUrl);
        $token = trim($token);
        if ($storeUrl === '' || $token === '') {
            return null;
        }

        return self::idFromGraphql($storeUrl, $token, $sku);
    }

    /**
     * One Shopify search for every SKU that is not already in shopify_skus.
     *
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    public static function idsForSkus(string $storeUrl, string $token, array $skus): array
    {
        $out = [];
        $missing = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || isset($out[$sku])) {
                continue;
            }
            $row = ShopifySku::firstForProductSku($sku);
            if ($row && trim((string) ($row->variant_id ?? '')) !== '') {
                $out[$sku] = (int) $row->variant_id;
                continue;
            }
            $missing[$sku] = $sku;
        }

        foreach (array_chunk(array_values($missing), 8) as $chunk) {
            foreach (self::idsFromGraphqlMany($storeUrl, $token, $chunk) as $sku => $id) {
                $out[$sku] = $id;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    public static function matchingVariantId(array $variants, string $sku): ?int
    {
        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            if (! ShopifySku::skusMatch($sku, (string) ($variant['sku'] ?? ''))) {
                continue;
            }
            $id = (int) ($variant['id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    public static function numericVariantId(string $gidOrId): ?int
    {
        if (preg_match('/(\d+)$/', trim($gidOrId), $match) !== 1) {
            return null;
        }
        $id = (int) $match[1];

        return $id > 0 ? $id : null;
    }

    private static function idFromGraphql(string $storeUrl, string $token, string $sku): ?int
    {
        $query = <<<'GQL'
        query ($q: String!) {
          productVariants(first: 10, query: $q) {
            edges { node { id sku } }
          }
        }
        GQL;

        $quoted = str_replace(['\\', '"'], ['\\\\', '\\"'], $sku);

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(
                'https://'.$storeUrl.'/admin/api/2024-10/graphql.json',
                [
                    'query' => $query,
                    'variables' => ['q' => 'sku:"'.$quoted.'"'],
                ]
            );

            if (! $response->successful()) {
                return null;
            }

            $edges = $response->json('data.productVariants.edges') ?? [];
            if (! is_array($edges)) {
                return null;
            }

            foreach ($edges as $edge) {
                $node = is_array($edge['node'] ?? null) ? $edge['node'] : [];
                if (! ShopifySku::skusMatch($sku, (string) ($node['sku'] ?? ''))) {
                    continue;
                }
                $id = self::numericVariantId((string) ($node['id'] ?? ''));
                if ($id !== null) {
                    return $id;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ShopifyVariantIdLookup: variant lookup failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    private static function idsFromGraphqlMany(string $storeUrl, string $token, array $skus): array
    {
        $storeUrl = trim($storeUrl);
        $token = trim($token);
        if ($storeUrl === '' || $token === '' || $skus === []) {
            return [];
        }

        $parts = [];
        foreach ($skus as $sku) {
            $quoted = str_replace(['\\', '"'], ['\\\\', '\\"'], $sku);
            $parts[] = 'sku:"'.$quoted.'"';
        }

        $query = <<<'GQL'
        query ($q: String!) {
          productVariants(first: 40, query: $q) {
            edges { node { id sku } }
          }
        }
        GQL;

        $payload = [
            'query' => $query,
            'variables' => ['q' => implode(' OR ', $parts)],
        ];
        $url = 'https://'.$storeUrl.'/admin/api/2024-10/graphql.json';

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($url, $payload);
            } catch (\Throwable $e) {
                Log::warning('ShopifyVariantIdLookup: variant lookup failed', [
                    'error' => $e->getMessage(),
                ]);
                if ($attempt >= 4) {
                    return [];
                }
                sleep(2 * $attempt);

                continue;
            }

            if ($response->status() === 429 && $attempt < 4) {
                $wait = (int) ($response->header('Retry-After') ?: (2 * $attempt));
                sleep(max(2, min(15, $wait)));

                continue;
            }
            if (! $response->successful()) {
                return [];
            }

            $edges = $response->json('data.productVariants.edges') ?? [];
            if (! is_array($edges)) {
                return [];
            }

            $found = [];
            foreach ($skus as $sku) {
                foreach ($edges as $edge) {
                    $node = is_array($edge['node'] ?? null) ? $edge['node'] : [];
                    if (! ShopifySku::skusMatch($sku, (string) ($node['sku'] ?? ''))) {
                        continue;
                    }
                    $id = self::numericVariantId((string) ($node['id'] ?? ''));
                    if ($id !== null) {
                        $found[$sku] = $id;
                        break;
                    }
                }
            }

            return $found;
        }

        return [];
    }
}
