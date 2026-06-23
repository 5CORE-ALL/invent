<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One CL Mgr (manager / senior-level) checklist item, per designation.
 *
 * Weightage (1–10) drives the manager's own CL Mgr score; the combined
 * Mgr score formula additionally blends in the manager's juniors' scores
 * — see {@see \App\Http\Controllers\TaskController::buildMgrChecklistPayload()}.
 */
class DesignationMgrCheckpoint extends Model
{
    use HasFactory;

    protected $table = 'designation_mgr_checkpoints';

    protected $fillable = [
        'designation',
        'category',
        'title',
        'description',
        'weightage',
        'sort_order',
        'source',
        'created_by',
    ];

    protected $casts = [
        'weightage' => 'integer',
        'sort_order' => 'integer',
    ];

    public function progress(): HasMany
    {
        return $this->hasMany(UserMgrCheckpointProgress::class, 'designation_mgr_checkpoint_id');
    }
}
