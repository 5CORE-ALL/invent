<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDoc extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'label',
        'url',
        'path',
        'original_name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
