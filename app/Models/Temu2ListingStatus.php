<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temu2ListingStatus extends Model
{
    protected $table = 'temu2_listing_statuses';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
