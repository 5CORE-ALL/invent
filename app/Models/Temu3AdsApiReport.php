<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temu3AdsApiReport extends Model
{
    protected $table = 'temu3_ads_api_reports';

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

    public function displayAdStatus(): string
    {
        $status = trim((string) ($this->ad_status ?? ''));
        if ($status === '' || strcasecmp($status, 'Unknown') === 0) {
            return 'Not sync';
        }

        return $status;
    }

    public function scopeLiveAds($query)
    {
        return $query->whereIn('ad_status', ['Active', 'Inactive', 'Paused']);
    }

    /**
     * @return array{start_ts: int, end_ts: int}|null
     */
    public static function latestWindow(string $period): ?array
    {
        $row = static::query()
            ->where('period', strtoupper($period))
            ->whereNotNull('start_ts')
            ->orderByDesc('start_ts')
            ->first(['start_ts', 'end_ts']);
        if (! $row || $row->start_ts === null) {
            return null;
        }

        return [
            'start_ts' => (int) $row->start_ts,
            'end_ts' => (int) ($row->end_ts ?? 0),
        ];
    }

    public function scopeInLatestWindow($query, string $period)
    {
        $period = strtoupper($period);
        $window = static::latestWindow($period);
        $query->where('period', $period);
        if ($window) {
            $query->where('start_ts', $window['start_ts']);
        }

        return $query;
    }

    public function getRawPayloadAttribute(): ?array
    {
        if ($this->raw_response === null || $this->raw_response === '') {
            return null;
        }
        $decoded = json_decode($this->raw_response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
