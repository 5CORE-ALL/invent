<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One checkbox-style checkpoint that lives under a designation R&R item.
 *
 * Weightage (1–10) is the relative importance used by the CL R&R score
 * formula in {@see \App\Http\Controllers\TaskController::buildRRChecklistPayload()}.
 */
class DesignationRrCheckpoint extends Model
{
    use HasFactory;

    protected $table = 'designation_rr_checkpoints';

    protected $fillable = [
        'designation_rr_item_id',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(DesignationRrItem::class, 'designation_rr_item_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserRrCheckpointProgress::class, 'designation_rr_checkpoint_id');
    }
}
