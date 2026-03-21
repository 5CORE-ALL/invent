<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'question',
        'weight',
        'order',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns this item
     */
    public function category()
    {
        return $this->belongsTo(ChecklistCategory::class, 'category_id');
    }

    /**
     * Get all performance review items for this checklist item
     */
    public function reviewItems()
    {
        return $this->hasMany(PerformanceReviewItem::class, 'checklist_item_id');
    }
}
