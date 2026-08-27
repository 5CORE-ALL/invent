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
        'ad_create_reject',
        'ad_create_reject_at',
        'pause_run_ok',
        'pause_run_error',
        'pause_run_at',
        'pause_run_history',
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
        'ad_create_reject_at' => 'datetime',
        'pause_run_ok' => 'boolean',
        'pause_run_at' => 'datetime',
        'pause_run_history' => 'array',
    ];

    /**
     * Same Status shown on /temu/ads and /temu-decrease.
     * Empty / Unknown → Not sync. A confirmed "No ad" stays "No ad"
     * even when the goods report still has spend/clicks (ended campaign).
     */
    public function displayAdStatus(): string
    {
        $status = trim((string) ($this->ad_status ?? ''));
        if ($status === '' || strcasecmp($status, 'Unknown') === 0) {
            return 'Not sync';
        }

        return $status;
    }

    public function isActiveAd(): bool
    {
        return $this->displayAdStatus() === 'Active';
    }

    public function scopeActiveAds($query)
    {
        return $query->where('ad_status', 'Active');
    }

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
