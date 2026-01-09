<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaInsightDaily extends Model
{
    use HasFactory;

    protected $table = 'meta_insights_daily';

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'date_start',
        'breakdown_hash',
        'impressions',
        'clicks',
        'reach',
        'spend',
        'ctr',
        'cpc',
        'cpm',
        'cpp',
        'frequency',
        'actions_count',
        'actions',
        'action_values',
        'action_values_breakdown',
        'purchases',
        'purchase_roas',
        'cpa',
        'breakdowns_json',
        'synced_at',
    ];

    protected $casts = [
        'date_start' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'reach' => 'integer',
        'spend' => 'decimal:2',
        'ctr' => 'decimal:4',
        'cpc' => 'decimal:4',
        'cpm' => 'decimal:4',
        'cpp' => 'decimal:4',
        'frequency' => 'decimal:4',
        'actions_count' => 'integer',
        'actions' => 'array',
        'action_values' => 'decimal:2',
        'action_values_breakdown' => 'array',
        'purchases' => 'integer',
        'purchase_roas' => 'decimal:4',
        'cpa' => 'decimal:4',
        'breakdowns_json' => 'array',
        'synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
