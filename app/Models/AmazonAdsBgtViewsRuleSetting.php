<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonAdsBgtViewsRuleSetting extends Model
{
    protected $table = 'amazon_ads_bgt_views_rule_settings';

    protected $fillable = [
        'rule',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rule' => 'array',
    ];
}
