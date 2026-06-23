<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Single global checklist item — applies to every team member.
 *
 * Weightage (1–10) is used by the General score formula in
 * {@see \App\Http\Controllers\TaskController::buildGeneralChecklistPayload()}.
 */
class GeneralChecklistItem extends Model
{
    use HasFactory;

    protected $table = 'general_checklist_items';

    protected $fillable = [
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
        return $this->hasMany(UserGeneralChecklistProgress::class, 'general_checklist_item_id');
    }
}
