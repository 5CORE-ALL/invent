<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcAuditLog extends Model
{
    protected $table = 'cc_audit_logs';

    protected $fillable = [
        'channel_id',
        'all_messages_cleared',
        'all_messages_replied_correctly',
        'all_messages_noted_in_all_issues',
        'all_followup_created_cleared_on_time',
        'auditor_remarks',
        'audited_at',
        'user_id',
    ];

    protected $casts = [
        'all_messages_cleared' => 'boolean',
        'all_messages_replied_correctly' => 'boolean',
        'all_messages_noted_in_all_issues' => 'boolean',
        'all_followup_created_cleared_on_time' => 'boolean',
        'audited_at' => 'datetime',
    ];

    public function channel()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
