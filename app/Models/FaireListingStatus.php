<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaireListingStatus extends Model
{
    protected $table = 'faire_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
