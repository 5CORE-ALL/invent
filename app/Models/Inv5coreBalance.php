<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inv5coreBalance extends Model
{
    protected $table = 'inv_5core_balances';

    protected $fillable = [
        'product_master_id',
        'sku',
        'sku_compact',
        'opening_qty',
        'opening_seeded_at',
        'sales_after_order_id',
        'sales_after_manual_id',
        'qty_on_hand',
        'l30_sold',
        'shopify_locked',
    ];

    protected $casts = [
        'opening_qty' => 'float',
        'qty_on_hand' => 'float',
        'l30_sold' => 'float',
        'shopify_locked' => 'boolean',
        'opening_seeded_at' => 'datetime',
        'sales_after_order_id' => 'integer',
        'sales_after_manual_id' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Inv5coreTransaction::class, 'balance_id');
    }
}
