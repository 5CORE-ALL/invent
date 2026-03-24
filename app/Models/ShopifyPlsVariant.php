<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProLightSounds (PLS) Shopify variant row.
 */
class ShopifyPlsVariant extends ShopifyCatalogVariant
{
    public const STORE = 'pls';

    protected static function booted(): void
    {
        static::addGlobalScope('store_pls', function (Builder $builder) {
            $builder->where('store', self::STORE);
        });

        static::creating(function (ShopifyPlsVariant $model) {
            if ($model->store === null || $model->store === '') {
                $model->store = self::STORE;
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopifyPlsProduct::class, 'shopify_catalog_product_id');
    }
}
