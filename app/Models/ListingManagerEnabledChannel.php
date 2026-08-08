<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingManagerEnabledChannel extends Model
{
    protected $table = 'listing_manager_enabled_channels';

    protected $fillable = [
        'channel_id',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }
}
