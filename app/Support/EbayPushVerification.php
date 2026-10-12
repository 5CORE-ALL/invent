<?php

namespace App\Support;

/**
 * eBay price push: ReviseFixedPriceItem returning "success" only means eBay accepted the call.
 * Multi-variation listings can ignore an item-level StartPrice, and a failed read-back used to be
 * replaced by the price we asked for, so the listing looked updated (badge cleared, status "pushed")
 * while eBay still showed the old price. Amazon never did this: it reads the listing back and keeps
 * whatever price is really live (AmazonPushedPricePullService::confirmAfterPush).
 *
 * A push counts as applied only when the price read back from eBay equals the requested price.
 */
final class EbayPushVerification
{
    public const VERIFIED = 'verified';

    /** eBay accepted the revise, but the listing still shows another price. */
    public const NOT_APPLIED = 'not_applied';

    /** eBay accepted the revise, but the listing price could not be read back. */
    public const UNVERIFIED = 'unverified';

    public const TOLERANCE = 0.005;

    /** Pause before each read-back attempt (microseconds). eBay can take a moment to show the new price. */
    public const WAITS_US = [0, 800000, 2000000];

    /**
     * @param  callable():?float  $readLivePrice  reads the listing price from eBay (GetItem); null when it cannot
     * @param  list<int>|null  $waitsUs  override the pauses (tests pass [0, 0, 0])
     * @return array{state: string, live: ?float, message: string}
     */
    public static function confirm(float $requested, callable $readLivePrice, ?array $waitsUs = null): array
    {
        $requested = round($requested, 2);
        $last = null;

        foreach ($waitsUs ?? self::WAITS_US as $waitUs) {
            if ($waitUs > 0) {
                usleep($waitUs);
            }
            $live = $readLivePrice();
            if ($live !== null && $live > 0) {
                $last = round((float) $live, 2);
            }
            if ($last !== null && abs($last - $requested) <= self::TOLERANCE) {
                return ['state' => self::VERIFIED, 'live' => $last, 'message' => ''];
            }
        }

        if ($last === null) {
            return [
                'state' => self::UNVERIFIED,
                'live' => null,
                'message' => sprintf('eBay accepted the revise but the listing price could not be read back to confirm $%.2f', $requested),
            ];
        }

        return [
            'state' => self::NOT_APPLIED,
            'live' => $last,
            'message' => sprintf('eBay accepted the revise but the listing still shows $%.2f, not $%.2f', $last, $requested),
        ];
    }
}
