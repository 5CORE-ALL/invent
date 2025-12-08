<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetaAdGroup extends Model
{
    use HasFactory;

    protected $table = 'meta_ad_groups';

    protected $fillable = [
        'group_name',
    ];

    /**
     * Get all meta ads that belong to this group
     */
    public function metaAds()
    {
        return $this->hasMany(MetaAllAd::class, 'group_id');
    }
}
