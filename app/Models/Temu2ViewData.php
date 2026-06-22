<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Temu 2 view-data table. Same shape as TemuViewData, separated so that
 * uploading a view-data Excel for one store doesn't wipe the other store's
 * rows (the upload handler does a delete-all + insert).
 */
class Temu2ViewData extends Model
{
    use HasFactory;

    protected $table = 'temu2_view_data';

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
