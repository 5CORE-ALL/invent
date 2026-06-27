<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Same shape as {@see TemuViewData} and {@see TemuViewDataL7}; stores the
 * prior-week views upload (days 8–14 back) separately so the replace-all
 * upload on any single window doesn't wipe the others.
 *
 * Lets /temu-decrease compute week-over-week views deltas (L7 vs L7-to-L14)
 * alongside the existing L7-vs-L30 pace metric.
 */
class TemuViewDataL7ToL14 extends Model
{
    use HasFactory;

    protected $table = 'temu_view_data_l7_to_l14';

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
