<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-channel "Returns" checklist submission — backs the second
 * Status + History column pair (placed after R link) on the
 * /customer-care/cc-messages-returns page. Mirrors CcMessageChecklist
 * structurally but is stored separately so the two workflows can be
 * tracked independently.
 */
class CcReturnsChecklist extends Model
{
    protected $table = 'cc_returns_checklists';

    protected $fillable = [
        'channel_id',
        'channel',
        'user_id',
        'user_name',
        'messages_resolved',
        'unresolved_messages_followup',
        'activity_documented',
        'notes',
        'submitted_at',
    ];

    protected $casts = [
        'messages_resolved'            => 'boolean',
        'unresolved_messages_followup' => 'boolean',
        'activity_documented'          => 'boolean',
        'submitted_at'                 => 'datetime',
    ];
}
