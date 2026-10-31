<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonKwCousinLink extends Model
{
    protected $table = 'amazon_kw_cousin_links';

    protected $fillable = [
        'campaign',
        'linked_campaign',
        'campaign_norm',
        'linked_campaign_norm',
        'updated_by',
    ];
}
