<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temu2Metric extends Model
{
    use HasFactory;

    protected $table = 'temu2_metrics';

    protected $fillable = [
        'id',
        'sku',
        'sku_id',
        'goods_id',
        'base_price',
        'quantity',
        'quantity_purchased_l30',
        'quantity_purchased_l60',
        'recommended_base_price',
        'product_impressions_l30',
        'product_clicks_l30',
        'product_clicks_l7',
        'product_clicks_l1',
        'product_impressions_l60',
        'product_clicks_l60',
        'bullet_points',
        'goods_summary',
        'goods_desc',
        'description_master',
        'image_urls',
        'image_master_json',
        'video_master_json',
    ];
}
