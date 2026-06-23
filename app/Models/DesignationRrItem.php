<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One R&R bullet (template) attached to a designation.
 *
 * The Task Summary R&R modal shows the list of these rows for the user's
 * designation. The first time a designation has zero rows we ask the AI
 * to seed them via {@see \App\Http\Controllers\TaskController::generateDesignationRR()}.
 */
class DesignationRrItem extends Model
{
    use HasFactory;

    protected $table = 'designation_rr_items';

    protected $fillable = [
        'designation',
        'title',
        'description',
        'sort_order',
        'source',
        'created_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function progress(): HasMany
    {
        return $this->hasMany(UserRrProgress::class, 'designation_rr_item_id');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(DesignationRrCheckpoint::class, 'designation_rr_item_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
