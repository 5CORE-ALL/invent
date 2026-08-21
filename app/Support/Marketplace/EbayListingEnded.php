<?php

namespace App\Support\Marketplace;

final class EbayListingEnded
{
    public const STATUSES = ['ENDED', 'INACTIVE', 'UNSOLD', 'COMPLETED', 'SOLD'];

    public static function isEnded(?string $status): bool
    {
        return in_array(strtoupper(trim((string) $status)), self::STATUSES, true);
    }

    /**
     * @return array{listing_status: string, listing_ended: bool}
     */
    public static function fields(?object $metric): array
    {
        $status = strtoupper(trim((string) ($metric->listing_status ?? '')));

        return [
            'listing_status' => $status,
            'listing_ended' => self::isEnded($status),
        ];
    }

    public static function looksEndedError(?string $message): bool
    {
        $blob = strtolower((string) $message);

        return str_contains($blob, '#291')
            || str_contains($blob, 'error 291')
            || str_contains($blob, 'ended listing')
            || str_contains($blob, 'revise ended');
    }
}
