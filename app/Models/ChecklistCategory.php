<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'designation_id',
        'name',
        'description',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the designation that owns this category
     */
    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get all checklist items for this category
     */
    public function items()
    {
        return $this->hasMany(ChecklistItem::class, 'category_id')->where('is_active', true)->orderBy('order');
    }

    /**
     * Get all items including inactive
     */
    public function allItems()
    {
        return $this->hasMany(ChecklistItem::class, 'category_id')->orderBy('order');
    }
}
