<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacebookVideoAdCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'facebook_video_ad_categories';

    protected $fillable = [
        'category_name',
        'code',
        'description',
        'status',
    ];

    /**
     * Get all Facebook video ads that belong to this category
     */
    public function facebookVideoAds()
    {
        return $this->hasMany(FacebookVideoAd::class, 'category_id');
    }
}
