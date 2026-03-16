<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebay3Metric extends Model
{
    use HasFactory;

    protected $table = 'ebay_3_metrics';

    protected $fillable = [
        'item_id',
        'sku',
        'ebay_title',
        'ebay_link',
        'ebay_l30',
        'ebay_l60',
        'ebay_l7',
        'ebay_price',
        'ebay_stock',
        'views',
        'l7_views',
        'price_lmpa',
        'lmp_link',
        'lmp_data',
    ];

    protected $casts = [
        'lmp_data' => 'array',
    ];
}
