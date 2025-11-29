<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business5CoreProduct extends Model
{
    use HasFactory;

    protected $table = 'business_5core_products';

    protected $fillable = [
        'sku',
        'b5c_l30',
        'b5c_l60',
        'price',
    ];
}
