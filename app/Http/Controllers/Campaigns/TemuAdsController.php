<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuAdsApiReport;
use App\Services\TemuAdsApiReportService;
use App\Services\TemuAdsAutoPauseService;
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
    public function getTemuAdsData(Request $request, TemuAdsApiReportService $service)
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

        $l30ClicksByGoods = TemuAdsApiReport::query()
            ->where('period', 'L30')
            ->whereNotNull('goods_id')
            ->get(['goods_id', 'clicks'])
            ->keyBy(fn (TemuAdsApiReport $r) => (string) $r->goods_id);

        $spendL1ByGoods = [];
        if (! in_array($period, ['L7', 'L30'], true)) {
            $spendLookups = TemuAdsApiReport::query()
                ->whereIn('period', ['L7', 'L30'])
                ->whereNotNull('raw_response')
                ->get(['goods_id', 'period', 'raw_response']);
            foreach ($spendLookups as $rep) {
                $gidKey = (string) $rep->goods_id;
                $extracted = $service->lastDaySpendFromResult($rep->rawPayload);
                if ($extracted === null) {
                    continue;
                }
                if ($rep->period === 'L7' || ! isset($spendL1ByGoods[$gidKey])) {
                    $spendL1ByGoods[$gidKey] = $extracted;
                }
            }
        }

        $skus = $records->pluck('sku')
            ->filter(fn ($s) => $s !== null && trim((string) $s) !== '')
            ->map(fn ($s) => (string) $s)
            ->unique()
            ->values()
            ->all();
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);
        $productMasterByNorm = $this->productMasterByNormalizedSku($skus);

        $rows = $records->map(function (TemuAdsApiReport $r) use ($l7ClicksByGoods, $l30ClicksByGoods, $spendL1ByGoods, $shopifyByNorm, $productMasterByNorm, $service) {
            $clicks = (int) ($r->clicks ?? 0);
            $orders = (int) ($r->order_pay_cnt ?? 0);
            $gid = (string) $r->goods_id;
            $l7Row = $l7ClicksByGoods->get($gid);
            $l30Row = $l30ClicksByGoods->get($gid);
            $clicksL7 = $r->period === 'L7'
                ? $clicks
                : (int) (optional($l7Row)->clicks ?? 0);
            $clicksL30 = $r->period === 'L30'
                ? $clicks
                : (int) (optional($l30Row)->clicks ?? 0);
            $spendL1 = in_array((string) $r->period, ['L7', 'L30'], true)
                ? $service->lastDaySpendFromResult($r->rawPayload)
                : null;
            if ($spendL1 === null) {
                $spendL1 = $spendL1ByGoods[$gid] ?? 0;
            }

            $skuKey = ShopifySku::normalizeSkuForShopifyLookup((string) ($r->sku ?? ''));
            $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
            $productMaster = $skuKey !== '' ? ($productMasterByNorm[$skuKey] ?? null) : null;

            return [
                'id' => $r->id,
                'goods_id' => $r->goods_id,
                'sku' => $r->sku,
                'image_path' => $this->productMasterImagePath($productMaster, $shopify),
                'inv' => $shopify ? (int) ($shopify->inv ?? 0) : 0,
                'period' => $r->period,
                'impressions' => $r->impressions,
                'clicks' => $r->clicks,
                'clicks_l7' => $clicksL7,
                'clicks_l30' => $clicksL30,
                'ctr' => $r->ctr,
                'cvr' => $clicks > 0 ? round($orders / $clicks * 100, 2) : 0,
                'cart_cnt' => $r->cart_cnt,
                'order_pay_cnt' => $r->order_pay_cnt,
                'order_pay_amt' => $r->order_pay_amt,
                'ad_spend' => $r->ad_spend,
                'spend_l1' => $spendL1,
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

        try {
            $this->snapshotBadgeMetricsFromRows($rows, $period ?: 'ALL');
        } catch (\Throwable $e) {
            Log::warning('TemuAdsController badge snapshot failed', ['error' => $e->getMessage()]);
        }

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
     * Create Temu search ads for many goods IDs (same budget / ROAS as Create Ad).
     */
    public function createAdsBulk(Request $request, TemuApiService $temuApi)
    {
        $request->validate([
            'goods_ids' => 'required|array|min:1|max:20',
            'goods_ids.*' => 'string|max:64',
            'budget' => 'required|numeric|min:1|max:10000',
            'roas' => 'required|numeric|min:0.1|max:1000',
            'roas_by_goods' => 'nullable|array',
            'roas_by_goods.*' => 'numeric|min:0.1|max:1000',
        ]);

        @set_time_limit(180);

        $budget = (float) $request->input('budget');
        $roas = (float) $request->input('roas');
        $roasByGoods = $request->input('roas_by_goods', []);
        $ids = [];
        foreach ($request->input('goods_ids', []) as $id) {
            $gid = trim((string) $id);
            if ($gid !== '' && ! in_array($gid, $ids, true)) {
                $ids[] = $gid;
            }
        }

        $created = [];
        $failed = [];
        foreach ($ids as $i => $goodsId) {
            if ($i > 0) {
                usleep(250000);
            }
            $rowRoas = isset($roasByGoods[$goodsId]) ? (float) $roasByGoods[$goodsId] : $roas;
            $result = $temuApi->createAd($goodsId, $budget, $rowRoas);
            if ($result['ok'] ?? false) {
                TemuAdsApiReport::where('goods_id', $goodsId)->update(['ad_status' => 'Active']);
                $created[] = $goodsId;
            } else {
                $failed[] = [
                    'goods_id' => $goodsId,
                    'message' => (string) ($result['error_msg'] ?? 'unknown error'),
                ];
            }
        }

        Log::info('TemuAdsController::createAdsBulk', [
            'budget' => $budget,
            'roas' => $roas,
            'requested' => count($ids),
            'created' => count($created),
            'failed' => count($failed),
        ]);

        $ok = count($created);
        $fail = count($failed);
        $success = $ok > 0 || $fail === 0;

        return response()->json([
            'success' => $success,
            'message' => "Created {$ok}/".count($ids).' ads'
                .($fail > 0 ? ", failed {$fail}" : ''),
            'created' => $created,
            'failed' => $failed,
            'budget' => $budget,
            'roas' => $roas,
        ], $success ? 200 : 422);
    }

    /**
     * Push Target ROAS from the ROAS Rule slabs to existing Temu ads (ad.modify status 5).
     */
    public function pushRoasRule(Request $request, TemuApiService $temuApi)
    {
        $request->validate([
            'items' => 'required|array|min:1|max:50',
            'items.*.goods_id' => 'required|string|max:64',
            'items.*.roas' => 'required|numeric|min:0.1|max:1000',
            'roas_rule_slabs' => 'nullable|array',
            'roas_rule_slabs.*.spend_min' => 'nullable|numeric|min:0|max:100000',
            'roas_rule_slabs.*.spend_max' => 'nullable|numeric|min:0|max:100000',
            'roas_rule_slabs.*.roas_min' => 'nullable|numeric|min:0|max:1000',
            'roas_rule_slabs.*.roas_max' => 'nullable|numeric|min:0|max:1000',
            'roas_rule_slabs.*.target_roas' => 'nullable|numeric|min:0.1|max:1000',
            'roas_rule_slabs.*.style' => 'nullable|in:red,green,pink',
        ]);

        if ($request->has('roas_rule_slabs')) {
            $roasRuleSlabs = $this->normalizeRoasRuleSlabs($request->input('roas_rule_slabs'));
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => 'temu_ads_roas_rule_slabs'],
                ['column_order' => [json_encode($roasRuleSlabs)]]
            );
        }

        @set_time_limit(180);

        $updated = [];
        $failed = [];
        $seen = [];
        foreach ($request->input('items', []) as $i => $item) {
            $goodsId = trim((string) ($item['goods_id'] ?? ''));
            $roas = round((float) ($item['roas'] ?? 0), 1);
            if ($goodsId === '' || $roas < 0.1 || isset($seen[$goodsId])) {
                continue;
            }
            $seen[$goodsId] = true;
            if (count($updated) + count($failed) > 0) {
                usleep(200000);
            }

            $result = $temuApi->modifyAdRoas($goodsId, $roas);
            if ($result['ok'] ?? false) {
                $updated[] = [
                    'goods_id' => $goodsId,
                    'roas' => $roas,
                ];
            } else {
                $failed[] = [
                    'goods_id' => $goodsId,
                    'roas' => $roas,
                    'message' => (string) ($result['error_msg'] ?? 'unknown error'),
                ];
            }
        }

        $ok = count($updated);
        $fail = count($failed);
        $total = $ok + $fail;
        $success = $ok > 0 || $fail === 0;

        Log::info('TemuAdsController::pushRoasRule', [
            'requested' => $total,
            'updated' => $ok,
            'failed' => $fail,
        ]);

        return response()->json([
            'success' => $success,
            'message' => "Pushed ROAS for {$ok}/{$total} ads"
                .($fail > 0 ? ", failed {$fail}" : ''),
            'updated' => $updated,
            'failed' => $failed,
        ], $success ? 200 : 422);
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

    public function getColorRules(TemuAdsAutoPauseService $pause)
    {
        return response()->json([
            'l7_clicks_red_below' => $pause->l7ClicksRedBelow(),
            'target_roas_bidding' => $pause->targetRoasBidding(),
            'pause_run_slabs' => $this->pauseRunSlabs(),
            'pause_run_inv_zero' => $this->pauseRunInvZero(),
            'roas_rule_slabs' => $this->roasRuleSlabs(),
            'auto_pause_cron' => $pause->cronEnabled(),
            'matching_active_ads' => count($pause->matchingAds()),
        ]);
    }

    public function saveColorRules(Request $request, TemuAdsAutoPauseService $pause)
    {
        $request->validate([
            'l7_clicks_red_below' => 'nullable|integer|min:0|max:100000',
            'target_roas_bidding' => 'nullable|numeric|min:0.1|max:1000',
            'pause_run_slabs' => 'nullable|array',
            'pause_run_slabs.*.min' => 'required_with:pause_run_slabs|integer|min:0|max:1000000',
            'pause_run_slabs.*.max' => 'nullable|integer|min:0|max:1000000',
            'pause_run_slabs.*.action' => 'required_with:pause_run_slabs|in:pause,run',
            'pause_run_inv_zero' => 'nullable|boolean',
            'roas_rule_slabs' => 'nullable|array',
            'roas_rule_slabs.*.spend_min' => 'nullable|numeric|min:0|max:100000',
            'roas_rule_slabs.*.spend_max' => 'nullable|numeric|min:0|max:100000',
            'roas_rule_slabs.*.roas_min' => 'nullable|numeric|min:0|max:1000',
            'roas_rule_slabs.*.roas_max' => 'nullable|numeric|min:0|max:1000',
            'roas_rule_slabs.*.target_roas' => 'nullable|numeric|min:0.1|max:1000',
            'roas_rule_slabs.*.style' => 'nullable|in:red,green,pink',
        ]);

        $below = $request->has('l7_clicks_red_below')
            ? (int) $request->input('l7_clicks_red_below')
            : $pause->l7ClicksRedBelow();
        $targetRoas = $request->has('target_roas_bidding')
            ? round((float) $request->input('target_roas_bidding'), 1)
            : $pause->targetRoasBidding();

        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => 'temu_ads_l7_clicks_red_below'],
            ['column_order' => [(string) $below]]
        );
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => 'temu_ads_target_roas_bidding'],
            ['column_order' => [(string) $targetRoas]]
        );

        $slabs = $this->pauseRunSlabs();
        if ($request->has('pause_run_slabs')) {
            $slabs = $this->normalizePauseRunSlabs($request->input('pause_run_slabs'));
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => 'temu_ads_pause_run_slabs'],
                ['column_order' => [json_encode($slabs)]]
            );
        }

        $invZero = $this->pauseRunInvZero();
        if ($request->has('pause_run_inv_zero')) {
            $invZero = $request->boolean('pause_run_inv_zero');
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => 'temu_ads_pause_run_inv_zero'],
                ['column_order' => [$invZero ? '1' : '0']]
            );
        }

        $roasRuleSlabs = $this->roasRuleSlabs();
        if ($request->has('roas_rule_slabs')) {
            $roasRuleSlabs = $this->normalizeRoasRuleSlabs($request->input('roas_rule_slabs'));
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => 'temu_ads_roas_rule_slabs'],
                ['column_order' => [json_encode($roasRuleSlabs)]]
            );
        }

        return response()->json([
            'success' => true,
            'l7_clicks_red_below' => $below,
            'target_roas_bidding' => $targetRoas,
            'pause_run_slabs' => $slabs,
            'pause_run_inv_zero' => $invZero,
            'roas_rule_slabs' => $roasRuleSlabs,
            'auto_pause_cron' => $pause->cronEnabled(),
            'matching_active_ads' => count($pause->matchingAds()),
        ]);
    }

    /**
     * @return array<int, array{min: int, max: int|null, action: string}>
     */
    private function defaultPauseRunSlabs(): array
    {
        return [
            ['min' => 0, 'max' => 69, 'action' => 'run'],
            ['min' => 70, 'max' => null, 'action' => 'pause'],
        ];
    }

    /**
     * @return array<int, array{min: int, max: int|null, action: string}>
     */
    private function pauseRunSlabs(): array
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_pause_run_slabs')
            ->first();
        $raw = $row && is_array($row->column_order) ? $row->column_order : [];

        return $this->normalizePauseRunSlabs($raw) ?: $this->defaultPauseRunSlabs();
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{min: int, max: int|null, action: string}>
     */
    private function normalizePauseRunSlabs($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }
        if (count($raw) === 1 && is_string($raw[0] ?? null)) {
            $decoded = json_decode((string) $raw[0], true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $min = max(0, (int) ($item['min'] ?? 0));
            $max = array_key_exists('max', $item) && $item['max'] !== null && $item['max'] !== ''
                ? (int) $item['max']
                : null;
            if ($max !== null && $max < $min) {
                $max = $min;
            }
            $action = (($item['action'] ?? '') === 'run') ? 'run' : 'pause';
            $out[] = ['min' => $min, 'max' => $max, 'action' => $action];
        }
        usort($out, fn ($a, $b) => $a['min'] <=> $b['min']);

        return $out;
    }

    /**
     * @return array<int, array{spend_min: float|null, spend_max: float|null, roas_min: float|null, roas_max: float|null, style: string}>
     */
    private function defaultRoasRuleSlabs(): array
    {
        return [
            ['spend_min' => 0.0, 'spend_max' => 5.99, 'roas_min' => null, 'roas_max' => null, 'target_roas' => 8.0, 'style' => 'red'],
            ['spend_min' => 6.0, 'spend_max' => 9.0, 'roas_min' => null, 'roas_max' => null, 'target_roas' => 8.0, 'style' => 'green'],
            ['spend_min' => 9.01, 'spend_max' => null, 'roas_min' => null, 'roas_max' => null, 'target_roas' => 8.0, 'style' => 'pink'],
        ];
    }

    /**
     * @return array<int, array{spend_min: float|null, spend_max: float|null, roas_min: float|null, roas_max: float|null, style: string}>
     */
    private function roasRuleSlabs(): array
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_roas_rule_slabs')
            ->first();
        $raw = $row && is_array($row->column_order) ? $row->column_order : [];

        return $this->normalizeRoasRuleSlabs($raw) ?: $this->defaultRoasRuleSlabs();
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{spend_min: float|null, spend_max: float|null, roas_min: float|null, roas_max: float|null, style: string}>
     */
    private function normalizeRoasRuleSlabs($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }
        if (count($raw) === 1 && is_string($raw[0] ?? null)) {
            $decoded = json_decode((string) $raw[0], true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        $money = function ($v): ?float {
            if ($v === null || $v === '') {
                return null;
            }
            if (! is_numeric($v)) {
                return null;
            }
            $n = round((float) $v, 2);

            return $n < 0 ? null : $n;
        };

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $style = strtolower((string) ($item['style'] ?? 'red'));
            if (! in_array($style, ['red', 'green', 'pink'], true)) {
                $style = 'red';
            }
            $spendMin = $money($item['spend_min'] ?? $item['min'] ?? null);
            $spendMax = $money($item['spend_max'] ?? $item['max'] ?? null);
            $roasMin = $money($item['roas_min'] ?? null);
            $roasMax = $money($item['roas_max'] ?? null);
            $targetRoas = $money($item['target_roas'] ?? null);
            if ($targetRoas !== null && $targetRoas < 0.1) {
                $targetRoas = 0.1;
            }
            if ($spendMin === null && $spendMax === null && $roasMin === null && $roasMax === null && $targetRoas === null) {
                continue;
            }
            if ($spendMax !== null && $spendMin !== null && $spendMax < $spendMin) {
                $spendMax = $spendMin;
            }
            if ($roasMax !== null && $roasMin !== null && $roasMax < $roasMin) {
                $roasMax = $roasMin;
            }
            $out[] = [
                'spend_min' => $spendMin,
                'spend_max' => $spendMax,
                'roas_min' => $roasMin,
                'roas_max' => $roasMax,
                'target_roas' => $targetRoas,
                'style' => $style,
            ];
        }

        return $out;
    }

    /**
     * Pause Active ads that match the L7 clicks / Stop ROAS rule.
     */
    public function autoPause(Request $request, TemuAdsAutoPauseService $pause)
    {
        $dryRun = $request->boolean('dry_run');
        $stats = $pause->pauseMatching($dryRun);

        $rule = "L7 < {$stats['l7_clicks_red_below']} → ROAS {$stats['target_roas_bidding']}";
        if ($dryRun) {
            $message = "{$stats['matched']} Active ads match {$rule}";
        } elseif ($stats['matched'] === 0) {
            $message = "No Active ads match {$rule}";
        } else {
            $message = "Paused {$stats['paused']}/{$stats['matched']} ads ({$rule})";
            if ($stats['failed'] > 0) {
                $message .= ". Failed {$stats['failed']}";
            }
        }

        $success = $stats['failed'] === 0 || $stats['paused'] > 0 || $stats['matched'] === 0;

        Log::info('TemuAdsController::autoPause', [
            'dry_run' => $dryRun,
            'matched' => $stats['matched'],
            'paused' => $stats['paused'],
            'failed' => $stats['failed'],
        ]);

        return response()->json([
            'success' => $success,
            'message' => $message,
            'stats' => $stats,
        ], $success ? 200 : 422);
    }

    /**
     * Toggle the daily auto-pause cron (L7 fetch + 16:10 IST job).
     */
    public function toggleAutoPauseCron(Request $request, TemuAdsAutoPauseService $pause)
    {
        $enabled = $request->has('enabled')
            ? $request->boolean('enabled')
            : ! $pause->cronEnabled();
        $pause->setCronEnabled($enabled);

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled
                ? 'Daily auto-pause cron is ON. Matching ads will pause after the L7 fetch and at 16:10 IST.'
                : 'Daily auto-pause cron is PAUSED. Scheduled and post-fetch auto-pause will not run.',
        ]);
    }

    /**
     * Pause or run one Temu ad from the Pause/Run toggle.
     */
    public function toggleAd(Request $request, TemuApiService $temuApi)
    {
        $request->validate([
            'goods_id' => 'required|string|max:64',
            'action' => 'required|in:pause,run',
        ]);

        $goodsId = trim((string) $request->input('goods_id'));
        $action = (string) $request->input('action');
        $result = $action === 'run'
            ? $temuApi->resumeAd($goodsId)
            : $temuApi->pauseAd($goodsId);

        if ($result['ok'] ?? false) {
            TemuAdsApiReport::where('goods_id', $goodsId)->update([
                'ad_status' => $action === 'run' ? 'Active' : 'Inactive',
            ]);
        }

        Log::info('TemuAdsController::toggleAd', [
            'goods_id' => $goodsId,
            'action' => $action,
            'ok' => $result['ok'] ?? false,
            'error' => $result['error_msg'] ?? null,
        ]);

        $verb = $action === 'run' ? 'Run' : 'Pause';

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'action' => $action,
            'ad_status' => $action === 'run' ? 'Active' : 'Inactive',
            'message' => ($result['ok'] ?? false)
                ? "{$verb} sent to Temu for goods {$goodsId}"
                : ("{$verb} failed: " . ($result['error_msg'] ?? 'unknown error')),
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    /**
     * Daily badge history for the Temu Ads summary strip (Chart.js dot graph).
     */
    public function badgeHistory(Request $request)
    {
        $metric = strtolower((string) $request->query('metric', ''));
        $days = max(1, min(180, (int) $request->query('days', 30)));
        $period = strtoupper((string) $request->query('period', 'L30'));
        if ($period === '') {
            $period = 'ALL';
        }

        $allowed = ['rows', 'impressions', 'clicks', 'spend', 'create', 'pause', 'run', 'ctr', 'cvr'];
        if (! in_array($metric, $allowed, true)) {
            return response()->json([
                'success' => false,
                'error' => 'Unknown metric',
                'data' => [],
            ], 422);
        }

        $hist = $this->badgeHistoryStore($period);
        ksort($hist);
        $from = now()->subDays($days - 1)->toDateString();
        $data = [];
        foreach ($hist as $date => $metrics) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            if ($date < $from) {
                continue;
            }
            if (! is_array($metrics)) {
                continue;
            }
            $data[] = [
                'date' => date('M d', strtotime($date)),
                'value' => (float) ($metrics[$metric] ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'days' => $days,
            'period' => $period,
            'data' => $data,
        ]);
    }

    /**
     * Persist today's badge totals (same numbers the summary strip shows).
     */
    public function saveBadgeSnapshot(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|numeric',
            'impressions' => 'required|numeric',
            'clicks' => 'required|numeric',
            'spend' => 'required|numeric',
            'create' => 'required|numeric',
            'pause' => 'required|numeric',
            'run' => 'required|numeric',
            'ctr' => 'required|numeric',
            'cvr' => 'nullable|numeric',
            'sold' => 'nullable|numeric',
            'period' => 'nullable|string|max:8',
        ]);

        $period = strtoupper((string) ($validated['period'] ?? 'L30'));
        if ($period === '') {
            $period = 'ALL';
        }

        try {
            $this->storeBadgeSnapshot($period, [
                'rows' => (float) $validated['rows'],
                'impressions' => (float) $validated['impressions'],
                'clicks' => (float) $validated['clicks'],
                'spend' => (float) $validated['spend'],
                'create' => (float) $validated['create'],
                'pause' => (float) $validated['pause'],
                'run' => (float) $validated['run'],
                'ctr' => (float) $validated['ctr'],
                'cvr' => (float) ($validated['cvr'] ?? 0),
                'sold' => (float) ($validated['sold'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TemuAdsController::saveBadgeSnapshot failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Snapshot failed'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    private function snapshotBadgeMetricsFromRows($rows, string $period): void
    {
        $createN = 0;
        $pauseN = 0;
        $runN = 0;
        $impr = 0.0;
        $clicks = 0.0;
        $spend = 0.0;
        $sold = 0.0;

        foreach ($rows as $row) {
            $impr += (float) ($row['impressions'] ?? 0);
            $clicks += (float) ($row['clicks'] ?? 0);
            $spend += (float) ($row['ad_spend'] ?? 0);
            $sold += (float) ($row['order_pay_cnt'] ?? 0);
            if (($row['ad_status'] ?? '') === 'No ad') {
                $createN++;
            }
            $action = $this->actionFromPauseRunSlabs((int) ($row['clicks_l7'] ?? 0), (int) ($row['inv'] ?? 0));
            if ($action === 'run') {
                $runN++;
            } else {
                $pauseN++;
            }
        }

        $this->storeBadgeSnapshot($period, [
            'rows' => (float) $rows->count(),
            'impressions' => $impr,
            'clicks' => $clicks,
            'spend' => $spend,
            'create' => (float) $createN,
            'pause' => (float) $pauseN,
            'run' => (float) $runN,
            'ctr' => $impr > 0 ? round(($clicks / $impr) * 100, 2) : 0.0,
            'sold' => $sold,
            'cvr' => $clicks > 0 ? round(($sold / $clicks) * 100, 2) : 0.0,
        ]);
    }

    /**
     * @param  array<string, float|int>  $metrics
     */
    private function storeBadgeSnapshot(string $period, array $metrics): void
    {
        $period = strtoupper($period !== '' ? $period : 'ALL');
        $today = now()->toDateString();
        $row = ChannelTabulatorColumnSetting::query()->firstOrNew([
            'channel_name' => 'temu_ads_badge_history',
        ]);
        $hist = is_array($row->visibility) ? $row->visibility : [];
        if (! isset($hist[$period]) || ! is_array($hist[$period])) {
            $hist[$period] = [];
        }
        $hist[$period][$today] = $metrics;
        ksort($hist[$period]);
        if (count($hist[$period]) > 180) {
            $hist[$period] = array_slice($hist[$period], -180, 180, true);
        }
        $row->visibility = $hist;
        if ($row->column_order === null) {
            $row->column_order = [];
        }
        $row->save();
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function badgeHistoryStore(string $period): array
    {
        $period = strtoupper($period !== '' ? $period : 'ALL');
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_badge_history')
            ->first();
        $hist = is_array($row?->visibility) ? $row->visibility : [];
        $bucket = $hist[$period] ?? [];

        return is_array($bucket) ? $bucket : [];
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, ProductMaster>
     */
    private function productMasterByNormalizedSku(array $skus): array
    {
        $wanted = [];
        foreach ($skus as $sku) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($key !== '') {
                $wanted[$key] = true;
            }
        }
        if ($wanted === []) {
            return [];
        }

        $out = [];
        foreach (ProductMaster::query()->whereIn('sku', $skus)->get(['id', 'sku', 'Values', 'main_image']) as $pm) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
            if ($key !== '' && isset($wanted[$key]) && ! isset($out[$key])) {
                $out[$key] = $pm;
                unset($wanted[$key]);
            }
        }
        if ($wanted === []) {
            return $out;
        }

        ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$out, &$wanted) {
                foreach ($rows as $pm) {
                    $key = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
                    if ($key !== '' && isset($wanted[$key]) && ! isset($out[$key])) {
                        $out[$key] = $pm;
                        unset($wanted[$key]);
                    }
                }

                return count($wanted) > 0;
            });

        return $out;
    }

    private function productMasterImagePath(?ProductMaster $productMaster, ?ShopifySku $shopify): ?string
    {
        $values = is_array($productMaster?->Values)
            ? $productMaster->Values
            : (is_string($productMaster?->Values) ? (json_decode((string) $productMaster->Values, true) ?: []) : []);
        $local = trim((string) ($values['image_path'] ?? $productMaster?->main_image ?? ''));
        $shopifyImage = trim((string) ($shopify?->image_src ?? ''));

        if ($local !== '' && (str_contains($local, 'storage/') || str_contains($local, '/storage/'))) {
            return '/'.ltrim($local, '/');
        }
        if ($shopifyImage !== '') {
            return $shopifyImage;
        }
        if ($local === '') {
            return null;
        }
        if (str_starts_with($local, 'http://') || str_starts_with($local, 'https://')) {
            return $local;
        }

        return '/'.ltrim($local, '/');
    }

    private function pauseRunInvZero(): bool
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_pause_run_inv_zero')
            ->first();
        if (! $row) {
            return true;
        }
        $raw = is_array($row->column_order) ? ($row->column_order[0] ?? '1') : '1';

        return ! in_array(strtolower((string) $raw), ['0', 'false', 'off'], true);
    }

    private function actionFromPauseRunSlabs(int $clicksL7, int $inv = 0): string
    {
        if ($inv <= 0) {
            return 'pause';
        }

        foreach ($this->pauseRunSlabs() as $slab) {
            $min = (int) ($slab['min'] ?? 0);
            $max = $slab['max'] ?? null;
            if ($clicksL7 >= $min && ($max === null || $clicksL7 <= (int) $max)) {
                return (($slab['action'] ?? '') === 'run') ? 'run' : 'pause';
            }
        }

        return $clicksL7 < 70 ? 'run' : 'pause';
    }
}
