<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReverbListingStatus extends Model
{
    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
