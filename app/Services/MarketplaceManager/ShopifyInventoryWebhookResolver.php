<?php

namespace App\Services\MarketplaceManager;

use App\Models\Inventory;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve Shopify inventory_levels webhook payload → product SKU(s).
 */
final class ShopifyInventoryWebhookResolver
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{skus: array<int, string>, available: int|null, inventory_item_id: string|null}
     */
    public static function resolve(array $payload): array
    {
        $inventoryItemId = self::extractInventoryItemId($payload);
        $available = self::extractAvailable($payload);

        $skus = [];
        if ($inventoryItemId !== null && $inventoryItemId !== '') {
            $skus = self::skusForInventoryItemId($inventoryItemId);
        }

        return [
            'skus' => $skus,
            'available' => $available,
            'inventory_item_id' => $inventoryItemId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function extractInventoryItemId(array $payload): ?string
    {
        $raw = $payload['inventory_item_id']
            ?? $payload['inventory_item']['id']
            ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw) && str_contains($raw, 'InventoryItem/')) {
            $raw = preg_replace('/.*InventoryItem\//', '', $raw);
        }

        $digits = preg_replace('/\D+/', '', (string) $raw);

        return $digits !== '' ? $digits : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function extractAvailable(array $payload): ?int
    {
        if (array_key_exists('available', $payload) && is_numeric($payload['available'])) {
            return (int) $payload['available'];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function skusForInventoryItemId(string $inventoryItemId, bool $forceLive = false): array
    {
        $skus = [];

        // 0) MM inventory ledger map (fast, preferred)
        if (! $forceLive) {
            try {
                $skus = app(InventoryLedgerService::class)->skusForInventoryItemId($inventoryItemId);
            } catch (\Throwable $e) {
                // non-fatal — table may not exist yet mid-deploy
            }
            if ($skus !== []) {
                return array_values(array_unique($skus));
            }
        }

        // 1) Local WMS inventories table
        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'shopify_inventory_item_id')) {
            $rows = Inventory::query()
                ->where('shopify_inventory_item_id', $inventoryItemId)
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku')
                ->all();
            foreach ($rows as $sku) {
                $sku = trim((string) $sku);
                if ($sku !== '') {
                    $skus[] = $sku;
                }
            }
        }

        if ($skus !== [] && ! $forceLive) {
            return array_values(array_unique($skus));
        }

        // 2) Live Shopify GraphQL: inventoryItem → variant.sku
        $fromApi = self::skuFromShopifyGraphql($inventoryItemId);
        if ($fromApi !== null && $fromApi !== '') {
            return [$fromApi];
        }

        // 3) Live Shopify REST inventory_item
        $fromRest = self::skuFromShopifyRestVariantLookup($inventoryItemId);
        if ($fromRest !== null && $fromRest !== '') {
            return [$fromRest];
        }

        if ($skus !== []) {
            return array_values(array_unique($skus));
        }

        Log::warning('ShopifyInventoryWebhookResolver: could not resolve SKU', [
            'inventory_item_id' => $inventoryItemId,
            'force_live' => $forceLive,
        ]);

        return [];
    }

    protected static function skuFromShopifyGraphql(string $inventoryItemId): ?string
    {
        $store = self::storeHost();
        $token = self::token();
        if ($store === '' || $token === '') {
            return null;
        }

        $gid = 'gid://shopify/InventoryItem/'.$inventoryItemId;
        $query = <<<'GQL'
query ($id: ID!) {
  inventoryItem(id: $id) {
    id
    variant {
      id
      sku
    }
  }
}
GQL;

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(20)->post("https://{$store}/admin/api/2025-01/graphql.json", [
                'query' => $query,
                'variables' => ['id' => $gid],
            ]);

            if (! $response->successful()) {
                return null;
            }

            $sku = trim((string) data_get($response->json(), 'data.inventoryItem.variant.sku', ''));
            $variantGid = (string) data_get($response->json(), 'data.inventoryItem.variant.id', '');
            if ($sku !== '') {
                self::touchShopifySkuCache($sku, $variantGid, null);

                return $sku;
            }
        } catch (\Throwable $e) {
            Log::warning('ShopifyInventoryWebhookResolver: GraphQL resolve failed', [
                'inventory_item_id' => $inventoryItemId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected static function skuFromShopifyRestVariantLookup(string $inventoryItemId): ?string
    {
        $store = self::storeHost();
        $token = self::token();
        if ($store === '' || $token === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(20)->get("https://{$store}/admin/api/2025-01/inventory_items/{$inventoryItemId}.json");

            if ($response->successful()) {
                $sku = trim((string) ($response->json('inventory_item.sku') ?? ''));
                if ($sku !== '') {
                    return $sku;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ShopifyInventoryWebhookResolver: REST inventory_item failed', [
                'inventory_item_id' => $inventoryItemId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected static function touchShopifySkuCache(string $sku, string $variantGid, ?int $available): void
    {
        try {
            $variantId = preg_replace('/\D+/', '', $variantGid) ?: null;
            $row = ShopifySku::firstForProductSku($sku);
            if (! $row) {
                return;
            }
            $updates = [];
            if ($variantId && empty($row->variant_id)) {
                $updates['variant_id'] = $variantId;
            }
            if ($available !== null) {
                // Keep Shopify negatives (oversell) so Main-INV matches Admin.
                $updates['available_to_sell'] = (int) $available;
                $updates['inv'] = (int) $available;
            }
            if ($updates !== []) {
                $row->fill($updates)->save();
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    protected static function storeHost(): string
    {
        return (string) preg_replace('#^https?://#', '', rtrim((string) config('services.shopify.store_url'), '/'));
    }

    protected static function token(): string
    {
        return (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');
    }
}
