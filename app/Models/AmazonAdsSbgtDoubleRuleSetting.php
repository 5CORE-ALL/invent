<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonAdsSbgtDoubleRuleSetting extends Model
{
    protected $table = 'amazon_ads_sbgt_double_rule_settings';

    protected $fillable = [
        'rule',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rule' => 'array',
    ];
}
