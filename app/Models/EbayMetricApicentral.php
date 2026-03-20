<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EbayMetricApicentral extends Model
{
    protected $connection = 'apicentral';
    protected $table = 'ebay_one_metrics';

    public $timestamps = false;
}
