<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\Schema;

final class EbayListingEnded
{
    public const STATUSES = ['ENDED', 'INACTIVE', 'UNSOLD', 'COMPLETED', 'SOLD'];

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public static function withStatusColumn(string $table, array $columns): array
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'listing_status')) {
            $columns[] = 'listing_status';
        }

        return $columns;
    }

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

    /**
     * A child SKU can exist on an old ended item_id and a newer live relist.
     * Prefer the live row so the tabulator / price push do not follow the ended one.
     *
     * @param  iterable<int, object>  $metrics
     */
    public static function preferLiveMetric(iterable $metrics): ?object
    {
        $best = null;
        $bestScore = -1;
        $bestId = null;

        foreach ($metrics as $metric) {
            if (! is_object($metric)) {
                continue;
            }
            $itemId = trim((string) ($metric->item_id ?? ''));
            if ($itemId === '') {
                continue;
            }
            $status = strtoupper(trim((string) ($metric->listing_status ?? '')));
            $ended = self::isEnded($status);
            $unknown = $status === '';
            // ACTIVE (2) beats unknown (1) beats ENDED (0); newer row wins ties.
            $score = $ended ? 0 : ($unknown ? 1 : 2);
            $id = (int) ($metric->id ?? 0);
            if ($best === null
                || $score > $bestScore
                || ($score === $bestScore && $id > $bestId)
            ) {
                $best = $metric;
                $bestScore = $score;
                $bestId = $id;
            }
        }

        return $best;
    }

    /**
     * @param  class-string  $metricClass
     */
    public static function preferredRow(string $metricClass, string $sku): ?object
    {
        $sku = trim($sku);
        if ($sku === '' || ! class_exists($metricClass)) {
            return null;
        }

        try {
            $rows = $metricClass::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->orderBy('id')
                ->get();
        } catch (\Throwable) {
            return null;
        }

        return self::preferLiveMetric($rows);
    }
}
