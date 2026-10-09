<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveActivity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'drive_activities';

    protected $fillable = ['item_id', 'user_id', 'action', 'details'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
