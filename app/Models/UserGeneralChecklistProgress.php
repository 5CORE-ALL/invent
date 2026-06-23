<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's check state on one general (team-wide) checklist item.
 *
 * Upserted from the CL Gen modal. Combined with item weightages to
 * compute the General score per user.
 */
class UserGeneralChecklistProgress extends Model
{
    use HasFactory;

    protected $table = 'user_general_checklist_progress';

    protected $fillable = [
        'user_id',
        'general_checklist_item_id',
        'checked',
        'checked_at',
        'note',
    ];

    protected $casts = [
        'checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(GeneralChecklistItem::class, 'general_checklist_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
