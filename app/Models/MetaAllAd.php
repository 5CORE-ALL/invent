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
        'platform',
        'campaign_delivery',
        'bgt',
        'imp_l30',
        'spent_l30',
        'clicks_l30',
        'imp_l7',
        'spent_l7',
        'clicks_l7',
    ];

    protected $casts = [
        'bgt' => 'decimal:2',
        'spent_l30' => 'decimal:2',
        'imp_l30' => 'integer',
        'clicks_l30' => 'integer',
        'spent_l7' => 'decimal:2',
        'imp_l7' => 'integer',
        'clicks_l7' => 'integer',
    ];

    // Define the allowed ad types
    public static $adTypes = [
        'Facebook Single Image',
        'Facebook Single Video',
        'Facebook Carousal',
        'Facebook Existing Post',
        'Facebook Catalogue Ad',
        'Instagram Single Image',
        'Instagram Single Video',
        'Instagram Carousal',
        'Instagram Existing Post',
        'Instagram Catalogue Ad',
    ];
}
