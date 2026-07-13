<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonNegativeCampaignLink extends Model
{
    protected $table = 'amazon_negative_campaign_links';

    protected $fillable = [
        'campaign',
        'linked_campaign',
        'campaign_norm',
        'linked_campaign_norm',
        'updated_by',
    ];
}
