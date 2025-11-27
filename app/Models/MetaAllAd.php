<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetaAllAd extends Model
{
    use HasFactory;

    protected $table = 'meta_all_ads';

    protected $fillable = [
        'campaign_name',
        'campaign_id',
        'ad_type',
        'campaign_delivery',
        'bgt',
        'imp_l30',
        'spent_l30',
        'clicks_l30',
    ];

    protected $casts = [
        'bgt' => 'decimal:2',
        'spent_l30' => 'decimal:2',
        'imp_l30' => 'integer',
        'clicks_l30' => 'integer',
    ];
}
