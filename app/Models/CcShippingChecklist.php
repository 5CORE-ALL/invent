<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shipping-page Messages-side checklist submission. Mirrors
 * CcMessageChecklist structurally so the same checklist UI / formatter
 * pipeline can read either workflow's data.
 */
class CcShippingChecklist extends Model
{
    protected $table = 'cc_shipping_checklists';

    protected $fillable = [
        'channel_id',
        'channel',
        'user_id',
        'user_name',
        'messages_resolved',
        'unresolved_messages_followup',
        'activity_documented',
        'extra_check',
        'extra_check_2',
        'notes',
        'submitted_at',
    ];

    protected $casts = [
        'messages_resolved'            => 'boolean',
        'unresolved_messages_followup' => 'boolean',
        'activity_documented'          => 'boolean',
        'extra_check'                  => 'boolean',
        'extra_check_2'                => 'boolean',
        'submitted_at'                 => 'datetime',
    ];
}
