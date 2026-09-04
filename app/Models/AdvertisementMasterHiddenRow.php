<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementMasterHiddenRow extends Model
{
    protected $table = 'advertisement_master_hidden_rows';

    protected $fillable = [
        'channel_key',
    ];
}
