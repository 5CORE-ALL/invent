<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Main Shopify store catalog product (5-core / services.shopify).
 */
class ShopifyProduct extends ShopifyCatalogProduct
{
    public const STORE = 'main';

    protected static function booted(): void
    {
        static::addGlobalScope('store_main', function (Builder $builder) {
            $builder->where('store', self::STORE);
        });

        static::creating(function (ShopifyProduct $model) {
            if ($model->store === null || $model->store === '') {
                $model->store = self::STORE;
            }
        });
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ShopifyVariant::class, 'shopify_catalog_product_id');
    }
}
