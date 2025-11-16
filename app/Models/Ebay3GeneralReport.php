<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebay3GeneralReport extends Model
{
    use HasFactory;

    protected $table = 'ebay_3_general_reports';

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
}
