<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BestbuyUsaProduct extends Model
{
    use HasFactory;

    protected $table = 'bestbuy_usa_products';

    protected $fillable = [
        'sku',
        'm_l30',
        'm_l60',
        'price',
    ];
}
