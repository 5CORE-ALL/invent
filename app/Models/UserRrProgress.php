<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's progress on a designation-level R&R item.
 *
 * One row per (user, item). Updated/created from the Task Summary R&R
 * modal whenever a user toggles status or saves a note.
 */
class UserRrProgress extends Model
{
    use HasFactory;

    protected $table = 'user_rr_progress';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
    ];

    protected $fillable = [
        'user_id',
        'designation_rr_item_id',
        'status',
        'note',
        'done_at',
    ];

    protected $casts = [
        'done_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(DesignationRrItem::class, 'designation_rr_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
