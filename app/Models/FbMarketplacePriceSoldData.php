<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbMarketplacePriceSoldData extends Model
{
    use HasFactory;

    protected $table = 'fb_marketplace_price_sold_data';

    protected $fillable = [
        'sku', 'price', 'sold',
    ];
}
