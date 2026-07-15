<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonCampaignLink extends Model
{
    protected $table = 'amazon_campaign_links';

    protected $fillable = [
        'campaign',
        'linked_campaign',
        'campaign_norm',
        'linked_campaign_norm',
        'updated_by',
    ];
}
