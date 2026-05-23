<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Submission of the per-channel "CC Message & Returns" checklist from the
 * /customer-care/cc-messages-returns page. Each row is a single click of
 * "Submit" by an agent and is treated as an audit history entry.
 */
class CcMessageChecklist extends Model
{
    protected $table = 'cc_message_checklists';

    protected $fillable = [
        'channel_id',
        'channel',
        'user_id',
        'user_name',
        'messages_resolved',
        'returns_resolved',
        'unresolved_messages_followup',
        'activity_documented',
        'notes',
        'submitted_at',
    ];

    protected $casts = [
        'messages_resolved'            => 'boolean',
        'returns_resolved'             => 'boolean',
        'unresolved_messages_followup' => 'boolean',
        'activity_documented'          => 'boolean',
        'submitted_at'                 => 'datetime',
    ];
}
