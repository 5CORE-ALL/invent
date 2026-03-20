<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeweggB2CListingStatus extends Model
{
    protected $table = 'newegg_b2c_listing_statuses';
    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
