<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChinaLoad extends Model
{
    public $timestamps = false;

    protected $table = 'china_load';

    protected $fillable = [
        'container_sl_no', 'load', 'list_of_goods', 'shut_out',
        'obl', 'mbl', 'container_no', 'item', 'cha_china', 'consignee'
    ];
}
