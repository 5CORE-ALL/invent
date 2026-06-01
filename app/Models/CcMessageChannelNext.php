<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Next" priority value (1..9) per channel, used by the manager on the
 * /customer-care/cc-messages-returns page. One row per channel; edits
 * are restricted to NEXT_EDITOR_EMAIL on the controller side.
 */
class CcMessageChannelNext extends Model
{
    protected $table = 'cc_message_channel_next';

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
