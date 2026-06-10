<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MercariWoShipPriceSoldData extends Model
{
    use HasFactory;

    protected $table = 'mercari_woship_price_sold_data';

    protected $fillable = [
        'sku', 'price', 'sold',
    ];
}
