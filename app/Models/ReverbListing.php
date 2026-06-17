<?php

namespace App\Models;

/**
 * Canonical Reverb listing row (same table as {@see ReverbProduct}).
 * Use this when code expects a "listing" shape (listing_id, title, description, status).
 */
class ReverbListing extends ReverbProduct
{
    protected $table = 'reverb_products';

    protected $appends = [
        'listing_id',
        'title',
        'status',
    ];

    protected $fillable = [
        'sku',
        'reverb_listing_id',
        'listing_state',
        'product_title',
        'description',
        'last_synced_at',
        'last_shopify_qty',
        'r_l30',
        'r_l60',
        'price',
        'views',
        'remaining_inventory',
        'bump_bid',
        'recommended_bid',
        'status',
    ];

    public function getListingIdAttribute(): ?string
    {
        $v = $this->attributes['reverb_listing_id'] ?? null;

        return $v !== null && $v !== '' ? (string) $v : null;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->attributes['product_title'] ?? null;
    }

    public function getStatusAttribute(): ?string
    {
        return $this->attributes['listing_state'] ?? $this->attributes['status'] ?? null;
    }
}
