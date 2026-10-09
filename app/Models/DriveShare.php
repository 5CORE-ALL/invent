<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveShare extends Model
{
    protected $table = 'drive_shares';

    protected $fillable = ['item_id', 'user_id', 'email', 'role', 'shared_by'];

    public function item()
    {
        return $this->belongsTo(DriveItem::class, 'item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sharer()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
