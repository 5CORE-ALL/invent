<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EbayListingStatus extends Model
{
    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array', // Automatically cast JSON to array
    ];
}
