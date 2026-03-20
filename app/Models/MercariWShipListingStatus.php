<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MercariWShipListingStatus extends Model
{
    protected $table = 'mercari_w_ship_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
