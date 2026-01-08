<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MacysPriceData extends Model
{
    use HasFactory;

    protected $table = 'macys_price_data';

    protected $fillable = [
        'sku',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
