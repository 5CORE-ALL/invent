<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatNotificationPref extends Model
{
    protected $fillable = ['user_id', 'mode'];
}
