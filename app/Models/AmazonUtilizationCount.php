<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonUtilizationCount extends Model
{
    protected $table = 'amazon_utilization_counts';

    protected $fillable = [
        'campaign_id',
        'campaign_name',
        'campaign_type',
        'ub7',
        'ub1',
        'inventory',
    ];

    protected $casts = [
        'ub7' => 'float',
        'ub1' => 'float',
        'inventory' => 'integer',
    ];
}
