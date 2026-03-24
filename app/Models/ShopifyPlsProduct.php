<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProLightSounds (PLS / wholesale) Shopify catalog product.
 */
class ShopifyPlsProduct extends ShopifyCatalogProduct
{
    public const STORE = 'pls';

    protected static function booted(): void
    {
        static::addGlobalScope('store_pls', function (Builder $builder) {
            $builder->where('store', self::STORE);
        });

        static::creating(function (ShopifyPlsProduct $model) {
            if ($model->store === null || $model->store === '') {
                $model->store = self::STORE;
            }
        });
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ShopifyPlsVariant::class, 'shopify_catalog_product_id');
    }
}
