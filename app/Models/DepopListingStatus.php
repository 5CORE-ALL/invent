<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepopListingStatus extends Model
{
    protected $table = 'depop_listing_statuses';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
