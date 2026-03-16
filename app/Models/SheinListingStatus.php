<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SheinListingStatus extends Model
{
    protected $table = 'shein_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
