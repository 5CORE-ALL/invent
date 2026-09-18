<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LqsMasterEntry extends Model
{
    use HasFactory;

    protected $table = 'lqs_master_entries';

    protected $fillable = [
        'channel_key',
        'channel',
        'lqs',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'lqs' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
