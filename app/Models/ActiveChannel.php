<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActiveChannel extends Model
{
    protected $table = 'active_channels';

    protected $fillable = ['name', 'status'];

    protected $casts = ['status' => 'integer'];

    public function followups(): HasMany
    {
        return $this->hasMany(CustomerFollowup::class, 'active_channel_id');
    }
}
