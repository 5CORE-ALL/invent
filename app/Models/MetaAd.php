<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaAd extends Model
{
    use HasFactory;

    protected $table = 'meta_ads';

    protected $fillable = [
        'user_id',
        'ad_account_id',
        'campaign_id',
        'adset_id',
        'meta_id',
        'name',
        'status',
        'effective_status',
        'creative_id',
        'preview_shareable_link',
        'meta_updated_time',
        'synced_at',
        'raw_json',
    ];

    protected $casts = [
        'meta_updated_time' => 'datetime',
        'synced_at' => 'datetime',
        'raw_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'ad_account_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaCampaign::class, 'campaign_id');
    }

    public function adset(): BelongsTo
    {
        return $this->belongsTo(MetaAdSet::class, 'adset_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaInsightDaily::class, 'entity_id')
            ->where('entity_type', 'ad');
    }
}
