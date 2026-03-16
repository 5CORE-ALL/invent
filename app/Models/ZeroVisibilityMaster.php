<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZeroVisibilityMaster extends Model
{
    use HasFactory;

    protected $table = 'zero_visibility_masters';

    protected $fillable = [
        'channel_name',
        'sheet_link',
        'is_ra_checked',
        'total_sku',
        'nr',
        'listed_req',
        'listed',
        'listing_pending',
        'zero_inv',
        'live_req',
        'active_and_live',
        'live_pending',
        'zero_visibility_sku_count',
        'reason',
        'step_taken',
    ];

    protected $casts = [
        'is_ra_checked' => 'boolean',
    ];

}
