<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends Model
{
    public const TYPE_CALL = 'call';

    public const TYPE_EMAIL = 'email';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_SMS = 'sms';

    protected $fillable = [
        'customer_id',
        'follow_up_id',
        'user_id',
        'type',
        'message',
        'attachment_path',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
