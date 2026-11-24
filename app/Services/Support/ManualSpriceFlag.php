<?php

namespace App\Services\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * "Hand-set S PRC" flag shared by every channel's rule jobs.
 *
 * The Price Increase modal (SPRICE $ box / ✓ / Channels modal) saves a SPRICE with SPRICE_MANUAL=true in the channel's
 * *DataView JSON. The twice-daily "sprc-dil-auto-push" jobs and the 10-minute "push-blue-prices" catch-up jobs recompute
 * the Dil / Std-prc rule price for every SKU and save + push it — which overwrote the price typed in the modal. Rows
 * flagged manual are left out of those runs; the price keeps what the modal saved (and pushed).
 */
class ManualSpriceFlag
{
    /**
     * @param  class-string<Model>  $modelClass  the channel's *DataView model
     * @return array<string, true> UPPER(TRIM(sku)) => true
     */
    public static function skus(string $modelClass, string $column = 'value'): array
    {
        try {
            /** @var Model $m */
            $m = new $modelClass;
            $table = $m->getTable();
            $rows = DB::table($table)
                ->whereRaw('`'.str_replace('`', '', $column).'` REGEXP ?', ['"SPRICE_MANUAL": ?true'])
                ->pluck('sku');
            $out = [];
            foreach ($rows as $sku) {
                $out[strtoupper(trim((string) $sku))] = true;
            }

            return $out;
        } catch (Throwable $e) {
            return []; // never block the rule jobs because the flag could not be read
        }
    }

    /**
     * Candidate rows without the hand-set SKUs.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  class-string<Model>  $modelClass
     * @return list<array<string, mixed>>
     */
    public static function withoutManual(array $rows, string $modelClass, string $column = 'value'): array
    {
        $manual = self::skus($modelClass, $column);
        if ($manual === []) {
            return $rows;
        }

        return array_values(array_filter($rows, static function ($row) use ($manual) {
            $sku = is_array($row) ? ($row['sku'] ?? null) : null;

            return $sku === null || ! isset($manual[strtoupper(trim((string) $sku))]);
        }));
    }
}
