<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleShoppingBgtAcosLtRuleSetting extends Model
{
    protected $table = 'google_shopping_bgt_acos_lt_rule_settings';

    protected $fillable = [
        'rule',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rule' => 'array',
    ];
}
