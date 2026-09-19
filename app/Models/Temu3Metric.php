<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temu3Metric extends Model
{
    use HasFactory;

    protected $table = 'temu3_metrics';

    protected $fillable = [
        'sku',
        'sku_id',
        'goods_id',
        'base_price',
        'quantity',
        'listing_status',
        'quantity_purchased_l30',
        'quantity_purchased_l60',
        'product_impressions_l30',
        'product_clicks_l30',
        'product_impressions_l60',
        'product_clicks_l60',
    ];
}
