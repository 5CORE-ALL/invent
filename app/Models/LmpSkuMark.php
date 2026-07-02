<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LmpSkuMark extends Model
{
    protected $fillable = [
        'sku',
        'sku_norm',
        'm',
        'updated_by',
    ];
}
