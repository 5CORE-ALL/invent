<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CjdropshippingListingStatus extends Model
{
    protected $table = 'cjdropshipping_listing_statuses';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
