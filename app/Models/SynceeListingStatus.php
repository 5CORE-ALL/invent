<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SynceeListingStatus extends Model
{
    protected $table = 'syncee_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
