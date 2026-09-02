<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Temu 3 view-data table. Same shape as TemuViewData, separated so uploads
 * for one store do not wipe another store's rows.
 */
class Temu3ViewData extends Model
{
    use HasFactory;

    protected $table = 'temu3_view_data';

    protected $fillable = [
        'id',
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
