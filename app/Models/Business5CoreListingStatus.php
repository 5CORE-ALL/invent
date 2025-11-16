<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business5CoreListingStatus extends Model
{
    protected $table = 'business5core_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
