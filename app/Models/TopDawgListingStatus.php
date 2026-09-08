<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopDawgListingStatus extends Model
{
    protected $table = 'topdawg_listing_statuses';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
