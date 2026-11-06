<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Today's (Pacific day so far) Amazon ad spend, Sponsored Products + Sponsored Brands.
 * Written by amazon:pull-today-ad-spend, read by /all-marketplace-master for T Ads%.
 */
class AmazonTodayAdSpend extends Model
{
    protected $table = 'amazon_today_ad_spend';

    protected $fillable = ['spend_date', 'sp_spend', 'sb_spend', 'total_spend', 'pulled_at'];

    protected $casts = [
        'spend_date' => 'date',
        'sp_spend' => 'float',
        'sb_spend' => 'float',
        'total_spend' => 'float',
        'pulled_at' => 'datetime',
    ];

    public static function pacificToday(): string
    {
        return Carbon::now('America/Los_Angeles')->toDateString();
    }

    /**
     * SP + SB spend last pulled for one Pacific day (the final hourly pull of that day), or null
     * when nothing was pulled for it. Used for Y Ads% while Amazon's finished-day campaign report
     * for that day has not been imported yet.
     */
    public static function spendForDate(string $ymd): ?float
    {
        try {
            if (! Schema::hasTable('amazon_today_ad_spend')) {
                return null;
            }
            $row = static::query()->whereDate('spend_date', $ymd)->first();

            return ($row !== null && $row->pulled_at !== null && (float) $row->total_spend > 0)
                ? (float) $row->total_spend
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Today's pulled spend, or null when nothing has been pulled for the current Pacific day.
     * Also null in the 3 hours when the Eastern date (Today Sales) is already tomorrow while the
     * Pacific ad day is still today, so spend and sales never describe different days.
     */
    public static function currentSpend(): ?float
    {
        try {
            if (Carbon::now('America/New_York')->toDateString() !== self::pacificToday()) {
                return null;
            }
            if (! Schema::hasTable('amazon_today_ad_spend')) {
                return null;
            }
            $row = static::query()->whereDate('spend_date', self::pacificToday())->first();

            return $row !== null && $row->pulled_at !== null ? (float) $row->total_spend : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
