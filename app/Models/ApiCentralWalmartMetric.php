<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiCentralWalmartMetric extends Model
{
    use HasFactory;

    protected $connection = 'api_central'; // <-- tell Laravel to use api_central DB
    protected $table = 'walmart_metrics';     // table name in that DB
}
