<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
     * Sync live listing price/shipping into search-cache rows.
     * Avoids unique (search_query, item_id) collisions that used to break LMP pull.
     *
     * @param  array{price?: mixed, shipping_cost?: mixed, link?: ?string, title?: ?string, image?: ?string}  $live
     */
    public static function syncLiveListingData(string $listingId, ?string $originalItemId, array $live): void
    {
        $ids = array_values(array_unique(array_filter([
            (string) $listingId,
            $originalItemId !== null && $originalItemId !== '' ? (string) $originalItemId : null,
        ])));

        if ($ids === []) {
            return;
        }

        static::whereIn('item_id', $ids)->get()->each(function (self $row) use ($listingId, $live) {
            $payload = [
                'price' => $live['price'] ?? $row->price,
                'shipping_cost' => $live['shipping_cost'] ?? $row->shipping_cost,
                'link' => $live['link'] ?? $row->link,
                'title' => $live['title'] ?? $row->title,
                'image' => $live['image'] ?? $row->image,
            ];

            if ((string) $row->item_id !== (string) $listingId) {
                $conflict = static::where('search_query', $row->search_query)
                    ->where('item_id', $listingId)
                    ->where('id', '!=', $row->id)
                    ->exists();
                if (! $conflict) {
                    $payload['item_id'] = $listingId;
                }
            }

            try {
                $row->update($payload);
            } catch (\Throwable $e) {
                Log::warning('EbayCompetitorItem::syncLiveListingData skipped row', [
                    'id' => $row->id,
                    'listing_id' => $listingId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

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
