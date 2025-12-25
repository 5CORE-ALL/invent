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
     * Get the group that this Facebook video ad belongs to
     */
    public function group()
    {
        return $this->belongsTo(FacebookVideoAdGroup::class, 'group_id');
    }

    /**
     * Get the category that this Facebook video ad belongs to
     */
    public function category()
    {
        return $this->belongsTo(FacebookVideoAdCategory::class, 'category_id');
    }
}
