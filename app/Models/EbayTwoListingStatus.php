<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EbayTwoListingStatus extends Model
{
    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
