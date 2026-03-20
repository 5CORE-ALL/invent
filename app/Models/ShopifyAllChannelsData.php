<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyAllChannelsData extends Model
{
    use HasFactory;

    protected $table = 'shopify_all_channels_data';

    protected $fillable = [
        'sku',
        'parent',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}


