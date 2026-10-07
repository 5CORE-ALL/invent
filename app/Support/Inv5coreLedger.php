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
