<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyMetaCampaign extends Model
{
    use HasFactory;

    protected $table = 'shopify_meta_campaigns';

    protected $fillable = [
        'campaign_id',
        'campaign_name',
        'date_range',
        'start_date',
        'end_date',
        'sales',
        'orders',
        'sessions',
        'conversion_rate',
        'ad_spend',
        'roas',
        'referring_channel',
        'traffic_type',
        'country',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sales' => 'decimal:2',
        'orders' => 'integer',
        'sessions' => 'integer',
        'conversion_rate' => 'decimal:4',
        'ad_spend' => 'decimal:2',
        'roas' => 'decimal:2',
    ];

    /**
     * Latest Shopify-attributed sales/orders per Meta campaign ID.
     * Uses the newest row per campaign + date_range + channel, then sums
     * Facebook + Instagram so totals match Shopify campaign attribution.
     *
     * @param array<int, string> $dateRanges
     * @param array<int, string>|null $channels When set, only include these referring_channel values.
     * @return array<string, array<string, array{sales: float, orders: int, sessions: int}>>
     */
    public static function latestCampaignMetricsMap(array $dateRanges = ['7_days', '30_days', '60_days'], ?array $channels = null): array
    {
        $map = [];

        foreach ($dateRanges as $range) {
            $query = self::query()
                ->where('date_range', $range)
                ->whereNotNull('campaign_id')
                ->orderByDesc('updated_at');

            if ($channels !== null) {
                $query->whereIn('referring_channel', $channels);
            }

            $rows = $query->get();

            $seen = [];

            foreach ($rows as $row) {
                $campaignId = (string) $row->campaign_id;
                $channel = (string) ($row->referring_channel ?? '');
                $seenKey = $campaignId . '|' . $channel;

                if (isset($seen[$seenKey])) {
                    continue;
                }

                $seen[$seenKey] = true;

                if (! isset($map[$campaignId][$range])) {
                    $map[$campaignId][$range] = [
                        'sales' => 0.0,
                        'orders' => 0,
                        'sessions' => 0,
                    ];
                }

                $map[$campaignId][$range]['sales'] += (float) $row->sales;
                $map[$campaignId][$range]['orders'] += (int) $row->orders;
                $map[$campaignId][$range]['sessions'] += (int) $row->sessions;
            }
        }

        return $map;
    }

    /**
     * @return array{sales: float, orders: int, sessions: int}
     */
    public static function emptyMetrics(): array
    {
        return [
            'sales' => 0.0,
            'orders' => 0,
            'sessions' => 0,
        ];
    }

    /**
     * @return array{sales: float, orders: int, sessions: int}
     */
    public static function metricsForCampaign(string $campaignId, string $dateRange = '30_days'): array
    {
        $map = self::latestCampaignMetricsMap([$dateRange]);

        return $map[$campaignId][$dateRange] ?? self::emptyMetrics();
    }
}

