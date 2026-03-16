<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalsChannelMaster extends Model
{
    protected $table = 'approvals_channel_master';

    protected $fillable = [
        'type',
        'channel_name',
        'regn_link',
        'status',
        'aa_stage',
        'date',
        'login_link',
        'email_userid',
        'password',
        'last_date',
        'remarks',
        'next_date'
    ];

    public function channelMaster()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }

}
