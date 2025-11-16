<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercariWoShipListingStatus extends Model
{
    protected $table = 'mercari_wo_ship_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
