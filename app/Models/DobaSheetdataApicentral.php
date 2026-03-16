<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DobaSheetdataApicentral extends Model
{
    protected $connection = 'apicentral';
    protected $table = 'doba_metrics';

    public $timestamps = false;
}
