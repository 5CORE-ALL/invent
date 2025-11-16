<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YamibuyListingStatus extends Model
{
    protected $table = 'yamibuy_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
