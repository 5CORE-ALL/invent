<?php

namespace App\Support;

/**
 * Doba prepaid-label tracking as uploaded to Shopify.
 * Keep the waybill only — never the carrier in parentheses.
 *
 * 1Z16E50BYW58136534(UPS) → 1Z16E50BYW58136534
 */
class DobaTrackingNumber
{
    public static function sanitize(string $tracking): string
    {
        $tracking = trim(str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $tracking));
        if ($tracking === '') {
            return '';
        }

        $tracking = preg_replace('/\s*\([^)]*\)\s*/', '', $tracking) ?? $tracking;
        $tracking = preg_replace('/\s+(UPS|USPS|FEDEX|DHL|GOFO|ONTRAC|UNIUNI|AMAZON)\s*$/i', '', $tracking) ?? $tracking;
        $tracking = strtoupper(preg_replace('/\s+/', '', $tracking) ?? $tracking);

        return $tracking;
    }

    public static function needsSanitize(string $tracking): bool
    {
        $tracking = trim($tracking);

        return $tracking !== '' && self::sanitize($tracking) !== strtoupper(preg_replace('/\s+/', '', $tracking) ?? $tracking);
    }
}
