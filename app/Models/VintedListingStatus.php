<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VintedListingStatus extends Model
{
    protected $table = 'vinted_listing_statuses';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
