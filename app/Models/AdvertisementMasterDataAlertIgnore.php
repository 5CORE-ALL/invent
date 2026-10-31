<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementMasterDataAlertIgnore extends Model
{
    protected $table = 'advertisement_master_data_alert_ignores';

    protected $fillable = [
        'channel_key',
        'field',
    ];
}
