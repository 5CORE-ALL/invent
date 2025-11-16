<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundRate extends Model
{
    protected $table = 'refund_rate';

    protected $fillable = [
        'channel_id',
        'report_date',
        'week_one',
        'week_two',
        'week_three',
        'week_four',
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
