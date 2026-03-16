<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferupListingStatus extends Model
{
    protected $table = 'offerup_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
