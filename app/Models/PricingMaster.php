<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingMaster extends Model
{
        protected $table = 'pricing_master';
    protected $fillable = [
        'sku', 'sprice', 'sprofit_percent', 'sroi_percent'
    ];

    public $timestamps = false;
}