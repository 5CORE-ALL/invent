<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpocketListingStatus extends Model
{
    protected $table = 'spocket_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
