<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemuAdsApiReport extends Model
{
    protected $table = 'temu_ads_api_reports';

    protected $fillable = [
        'goods_id',
        'sku',
        'period',
        'start_ts',
        'end_ts',
        'impressions',
        'clicks',
        'ctr',
        'cart_cnt',
        'order_pay_cnt',
        'order_pay_amt',
        'ad_spend',
        'roas',
        'acos',
        'ad_status',
        'raw_response',
        'success',
        'error_msg',
        'fetched_at',
    ];

    protected $casts = [
        'start_ts' => 'integer',
        'end_ts' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr' => 'float',
        'cart_cnt' => 'integer',
        'order_pay_cnt' => 'integer',
        'order_pay_amt' => 'float',
        'ad_spend' => 'float',
        'roas' => 'float',
        'acos' => 'float',
        'success' => 'boolean',
        'fetched_at' => 'datetime',
    ];

    /**
     * Decode stored raw API payload.
     */
    public function getRawPayloadAttribute(): ?array
    {
        if ($this->raw_response === null || $this->raw_response === '') {
            return null;
        }
        $decoded = json_decode($this->raw_response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
