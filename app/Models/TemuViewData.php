<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuViewData extends Model
{
    use HasFactory;

    protected $table = 'temu_view_data';

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
