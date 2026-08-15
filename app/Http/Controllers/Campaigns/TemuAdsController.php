<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\TemuAdsApiReport;
use App\Services\TemuAdsApiReportService;
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

        $rows = $records->map(function (TemuAdsApiReport $r) {
            $clicks = (int) ($r->clicks ?? 0);
            $orders = (int) ($r->order_pay_cnt ?? 0);

            return [
                'id' => $r->id,
                'goods_id' => $r->goods_id,
                'sku' => $r->sku,
                'period' => $r->period,
                'impressions' => $r->impressions,
                'clicks' => $r->clicks,
                'ctr' => $r->ctr,
                'cvr' => $clicks > 0 ? round($orders / $clicks * 100, 2) : 0,
                'cart_cnt' => $r->cart_cnt,
                'order_pay_cnt' => $r->order_pay_cnt,
                'order_pay_amt' => $r->order_pay_amt,
                'ad_spend' => $r->ad_spend,
                'roas' => $r->roas,
                'acos' => $r->acos,
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
}
