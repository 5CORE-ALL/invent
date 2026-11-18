<?php

namespace App\Support;

use App\Models\EbaySkuDailyData;
use App\Models\ShopifySku;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * LT O CVR (%) for eBay, the same measure as Amazon's LT O CVR: overall CVR over the last 60 days.
 *
 *   (units L30 + units L31-60) ÷ (views L30 + views L31-60) × 100
 *
 * eBay stores only a rolling 30-day views number, so the views of the prior 30 days are the `views` value saved
 * in ebay_sku_daily_data 30 days ago. Null when that snapshot is missing or there are no views.
 */
final class EbayLtoCvr
{
    /** How many days before the target day a snapshot may sit (a missed collect day). */
    private const SNAPSHOT_TOLERANCE_DAYS = 3;

    /**
     * Rolling-30-day views as saved ~30 days ago, keyed by normalized SKU.
     * One query for all SKUs: only 4 daily snapshots are read.
     *
     * @return array<string, float>
     */
    public static function priorViewsBySku(): array
    {
        $target = Carbon::now('America/Los_Angeles')->subDays(30)->startOfDay();
        $out = [];
        $day = [];
        try {
            EbaySkuDailyData::query()
                ->whereBetween('record_date', [
                    $target->copy()->subDays(self::SNAPSHOT_TOLERANCE_DAYS)->toDateString(),
                    $target->toDateString(),
                ])
                ->orderBy('record_date')
                ->get(['sku', 'record_date', 'daily_data'])
                ->each(static function ($rec) use (&$out, &$day): void {
                    $data = is_array($rec->daily_data)
                        ? $rec->daily_data
                        : (json_decode((string) ($rec->daily_data ?? ''), true) ?: []);
                    if (! array_key_exists('views', $data) || ! is_numeric($data['views'])) {
                        return;
                    }
                    $key = ShopifySku::normalizeSkuForShopifyLookup((string) $rec->sku);
                    if ($key === '') {
                        return;
                    }
                    // Ordered by date, so the latest snapshot on or before the target wins.
                    $out[$key] = (float) $data['views'];
                    $day[$key] = (string) $rec->record_date;
                });
        } catch (\Throwable $e) {
            Log::warning('eBay LT O CVR: prior views lookup failed', ['error' => $e->getMessage()]);

            return [];
        }

        return $out;
    }

    /**
     * @return float|null  null when the prior-period views are unknown or both periods have no views
     */
    public static function compute(float $unitsL30, float $unitsL60, float $viewsL30, ?float $viewsPrior): ?float
    {
        if ($viewsPrior === null) {
            return null;
        }
        $views = $viewsL30 + $viewsPrior;
        if ($views <= 0) {
            return null;
        }
        $n = (($unitsL30 + $unitsL60) / $views) * 100;

        return is_finite($n) ? round($n, 2) : null;
    }
}
