<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Internal base for synced Shopify Admin API products (main + PLS).
 */
class ShopifyCatalogProduct extends Model
{
    protected $table = 'shopify_catalog_products';

    protected $fillable = [
        'store',
        'shopify_id',
        'title',
        'handle',
        'status',
        'body_html',
        'vendor',
        'product_type',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function catalogVariants(): HasMany
    {
        return $this->hasMany(ShopifyCatalogVariant::class, 'shopify_catalog_product_id');
    }
}
