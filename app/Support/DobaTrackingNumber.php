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
        $hit = self::firstTrackingInPayload($order);

        return $hit['tracking'] !== '' ? $hit : ['tracking' => '', 'carrier' => ''];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{tracking: string, carrier: string}
     */
    protected static function firstTrackingInPayload(array $node): array
    {
        $direct = self::trackingFromNode($node);
        if ($direct['tracking'] !== '') {
            return $direct;
        }
        foreach ($node as $value) {
            if (! is_array($value)) {
                continue;
            }
            $items = array_is_list($value) ? $value : [$value];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $hit = self::firstTrackingInPayload($item);
                if ($hit['tracking'] !== '') {
                    return $hit;
                }
            }
        }

        return ['tracking' => '', 'carrier' => ''];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{tracking: string, carrier: string}
     */
    protected static function trackingFromNode(array $node): array
    {
        foreach ([
            'trackingNumber', 'tracking_number', 'logisticsNo', 'logistics_no',
            'expressNo', 'express_no', 'waybillNo', 'waybill_no', 'trackingNo', 'shipNo',
        ] as $key) {
            if (! isset($node[$key]) || ! is_scalar($node[$key])) {
                continue;
            }
            $tracking = self::sanitize((string) $node[$key]);
            if (strlen($tracking) < 8) {
                continue;
            }
            $carrier = '';
            foreach (['carrier', 'carrierName', 'logisticsCompany', 'logisticsType', 'logisticsName', 'shippingCompany'] as $carrierKey) {
                $carrier = trim((string) ($node[$carrierKey] ?? ''));
                if ($carrier !== '') {
                    break;
                }
            }

            return ['tracking' => $tracking, 'carrier' => $carrier];
        }

        return ['tracking' => '', 'carrier' => ''];
    }

    public static function needsSanitize(string $tracking): bool
    {
        $tracking = trim($tracking);

        return $tracking !== '' && self::sanitize($tracking) !== strtoupper(preg_replace('/\s+/', '', $tracking) ?? $tracking);
    }
}
