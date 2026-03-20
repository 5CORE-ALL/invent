<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalmartCampaignReport extends Model
{
    use HasFactory;

    protected $table = 'walmart_campaign_reports'; 

    public $timestamps = true; 

    protected $fillable = [
        'campaign_id',
        'report_range',
        'campaign_id',
        'campaignName',
        'budget',
        'spend',
        'sales',
        'cpc',
        'impression',
        'clicks',
        'sold',
        'status',
        'created_at',
        'updated_at',
    ];
}
