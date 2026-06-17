<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FollowUp extends Model
{
    public const TYPE_CALL = 'call';

    public const TYPE_EMAIL = 'email';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_SMS = 'sms';

    public const TYPE_OTHER = 'other';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_POSTPONED = 'postponed';

    public const STATUS_CANCELLED = 'cancelled';

    public const OUTCOME_INTERESTED = 'interested';

    public const OUTCOME_NOT_INTERESTED = 'not_interested';

    public const OUTCOME_CALLBACK = 'callback';

    public const OUTCOME_CONVERTED = 'converted';

    protected $fillable = [
        'customer_id',
        'shopify_order_id',
        'company_id',
        'assigned_user_id',
        'title',
        'description',
        'follow_up_type',
        'priority',
        'status',
        'scheduled_at',
        'reminder_at',
        'next_follow_up_at',
        'outcome',
        'reminder_notified_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'reminder_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'reminder_notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Sales / marketing rep assigned to this follow-up (users.id via assigned_user_id).
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Alias for {@see assignedUser()} — same underlying assigned_user_id → users.id.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(FollowUpStatusHistory::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Order: high → medium → low (portable across MySQL / SQLite / PostgreSQL).
     */
    public function scopeOrderByPriority($query)
    {
        return $query->orderByRaw(
            "CASE follow_ups.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END"
        );
    }

    public function scopeOverdue($query)
    {
        return $query->pending()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()]);
    }

    /**
     * Scheduled within the current calendar day (any status). Combine with {@see scopePending} for “today’s queue”.
     */
    public function scopeScheduledToday($query)
    {
        return $query->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()]);
    }

    /**
     * Pending follow-ups scheduled for today.
     */
    public function scopePendingScheduledToday($query)
    {
        return $query->pending()->scheduledToday();
    }

    /**
     * Follow-ups whose reminder time has passed and have not been reminded yet.
     */
    public function scopeReminderDue($query)
    {
        return $query->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', now())
            ->whereNull('reminder_notified_at')
            ->where('status', self::STATUS_PENDING);
    }
}
