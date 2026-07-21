<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MacyCompetitorItem extends Model
{
    use HasFactory;

    protected $table = 'macy_competitor_items';

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

    public function getTotalPriceAttribute()
    {
        return ($this->price ?? 0) + ($this->shipping_cost ?? 0);
    }

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

    public function scopeLowestPrice($query)
    {
        return $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price ASC, position ASC');
    }

    public function scopeHighestPrice($query)
    {
        return $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END, price DESC, position ASC');
    }
}
