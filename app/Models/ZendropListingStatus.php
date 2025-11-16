<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZendropListingStatus extends Model
{
    protected $table = 'zendrop_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
