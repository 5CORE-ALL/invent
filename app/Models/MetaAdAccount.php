<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaAdAccount extends Model
{
    use HasFactory;

    protected $table = 'meta_ad_accounts';

    protected $fillable = [
        'user_id',
        'meta_id',
        'account_id',
        'name',
        'account_status',
        'currency',
        'timezone_name',
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

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaCampaign::class, 'ad_account_id');
    }
}
