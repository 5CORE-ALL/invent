<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialMediaAccount extends Model
{
    protected $fillable = [
        'platform',
        'external_account_id',
        'account_name',
        'username',
        'profile_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'status',
        'sync_enabled',
        'last_synced_at',
        'last_sync_status',
        'last_sync_error',
        'user_id',
        'created_by',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'sync_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialMediaPost::class);
    }

    public function accountMetrics(): HasMany
    {
        return $this->hasMany(SocialMediaAccountMetric::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SocialMediaSyncLog::class);
    }

    public function tokenStatus(): string
    {
        if (! $this->access_token) {
            return 'missing';
        }
        if ($this->token_expires_at && $this->token_expires_at->isPast()) {
            return 'expired';
        }

        return 'valid';
    }
}
