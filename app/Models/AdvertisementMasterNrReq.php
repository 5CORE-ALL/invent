<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementMasterNrReq extends Model
{
    protected $table = 'advertisement_master_nr_reqs';

    protected $fillable = [
        'channel_key',
        'nr_req',
    ];
}
