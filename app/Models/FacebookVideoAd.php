<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookVideoAd extends Model
{
    protected $fillable = ['sku', 'value', 'group_id', 'category_id'];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Get the group that this Facebook video ad belongs to (from Group Master)
     */
    public function group()
    {
        return $this->belongsTo(\App\Models\ProductGroup::class, 'group_id');
    }

    /**
     * Get the category that this Facebook video ad belongs to (from Group Master)
     */
    public function category()
    {
        return $this->belongsTo(\App\Models\ProductCategory::class, 'category_id');
    }
}
