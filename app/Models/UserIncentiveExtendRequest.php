<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIncentiveExtendRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const TYPE_EXTEND = 'extend';

    public const TYPE_END = 'end';

    public const TYPE_HELP = 'help';

    protected $fillable = [
        'user_incentive_id',
        'user_id',
        'requested_by_user_id',
        'current_cutoff',
        'request_type',
        'requested_cutoff',
        'reason',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'requested_cutoff' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function incentive(): BelongsTo
    {
        return $this->belongsTo(UserIncentive::class, 'user_incentive_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
