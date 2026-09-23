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

    /**
     * Tracking Doba puts on the prepaid label, not the empty top-level field.
     *
     * @param  array<string, mixed>  $order
     * @return array{tracking: string, carrier: string}
     */
    public static function fromOrderPayload(array $order): array
    {
        foreach (['buyerPrepaidLabelList', 'shippingLabels', 'shippingLabelList'] as $key) {
            $list = $order[$key] ?? null;
            if (! is_array($list)) {
                continue;
            }
            $items = array_is_list($list) ? $list : [$list];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $tracking = self::sanitize((string) ($item['trackingNumber'] ?? $item['tracking_number'] ?? ''));
                if (strlen($tracking) < 8) {
                    continue;
                }
                $carrier = trim((string) ($item['carrier'] ?? $item['carrierName'] ?? $item['logisticsCompany'] ?? ''));

                return ['tracking' => $tracking, 'carrier' => $carrier];
            }
        }

        $tracking = self::sanitize((string) ($order['trackingNumber'] ?? $order['tracking_number'] ?? ''));
        if (strlen($tracking) < 8) {
            return ['tracking' => '', 'carrier' => ''];
        }

        return [
            'tracking' => $tracking,
            'carrier' => trim((string) ($order['logisticsType'] ?? '')),
        ];
    }

    public static function needsSanitize(string $tracking): bool
    {
        $tracking = trim($tracking);

        return $tracking !== '' && self::sanitize($tracking) !== strtoupper(preg_replace('/\s+/', '', $tracking) ?? $tracking);
    }
}
