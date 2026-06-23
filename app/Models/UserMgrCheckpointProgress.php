<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's check state on one CL Mgr checkpoint.
 *
 * Upserted from the CL Mgr modal every time a checkbox is toggled.
 */
class UserMgrCheckpointProgress extends Model
{
    use HasFactory;

    protected $table = 'user_mgr_checkpoint_progress';

    protected $fillable = [
        'user_id',
        'designation_mgr_checkpoint_id',
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
        return $this->belongsTo(DesignationMgrCheckpoint::class, 'designation_mgr_checkpoint_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
