<?php

namespace App\Support;

/**
 * Which BGT rule set is in force: the standard one (SP + SB campaigns) or the HL one (SBGT HL).
 *
 * Rules live in amazon_ads_rules. The HL set is stored under the same key with an "hl_" prefix
 * (hl_acos, hl_views, ...). Until an HL rule is saved, the HL set reads the standard rule, so
 * nothing changes for HL campaigns until someone edits them in the SBGT HL modal.
 *
 * An HL campaign is a Sponsored Brands campaign named "[PARENT ]{parent} HL|HEAD[ n]" (the same
 * naming rule the Missing Ads HL import uses). "... HEAD KW" is a keyword campaign, not HL.
 */
final class AmazonAdsRuleScope
{
    public const DEFAULT = '';

    public const HL = 'hl';

    private const HL_NAME = '/\s+(?:HL|HEAD)(?:\s*\d+)?$/i';

    private static string $current = self::DEFAULT;

    public static function normalize(?string $scope): string
    {
        return strtolower(trim((string) $scope)) === self::HL ? self::HL : self::DEFAULT;
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function set(?string $scope): void
    {
        self::$current = self::normalize($scope);
    }

    public static function isHlScope(): bool
    {
        return self::$current === self::HL;
    }

    /** Storage key for the scope in force. */
    public static function keyFor(string $key): string
    {
        return self::$current === self::HL ? 'hl_'.$key : $key;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public static function run(?string $scope, callable $fn): mixed
    {
        $previous = self::$current;
        self::$current = self::normalize($scope);
        try {
            return $fn();
        } finally {
            self::$current = $previous;
        }
    }

    /**
     * True for an HL campaign. $isSb: true / false when the report source is known (Sponsored Brands or not),
     * null when it is not, in which case the name alone decides.
     */
    public static function isHlCampaign(?string $campaignName, ?bool $isSb = null): bool
    {
        if ($isSb === false) {
            return false;
        }
        $name = strtoupper(rtrim(preg_replace('/\s+/', ' ', trim((string) $campaignName)), '.'));
        if ($name === '') {
            return false;
        }

        return preg_match(self::HL_NAME, $name) === 1;
    }

    /** Ad type text ("SPONSORED_BRANDS", "SB") or report table name to Sponsored Brands yes / no / unknown. */
    public static function isSbSource(?string $adTypeOrTable): ?bool
    {
        $t = strtoupper(trim((string) $adTypeOrTable));
        if ($t === '') {
            return null;
        }
        if (str_contains($t, 'BRAND') || $t === 'SB' || str_contains($t, '_SB_')) {
            return true;
        }

        return false;
    }

    /** Scope a campaign's rules come from. */
    public static function scopeForCampaign(?string $campaignName, ?string $adTypeOrTable = null): string
    {
        return self::isHlCampaign($campaignName, self::isSbSource($adTypeOrTable)) ? self::HL : self::DEFAULT;
    }
}
