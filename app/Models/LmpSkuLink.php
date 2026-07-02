<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LmpSkuLink extends Model
{
    protected $fillable = [
        'sku',
        'linked_sku',
        'sku_norm',
        'linked_sku_norm',
        'updated_by',
    ];
}
