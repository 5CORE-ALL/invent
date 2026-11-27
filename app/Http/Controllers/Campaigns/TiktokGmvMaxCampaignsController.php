<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Services\TikTokGmvMaxCampaignStore;
use App\Services\TikTokGmvMaxPushService;
use App\Support\TikTokAdsSkuResolver;
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
        $skuMap = TikTokAdsSkuResolver::productIdToSkuMap();
        foreach ($rows as &$row) {
            $ids = json_decode((string) ($row['item_group_ids'] ?? ''), true);
            $skus = [];
            foreach (is_array($ids) ? $ids : [] as $id) {
                if (isset($skuMap[(string) $id])) {
                    $skus[$skuMap[(string) $id]] = true;
                }
            }
            $row['skus'] = implode(', ', array_keys($skus));
            $row['acos'] = (float) $row['gross_revenue'] > 0 ? round((float) $row['cost'] / (float) $row['gross_revenue'] * 100, 2) : null;
            $row['products'] = is_array($ids) ? count($ids) : 0;
            unset($row['item_group_ids']);
        }
        unset($row);
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

    /**
     * Queue a daily-budget change; the GitHub relay job pushes it to TikTok within minutes.
     */
    public function pushBudget(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'string', 'max:40'],
            'budget' => ['required', 'numeric'],
        ]);

        try {
            $row = TikTokGmvMaxPushService::queueBudget(
                $data['campaign_id'],
                (float) $data['budget'],
                optional($request->user())->name ?? optional($request->user())->email
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'queued' => $row]);
    }

    /**
     * Queue daily-budget changes for many campaigns at once (the "Push budgets" modal).
     * Every item goes through the same limits as a single push; refused items come back with the reason.
     */
    public function pushBudgets(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.campaign_id' => ['required', 'string', 'max:40'],
            'items.*.budget' => ['required', 'numeric'],
        ]);

        $by = optional($request->user())->name ?? optional($request->user())->email;
        $queued = 0;
        $results = [];
        foreach ($data['items'] as $item) {
            try {
                TikTokGmvMaxPushService::queueBudget((string) $item['campaign_id'], (float) $item['budget'], $by);
                $queued++;
                $results[] = ['campaign_id' => (string) $item['campaign_id'], 'ok' => true];
            } catch (\InvalidArgumentException $e) {
                $results[] = ['campaign_id' => (string) $item['campaign_id'], 'ok' => false, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => $queued > 0,
            'queued' => $queued,
            'refused' => count($results) - $queued,
            'results' => $results,
        ], $queued > 0 ? 200 : 422);
    }

    public function pushLog(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => TikTokGmvMaxPushService::recent(50)]);
    }
}
