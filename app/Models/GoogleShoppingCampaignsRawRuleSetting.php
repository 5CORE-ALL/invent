<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleShoppingCampaignsRawRuleSetting extends Model
{
    protected $table = 'google_shopping_campaigns_raw_rule_settings';

    protected $fillable = [
        'rule',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'rule' => 'array',
    ];
}
