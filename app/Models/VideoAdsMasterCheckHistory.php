<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAdsMasterCheckHistory extends Model
{
    use HasFactory;

    protected $table = 'video_ads_master_check_history';

    // Only a created_at column exists (append-only log), so disable the
    // updated_at timestamp handling.
    public $timestamps = false;

    protected $fillable = [
        'video_ads_master_id',
        'is_checked',
        'action',
        'username',
        'created_at',
    ];

    protected $casts = [
        'is_checked' => 'boolean',
        'created_at' => 'datetime',
    ];
}
