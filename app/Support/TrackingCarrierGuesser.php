<?php

namespace App\Support;

/**
 * Infer a carrier from a tracking / waybill number.
 */
class TrackingCarrierGuesser
{
    /**
     * Lowercase slug used by tracking providers (ups, usps, fedex, …).
     */
    public static function slugFromNumber(string $number): ?string
    {
        $n = strtoupper(preg_replace('/\s+/', '', $number) ?? '');
        if ($n === '') {
            return null;
        }

        if (str_starts_with($n, '1Z')) {
            return 'ups';
        }
        if (str_starts_with($n, 'TBA')) {
            return 'amazon';
        }
        if (preg_match('/^(UN|UU|UNI)[A-Z0-9]{6,}$/', $n) === 1) {
            return 'uniuni';
        }
        if (preg_match('/^GF[A-Z0-9]{6,}$/', $n) === 1) {
            return 'gofo';
        }
        if (preg_match('/^(JD|GM)\d{10,}$/', $n) === 1 || preg_match('/^3S[A-Z0-9]{8,}$/', $n) === 1) {
            return 'dhl';
        }
        if (preg_match('/^[CD]\d{14,15}$/', $n) === 1) {
            return 'ontrac';
        }
        // USPS IMpb / Scan Based Payment: 91–96 + 18–22 more digits
        if (preg_match('/^(94|93|92|95|96|91)\d{18,22}$/', $n) === 1) {
            return 'usps';
        }
        if (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/', $n) === 1) {
            return 'usps';
        }
        // FedEx door-tag / Express: 12 digits, or 15 digits starting with 96
        if (preg_match('/^\d{12}$/', $n) === 1 || preg_match('/^96\d{13}$/', $n) === 1) {
            return 'fedex';
        }
        if (preg_match('/^\d{10}$/', $n) === 1) {
            return 'dhl';
        }

        return null;
    }

    /**
     * Display label for the SOF Carrier column (UPS, USPS, FedEx, …).
     */
    public static function labelFromNumber(string $number): ?string
    {
        $slug = self::slugFromNumber($number);

        return $slug !== null ? self::labelFromSlug($slug) : null;
    }

    public static function labelFromSlug(string $slug): string
    {
        return match (strtolower(trim($slug))) {
            'ups' => 'UPS',
            'usps' => 'USPS',
            'fedex' => 'FedEx',
            'dhl' => 'DHL',
            'gofo' => 'GOFO',
            'ontrac' => 'OnTrac',
            'amazon' => 'Amazon',
            'uniuni' => 'UniUni',
            default => strtoupper($slug),
        };
    }

    /**
     * Names that are not a real carrier. The tracking number decides instead.
     */
    public static function isPlaceholder(?string $carrier): bool
    {
        $carrier = strtolower(trim((string) $carrier));
        if ($carrier === '' || in_array($carrier, [
            '-', '—', 'n/a', 'na', 'none', 'null', 'unknown', 'other', 'others',
        ], true)) {
            return true;
        }

        if (str_contains($carrier, 'seller') || str_contains($carrier, 'marketplace')) {
            return true;
        }

        return str_contains($carrier, 'shipping') && str_contains($carrier, 'local');
    }

    /**
     * A carrier we recognize. Marketplace method names are not included.
     */
    public static function knownLabel(?string $carrier): ?string
    {
        $carrier = strtolower(trim((string) $carrier));
        if ($carrier === '' || self::isPlaceholder($carrier)) {
            return null;
        }
        if (str_contains($carrier, 'usps') || str_contains($carrier, 'united states postal') || str_contains($carrier, 'postal service')) {
            return 'USPS';
        }
        if (str_contains($carrier, 'fedex') || str_contains($carrier, 'federal express')) {
            return 'FedEx';
        }
        if (preg_match('/\bups\b/', $carrier) === 1 || str_contains($carrier, 'united parcel')) {
            return 'UPS';
        }
        if (str_contains($carrier, 'dhl')) {
            return 'DHL';
        }
        if (str_contains($carrier, 'gofo')) {
            return 'GOFO';
        }
        if (str_contains($carrier, 'ontrac') || str_contains($carrier, 'on trac') || str_contains($carrier, 'lasership') || str_contains($carrier, 'laser ship')) {
            return str_contains($carrier, 'laser') ? 'LaserShip' : 'OnTrac';
        }
        if (str_contains($carrier, 'amazon') || $carrier === 'amz' || str_contains($carrier, 'amzl')) {
            return 'Amazon';
        }
        if (str_contains($carrier, 'uniuni') || str_contains($carrier, 'uni uni')) {
            return 'UniUni';
        }
        if (str_contains($carrier, 'veeqo')) {
            return 'Veeqo';
        }

        return null;
    }

    /**
     * Carrier comes from the tracking number. Marketplace names such as
     * "Seller Shipping local" are never kept when the number identifies a carrier.
     */
    public static function fill(?string $carrier, ?string $trackingNumber): ?string
    {
        $guess = self::labelFromNumber((string) $trackingNumber);
        if ($guess !== null && $guess !== '') {
            return $guess;
        }

        return self::knownLabel($carrier);
    }
}
