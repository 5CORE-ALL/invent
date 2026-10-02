<?php

namespace App\Services;

/**
 * Shopify Admin GraphQL payloads for stock-balance transfers.
 * GraphQL uses a cost bucket separate from the REST 2-calls/second limit,
 * so a transfer can still run while REST is returning 429.
 */
class ShopifyStockTransferGraphql
{
    public const VARIANT_INVENTORY_QUERY = <<<'GQL'
query VariantAtLocation($id: ID!, $locationId: ID!) {
  productVariant(id: $id) {
    inventoryItem {
      id
      inventoryLevel(locationId: $locationId) {
        quantities(names: ["available"]) {
          name
          quantity
        }
      }
    }
  }
}
GQL;

    public const ADJUST_MUTATION = <<<'GQL'
mutation AdjustAvailable($input: InventoryAdjustQuantitiesInput!) {
  inventoryAdjustQuantities(input: $input) {
    userErrors {
      field
      message
    }
    inventoryAdjustmentGroup {
      reason
    }
  }
}
GQL;

    public const LOCATIONS_QUERY = <<<'GQL'
query StockBalanceLocations {
  locations(first: 50) {
    nodes {
      id
      name
      isActive
    }
  }
}
GQL;

    public static function isThrottled(?array $json): bool
    {
        if ($json === null) {
            return false;
        }

        foreach ($json['errors'] ?? [] as $error) {
            if (! is_array($error)) {
                continue;
            }
            $code = strtoupper((string) ($error['extensions']['code'] ?? ''));
            $message = strtolower((string) ($error['message'] ?? ''));
            if ($code === 'THROTTLED' || str_contains($message, 'throttled')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Seconds to wait before the next GraphQL attempt.
     */
    public static function throttleWaitSeconds(mixed $retryAfter, ?array $json, int $fallbackSeconds): int
    {
        if (is_numeric($retryAfter) && (float) $retryAfter > 0) {
            return (int) min(12, max(2, ceil((float) $retryAfter)));
        }

        $status = $json['extensions']['cost']['throttleStatus'] ?? null;
        if (is_array($status)) {
            $available = (float) ($status['currentlyAvailable'] ?? 0);
            $restore = (float) ($status['restoreRate'] ?? 0);
            $requested = (float) ($json['extensions']['cost']['requestedQueryCost'] ?? 0);
            if ($restore > 0 && $requested > $available) {
                return (int) min(12, max(2, ceil(($requested - $available) / $restore)));
            }
        }

        return (int) min(12, max(2, $fallbackSeconds));
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array{status: string, inventory_item_id?: string, available?: int|null}
     */
    public static function parseVariantInventory(?array $json): array
    {
        if ($json === null || self::isThrottled($json)) {
            return ['status' => 'failed'];
        }

        if (! empty($json['errors']) && ! isset($json['data']['productVariant'])) {
            return ['status' => 'failed'];
        }

        $variant = $json['data']['productVariant'] ?? null;
        if (! is_array($variant)) {
            return ['status' => 'missing'];
        }

        $itemGid = $variant['inventoryItem']['id'] ?? null;
        $inventoryItemId = self::gidNumeric(is_string($itemGid) ? $itemGid : null, 'InventoryItem');
        if ($inventoryItemId === null) {
            return ['status' => 'missing'];
        }

        $level = $variant['inventoryItem']['inventoryLevel'] ?? null;
        if (! is_array($level)) {
            return [
                'status' => 'ok',
                'inventory_item_id' => $inventoryItemId,
                'available' => null,
            ];
        }

        $available = null;
        foreach ($level['quantities'] ?? [] as $quantity) {
            if (! is_array($quantity)) {
                continue;
            }
            if (($quantity['name'] ?? '') === 'available' && isset($quantity['quantity']) && is_numeric($quantity['quantity'])) {
                $available = (int) $quantity['quantity'];
                break;
            }
        }

        if ($available === null) {
            $first = $level['quantities'][0]['quantity'] ?? null;
            $available = is_numeric($first) ? (int) $first : null;
        }

        return [
            'status' => 'ok',
            'inventory_item_id' => $inventoryItemId,
            'available' => $available,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array{success: bool, error?: string}
     */
    public static function parseAdjust(?array $json): array
    {
        if ($json === null || self::isThrottled($json)) {
            return ['success' => false, 'error' => 'Shopify request failed'];
        }

        $payload = $json['data']['inventoryAdjustQuantities'] ?? null;
        if (! is_array($payload)) {
            $message = $json['errors'][0]['message'] ?? 'Shopify did not adjust inventory';

            return ['success' => false, 'error' => is_string($message) ? $message : 'Shopify did not adjust inventory'];
        }

        $userErrors = $payload['userErrors'] ?? [];
        if (is_array($userErrors) && $userErrors !== []) {
            $message = $userErrors[0]['message'] ?? 'Shopify rejected the inventory adjustment';

            return ['success' => false, 'error' => is_string($message) ? $message : 'Shopify rejected the inventory adjustment'];
        }

        if (empty($payload['inventoryAdjustmentGroup'])) {
            return ['success' => false, 'error' => 'Shopify did not confirm the inventory adjustment'];
        }

        return ['success' => true];
    }

    /**
     * Prefer a location named Ohio, then Main Warehouse.
     *
     * @param  array<string, mixed>|null  $json
     */
    public static function parseOhioLocationId(?array $json): ?string
    {
        $nodes = $json['data']['locations']['nodes'] ?? null;
        if (! is_array($nodes)) {
            return null;
        }

        $bestId = null;
        $bestRank = PHP_INT_MAX;
        foreach ($nodes as $node) {
            if (! is_array($node) || ($node['isActive'] ?? true) === false) {
                continue;
            }
            $rank = self::locationNameRank((string) ($node['name'] ?? ''));
            $id = self::gidNumeric(isset($node['id']) ? (string) $node['id'] : null, 'Location');
            if ($rank === null || $id === null || $rank >= $bestRank) {
                continue;
            }
            $bestRank = $rank;
            $bestId = $id;
        }

        return $bestId;
    }

    public static function gidNumeric(?string $gid, string $type): ?string
    {
        if ($gid === null || $gid === '') {
            return null;
        }
        if (! preg_match('#'.preg_quote($type, '#').'/(\d+)#', $gid, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private static function locationNameRank(string $name): ?int
    {
        $compact = strtolower((string) preg_replace('/\s+/', '', trim($name)));
        if (str_contains(strtolower($name), 'ohio')) {
            return 0;
        }
        if ($compact === 'mainwarehouse') {
            return 1;
        }

        return null;
    }
}
