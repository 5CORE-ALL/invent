<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingManagerChannelDraft extends Model
{
    protected $table = 'listing_manager_channel_drafts';

    protected $fillable = [
        'channel_id',
        'seller_sku',
        'asin',
        'title',
        'thumbnail_image',
        'price',
        'quantity',
        'status',
        'external_listing_id',
        'amazon_snapshot',
        'listing_details',
        'created_by',
        'listed_at',
        'publish_checked_at',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'amazon_snapshot' => 'array',
        'listing_details' => 'array',
        'listed_at' => 'datetime',
        'publish_checked_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }
}
