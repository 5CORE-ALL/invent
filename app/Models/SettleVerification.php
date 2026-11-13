<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Insert-only history of settle verification runs (new table).
 */
class SettleVerification extends Model
{
    protected $table = 'settle_verifications';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'settle_date' => 'date:Y-m-d',
        'checks' => 'array',
        'metrics' => 'array',
    ];
}
