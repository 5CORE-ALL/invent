<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifySkuInventoryHistory extends Model
{
    use HasFactory;

    protected $table = 'shopifysku_inventory_history';

    protected $fillable = [
        'sku_id',
        'sku',
        'product_name',
        'opening_inventory',
        'closing_inventory',
        'sold_quantity',
        'restocked_quantity',
        'snapshot_date',
        'pst_start_datetime',
        'pst_end_datetime',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'pst_start_datetime' => 'datetime',
        'pst_end_datetime' => 'datetime',
        'opening_inventory' => 'integer',
        'closing_inventory' => 'integer',
        'sold_quantity' => 'integer',
        'restocked_quantity' => 'integer',
    ];

    public function shopifySku()
    {
        return $this->belongsTo(ShopifySku::class, 'sku_id');
    }
}
