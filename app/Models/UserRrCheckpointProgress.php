<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's check state on a single CL R&R checkpoint.
 *
 * Upserted from the CL R&R modal every time the user toggles a checkbox.
 * Combined with checkpoint weightages to compute per-item and overall
 * scores in {@see \App\Http\Controllers\TaskController::buildRRChecklistPayload()}.
 */
class UserRrCheckpointProgress extends Model
{
    use HasFactory;

    protected $table = 'user_rr_checkpoint_progress';

    protected $fillable = [
        'user_id',
        'designation_rr_checkpoint_id',
        'checked',
        'checked_at',
        'note',
    ];

    protected $casts = [
        'checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(DesignationRrCheckpoint::class, 'designation_rr_checkpoint_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
