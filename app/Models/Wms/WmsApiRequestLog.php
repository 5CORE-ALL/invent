<?php

namespace App\Models\Wms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WmsApiRequestLog extends Model
{
    public $timestamps = false;

    protected $table = 'wms_api_request_logs';

    protected $fillable = [
        'user_id',
        'method',
        'path',
        'status',
        'duration_ms',
        'request_body',
        'response_body',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
