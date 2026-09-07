<?php

namespace App\Services\MarketplaceManager;

/**
 * Shared skip-if-unchanged checks for eBay inventory sync.
 *
 * Quantity comparison matches eBay 2: live listing cache + last synced
 * marketplace qty (metrics / product_stock_mappings). Unknown current qty
 * is never treated as "already at target" so the first push still happens.
 */
final class EbayInventoryUnchangedSkip
{
    /**
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    public static function qtyMapForSkip(string $channel, array $skus): array
    {
        $liveRows = match ($channel) {
            MarketplaceListingStockResolver::CHANNEL_EBAY1 => app(Ebay1LiveListingsService::class)->peekCached(),
            MarketplaceListingStockResolver::CHANNEL_EBAY2 => app(Ebay2LiveListingsService::class)->peekCached(),
            MarketplaceListingStockResolver::CHANNEL_EBAY3 => app(Ebay3LiveListingsService::class)->peekCached(),
            default => null,
        };

        $local = MarketplaceListingStockResolver::stockMapForSkus($channel, $skus);

        return MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            is_array($liveRows) ? $liveRows : null,
            $local
        );
    }

    /**
     * @param  array<string, int>  $liveMpQty
     */
    public static function qtyAlreadyAtTarget(array $liveMpQty, string $sku, int $pushQty): bool
    {
        $current = MarketplaceListingStockResolver::qtyFromMap($liveMpQty, $sku);

        return $current !== null && (int) $current === $pushQty;
    }

    public static function priceAlreadyAtTarget(mixed $lastEbayPrice, mixed $targetPrice): bool
    {
        if ($targetPrice === null || (float) $targetPrice <= 0) {
            return true;
        }
        if ($lastEbayPrice === null || $lastEbayPrice === '') {
            return false;
        }

        return round((float) $lastEbayPrice, 2) === round((float) $targetPrice, 2);
    }
}
