<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\TemuAdsApiReport;
use App\Services\TemuAdsApiReportService;
use App\Services\TemuApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Temu Ads (API) — tabulator view of temu_ads_api_reports (full raw goods ad reports).
 */
class TemuAdsController extends Controller
{
    public function index()
    {
        return view('campaign.temu.temu-ads');
    }

    /**
     * Return stored API report rows for Tabulator.
     */
    public function getTemuAdsData(Request $request)
    {
        $query = TemuAdsApiReport::query()->orderByDesc('fetched_at')->orderByDesc('id');

        $period = $request->query('period');
        if (in_array($period, ['L7', 'L30', 'L60'], true)) {
            $query->where('period', $period);
        }

        $records = $query->get();
        $spendSum = round((float) $records->sum(fn (TemuAdsApiReport $r) => (float) ($r->ad_spend ?? 0)), 2);
        $imprSum = (int) $records->sum(fn (TemuAdsApiReport $r) => (int) ($r->impressions ?? 0));
        $clickSum = (int) $records->sum(fn (TemuAdsApiReport $r) => (int) ($r->clicks ?? 0));

        $l7ClicksByGoods = TemuAdsApiReport::query()
            ->where('period', 'L7')
            ->whereNotNull('goods_id')
            ->get(['goods_id', 'clicks'])
            ->keyBy(fn (TemuAdsApiReport $r) => (string) $r->goods_id);

        $rows = $records->map(function (TemuAdsApiReport $r) use ($l7ClicksByGoods) {
            $clicks = (int) ($r->clicks ?? 0);
            $orders = (int) ($r->order_pay_cnt ?? 0);
            $l7Row = $l7ClicksByGoods->get((string) $r->goods_id);
            $clicksL7 = $r->period === 'L7'
                ? $clicks
                : (int) (optional($l7Row)->clicks ?? 0);

            return [
                'id' => $r->id,
                'goods_id' => $r->goods_id,
                'sku' => $r->sku,
                'period' => $r->period,
                'impressions' => $r->impressions,
                'clicks' => $r->clicks,
                'clicks_l7' => $clicksL7,
                'ctr' => $r->ctr,
                'cvr' => $clicks > 0 ? round($orders / $clicks * 100, 2) : 0,
                'cart_cnt' => $r->cart_cnt,
                'order_pay_cnt' => $r->order_pay_cnt,
                'order_pay_amt' => $r->order_pay_amt,
                'ad_spend' => $r->ad_spend,
                'roas' => $r->roas,
                'acos' => $r->acos,
                'ad_status' => $r->displayAdStatus(),
                'success' => (bool) $r->success,
                'error_msg' => $r->error_msg,
                'fetched_at' => optional($r->fetched_at)->toDateTimeString(),
                'updated_at' => optional($r->updated_at)->toDateTimeString(),
                'raw_response' => $r->raw_response,
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'total' => $rows->count(),
            'spend_sum' => $spendSum,
            'impressions_sum' => $imprSum,
            'clicks_sum' => $clickSum,
        ]);
    }

    /**
     * Trigger API fetch for a period (stores full raw into temu_ads_api_reports).
     * For a single goods_id this runs inline; for all goods it shells the artisan command.
     */
    public function refresh(Request $request, TemuAdsApiReportService $service)
    {
        $request->validate([
            'period' => 'required|in:L7,L30,L60',
            'goods_id' => 'nullable|string|max:64',
        ]);

        $period = $request->input('period');
        $goodsId = $request->input('goods_id') ?: null;

        try {
            // Single goods: fetch immediately so the UI can refresh the row
            if ($goodsId) {
                $result = $service->fetchAndStore($goodsId, $period);

                return response()->json([
                    'success' => $result['ok'],
                    'message' => $result['ok']
                        ? "Fetched ads API data for goods {$goodsId} ({$period})"
                        : ('Fetch failed: ' . ($result['message'] ?? 'unknown error')),
                    'result' => $result,
                ], $result['ok'] ? 200 : 422);
            }

            // Full catalog: run artisan in background via exec if possible; else sync
            $exit = Artisan::call('temu:fetch-ads-api-reports', [
                '--period' => $period,
            ]);
            $output = trim(Artisan::output());

            return response()->json([
                'success' => $exit === 0,
                'message' => $exit === 0
                    ? "Fetched Temu ads API reports for {$period}"
                    : 'Fetch finished with errors — check logs',
                'output' => $output,
            ], $exit === 0 ? 200 : 500);
        } catch (\Throwable $e) {
            Log::error('TemuAdsController::refresh failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a Temu search ad (temu.searchrec.ad.create).
     */
    public function createAd(Request $request, TemuApiService $temuApi)
    {
        $request->validate([
            'goods_id' => 'required|string|max:64',
            'budget' => 'required|numeric|min:1|max:10000',
            'roas' => 'required|numeric|min:0.1|max:1000',
        ]);

        $goodsId = trim((string) $request->input('goods_id'));
        $budget = (float) $request->input('budget');
        $roas = (float) $request->input('roas');

        $result = $temuApi->createAd($goodsId, $budget, $roas);
        if ($result['ok']) {
            TemuAdsApiReport::where('goods_id', $goodsId)->update(['ad_status' => 'Active']);
        }

        Log::info('TemuAdsController::createAd', [
            'goods_id' => $goodsId,
            'budget' => $budget,
            'roas' => $roas,
            'ok' => $result['ok'],
            'error' => $result['error_msg'] ?? null,
        ]);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? "Created Temu ad for goods {$goodsId} (budget \${$budget}, ROAS {$roas})"
                : ('Create failed: ' . ($result['error_msg'] ?? 'unknown error')),
            'result' => $result['result'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Suggested ROAS from Temu (temu.searchrec.ad.roas.pred).
     */
    public function predictRoas(Request $request, TemuApiService $temuApi)
    {
        $request->validate([
            'goods_id' => 'required|string|max:64',
        ]);

        $goodsId = trim((string) $request->input('goods_id'));
        $result = $temuApi->predictAdRoas($goodsId);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? "ROAS prediction for goods {$goodsId}"
                : ('Predict failed: ' . ($result['error_msg'] ?? 'unknown error')),
            'result' => $result['result'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Refresh Active/Inactive from Temu ad detail API.
     */
    public function refreshStatus(Request $request, TemuAdsApiReportService $service)
    {
        $request->validate([
            'goods_id' => 'nullable|string|max:64',
        ]);

        $goodsId = $request->input('goods_id') ?: null;
        $stats = $service->refreshAdStatuses($goodsId);

        $error = $stats['error'] ?? null;
        $success = ($stats['ok'] > 0 || $stats['total'] === 0) && ($error === null || $stats['ok'] > 0);
        if ($stats['total'] === 0) {
            $message = 'No goods to refresh';
        } elseif ($stats['ok'] === 0 && $error) {
            $message = 'Status not sync: '.$error;
        } else {
            $message = "Updated ad status for {$stats['ok']}/{$stats['total']} goods";
            if ($error && $stats['fail'] > 0) {
                $message .= ' — some failed: '.$error;
            }
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'stats' => $stats,
        ], $success ? 200 : 422);
    }

    public function getColorRules()
    {
        return response()->json([
            'l7_clicks_red_below' => $this->l7ClicksRedBelow(),
            'target_roas_bidding' => $this->targetRoasBidding(),
        ]);
    }

    public function saveColorRules(Request $request)
    {
        $request->validate([
            'l7_clicks_red_below' => 'nullable|integer|min:0|max:100000',
            'target_roas_bidding' => 'nullable|numeric|min:0.1|max:1000',
        ]);

        $below = $request->has('l7_clicks_red_below')
            ? (int) $request->input('l7_clicks_red_below')
            : $this->l7ClicksRedBelow();
        $targetRoas = $request->has('target_roas_bidding')
            ? round((float) $request->input('target_roas_bidding'), 1)
            : $this->targetRoasBidding();

        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => 'temu_ads_l7_clicks_red_below'],
            ['column_order' => [(string) $below]]
        );
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => 'temu_ads_target_roas_bidding'],
            ['column_order' => [(string) $targetRoas]]
        );

        return response()->json([
            'success' => true,
            'l7_clicks_red_below' => $below,
            'target_roas_bidding' => $targetRoas,
        ]);
    }

    private function l7ClicksRedBelow(): int
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_l7_clicks_red_below')
            ->first();
        $n = isset($row->column_order[0]) ? (int) $row->column_order[0] : 70;

        return $n >= 0 ? $n : 70;
    }

    private function targetRoasBidding(): float
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_target_roas_bidding')
            ->first();
        $n = isset($row->column_order[0]) ? (float) $row->column_order[0] : 8.0;

        return $n >= 0.1 ? round($n, 1) : 8.0;
    }

}
