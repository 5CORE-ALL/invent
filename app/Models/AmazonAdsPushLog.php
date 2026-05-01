<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmazonAdsPushLog extends Model
{
    protected $table = 'amazon_ads_push_logs';

    protected $fillable = [
        'push_type',
        'campaign_id',
        'campaign_name',
        'value',
        'status',
        'reason',
        'request_data',
        'response_data',
        'http_status',
        'source',
        'user_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'request_data' => 'array',
        'response_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who initiated the push
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for failed/skipped records
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'skipped']);
    }

    /**
     * Scope for specific push type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('push_type', $type);
    }

    /**
     * Scope for recent records
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get human-readable push type
     */
    public function getPushTypeNameAttribute(): string
    {
        return match($this->push_type) {
            'sp_sbid' => 'SP Bid (Sponsored Products)',
            'sb_sbid' => 'SB Bid (Sponsored Brands)',
            'sp_sbgt' => 'SP Budget (Sponsored Products)',
            'sb_sbgt' => 'SB Budget (Sponsored Brands)',
            default => $this->push_type,
        };
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'success' => 'success',
            'skipped' => 'warning',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Log a push attempt
     */
    public static function logPush(array $data): self
    {
        return self::create([
            'push_type' => $data['push_type'],
            'campaign_id' => $data['campaign_id'] ?? null,
            'campaign_name' => $data['campaign_name'] ?? null,
            'value' => $data['value'] ?? null,
            'status' => $data['status'] ?? 'skipped',
            'reason' => $data['reason'] ?? null,
            'request_data' => $data['request_data'] ?? null,
            'response_data' => $data['response_data'] ?? null,
            'http_status' => $data['http_status'] ?? null,
            'source' => $data['source'] ?? 'web',
            'user_id' => $data['user_id'] ?? auth()->id(),
        ]);
    }

    /**
     * Log batch push results
     */
    public static function logBatch(string $pushType, array $results, string $source = 'web'): void
    {
        $logs = [];
        $userId = auth()->id();
        $now = now();

        foreach ($results as $result) {
            $logs[] = [
                'push_type' => $pushType,
                'campaign_id' => $result['campaign_id'] ?? null,
                'campaign_name' => $result['campaign_name'] ?? null,
                'value' => $result['value'] ?? $result['bid'] ?? $result['sbgt'] ?? null,
                'status' => $result['status'] ?? 'skipped',
                'reason' => $result['reason'] ?? null,
                'request_data' => json_encode($result['request_data'] ?? []),
                'response_data' => json_encode($result['response_data'] ?? []),
                'http_status' => $result['http_status'] ?? null,
                'source' => $source,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($logs)) {
            self::insert($logs);
        }
    }

    /**
     * Get statistics for a date range
     */
    public static function getStats(string $startDate = null, string $endDate = null): array
    {
        $query = self::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $success = (clone $query)->where('status', 'success')->count();
        $skipped = (clone $query)->where('status', 'skipped')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        return [
            'total' => $total,
            'success' => $success,
            'skipped' => $skipped,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'skip_rate' => $total > 0 ? round(($skipped / $total) * 100, 2) : 0,
            'fail_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];
    }
}
