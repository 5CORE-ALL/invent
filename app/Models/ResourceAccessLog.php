<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAccessLog extends Model
{
    public $timestamps = false;

    public const UPDATED_AT = null;

    protected $fillable = ['resource_id', 'user_id', 'action', 'ip_address', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ResourceMaster::class, 'resource_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
