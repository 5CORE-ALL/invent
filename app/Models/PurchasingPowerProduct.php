<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchasingPowerProduct extends Model
{
    use HasFactory;

    protected $table = 'purchasing_power_products';

    protected $fillable = [
        'sku',
        'm_l30',
        'price',
        'stock',
    ];
}
