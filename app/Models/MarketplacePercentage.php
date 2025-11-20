<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplacePercentage extends Model
{
    use SoftDeletes;

    protected $table = 'marketplace_percentages';

    protected $fillable = ['marketplace', 'percentage','ad_updates'];

    protected $dates = ['deleted_at'];
}