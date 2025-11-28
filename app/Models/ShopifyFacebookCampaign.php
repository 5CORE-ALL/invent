<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyFacebookCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'campaign_name',
        'date_range',
        'start_date',
        'end_date',
        'sales',
        'orders',
        'sessions',
        'conversion_rate',
        'ad_spend',
        'roas',
        'referring_channel',
        'traffic_type',
        'country',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sales' => 'decimal:2',
        'orders' => 'integer',
        'sessions' => 'integer',
        'conversion_rate' => 'decimal:4',
        'ad_spend' => 'decimal:2',
        'roas' => 'decimal:2',
    ];
}
