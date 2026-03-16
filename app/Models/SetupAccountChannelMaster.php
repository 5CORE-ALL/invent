<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SetupAccountChannelMaster extends Model
{
    use HasFactory;

    protected $table = 'setup_account_channel_master';

    protected $fillable = [
        'channel_name',
        'type',
        'status',
        'login_link',
        'email_userid',
        'password',
        'remarks'
    ];

    public function channel()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }

}
