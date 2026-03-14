<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonKwLastSbidDaily extends Model
{
    protected $table = 'amazon_kw_last_sbid_daily';

    protected $fillable = [
        'campaign_id',
        'profile_id',
        'report_date',
        'last_sbid',
        'campaign_name',
    ];

    protected $casts = [
        'report_date' => 'date',
        'last_sbid' => 'decimal:4',
    ];
}
