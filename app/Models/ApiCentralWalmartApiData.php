<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiCentralWalmartApiData extends Model
{
    use HasFactory;

    protected $connection = 'api_central'; // <-- tell Laravel to use api_central DB
    protected $table = 'walmart_api_data';     // table name in that DB
}
