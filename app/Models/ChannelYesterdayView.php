<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelYesterdayView extends Model
{
    protected $table = 'channel_yesterday_views';

    protected $fillable = [
        'channel',
        'snapshot_date',
        'views',
        'l7_views',
        'source',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'views' => 'integer',
        'l7_views' => 'integer',
    ];
};
