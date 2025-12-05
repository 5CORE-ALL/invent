<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonDatasheet extends Model
{
    use HasFactory;

    protected $table = 'amazon_datsheets';

    protected $fillable = [
        'units_ordered_l30',
        'units_ordered_l60',
        'units_ordered_l90',
        'sessions_l30',
        'sessions_l60',
        'sessions_l90',        
        'asin',
        'sku',
        'price',
        'organic_views',
        'sold',
        'listing_status',
    ];
}
