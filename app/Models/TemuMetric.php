<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'sku_id',
        'quantity_purchased_l60',
        'quantity_purchased_l30',
        'goods_id',
        'base_price',
        'product_impressions_l30',
        'product_clicks_l30',
        'product_impressions_l60',
        'product_clicks_l60',
        'temu_sheet_price',
        'ad_sold',
        'organic_views',
    ];
}
