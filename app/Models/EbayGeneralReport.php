<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EbayGeneralReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_range',
        'campaign_id',
        'listing_id',
        'impressions',
        'clicks',
        'ad_fees',
        'sales',
        'sale_amount',
        'ctr',
        'avg_cost_per_sale',
        'ctr',
        'channels',
    ];

    protected $casts = [
        'impressions' => 'integer',
        'clicks' => 'integer',
        // Remove float cast for ad_fees and sale_amount since they contain 'USD ' prefix
        // 'ad_fees' => 'float',
        'sales' => 'integer',
        // 'sale_amount' => 'float',
        'ctr' => 'float',
        'avg_cost_per_sale' => 'float',
    ];
}
