<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlibabaSheetPrice extends Model
{
    protected $table = 'alibaba_sheet_prices';

    protected $fillable = [
        'product_id',
        'sku',
        'status',
        'sku_price',
        'soh',
        'inv_update',
    ];

    protected $casts = [
        'sku_price' => 'decimal:2',
        'soh' => 'integer',
    ];
}
