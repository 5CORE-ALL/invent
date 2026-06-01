<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-channel "R link" (Returns link) for /customer-care/cc-messages-returns
 * (and the R link column on cc-shipping). Unlike M / H links on AHM field
 * definitions (shared per scope), each channel stores its own returns URL.
 */
class CcReturnsChannelLink extends Model
{
    protected $table = 'cc_returns_channel_links';

    protected $fillable = [
        'channel_id',
        'r_link',
        'updated_by_user_id',
        'updated_by_name',
    ];

    protected $casts = [
        'channel_id'         => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
