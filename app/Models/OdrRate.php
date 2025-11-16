<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdrRate extends Model
{
    protected $table = 'odr_rate';

    protected $fillable = [
        'channel_id',
        'allowed',
        'current',
        'report_date',
        'what',
        'why',
        'action',
        'c_action',
        'account_health_links',
    ];

    public function channel()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }
}
