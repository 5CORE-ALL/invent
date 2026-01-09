<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaActionLog extends Model
{
    use HasFactory;

    protected $table = 'meta_action_logs';

    protected $fillable = [
        'user_id',
        'action_type',
        'entity_type',
        'entity_meta_id',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'meta_error_code',
        'meta_error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
