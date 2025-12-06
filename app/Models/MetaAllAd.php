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
        'group_id',
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

    /**
     * Get the group that the ad belongs to
     */
    public function group()
    {
        return $this->belongsTo(MetaAdGroup::class, 'group_id');
    }

    /**
     * Assign group based on campaign name prefix
     */
    public static function assignGroupByCampaignName($campaignName)
    {
        $groups = MetaAdGroup::all();
        
        foreach ($groups as $group) {
            // Check if campaign name starts with group name (case-insensitive)
            if (stripos($campaignName, $group->group_name) === 0) {
                return $group->id;
            }
        }
        
        return null;
    }
}
