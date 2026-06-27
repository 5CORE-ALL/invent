<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Same shape as {@see TemuViewData}; stores the last-7-day views upload
 * separately so the replace-all upload on either table doesn't wipe the
 * other. The /temu-decrease page reads both and computes "L7 vs L30 %"
 * (= L7 daily-avg ÷ L30 daily-avg × 100) per goods_id.
 */
class TemuViewDataL7 extends Model
{
    use HasFactory;

    protected $table = 'temu_view_data_l7';

    protected $fillable = [
        'date',
        'goods_id',
        'goods_name',
        'product_impressions',
        'visitor_impressions',
        'product_clicks',
        'visitor_clicks',
        'ctr',
    ];

    protected $casts = [
        'date' => 'date',
        'product_impressions' => 'integer',
        'visitor_impressions' => 'integer',
        'product_clicks' => 'integer',
        'visitor_clicks' => 'integer',
        'ctr' => 'decimal:2',
    ];
}
