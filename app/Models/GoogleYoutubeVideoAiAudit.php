<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleYoutubeVideoAiAudit extends Model
{
    protected $table = 'google_youtube_video_ai_audits';

    protected $fillable = [
        'campaign_id',
        'campaign_name',
        'video_url',
        'prompt_used',
        'result',
        'fail_count',
        'model',
        'audited_by',
        'audited_by_name',
        'audited_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'result' => 'array',
        'fail_count' => 'integer',
        'audited_at' => 'datetime',
    ];
}
