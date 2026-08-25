<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonAdsPauseRuleSetting extends Model
{
    protected $table = 'amazon_ads_pause_rule_settings';

    protected $fillable = [
        'rule',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rule' => 'array',
    ];
}
