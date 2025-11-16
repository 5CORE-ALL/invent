<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AliExpressSheetData extends Model
{
    use HasFactory;

    protected $table = 'aliexpress_sheet_data';

    protected $fillable = [
        'sku',
        'price',
        'aliexpress_l30',
        'aliexpress_l60',
        'views',
        'created_at',
        'updated_at'
    ];
}
