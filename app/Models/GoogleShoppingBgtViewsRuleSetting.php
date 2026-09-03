<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleShoppingBgtViewsRuleSetting extends Model
{
    protected $table = 'google_shopping_bgt_views_rule_settings';

    protected $fillable = [
        'rule',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rule' => 'array',
    ];
}
