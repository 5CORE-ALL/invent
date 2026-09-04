<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleYoutubeVideoAudit extends Model
{
    protected $table = 'google_youtube_video_audits';

    protected $fillable = [
        'campaign_id',
        'campaign_name',
        'checks',
        'fail_count',
        'comments',
        'audited_by',
        'audited_by_name',
        'audited_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'checks' => 'array',
        'fail_count' => 'integer',
        'audited_at' => 'datetime',
    ];
}
