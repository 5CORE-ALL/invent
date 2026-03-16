<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MacysListingStatus extends Model
{
    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
