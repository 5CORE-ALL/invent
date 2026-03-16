<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaCampaign extends Model
{
    use HasFactory;

    protected $table = 'meta_campaigns';

    protected $fillable = [
        'user_id',
        'ad_account_id',
        'meta_id',
        'ad_type',
        'group',
        'parent',
        'name',
        'status',
        'effective_status',
        'objective',
        'daily_budget',
        'lifetime_budget',
        'budget_remaining',
        'start_time',
        'stop_time',
        'buying_type',
        'bid_strategy',
        'special_ad_categories',
        'meta_updated_time',
        'synced_at',
        'raw_json',
    ];

    protected $casts = [
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
        'budget_remaining' => 'decimal:2',
        'start_time' => 'datetime',
        'stop_time' => 'datetime',
        'meta_updated_time' => 'datetime',
        'synced_at' => 'datetime',
        'special_ad_categories' => 'array',
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

    public function adsets(): HasMany
    {
        return $this->hasMany(MetaAdSet::class, 'campaign_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(MetaAd::class, 'campaign_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaInsightDaily::class, 'entity_id')
            ->where('entity_type', 'campaign');
    }
}
