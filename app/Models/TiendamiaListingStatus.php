<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiendamiaListingStatus extends Model
{
    protected $table = 'tiendamia_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];
}
