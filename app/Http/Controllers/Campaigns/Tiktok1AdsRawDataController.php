<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\TiktokCampaignReport;
use App\Models\TiktokOrder;
use App\Support\TikTokAdsSkuResolver;
use App\Support\Tiktok1AdsRawDataTotals;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * TikTok 1 Ads Raw Data — every row saved by /tiktok/utilized/upload
 * into tiktok_campaign_reports. No L30/L7, Product card, or SKU filters.
 */
class Tiktok1AdsRawDataController extends Controller
{
    public function index()
    {
        return view('campaign.tiktok.tiktok_1_ads_raw_data');
    }

    public function getData()
    {
        try {
            if (! Schema::hasTable('tiktok_campaign_reports')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                    'sums' => [
                        'count' => 0,
                        'cost_l30' => 0,
                        'cost_l1' => 0,
                        'orders_l30' => 0,
                        'orders_l1' => 0,
                        'revenue_l30' => 0,
                        'revenue_l1' => 0,
                    ],
                ]);
            }

            $rows = TiktokCampaignReport::query()->orderByDesc('id')->get();
            $sums = Tiktok1AdsRawDataTotals::sums();

            $data = $rows->map(function (TiktokCampaignReport $row) {
                $arr = $row->toArray();
                $arr['sku'] = TikTokAdsSkuResolver::skuFor($row->product_id, $row->campaign_name);
                if (($arr['roi'] === null || $arr['roi'] === '') && (float) ($row->cost ?? 0) > 0) {
                    $arr['roi'] = round((float) ($row->gross_revenue ?? 0) / (float) $row->cost, 2);
                }
                $arr['time_posted'] = optional($row->time_posted)->format('Y-m-d H:i:s');
                $arr['created_at'] = optional($row->created_at)->format('Y-m-d H:i:s');
                $arr['updated_at'] = optional($row->updated_at)->format('Y-m-d H:i:s');

                return $arr;
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'sums' => $sums,
            ]);
        } catch (\Throwable $e) {
            Log::error('TikTok 1 Ads Raw Data failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Single TikTok 1 row for /advertisement-master — L30 Cost / clicks /
     * SKU orders / revenue from tiktok_campaign_reports, same totals as
     * /tiktok-1-ads-raw-data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdvertisementMasterChannelRows(): array
    {
        $sums = Tiktok1AdsRawDataTotals::sums();

        return [
            self::advertisementMasterMetricRow('TikTok 1', 'tiktok1', (object) [
                'spend' => (float) ($sums['cost_l30'] ?? 0),
                'clicks' => (int) ($sums['clicks_l30'] ?? 0),
                'sold' => (int) ($sums['orders_l30'] ?? 0),
                'sales' => (float) ($sums['revenue_l30'] ?? 0),
                'active' => $this->advertisementMasterActiveCount(),
            ]),
        ];
    }

    /**
     * TikTok Shop L30 store sales from tiktok_orders — same California
     * window as Channel Master / TikTok 1.
     */
    public static function advertisementMasterNetSales(): float
    {
        try {
            if (! TiktokOrder::tableReady()) {
                return 0.0;
            }

            [$start, $end] = TiktokOrder::californiaDaysWindow(
                30,
                Carbon::yesterday(TiktokOrder::TZ)
            );

            return round(TiktokOrder::salesAmountBetween($start, $end), 2);
        } catch (\Throwable $e) {
            Log::warning('Advertisement Master TikTok 1 net sales lookup failed: '.$e->getMessage());

            return 0.0;
        }
    }

    /**
     * Distinct L30 campaigns that are actually delivering — Exploring or
     * Explored. Ineligible-only campaigns (authorization / unavailable) are
     * excluded. The sheet `status` field is exploration state, not on/off.
     */
    protected function advertisementMasterActiveCount(): int
    {
        if (! Schema::hasTable('tiktok_campaign_reports')
            || ! Schema::hasColumn('tiktok_campaign_reports', 'campaign_id')
            || ! Schema::hasColumn('tiktok_campaign_reports', 'status')) {
            return 0;
        }

        return (int) DB::table('tiktok_campaign_reports')
            ->whereRaw("UPPER(TRIM(COALESCE(report_range, ''))) = 'L30'")
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '!=', '')
            ->whereRaw("UPPER(TRIM(status)) IN ('EXPLORING', 'EXPLORED')")
            ->distinct()
            ->count('campaign_id');
    }

    /**
     * @return array<string, mixed>
     */
    private static function advertisementMasterMetricRow(string $channel, string $source, ?object $row): array
    {
        $spend = (float) ($row->spend ?? 0);
        $clicks = (float) ($row->clicks ?? 0);
        $sold = (float) ($row->sold ?? 0);
        $sales = (float) ($row->sales ?? 0);

        return [
            'channel' => $channel,
            'source' => $source,
            'spend' => round($spend, 2),
            'clicks' => (int) round($clicks),
            'sold' => (int) round($sold),
            'sales' => round($sales, 2),
            'cvr' => $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0,
            'acos' => $sales > 0
                ? round(($spend / $sales) * 100, 0)
                : ($spend > 0 ? 100 : 0),
            'tcos' => 0,
            'active' => (int) ($row->active ?? 0),
            'is_sub_row' => false,
            'marketplace' => 'tiktok',
        ];
    }
}
