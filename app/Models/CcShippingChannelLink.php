<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-channel "S link" (Shipping link) used on the /customer-care/cc-shipping
 * page. Unlike M / H / R links — which live on
 * account_health_metric_field_definitions and are shared per scope across
 * pages — the S link is genuinely shipping-specific and stored once per
 * channel in its own table so the two pages stay isolated.
 */
class CcShippingChannelLink extends Model
{
    protected $table = 'cc_shipping_channel_links';

    protected $fillable = [
        'channel_id',
        's_link',
        'updated_by_user_id',
        'updated_by_name',
    ];

    protected $casts = [
        'channel_id'         => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
