<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ebay2MetricApicentral extends Model
{
    protected $connection = 'apicentral';
    protected $table = 'ebay2_metrics';

    public $timestamps = false;
}
