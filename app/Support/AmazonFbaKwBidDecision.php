<?php

namespace App\Support;

/**
 * FBA KW bid eligibility. A real Amazon PAUSED/ARCHIVED state blocks the push.
 * ENABLED stays eligible when Amazon's serving status is OUT_OF_BUDGET.
 * Historical daily status is not an input and is never rewritten here.
 */
final class AmazonFbaKwBidDecision
{
    public static function shouldPush(?string $campaignStatus, ?string $servingStatus, float $cellSbid, float $liveBid): bool
    {
        if (! self::allowsBidPush($campaignStatus, $servingStatus)) {
            return false;
        }
        if ($cellSbid <= 0) {
            return false;
        }
        if ($liveBid <= 0) {
            return true;
        }

        return abs($cellSbid - $liveBid) >= 0.005;
    }

    public static function allowsBidPush(?string $campaignStatus, ?string $servingStatus = null): bool
    {
        $status = self::normalize($campaignStatus);
        if (self::isRealPause($status) || $status === 'ARCHIVED' || $status === 'CAMPAIGN_ARCHIVED') {
            return false;
        }
        if ($status === 'ENABLED' || $status === 'CAMPAIGN_STATUS_ENABLED' || $status === 'CAMPAIGN_ENABLED') {
            return true;
        }
        $serving = self::normalize($servingStatus);
        if (str_contains($status, 'OUT_OF_BUDGET') || str_contains($serving, 'OUT_OF_BUDGET')) {
            return true;
        }

        return false;
    }

    public static function isRealPause(?string $campaignStatus): bool
    {
        $status = self::normalize($campaignStatus);

        return $status === 'PAUSED' || $status === 'CAMPAIGN_PAUSED';
    }

    /**
     * Recorded live bid (last_sbid). The stored suggestion column is not the live bid.
     */
    public static function recordedLiveBid(?object $primary, ?object $secondary): float
    {
        foreach ([$primary, $secondary] as $row) {
            if ($row === null) {
                continue;
            }
            $live = (float) ($row->last_sbid ?? 0);
            if ($live > 0) {
                return $live;
            }
        }

        return 0.0;
    }

    private static function normalize(?string $value): string
    {
        $v = strtoupper(trim((string) $value));
        $v = str_replace([' ', '-'], '_', $v);

        return $v;
    }
}
