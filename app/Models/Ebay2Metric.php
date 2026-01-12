<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebay2Metric extends Model
{
    use HasFactory;

    protected $table = 'ebay_2_metrics';
    
    protected $fillable = [
        'item_id',
        'sku',
        'ebay_l30',
        'ebay_l60',
        'ebay_l7',
        'ebay_price',
        'views',
        'l7_views',
        'report_range',
        'ebay_stock'
    ];
}
