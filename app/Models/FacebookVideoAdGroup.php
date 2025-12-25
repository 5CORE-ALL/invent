<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacebookVideoAdGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'facebook_video_ad_groups';

    protected $fillable = [
        'group_name',
        'description',
        'status',
    ];

    /**
     * Get all Facebook video ads that belong to this group
     */
    public function facebookVideoAds()
    {
        return $this->hasMany(FacebookVideoAd::class, 'group_id');
    }
}
