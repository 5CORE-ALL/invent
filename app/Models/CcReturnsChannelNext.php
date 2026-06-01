<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "R Next" priority value (1..9) per channel, driving the freshness
 * window for the Returns checklist (the second Status icon on the
 * /customer-care/cc-messages-returns page). One row per channel; edits
 * are gated to NEXT_EDITOR_EMAILS on the controller side, same as the
 * Messages "Next" value.
 */
class CcReturnsChannelNext extends Model
{
    protected $table = 'cc_returns_channel_next';

    protected $fillable = [
        'channel_id',
        'next_value',
        'updated_by_user_id',
        'updated_by_name',
    ];

    protected $casts = [
        'channel_id'         => 'integer',
        'next_value'         => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
