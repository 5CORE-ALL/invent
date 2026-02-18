<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EbayCompetitorItem extends Model
{
    use HasFactory;

    protected $table = 'ebay_competitor_items';

    protected $fillable = [
        'marketplace',
        'search_query',
        'item_id',
        'link',
        'title',
        'price',
        'condition',
        'seller_name',
        'seller_rating',
        'position',
        'image',
        'shipping_cost',
        'location',
    ];

    /**
     * Get the total price (price + shipping cost)
     * Only used when explicitly needed
     *
     * @return float
     */
    public function getTotalPriceAttribute()
    {
        return ($this->price ?? 0) + ($this->shipping_cost ?? 0);
    }

    /**
     * Scope to filter by price range (item price only)
     */
    public function scopePriceRange($query, $min = null, $max = null)
    {
        if ($min !== null) {
            $query->where('price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('price', '<=', $max);
        }
        return $query;
    }

    /**
     * Scope to sort by lowest price (nulls last)
     */
    public function scopeLowestPrice($query)
    {
        return $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price ASC, position ASC');
    }

    /**
     * Scope to sort by highest price (nulls last)
     */
    public function scopeHighestPrice($query)
    {
        return $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price DESC, position ASC');
    }
}
