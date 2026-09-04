<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleYoutubePauseRuleSetting extends Model
{
    protected $table = 'google_youtube_pause_rule_settings';

    protected $fillable = [
        'rule',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rule' => 'array',
    ];
}
