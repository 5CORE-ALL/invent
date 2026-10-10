<?php

namespace App\Support;

/**
 * Inventory rules for INV Management 5Core.
 *
 * INV / L30 / DIL% stay on the CP Master tables (product_master + shopify_skus).
 * INV APP is a separate ledger: Shopify on-hand is copied once per SKU, then
 * this page never reads Shopify again for that SKU. Later app sales and
 * manual adjustments are the only movements.
 */
class Inv5coreLedger
{
    /** @var list<string> */
    public const SKIPPED_STATUSES = ['refunded', 'voided', 'cancelled', 'canceled', 'void'];

    public static function isParentSku(?string $sku): bool
    {
        return str_contains(strtoupper(trim((string) $sku)), 'PARENT');
    }

    public static function statusSkipsSale(?string $status): bool
    {
        $status = strtolower(trim((string) $status));
        if ($status === '') {
            return false;
        }

        return in_array($status, self::SKIPPED_STATUSES, true);
    }

    /**
     * Shipped / delivered marketplace statuses. Pending orders are committed
     * only; on hand drops when the order is fulfilled.
     */
    public static function statusIsFulfilled(?string $status): bool
    {
        $compact = str_replace([' ', '_', '-'], '', strtolower(trim((string) $status)));

        return in_array($compact, [
            'shipped',
            'partiallyshipped',
            'fulfilled',
            'delivered',
            'partiallydelivered',
            'completed',
            'complete',
            'intransit',
            'pickedup',
            'closed',
            'received',
            'awaitingcollection',
            'partiallyshipping',
            'buyeracceptgoods',
            'finish',
            'tradefinished',
            'waitbuyeracceptgoods',
        ], true);
    }

    /**
     * @return array{on_hand: float, committed: float, unavailable: float, available: float}
     */
    public static function nextStates(
        float $onHand,
        float $committed,
        float $unavailable,
        float $onHandDelta,
        float $committedDelta = 0.0,
        float $unavailableDelta = 0.0
    ): array {
        $onHand = self::roundQty($onHand + $onHandDelta);
        $committed = self::roundQty($committed + $committedDelta);
        $unavailable = self::roundQty($unavailable + $unavailableDelta);

        return [
            'on_hand' => $onHand,
            'committed' => $committed,
            'unavailable' => $unavailable,
            'available' => self::roundQty($onHand - $committed - $unavailable),
        ];
    }

    public static function historyActivity(string $txnType, string $reference): string
    {
        $ref = ltrim(trim($reference), '#');
        $suffix = $ref !== '' ? ' (#'.$ref.')' : '';

        return match ($txnType) {
            'order_created' => 'Order created'.$suffix,
            'order_fulfilled', 'sale' => 'Order fulfilled'.$suffix,
            'return' => 'Order canceled'.$suffix,
            'opening' => 'Opening inventory',
            'incoming' => 'Inventory received',
            'write_off' => 'Write-off',
            'adjustment' => 'Inventory adjusted',
            default => ucfirst(str_replace('_', ' ', $txnType)).$suffix,
        };
    }

    /**
     * Order and opening rows are labeled with the marketplace stored on the
     * movement. A person's name is kept for manual adjustments.
     */
    public static function historyCreatedBy(string $txnType, string $channel, string $userName): string
    {
        $channel = trim($channel);
        $userName = trim($userName);
        $fromMarketplace = in_array($txnType, ['opening', 'order_created', 'order_fulfilled', 'sale', 'return'], true);
        if ($fromMarketplace && $channel !== '' && strcasecmp($channel, 'App') !== 0) {
            return $channel;
        }
        if ($userName !== '') {
            return $userName;
        }
        if ($channel !== '' && strcasecmp($channel, 'App') !== 0) {
            return $channel;
        }

        return '5Core Inventory';
    }

    /**
     * A source row reduces on-hand only when it was inserted after the
     * watermark saved with the one-time opening. Older rows are already
     * inside that Shopify on-hand number.
     */
    public static function saleIdAffectsBalance(int $sourceId, ?int $watermarkId): bool
    {
        if ($watermarkId === null || $sourceId <= 0) {
            return false;
        }

        return $sourceId > $watermarkId;
    }

    public static function needsReversal(bool $alreadyPosted, bool $statusSkipsSale): bool
    {
        return $alreadyPosted && $statusSkipsSale;
    }

    public static function roundQty(float $qty): float
    {
        return round($qty, 2);
    }

    public static function deltaForSet(float $onHand, float $newQty): float
    {
        return self::roundQty($newQty - $onHand);
    }

    public static function deltaForAdd(float $qty): float
    {
        return self::roundQty(abs($qty));
    }

    public static function deltaForSubtract(float $qty): float
    {
        return self::roundQty(-abs($qty));
    }

    public static function applyDelta(float $onHand, float $delta): float
    {
        return self::roundQty($onHand + $delta);
    }

    /**
     * Same image priority as CP Master: a stored upload, then the Shopify image.
     */
    public static function imagePath(?string $localImage, ?string $shopifyImage): ?string
    {
        if ($localImage && (str_contains($localImage, 'storage/') || str_contains($localImage, '/storage/'))) {
            return '/'.ltrim($localImage, '/');
        }
        if ($shopifyImage) {
            return $shopifyImage;
        }
        if ($localImage) {
            return '/'.ltrim($localImage, '/');
        }

        return null;
    }
}
