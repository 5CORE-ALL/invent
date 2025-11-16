<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BestbuyUSADataView extends Model
{
    protected $table = 'bestbuy_usa_data_views';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
