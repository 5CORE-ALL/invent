<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Last price fed in the "SPRICE $" box of the /price-increase SKU modal (one row per SKU).
 */
class PriceIncreaseBoxSprice extends Model
{
    protected $table = 'price_increase_box_sprices';

    protected $fillable = ['sku', 'sprice', 'updated_by'];

    protected $casts = ['sprice' => 'float'];
}
