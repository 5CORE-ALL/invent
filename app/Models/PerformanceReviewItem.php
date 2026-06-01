<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceReviewItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'checklist_item_id',
        'rating',
        'comment',
        'weighted_score',
    ];

    protected $casts = [
        'rating' => 'integer',
        'weighted_score' => 'decimal:2',
    ];

    /**
     * Get the performance review
     */
    public function review()
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }

    /**
     * Get the checklist item
     */
    public function checklistItem()
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }
}
