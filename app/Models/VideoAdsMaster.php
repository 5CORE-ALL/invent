<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAdsMaster extends Model
{
    use HasFactory;

    protected $table = 'video_ads_master';

    protected $fillable = [
        'target_type',
        'target_value',
        'name',
        'channel',
        'audience',
        'hook_name',
        'hook',
        'link',
        'is_checked',
        'checked_by',
        'checked_at',
        'ad_checked',
        'ad_checked_by',
        'ad_checked_at',
    ];

    protected $casts = [
        'is_checked'    => 'boolean',
        'checked_at'    => 'datetime',
        'ad_checked'    => 'boolean',
        'ad_checked_at' => 'datetime',
    ];

    public function checkHistory()
    {
        return $this->hasMany(VideoAdsMasterCheckHistory::class, 'video_ads_master_id')
            ->orderByDesc('id');
    }
}
