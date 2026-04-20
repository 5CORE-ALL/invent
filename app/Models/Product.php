<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read model for product_master rows (SKU image manager and similar features).
 * Business logic still uses ProductMaster where appropriate.
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_master';

    public function skuImages(): HasMany
    {
        return $this->hasMany(SkuImage::class, 'product_id');
    }
}
