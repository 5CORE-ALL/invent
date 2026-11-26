<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Services\TikTokGmvMaxCampaignStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GMV Max campaign totals straight from TikTok (same columns as the Ads Manager export).
 */
class TiktokGmvMaxCampaignsController extends Controller
{
    public function index()
    {
        return view('campaign.tiktok.tiktok_gmv_max_campaigns');
    }

    public function data(Request $request): JsonResponse
    {
        $range = strtoupper((string) $request->query('range', 'L30'));
        if (! in_array($range, TikTokGmvMaxCampaignStore::RANGES, true)) {
            $range = 'L30';
        }

        $rows = TikTokGmvMaxCampaignStore::rows($range);
        $cost = array_sum(array_map(fn ($r) => (float) $r['cost'], $rows));
        $orders = array_sum(array_map(fn ($r) => (int) $r['sku_orders'], $rows));
        $revenue = array_sum(array_map(fn ($r) => (float) $r['gross_revenue'], $rows));
        $first = $rows[0] ?? [];

        return response()->json([
            'success' => true,
            'range' => $range,
            'data' => $rows,
            'sums' => [
                'campaigns' => count($rows),
                'cost' => round($cost, 2),
                'sku_orders' => $orders,
                'gross_revenue' => round($revenue, 2),
                'roi' => $cost > 0 ? round($revenue / $cost, 2) : null,
                'cost_per_order' => $orders > 0 ? round($cost / $orders, 2) : null,
            ],
            'period_start' => $first['period_start'] ?? null,
            'period_end' => $first['period_end'] ?? null,
            'fetched_at' => $first['fetched_at'] ?? null,
        ]);
    }
}
