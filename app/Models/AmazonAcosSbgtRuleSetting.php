<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonAcosSbgtRuleSetting extends Model
{
    protected $table = 'amazon_acos_sbgt_rule_settings';

    protected $fillable = [
        'rule',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rule' => 'array',
    ];
}
