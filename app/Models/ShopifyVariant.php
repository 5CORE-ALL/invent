<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Main Shopify store variant row (synced from Admin API).
 */
class ShopifyVariant extends ShopifyCatalogVariant
{
    public const STORE = 'main';

    protected static function booted(): void
    {
        static::addGlobalScope('store_main', function (Builder $builder) {
            $builder->where('store', self::STORE);
        });

        static::creating(function (ShopifyVariant $model) {
            if ($model->store === null || $model->store === '') {
                $model->store = self::STORE;
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopifyProduct::class, 'shopify_catalog_product_id');
    }
}
