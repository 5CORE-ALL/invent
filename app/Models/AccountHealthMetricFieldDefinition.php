<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountHealthMetricFieldDefinition extends Model
{
    protected $table = 'account_health_metric_field_definitions';

    protected $fillable = [
        'definition_scope',
        'field_key',
        'label',
        'sort_order',
    ];
}
