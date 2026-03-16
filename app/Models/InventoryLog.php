<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    protected $fillable = [
        'sku',
        'old_qty',
        'new_qty',
        'qty_change',
        'change_source',
        'batch_id',
        'notes',
        'pushed_to_shopify',
        'shopify_pushed_at',
        'shopify_error',
        'created_by',
    ];

    protected $casts = [
        'pushed_to_shopify' => 'boolean',
        'shopify_pushed_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(InventoryImportBatch::class, 'batch_id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'sku', 'sku');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markPushedToShopify()
    {
        $this->update([
            'pushed_to_shopify' => true,
            'shopify_pushed_at' => now(),
            'shopify_error' => null,
        ]);
    }

    public function markShopifyError($error)
    {
        $this->update([
            'pushed_to_shopify' => false,
            'shopify_error' => $error,
        ]);
    }
}
