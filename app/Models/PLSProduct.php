<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PLSProduct extends Model
{
    use HasFactory;

    protected $table = 'pls_products';

    protected $fillable = [
        'sku',
        'p_l30',
        'p_l60',
        'price',
    ];
}
