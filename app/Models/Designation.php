<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all categories for this designation
     */
    public function categories()
    {
        return $this->hasMany(ChecklistCategory::class, 'designation_id')->where('is_active', true)->orderBy('order');
    }

    /**
     * Get all categories including inactive
     */
    public function allCategories()
    {
        return $this->hasMany(ChecklistCategory::class, 'designation_id')->orderBy('order');
    }

    /**
     * Get all checklist items through categories
     */
    public function checklistItems()
    {
        return $this->hasManyThrough(
            ChecklistItem::class, 
            ChecklistCategory::class,
            'designation_id', // Foreign key on checklist_categories table
            'category_id'     // Foreign key on checklist_items table
        );
    }

    /**
     * Get all performance reviews for this designation
     */
    public function performanceReviews()
    {
        return $this->hasMany(PerformanceReview::class);
    }
}
