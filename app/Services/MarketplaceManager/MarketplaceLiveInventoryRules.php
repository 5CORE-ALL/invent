<?php

namespace App\Services\MarketplaceManager;

/**
 * Hard rules for Marketplace Manager inventory (Shopify master):
 *
 * 1. NEVER update an unlinked SKU on any marketplace.
 * 2. NEVER update unpublished / suspended / dead Reverb listings.
 *    Draft MAY receive inventory updates but MUST stay draft (never publish/live).
 * 3. Linked + live / sold-out (sold / out_of_stock / ended) / draft MAY update from live Shopify.
 * 4. UI ALWAYS shows live Shopify inventory for listed SKUs.
 * 5. If live Shopify qty is 0 (or missing), marketplace qty MUST be 0.
 *    Never push 1 (or any positive) when Shopify is 0. min_quantity is ignored forever.
 */
final class MarketplaceLiveInventoryRules
{
    /**
     * Linked = real marketplace product/listing id that is not the SKU placeholder.
     */
    public static function isLinked(?string $productId, ?string $sku): bool
    {
        $productId = trim((string) $productId);
        $sku = trim((string) $sku);

        if ($productId === '' || $sku === '') {
            return false;
        }

        // Local "product_id = sku" means not linked to a marketplace listing id.
        return strcasecmp($productId, $sku) !== 0;
    }

    /**
     * eBay parent/placeholder SKUs (PARENT ALLIGATOR, etc.) are not Shopify SKUs.
     */
    public static function isParentPlaceholderSku(?string $sku): bool
    {
        $sku = trim((string) $sku);

        return $sku !== '' && stripos($sku, 'PARENT') !== false;
    }

    /**
     * Live Shopify → marketplace push qty.
     * Missing / null / negative => 0. Never invent stock via min_quantity.
     * Optional percent/max may only REDUCE inventory, never raise above live Shopify.
     * Negative live Shopify (oversell reporting) is treated as 0 for marketplace push.
     *
     * @param  int|string|null  $maxQty
     */
    public static function qtyFromLiveShopify(?int $liveShopifyQty, ?int $percent = 100, $maxQty = null): int
    {
        if ($liveShopifyQty === null || (int) $liveShopifyQty <= 0) {
            return 0;
        }

        $qty = (int) $liveShopifyQty;
        $percent = $percent === null ? 100 : max(0, min(100, (int) $percent));
        if ($percent < 100) {
            $qty = (int) floor($qty * $percent / 100);
        }

        if ($maxQty !== null && $maxQty !== '' && is_numeric($maxQty) && (int) $maxQty >= 0) {
            $qty = min($qty, (int) $maxQty);
        }

        // Absolute: never exceed live Shopify; never invent when live was 0.
        return max(0, min($qty, (int) $liveShopifyQty));
    }

    /**
     * SKU not found on live Shopify catalog => treat as OOS everywhere.
     */
    public static function qtyWhenMissingFromShopify(): int
    {
        return 0;
    }

    /**
     * Exact live Shopify qty for push (no percent/max). Still never invent from 0.
     */
    public static function pushQtyFromLiveShopify(?int $liveShopifyQty): int
    {
        return self::qtyFromLiveShopify($liveShopifyQty, 100, null);
    }

    /**
     * Draft-like states (UI / filters). Inventory: only true "draft" may update qty;
     * unpublished / suspended / dead stay blocked via reverbIsInactiveBlocked().
     */
    public static function reverbIsDraftLike(?string $state): bool
    {
        $state = strtolower(trim((string) $state));

        return in_array($state, ['draft', 'unpublished', 'suspended', 'dead'], true);
    }

    /**
     * States that must never receive inventory API writes.
     */
    public static function reverbIsInactiveBlocked(?string $state): bool
    {
        $state = strtolower(trim((string) $state));

        return in_array($state, ['unpublished', 'suspended', 'dead'], true);
    }

    /**
     * True draft only — inventory may update, but must never be published to live.
     */
    public static function reverbIsDraftOnly(?string $state): bool
    {
        return strtolower(trim((string) $state)) === 'draft';
    }

    /**
     * Sold-out / sold states that ARE allowed to receive Shopify inventory when linked.
     */
    public static function reverbIsSoldOutLike(?string $state): bool
    {
        $state = strtolower(trim((string) $state));

        return in_array($state, ['sold', 'out_of_stock', 'ended'], true);
    }

    /**
     * Linked Reverb listing may receive inventory updates from Shopify:
     * live, sold-out family, and draft (draft stays draft — never auto-publish).
     */
    public static function reverbMayUpdateInventory(?string $state): bool
    {
        if (self::reverbIsInactiveBlocked($state)) {
            return false;
        }

        $state = strtolower(trim((string) $state));
        if ($state === '') {
            // Unknown: allow (API may reject); caller already verified linked.
            return true;
        }

        return in_array($state, ['live', 'sold', 'out_of_stock', 'ended', 'draft'], true);
    }

    /**
     * Only sold-family may get publish=true / state=live on inventory PUT.
     * Draft must never use this path.
     */
    public static function reverbMayPublishRestock(?string $state): bool
    {
        return self::reverbIsSoldOutLike($state) && ! self::reverbIsDraftOnly($state);
    }

    /**
     * @deprecated Use reverbMayUpdateInventory / reverbIsDraftLike
     */
    public static function reverbMayCarryPositiveStock(?string $state): bool
    {
        return self::reverbMayUpdateInventory($state);
    }

    /**
     * @deprecated Use reverbIsInactiveBlocked for write blocks
     */
    public static function reverbBlocksPositiveStock(?string $state): bool
    {
        return self::reverbIsInactiveBlocked($state);
    }

    /** @deprecated Use reverbMayCarryPositiveStock */
    public static function reverbAllowsPositiveStock(?string $state): bool
    {
        return self::reverbMayCarryPositiveStock($state);
    }

    public static function reverbPushQty(?int $liveShopifyQty, ?string $state, ?int $percent = 100, $maxQty = null): int
    {
        if (self::reverbIsInactiveBlocked($state)) {
            return 0;
        }

        $qty = self::qtyFromLiveShopify($liveShopifyQty, $percent, $maxQty);
        if ($qty <= 0) {
            return 0;
        }

        return self::reverbMayUpdateInventory($state) ? $qty : 0;
    }

    /**
     * AliExpress: skip draft-like statuses; allow onSelling (and empty = treat as sellable).
     */
    public static function aliexpressMayCarryPositiveStock(?string $status): bool
    {
        $status = strtolower(trim((string) $status));
        if (in_array($status, ['draft', 'auditing', 'offline', 'deleted'], true)) {
            return false;
        }

        return $status === '' || $status === 'onselling';
    }

    public static function aliexpressPushQty(?int $liveShopifyQty, ?string $status, ?int $percent = 100, $maxQty = null): int
    {
        $qty = self::qtyFromLiveShopify($liveShopifyQty, $percent, $maxQty);
        if ($qty <= 0) {
            return 0;
        }

        return self::aliexpressMayCarryPositiveStock($status) ? $qty : 0;
    }

    /**
     * Absolute safety clamp before any marketplace API write.
     * If known Shopify qty is <= 0, force marketplace qty to 0.
     */
    public static function clampPushQty(int $marketplaceQty, ?int $liveShopifyQty = null): int
    {
        $qty = max(0, $marketplaceQty);
        if ($liveShopifyQty !== null && (int) $liveShopifyQty <= 0) {
            return 0;
        }

        return $qty;
    }

    /**
     * Guard against mass-zero pushes when Shopify live fetch fails.
     * If too few requested SKUs resolve to a live qty, abort the full sync instead
     * of treating every miss as Shopify OOS (qty 0).
     *
     * @param  array<int, string>  $requestedSkus
     * @param  array<string, int|string|null>  $shopifyQtyMap
     * @param  callable(string): (?int)  $resolveQty  fn(sku) => qty|null
     */
    public static function shopifyLiveCoverageOk(
        array $requestedSkus,
        callable $resolveQty,
        float $minRatio = 0.35,
        int $minAbsolute = 20
    ): bool {
        $requested = 0;
        $covered = 0;
        foreach ($requestedSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) {
                continue;
            }
            $requested++;
            if ($resolveQty($sku) !== null) {
                $covered++;
            }
        }

        if ($requested === 0) {
            return true;
        }

        // Tiny batches (webhooks / force SKU lists): require at least one hit if any requested.
        if ($requested < $minAbsolute) {
            return $covered > 0 || $requested === 0;
        }

        return ($covered / $requested) >= $minRatio;
    }

    /**
     * @param  array<int, string>  $requestedSkus
     * @param  callable(string): (?int)  $resolveQty
     * @return array{ok: bool, requested: int, covered: int, ratio: float, message: string}
     */
    public static function shopifyLiveCoverageReport(
        array $requestedSkus,
        callable $resolveQty,
        float $minRatio = 0.35,
        int $minAbsolute = 20
    ): array {
        $requested = 0;
        $covered = 0;
        foreach ($requestedSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) {
                continue;
            }
            $requested++;
            if ($resolveQty($sku) !== null) {
                $covered++;
            }
        }

        $ratio = $requested > 0 ? ($covered / $requested) : 1.0;
        $ok = self::shopifyLiveCoverageOk($requestedSkus, $resolveQty, $minRatio, $minAbsolute);

        return [
            'ok' => $ok,
            'requested' => $requested,
            'covered' => $covered,
            'ratio' => round($ratio, 3),
            'message' => $ok
                ? "Shopify live coverage {$covered}/{$requested} (".round($ratio * 100)."%)."
                : "Aborted: Shopify live coverage only {$covered}/{$requested} (".round($ratio * 100)."%). Refusing to mass-zero marketplace inventory.",
        ];
    }
}
