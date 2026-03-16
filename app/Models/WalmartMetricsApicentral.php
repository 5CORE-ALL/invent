<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalmartMetricsApicentral extends Model
{
    protected $connection = 'apicentral';     // <— important
    protected $table = 'walmart_metrics';     // table name in apicentral DB

    public $timestamps = false;
}
