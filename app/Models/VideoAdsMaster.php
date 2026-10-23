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
        'redo_checked',
        'redo_checked_by',
        'redo_checked_at',
        'redone_checked',
        'redone_checked_by',
        'redone_checked_at',
    ];

    protected $hidden = [
        'creators_initialized',
    ];

    protected $casts = [
        'is_checked'            => 'boolean',
        'checked_at'            => 'datetime',
        'ad_checked'            => 'boolean',
        'ad_checked_at'         => 'datetime',
        'redo_checked'          => 'boolean',
        'redo_checked_at'       => 'datetime',
        'redone_checked'        => 'boolean',
        'redone_checked_at'     => 'datetime',
        'creators_initialized'  => 'boolean',
    ];

    public function checkHistory()
    {
        return $this->hasMany(VideoAdsMasterCheckHistory::class, 'video_ads_master_id')
            ->orderByDesc('id');
    }

    public function creators()
    {
        return $this->hasMany(VideoAdsMasterCreator::class, 'video_ads_master_id')
            ->orderBy('id');
    }
}
