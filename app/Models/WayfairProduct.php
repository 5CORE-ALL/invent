<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WayfairProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku', 
        'po_number', 
        'purchase_order_data'
    ];

    protected $casts = [
        'purchase_order_data' => 'array',
    ];
}
