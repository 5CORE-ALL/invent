<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MmInventoryLedger extends Model
{
    protected $table = 'mm_inventory_ledgers';

    protected $fillable = [
        'store',
        'sku',
        'shopify_variant_id',
        'shopify_inventory_item_id',
        'location_id',
        'on_hand',
        'available',
        'version',
        'source',
        'synced_at',
    ];

    protected $casts = [
        'on_hand' => 'integer',
        'available' => 'integer',
        'version' => 'integer',
        'synced_at' => 'datetime',
    ];
}
