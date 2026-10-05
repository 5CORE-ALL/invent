<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temu3ListingStatus extends Model
{
    protected $table = 'temu3_listing_statuses';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
