<?php

namespace App\Support;

/**
 * Finds the outbound tracking number anywhere in a marketplace order payload
 * (Shein packageWaybillList, TikTok line_items, Temu packages, …) when the
 * field name differs per channel / API version.
 */
class TrackingPayloadExtractor
{
    /** Normalised (lowercase, alphanumeric) keys that hold a tracking / waybill number. */
    protected const TRACKING_KEYS = [
        'tracking', 'trackingnumber', 'trackingno', 'trackingnum', 'trackingcode', 'trackingid',
        'trackingnumbers', 'trackingnumberlist', 'tracknumber', 'trackno', 'tracknum', 'trackcode',
        'expressno', 'expresscode', 'expressnumber', 'expressnum', 'expresswaybillno',
        'waybill', 'waybillno', 'waybillnumber', 'waybillcode',
        'logisticsno', 'logisticsnumber', 'logisticsnum', 'logisticswaybillno', 'logisticstrackingno',
        'mailno', 'shippingcode', 'shippingno', 'shippingnumber', 'shippingtrackingnumber',
        'shipmenttrackingnumber', 'lastmiletrackingnumber', 'lastmiletrackingno', 'carriertrackingnumber',
    ];

    /** Normalised keys that hold the carrier / courier name. */
    protected const CARRIER_KEYS = [
        'carrier', 'carriername', 'expressname', 'expresscompany', 'expresscompanyname', 'expressidcode',
        'logisticscompany', 'logisticscompanyname', 'logisticsprovider', 'logisticsprovidername',
        'logisticsservicename', 'shippingprovider', 'shippingprovidername', 'shippingcarrier',
        'shippingcompany', 'courier', 'couriername',
    ];

    /**
     * @param  array<mixed>  $payload
     * @param  list<string>  $notTracking  values that must never be taken as tracking (order ids, package ids)
     * @return array{tracking: string, carrier: string}|null
     */
    public static function find(array $payload, array $notTracking = []): ?array
    {
        $exclude = [];
        foreach ($notTracking as $value) {
            $key = self::normaliseNumber((string) $value);
            if ($key !== '') {
                $exclude[$key] = true;
            }
        }

        $tracking = null;
        $gofo = null;
        $carrier = '';
        $walk = static function ($value, string $key) use (&$walk, &$tracking, &$gofo, &$carrier, $exclude): void {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    // List items inherit the parent key (trackingNumberList: ["GFUS…"]).
                    $walk($v, is_int($k) ? $key : self::normaliseKey((string) $k));
                }

                return;
            }
            if (! is_scalar($value)) {
                return;
            }
            $raw = trim((string) $value);
            if ($raw === '') {
                return;
            }
            if ($carrier === '' && in_array($key, self::CARRIER_KEYS, true) && ! is_numeric($raw) && strlen($raw) <= 64) {
                $carrier = $raw;
            }
            $number = self::normaliseNumber($raw);
            if ($number === '' || isset($exclude[$number])) {
                return;
            }
            // GOFO waybills are unambiguous wherever they appear.
            if ($gofo === null && preg_match('/^GF[A-Z]{2,4}\d{8,}$/', $number) === 1) {
                $gofo = $number;
            }
            if ($tracking === null && in_array($key, self::TRACKING_KEYS, true) && self::looksLikeTracking($number)) {
                $tracking = $number;
            }
        };
        $walk($payload, '');

        $number = $tracking ?? $gofo;
        if ($number === null) {
            return null;
        }
        if ($carrier === '' || TrackingCarrierGuesser::isPlaceholder($carrier)) {
            $carrier = TrackingCarrierGuesser::labelFromNumber($number) ?? 'Other';
        }

        return ['tracking' => $number, 'carrier' => $carrier];
    }

    public static function looksLikeTracking(string $number): bool
    {
        if (preg_match('/^[A-Z0-9]{8,40}$/', $number) !== 1 || preg_match_all('/\d/', $number) < 6) {
            return false;
        }
        // Repeated placeholder digits (00000000) and pure dates are not tracking numbers.
        if (preg_match('/^(.)\1+$/', $number) === 1 || preg_match('/^20\d{6}$/', $number) === 1) {
            return false;
        }

        return true;
    }

    protected static function normaliseKey(string $key): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));
    }

    protected static function normaliseNumber(string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $value));
    }
}
