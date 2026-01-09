<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAdSet extends Model
{
    use HasFactory;

    protected $table = 'meta_adsets';

    protected $fillable = [
        'user_id',
        'ad_account_id',
        'campaign_id',
        'meta_id',
        'name',
        'status',
        'effective_status',
        'optimization_goal',
        'daily_budget',
        'lifetime_budget',
        'budget_remaining',
        'start_time',
        'end_time',
        'billing_event',
        'bid_amount',
        'targeting',
        'meta_updated_time',
        'synced_at',
        'raw_json',
    ];

    protected $casts = [
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
        'budget_remaining' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'meta_updated_time' => 'datetime',
        'synced_at' => 'datetime',
        'targeting' => 'array',
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

    public function ads(): HasMany
    {
        return $this->hasMany(MetaAd::class, 'adset_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(MetaInsightDaily::class, 'entity_id')
            ->where('entity_type', 'adset');
    }
}
