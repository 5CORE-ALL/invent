<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookAllAdsSheet extends Model
{
    protected $table = 'facebook_all_ads_sheet';

    protected $fillable = [
        'import_batch_id',
        'source_filename',
        'row_index',
        'row_data',
        'ad_type',
        'uploaded_by',
    ];

    /** Allowed Ad Type dropdown values shown on the page. */
    public const AD_TYPES = [
        'GROUP VIDEO',
        'GROUP CAROUSAL',
        'PARENT VIDEO',
        'PARENT CAROUSAL',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];
}
