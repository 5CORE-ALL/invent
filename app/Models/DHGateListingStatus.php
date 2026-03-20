<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DHGateListingStatus extends Model
{
    protected $table = 'dhgate_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
