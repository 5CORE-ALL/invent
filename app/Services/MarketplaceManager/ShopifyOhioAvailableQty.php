<?php

namespace App\Services\MarketplaceManager;

use App\Services\ShopifyOhioLocationResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The one Shopify quantity every marketplace compare and push uses: "available"
 * at the Ohio location. Webhooks, the inventory poller and the Ohio dashboard
 * already write that number into shopify_skus; the marketplace push paths wrote
 * the variant's all-locations total into the same column, so a SKU with stock at
 * a second location flipped between matched and mismatched on every run.
 */
final class ShopifyOhioAvailableQty
{
    public static function locationGid(): ?string
    {
        try {
            $id = ShopifyOhioLocationResolver::preferredLocationId();
        } catch (\Throwable $e) {
            return null;
        }
        $id = preg_replace('/\D+/', '', (string) $id);

        return $id !== '' ? 'gid://shopify/Location/'.$id : null;
    }

    /**
     * Extra selection for a ProductVariant node; empty when no Ohio location is configured.
     */
    public static function variantSelection(?string $locationGid): string
    {
        if ($locationGid === null) {
            return '';
        }

        return 'inventoryItem { inventoryLevel(locationId: "'.$locationGid.'") { quantities(names: ["available"]) { name quantity } } }';
    }

    /**
     * Qty from a ProductVariant node fetched with variantSelection(). A variant
     * not stocked at Ohio is 0 there, which is what the webhook path records too.
     */
    public static function qtyFromVariantNode(array $node, bool $locationQueried): ?int
    {
        if ($locationQueried && array_key_exists('inventoryItem', $node) && is_array($node['inventoryItem'])) {
            return self::availableFromLevel($node['inventoryItem']['inventoryLevel'] ?? null);
        }

        if (array_key_exists('inventoryQuantity', $node) && $node['inventoryQuantity'] !== null) {
            return (int) $node['inventoryQuantity'];
        }

        return null;
    }

    /**
     * Ohio available per inventory item id (REST callers only have variant inventory_quantity).
     *
     * @param  list<int|string>  $inventoryItemIds
     * @return array<string, int>|null id => available; null when no Ohio location or every request failed
     */
    public static function byInventoryItemIds(array $inventoryItemIds): ?array
    {
        $locationGid = self::locationGid();
        if ($locationGid === null) {
            return null;
        }
        $store = preg_replace('#^https?://#', '', rtrim((string) config('services.shopify.store_url'), '/'));
        $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');
        if ($store === '' || $token === '') {
            return null;
        }

        $ids = [];
        foreach ($inventoryItemIds as $id) {
            $id = preg_replace('/\D+/', '', (string) $id);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $query = <<<'GQL'
        query ($ids: [ID!]!, $loc: ID!) {
          nodes(ids: $ids) {
            ... on InventoryItem {
              id
              inventoryLevel(locationId: $loc) {
                quantities(names: ["available"]) { name quantity }
              }
            }
          }
        }
        GQL;

        $out = [];
        $anyOk = false;
        $chunks = array_chunk(array_keys($ids), 50);
        foreach ($chunks as $i => $chunk) {
            $json = self::post($store, $token, $query, [
                'ids' => array_map(static fn ($id) => 'gid://shopify/InventoryItem/'.$id, $chunk),
                'loc' => $locationGid,
            ]);
            if ($json === null) {
                continue;
            }
            $anyOk = true;
            foreach ($json['data']['nodes'] ?? [] as $node) {
                if (! is_array($node) || ! preg_match('#InventoryItem/(\d+)#', (string) ($node['id'] ?? ''), $m)) {
                    continue;
                }
                $out[$m[1]] = self::availableFromLevel($node['inventoryLevel'] ?? null);
            }
            if ($i < count($chunks) - 1) {
                usleep(250000);
            }
        }

        return $anyOk ? $out : null;
    }

    private static function availableFromLevel(mixed $level): int
    {
        if (! is_array($level)) {
            return 0;
        }
        foreach ($level['quantities'] ?? [] as $row) {
            if (is_array($row) && ($row['name'] ?? '') === 'available' && isset($row['quantity']) && is_numeric($row['quantity'])) {
                return (int) $row['quantity'];
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>|null
     */
    private static function post(string $store, string $token, string $query, array $variables): ?array
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(45)->post("https://{$store}/admin/api/2025-01/graphql.json", [
                    'query' => $query,
                    'variables' => $variables,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ShopifyOhioAvailableQty: request failed', ['error' => $e->getMessage()]);

                return null;
            }
            if ($response->status() === 429) {
                sleep(max(1, (int) ($response->header('Retry-After') ?: $attempt)));
                continue;
            }
            if (! $response->successful()) {
                return null;
            }
            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }
            foreach ($json['errors'] ?? [] as $error) {
                if (is_array($error) && strtoupper((string) ($error['extensions']['code'] ?? '')) === 'THROTTLED') {
                    sleep($attempt);
                    continue 2;
                }
            }

            return $json;
        }

        return null;
    }
}
