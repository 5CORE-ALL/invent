<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EbayMetric extends Model
{
    use HasFactory;

    protected $table = 'ebay_metrics';

    protected $fillable = [
        'item_id',
        'sku',
        'ebay_price',
        'ebay_l30',
        'ebay_l60',
        'ebay_l7',
        'views',
        'l7_views',
        'organic_clicks',
        'price_lmpa',
        'report_date',
        'listing_status',
    ];

}
