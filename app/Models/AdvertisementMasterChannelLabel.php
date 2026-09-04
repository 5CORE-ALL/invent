<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementMasterChannelLabel extends Model
{
    protected $table = 'advertisement_master_channel_labels';

    protected $fillable = [
        'channel_key',
        'group_name',
        'channel_name',
    ];
};
