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
     * Today's pulled spend, or null when nothing has been pulled for the current Pacific day.
     */
    public static function currentSpend(): ?float
    {
        try {
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
