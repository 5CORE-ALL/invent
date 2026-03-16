<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeweggB2BListingStatus extends Model
{
    protected $table = 'newegg_b2b_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
