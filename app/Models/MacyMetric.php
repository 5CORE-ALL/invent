<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MacyMetric extends Model
{
    protected $table = 'macy_metrics';

    protected $fillable = [
        'sku',
        'image_urls',
        'image_master_json',
    ];
}
