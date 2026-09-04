<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementMasterCustomRow extends Model
{
    protected $table = 'advertisement_master_custom_rows';

    protected $fillable = [
        'channel_name',
        'type_name',
    ];
}
