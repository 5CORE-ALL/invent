<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoshmarkListingStatus extends Model
{
    protected $table = 'poshmark_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
