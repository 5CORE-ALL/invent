<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal base for synced Shopify Admin API variants (main + PLS).
 */
class ShopifyCatalogVariant extends Model
{
    protected $table = 'shopify_catalog_variants';

    protected $fillable = [
        'store',
        'shopify_catalog_product_id',
        'shopify_variant_id',
        'shopify_product_id',
        'sku',
        'variant_title',
        'price',
        'position',
        'inventory_quantity',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'price' => 'float',
    ];

    public function catalogProduct(): BelongsTo
    {
        return $this->belongsTo(ShopifyCatalogProduct::class, 'shopify_catalog_product_id');
    }
}
