<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleYoutubeVideoAiPrompt extends Model
{
    protected $table = 'google_youtube_video_ai_prompts';

    protected $fillable = [
        'prompt',
        'saved_by',
        'saved_by_name',
    ];
}
