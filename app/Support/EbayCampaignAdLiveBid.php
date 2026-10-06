<?php

namespace App\Support;

/**
 * Listing-level C Bid from the eBay Marketing ad payload.
 * The campaign fundingStrategy bid is a default for new ads, not the live
 * per-listing bid. Writing that fallback after Dil vs SBid overwrites the
 * value that was just pushed.
 */
final class EbayCampaignAdLiveBid
{
    /**
     * Listing bidPercentage only. Null when the list payload omitted it.
     */
    public static function fromPayload(array $ad): ?float
    {
        if (! array_key_exists('bidPercentage', $ad) || $ad['bidPercentage'] === null || $ad['bidPercentage'] === '') {
            return null;
        }
        if (! is_numeric($ad['bidPercentage'])) {
            return null;
        }

        return round((float) $ad['bidPercentage'], 2);
    }

    /**
     * Campaign-row fields for sync. bid_percentage is set only when the ad
     * carried a listing-level bid. Existing rows keep the stored C Bid when
     * the list API omitted it (do not fall back to the campaign default).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function applyToRow(array $row, array $ad): array
    {
        $bid = self::fromPayload($ad);
        if ($bid !== null) {
            $row['bid_percentage'] = $bid;
        }

        return $row;
    }

    /** Label for dry-run / log lines. */
    public static function logLabel(array $ad): string
    {
        $bid = self::fromPayload($ad);

        return $bid !== null ? $bid.'%' : 'listing bid missing (keep stored)';
    }

    /** Same tenth the C Bid / S Bid cells print. */
    public static function matches(?float $live, float $want): bool
    {
        if ($live === null || $live <= 0 || $want <= 0) {
            return false;
        }

        return abs(round($live, 1) - round($want, 1)) < 0.009;
    }
}
