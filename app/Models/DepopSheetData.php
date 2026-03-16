<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepopSheetData extends Model
{
    protected $table = 'depop_sheet_data';

    protected $fillable = [
        'product_name',
        'size',
        'retail_price',
        'warehouse_stock',
        'sku_code',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'warehouse_stock' => 'integer',
    ];
}
