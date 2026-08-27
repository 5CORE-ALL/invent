<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\Temu2CampaignReport;
use App\Support\TemuGoodsIdHelper;
use App\Services\Temu2AdsApiReportService;
use App\Services\Temu2AdsAutoPauseService;
use App\Services\Temu2ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Illuminate\Support\Facades\Log;

/**
 * Temu 2 Ads — tabulator view of temu2_campaign_reports (same features as /temu/ads).
 */
class Temu2AdsController extends Controller
{
    public function index()
    {
        return view('campaign.temu2.temu2-ads');
    }

    /**
     * Return stored API report rows for Tabulator.
     */
    public function getTemu2AdsData(Request $request)
    {
        $period = $request->query('period');

        return $this->getTemu2AdsDataFromCampaignReports(is_string($period) ? $period : null);
    }

    /**
     * Rows from existing temu2_campaign_reports (upload + API refresh both write here).
     */
    private function getTemu2AdsDataFromCampaignReports(?string $period)
    {
        $query = Temu2CampaignReport::query()->orderByDesc('id');
        if (in_array($period, ['L7', 'L30', 'L60'], true)) {
            $query->where('report_range', $period);
        }
        $records = $query->get();

        $l7ClicksByGoods = Temu2CampaignReport::query()
            ->where('report_range', 'L7')
            ->whereNotNull('goods_id')
            ->get(['goods_id', 'clicks'])
            ->keyBy(fn (Temu2CampaignReport $r) => (string) $r->goods_id);
        $l30ClicksByGoods = Temu2CampaignReport::query()
            ->where('report_range', 'L30')
            ->whereNotNull('goods_id')
            ->get(['goods_id', 'clicks'])
            ->keyBy(fn (Temu2CampaignReport $r) => (string) $r->goods_id);

        $skus = $records->pluck('sku')
            ->filter(fn ($s) => $s !== null && trim((string) $s) !== '')
            ->map(fn ($s) => (string) $s)
            ->unique()
            ->values()
            ->all();
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);
        $productMasterByNorm = $this->productMasterByNormalizedSku($skus);

        $rows = $records->map(function (Temu2CampaignReport $r) use ($l7ClicksByGoods, $l30ClicksByGoods, $shopifyByNorm, $productMasterByNorm) {
            $clicks = (int) ($r->clicks ?? 0);
            $orders = (int) ($r->sub_orders ?? 0);
            $gid = (string) $r->goods_id;
            $range = strtoupper((string) ($r->report_range ?? ''));
            $clicksL7 = $range === 'L7'
                ? $clicks
                : (int) (optional($l7ClicksByGoods->get($gid))->clicks ?? 0);
            $clicksL30 = $range === 'L30'
                ? $clicks
                : (int) (optional($l30ClicksByGoods->get($gid))->clicks ?? 0);

            $skuKey = ShopifySku::normalizeSkuForShopifyLookup((string) ($r->sku ?? ''));
            $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
            $productMaster = $skuKey !== '' ? ($productMasterByNorm[$skuKey] ?? null) : null;
            $soldQty = $shopify ? (float) ($shopify->quantity ?? $shopify->shopify_l30 ?? 0) : 0;
            $unitPrice = $shopify ? (float) ($shopify->price ?? $shopify->b2c_price ?? 0) : 0;
            $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $ovl30 = (int) round($soldQty);
            $dilPercent = $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0;
            $spend = (float) ($r->spend ?? 0);
            $sales = (float) ($r->base_price_sales ?? 0);
            $status = $r->displayAdStatus();

            return [
                'id' => $r->id,
                'goods_id' => $r->goods_id,
                'sku' => $r->sku,
                'image_path' => $this->productMasterImagePath($productMaster, $shopify),
                'inv' => $inv,
                'ovl30' => $ovl30,
                'dil_percent' => $dilPercent,
                'period' => $r->report_range,
                'impressions' => $r->impressions,
                'clicks' => $r->clicks,
                'clicks_l7' => $clicksL7,
                'clicks_l30' => $clicksL30,
                'ctr' => $r->ctr,
                'cvr' => $clicks > 0 ? round($orders / $clicks * 100, 2) : (float) ($r->cvr ?? 0),
                'cart_cnt' => $r->add_to_cart_number,
                'order_pay_cnt' => $orders,
                'order_pay_amt' => $sales,
                'all_sale' => round($soldQty * $unitPrice, 2),
                'ad_spend' => $spend,
                'spend_l1' => 0,
                'roas' => $r->roas,
                'acos' => $r->acos_ad,
                'ad_status' => $status,
                'success' => true,
                'error_msg' => null,
                'fetched_at' => optional($r->updated_at)->toDateTimeString(),
                'updated_at' => optional($r->updated_at)->toDateTimeString(),
                'raw_response' => null,
            ];
        })->values();

        $spendSum = round((float) $rows->sum(fn ($row) => (float) ($row['ad_spend'] ?? 0)), 2);
        $imprSum = (int) $rows->sum(fn ($row) => (int) ($row['impressions'] ?? 0));
        $clickSum = (int) $rows->sum(fn ($row) => (int) ($row['clicks'] ?? 0));

        try {
            $this->snapshotBadgeMetricsFromRows($rows, $period ?: 'ALL');
        } catch (\Throwable $e) {
            Log::warning('Temu2AdsController campaign-report snapshot failed', ['error' => $e->getMessage()]);
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
     * Trigger API fetch for a period (upserts temu2_campaign_reports).
     * For a single goods_id this runs inline; for all goods it shells the artisan command.
     */
    public function refresh(Request $request, Temu2AdsApiReportService $service)
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
            $exit = Artisan::call('temu2:fetch-ads-api-reports', [
                '--period' => $period,
            ]);
            $output = trim(Artisan::output());

            return response()->json([
                'success' => $exit === 0,
                'message' => $exit === 0
                    ? "Fetched Temu 2 ads API reports for {$period}"
                    : 'Fetch finished with errors — check logs',
                'output' => $output,
            ], $exit === 0 ? 200 : 500);
        } catch (\Throwable $e) {
            Log::error('Temu2AdsController::refresh failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a Temu search ad (temu.searchrec.ad.create).
     */
    public function createAd(Request $request, Temu2ApiService $temuApi)
    {
        $request->validate([
            'goods_id' => 'required|string|max:64',
            'budget' => 'required|numeric|min:1|max:10000',
            'roas' => 'required|numeric|min:0.1|max:12',
        ]);

        $goodsId = trim((string) $request->input('goods_id'));
        $budget = (float) $request->input('budget');
        $roas = (float) $request->input('roas');

        $result = $temuApi->createAd($goodsId, $budget, $roas);
        if ($result['ok']) {
            $statuses = $temuApi->queryAdStatuses([$goodsId]);
            $status = $statuses['statuses'][$goodsId] ?? 'Inactive';
            Temu2CampaignReport::where('goods_id', $goodsId)->update(['status' => $status]);
        }

        Log::info('Temu2AdsController::createAd', [
            'goods_id' => $goodsId,
            'budget' => $budget,
            'roas' => $roas,
            'ok' => $result['ok'],
            'error' => $result['error_msg'] ?? null,
        ]);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? "Created Temu 2 ad for goods {$goodsId} (budget \${$budget}, ROAS {$roas})"
                : ('Create failed: ' . ($result['error_msg'] ?? 'unknown error')),
            'result' => $result['result'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Create Temu search ads for many goods IDs (same budget / ROAS as Create Ad).
     */
    public function createAdsBulk(Request $request, Temu2ApiService $temuApi)
    {
        $request->validate([
            'goods_ids' => 'required|array|min:1|max:20',
            'goods_ids.*' => 'string|max:64',
            'budget' => 'required|numeric|min:1|max:10000',
            'roas' => 'required|numeric|min:0.1|max:12',
            'roas_by_goods' => 'nullable|array',
            'roas_by_goods.*' => 'numeric|min:0.1|max:12',
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
                $created[] = $goodsId;
            } else {
                $failed[] = [
                    'goods_id' => $goodsId,
                    'message' => (string) ($result['error_msg'] ?? 'unknown error'),
                ];
            }
        }

        if ($created !== []) {
            $statuses = $temuApi->queryAdStatuses($created);
            foreach ($created as $goodsId) {
                $status = $statuses['statuses'][$goodsId] ?? 'Inactive';
                Temu2CampaignReport::where('goods_id', $goodsId)->update(['status' => $status]);
            }
        }

        Log::info('Temu2AdsController::createAdsBulk', [
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
    public function pushRoasRule(Request $request, Temu2ApiService $temuApi)
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
            'roas_rule_slabs.*.target_roas' => 'nullable|numeric|min:-100|max:1000',
            'roas_rule_slabs.*.style' => 'nullable|in:red,green,pink,yellow',
        ]);

        if ($request->has('roas_rule_slabs')) {
            $roasRuleSlabs = $this->normalizeRoasRuleSlabs($request->input('roas_rule_slabs'));
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => 'temu2_ads_roas_rule_slabs'],
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

        Log::info('Temu2AdsController::pushRoasRule', [
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
    public function predictRoas(Request $request, Temu2ApiService $temuApi)
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
    public function refreshStatus(Request $request, Temu2AdsApiReportService $service)
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

    public function getColorRules(Temu2AdsAutoPauseService $pause)
    {
        return response()->json([
            'l7_clicks_red_below' => $pause->l7ClicksRedBelow(),
            'target_roas_bidding' => $pause->targetRoasBidding(),
            'pause_run_slabs' => $this->pauseRunSlabs(),
            'pause_run_inv_zero' => $this->pauseRunInvZero(),
            'roas_rule_slabs' => $this->roasRuleSlabs(),
            'auto_pause_cron' => $pause->cronEnabled(),
            'matching_active_ads' => 0,
        ]);
    }

    public function saveColorRules(Request $request, Temu2AdsAutoPauseService $pause)
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
            'roas_rule_slabs.*.target_roas' => 'nullable|numeric|min:-100|max:1000',
            'roas_rule_slabs.*.style' => 'nullable|in:red,green,pink,yellow',
        ]);

        $below = $request->has('l7_clicks_red_below')
            ? (int) $request->input('l7_clicks_red_below')
            : $pause->l7ClicksRedBelow();
        $targetRoas = $request->has('target_roas_bidding')
            ? round((float) $request->input('target_roas_bidding'), 1)
            : $pause->targetRoasBidding();

        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => 'temu2_ads_l7_clicks_red_below'],
            ['column_order' => [(string) $below]]
        );
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => 'temu2_ads_target_roas_bidding'],
            ['column_order' => [(string) $targetRoas]]
        );

        $slabs = $this->pauseRunSlabs();
        if ($request->has('pause_run_slabs')) {
            $slabs = $this->normalizePauseRunSlabs($request->input('pause_run_slabs'));
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => 'temu2_ads_pause_run_slabs'],
                ['column_order' => [json_encode($slabs)]]
            );
        }

        $invZero = $this->pauseRunInvZero();
        if ($request->has('pause_run_inv_zero')) {
            $invZero = $request->boolean('pause_run_inv_zero');
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => 'temu2_ads_pause_run_inv_zero'],
                ['column_order' => [$invZero ? '1' : '0']]
            );
        }

        $roasRuleSlabs = $this->roasRuleSlabs();
        if ($request->has('roas_rule_slabs')) {
            $roasRuleSlabs = $this->normalizeRoasRuleSlabs($request->input('roas_rule_slabs'));
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => 'temu2_ads_roas_rule_slabs'],
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
            'matching_active_ads' => 0,
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
            ->where('channel_name', 'temu2_ads_pause_run_slabs')
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
            ['spend_min' => 0.0, 'spend_max' => 0.0, 'roas_min' => null, 'roas_max' => null, 'target_roas' => 4.0, 'style' => 'red'],
            ['spend_min' => 0.01, 'spend_max' => 5.99, 'roas_min' => null, 'roas_max' => null, 'target_roas' => 5.0, 'style' => 'yellow'],
            ['spend_min' => 6.0, 'spend_max' => 9.0, 'roas_min' => null, 'roas_max' => null, 'target_roas' => 10.0, 'style' => 'green'],
            ['spend_min' => 9.01, 'spend_max' => null, 'roas_min' => null, 'roas_max' => null, 'target_roas' => 12.0, 'style' => 'pink'],
        ];
    }

    /**
     * @return array<int, array{spend_min: float|null, spend_max: float|null, roas_min: float|null, roas_max: float|null, style: string}>
     */
    private function roasRuleSlabs(): array
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu2_ads_roas_rule_slabs')
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
            if (! in_array($style, ['red', 'green', 'pink', 'yellow'], true)) {
                $style = 'red';
            }
            $spendMin = $money($item['spend_min'] ?? $item['min'] ?? null);
            $spendMax = $money($item['spend_max'] ?? $item['max'] ?? null);
            $roasMin = $money($item['roas_min'] ?? null);
            $roasMax = $money($item['roas_max'] ?? null);
            $targetRoas = null;
            if (isset($item['target_roas']) && $item['target_roas'] !== '' && is_numeric($item['target_roas'])) {
                $targetRoas = round((float) $item['target_roas'], 2);
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

        return $this->migrateLegacyRoasRuleSlabs($out);
    }

    /**
     * @param  array<int, array{spend_min: float|null, spend_max: float|null, roas_min: float|null, roas_max: float|null, target_roas: float|null, style: string}>  $slabs
     * @return array<int, array{spend_min: float|null, spend_max: float|null, roas_min: float|null, roas_max: float|null, target_roas: float|null, style: string}>
     */
    private function migrateLegacyRoasRuleSlabs(array $slabs): array
    {
        $first = $slabs[0] ?? null;
        if (
            $first
            && round((float) ($first['spend_min'] ?? -1), 2) === 0.0
            && round((float) ($first['spend_max'] ?? -1), 2) === 0.0
            && (float) ($first['target_roas'] ?? 0) === -3.0
        ) {
            $slabs[0]['target_roas'] = 4.0;
            $first = $slabs[0];
        }
        if (
            ! $first
            || round((float) ($first['spend_min'] ?? -1), 2) !== 0.0
            || round((float) ($first['spend_max'] ?? -1), 2) !== 5.99
        ) {
            return $slabs;
        }

        return array_merge([
            ['spend_min' => 0.0, 'spend_max' => 0.0, 'roas_min' => null, 'roas_max' => null, 'target_roas' => 4.0, 'style' => 'red'],
            [
                'spend_min' => 0.01,
                'spend_max' => 5.99,
                'roas_min' => $first['roas_min'] ?? null,
                'roas_max' => $first['roas_max'] ?? null,
                'target_roas' => 5.0,
                'style' => 'yellow',
            ],
        ], array_slice($slabs, 1));
    }

    /**
     * Pause Active ads that match the L7 clicks / T ROAS rule.
     */
    public function autoPause(Request $request, Temu2AdsAutoPauseService $pause)
    {
        $dryRun = $request->boolean('dry_run');
        $stats = $pause->pauseMatching($dryRun);

        $rule = "L7 click limit {$stats['l7_clicks_red_below']}";
        if ($dryRun) {
            $message = "{$stats['matched']} ads would change Active/Pause from {$rule}";
        } elseif ($stats['matched'] === 0) {
            $message = "No ads need an Active/Pause change from {$rule}";
        } else {
            $message = "Paused {$stats['paused']}, resumed {$stats['resumed']} ({$rule})";
            if ($stats['failed'] > 0) {
                $message .= ". Failed {$stats['failed']}";
            }
        }

        $success = $stats['failed'] === 0 || $stats['paused'] > 0 || ($stats['resumed'] ?? 0) > 0 || $stats['matched'] === 0;

        Log::info('Temu2AdsController::autoPause', [
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
    public function toggleAutoPauseCron(Request $request, Temu2AdsAutoPauseService $pause)
    {
        $enabled = $request->has('enabled')
            ? $request->boolean('enabled')
            : ! $pause->cronEnabled();
        $pause->setCronEnabled($enabled);

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled
                ? 'Auto Cron is ON. Only rows whose Active/Pause status changes from the click limit are pushed after L7 fetch and at 16:10 IST.'
                : 'Auto Cron is OFF. Scheduled and post-fetch pushes will not run.',
        ]);
    }

    /**
     * Pause or run one Temu ad from the Pause/Run toggle.
     */
    public function toggleAd(Request $request, Temu2ApiService $temuApi)
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
            Temu2CampaignReport::where('goods_id', $goodsId)->update([
                'status' => $action === 'run' ? 'Active' : 'Inactive',
            ]);
        }

        Log::info('Temu2AdsController::toggleAd', [
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

        $allowed = ['rows', 'impressions', 'clicks', 'spend', 'y_spend', 'create', 'pause', 'run', 'ctr', 'cvr', 'roas', 'acos', 'tacos'];
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
            'y_spend' => 'nullable|numeric',
            'create' => 'required|numeric',
            'pause' => 'required|numeric',
            'run' => 'required|numeric',
            'ctr' => 'required|numeric',
            'cvr' => 'nullable|numeric',
            'roas' => 'nullable|numeric',
            'acos' => 'nullable|numeric',
            'tacos' => 'nullable|numeric',
            'sold' => 'nullable|numeric',
            'sales' => 'nullable|numeric',
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
                'y_spend' => (float) ($validated['y_spend'] ?? 0),
                'create' => (float) $validated['create'],
                'pause' => (float) $validated['pause'],
                'run' => (float) $validated['run'],
                'ctr' => (float) $validated['ctr'],
                'cvr' => (float) ($validated['cvr'] ?? 0),
                'roas' => (float) ($validated['roas'] ?? 0),
                'acos' => (float) ($validated['acos'] ?? 0),
                'tacos' => (float) ($validated['tacos'] ?? 0),
                'sold' => (float) ($validated['sold'] ?? 0),
                'sales' => (float) ($validated['sales'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Temu2AdsController::saveBadgeSnapshot failed', ['error' => $e->getMessage()]);

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
        $ySpend = 0.0;
        $sold = 0.0;
        $sales = 0.0;
        $allSales = 0.0;
        $seenSku = [];

        foreach ($rows as $row) {
            $impr += (float) ($row['impressions'] ?? 0);
            $clicks += (float) ($row['clicks'] ?? 0);
            $spend += (float) ($row['ad_spend'] ?? 0);
            $ySpend += (float) ($row['spend_l1'] ?? 0);
            $sold += (float) ($row['order_pay_cnt'] ?? 0);
            $sales += (float) ($row['order_pay_amt'] ?? 0);
            $skuKey = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($skuKey === '' || ! isset($seenSku[$skuKey])) {
                if ($skuKey !== '') {
                    $seenSku[$skuKey] = true;
                }
                $allSales += (float) ($row['all_sale'] ?? 0);
            }
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
            'y_spend' => $ySpend,
            'create' => (float) $createN,
            'pause' => (float) $pauseN,
            'run' => (float) $runN,
            'ctr' => $impr > 0 ? round(($clicks / $impr) * 100, 2) : 0.0,
            'sold' => $sold,
            'sales' => $sales,
            'cvr' => $clicks > 0 ? round(($sold / $clicks) * 100, 2) : 0.0,
            'roas' => $spend > 0 ? round($sales / $spend, 2) : 0.0,
            'acos' => $sales > 0 ? round(($spend / $sales) * 100, 2) : ($spend > 0 ? 100.0 : 0.0),
            'tacos' => $allSales > 0 ? round(($spend / $allSales) * 100, 2) : ($spend > 0 ? 100.0 : 0.0),
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
            'channel_name' => 'temu2_ads_badge_history',
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
            ->where('channel_name', 'temu2_ads_badge_history')
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
            ->where('channel_name', 'temu2_ads_pause_run_inv_zero')
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

    /**
     * Upload Temu 2 ads export (xlsx/xls/csv/tsv/txt) into temu2_campaign_reports.
     * Kept for the existing upload route; the page itself is API-driven like Temu 1.
     */
    public function uploadCampaignReport(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'report_range' => 'required|in:L7,L30,L60',
            ]);

            $file = $request->file('file');
            $reportRange = $request->input('report_range');
            $ext = strtolower($file->getClientOriginalExtension());

            $isTsv = in_array($ext, ['txt', 'tsv', ''], true)
                || $this->detectTsv($file->getPathname());

            if ($isTsv) {
                [$headers, $dataRows] = $this->parseTsvFile($file->getPathname());
                $sheet = null;
            } else {
                $spreadsheet = IOFactory::load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rawHeaders = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1', null, true, false)[0] ?? [];
                $headers = array_map(fn ($h) => is_string($h) ? trim($h) : $h, $rawHeaders);
                $dataRows = null;
            }

            $goodsIdColIdx = array_search('Goods ID', $headers, true);
            if ($goodsIdColIdx === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'File must contain a column named exactly "Goods ID".',
                ], 422);
            }
            $skuColIdx = array_search('SKU', $headers, true);

            $normalizeCellValue = function ($value) {
                if ($value instanceof RichText) {
                    return trim($value->getPlainText());
                }
                if (is_object($value) && method_exists($value, '__toString')) {
                    return trim((string) $value);
                }
                if (is_string($value)) {
                    return trim($value);
                }

                return $value;
            };
            $parseCurrency = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if ($value === null || $value === '' || $value === '∞') {
                    return null;
                }

                return floatval(str_replace(['$', ','], '', (string) $value));
            };
            $parsePercent = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if ($value === null || $value === '' || $value === '∞') {
                    return null;
                }

                return floatval(str_replace('%', '', (string) $value));
            };
            $parseNumber = function ($value) use ($normalizeCellValue) {
                $value = $normalizeCellValue($value);
                if ($value === null || $value === '' || $value === '∞') {
                    return 0;
                }

                return floatval(str_replace([',', '%', '$'], '', (string) $value));
            };
            $col = function (array $rowData, array $aliases) {
                foreach ($aliases as $a) {
                    if (array_key_exists($a, $rowData) && $rowData[$a] !== null && $rowData[$a] !== '') {
                        return $rowData[$a];
                    }
                }

                return null;
            };

            $imported = 0;
            $skipped = 0;
            $rowErrors = 0;
            $firstRowError = null;
            $numCols = count($headers);
            $highestRow = 0;
            $allRows = $isTsv ? $dataRows : null;
            if (! $isTsv) {
                $highestRow = (int) $sheet->getHighestDataRow();
            }

            DB::beginTransaction();
            try {
                Temu2CampaignReport::where('report_range', $reportRange)->delete();

                $iterateFn = function () use ($isTsv, $allRows, &$sheet, $highestRow, $normalizeCellValue, $numCols) {
                    if ($isTsv) {
                        foreach ($allRows as $row) {
                            yield $row;
                        }
                    } else {
                        for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                            $raw = [];
                            for ($c = 1; $c <= $numCols; $c++) {
                                $raw[] = $normalizeCellValue($sheet->getCell(Coordinate::stringFromColumnIndex($c).$rowNum)->getValue());
                            }
                            yield ['_rowNum' => $rowNum, '_raw' => $raw];
                        }
                    }
                };

                foreach ($iterateFn() as $entry) {
                    if ($isTsv) {
                        $row = $entry;
                        if (stripos((string) ($row[0] ?? ''), 'Total') !== false) {
                            $skipped++;
                            continue;
                        }
                        $rowNum = null;
                    } else {
                        $rowNum = $entry['_rowNum'];
                        $row = $entry['_raw'];
                        $firstCell = $row[0] ?? null;
                        if ($firstCell !== null && $firstCell !== '' && stripos((string) $firstCell, 'Total') !== false) {
                            $skipped++;
                            continue;
                        }
                    }

                    if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                        $skipped++;
                        continue;
                    }

                    $rowData = @array_combine($headers, array_pad(array_slice($row, 0, $numCols), $numCols, null));
                    if (! is_array($rowData)) {
                        $skipped++;
                        continue;
                    }

                    if ($isTsv) {
                        $rawGoodsId = trim((string) ($row[$goodsIdColIdx] ?? ''));
                        $goodsIdNormalized = $rawGoodsId !== '' ? TemuGoodsIdHelper::normalizeKey($rawGoodsId) : null;
                    } else {
                        $goodsCell = $sheet->getCell(Coordinate::stringFromColumnIndex($goodsIdColIdx + 1).$rowNum);
                        $goodsIdNormalized = TemuGoodsIdHelper::fromSpreadsheetCell($goodsCell);
                    }

                    if (! $goodsIdNormalized) {
                        $skipped++;
                        continue;
                    }

                    $skuValue = $skuColIdx !== false
                        ? trim((string) ($row[$skuColIdx] ?? ''))
                        : null;

                    try {
                        Temu2CampaignReport::create([
                            'goods_name' => $rowData['Goods name'] ?? null,
                            'goods_id' => $goodsIdNormalized,
                            'sku' => $skuValue !== '' ? $skuValue : null,
                            'report_range' => $reportRange,
                            'spend' => $parseCurrency($col($rowData, ['Spend'])),
                            'base_price_sales' => $parseCurrency($col($rowData, ['Base Price Sales (Ad)', 'Base Price Sales (Overall)', 'Base price sales'])),
                            'roas' => $parseNumber($col($rowData, ['ROAS (Ad)', 'ROAS (Overall)', 'ROAS']) ?? 0),
                            'acos_ad' => $parsePercent($col($rowData, ['ACOS (Ad)', 'ACOS (Overall)', 'ACOS(AD)'])),
                            'cost_per_transaction' => $parseCurrency($col($rowData, ['Cost Per Order (Ad)', 'Cost Per Order (Overall)', 'Cost per transaction'])),
                            'sub_orders' => (int) str_replace(',', '', (string) ($col($rowData, ['Sub Order Count (Ad)', 'Sub Order Count (Overall)', 'Sub-Orders']) ?? 0)),
                            'items' => (int) str_replace(',', '', (string) ($col($rowData, ['Item Quantity (Ad)', 'Items (Overall)', 'Items']) ?? 0)),
                            'net_total_cost' => $parseCurrency($col($rowData, ['Net total cost'])),
                            'net_declared_sales' => $parseCurrency($col($rowData, ['Net Base Price Sales (Ad)', 'Net Base Price Sales (Overall)', 'Net declared sales'])),
                            'net_roas' => $parseNumber($col($rowData, ['Net ROAS (Ad)', 'Net ROAS (Overall)', 'Net advertising return on investment (ROAS)']) ?? 0),
                            'net_acos_ad' => $parsePercent($col($rowData, ['Net ACOS (Ad)', 'Net ACOS (Overall)', 'Net advertising cost ratio (advertising)'])),
                            'net_cost_per_transaction' => $parseCurrency($col($rowData, ['Net Cost Per Order (Ad)', 'Net Cost Per Order (Overall)', 'Net cost per transaction'])),
                            'net_orders' => (int) str_replace(',', '', (string) ($col($rowData, ['Net Sub Order Count (Ad)', 'Net Sub Order Count (Overall)', 'Net Orders']) ?? 0)),
                            'net_number_pieces' => (int) str_replace(',', '', (string) ($col($rowData, ['Net Item Quantity (Ad)', 'Net Items (Overall)', 'Net number of pieces']) ?? 0)),
                            'impressions' => (int) str_replace(',', '', (string) ($col($rowData, ['Impressions (Ad)', 'Impressions (Overall)', 'Impressions']) ?? 0)),
                            'clicks' => (int) str_replace(',', '', (string) ($col($rowData, ['Clicks (Ad)', 'Clicks (Overall)', 'Clicks']) ?? 0)),
                            'ctr' => $parsePercent($col($rowData, ['CTR (Ad)', 'CTR (Overall)', 'CTR'])),
                            'cvr' => $parsePercent($col($rowData, ['Conversion Rate (Ad)', 'CVR (Overall)', 'Conversion Rate (CVR)'])),
                            'add_to_cart_number' => (int) str_replace(',', '', (string) ($col($rowData, ['Add To Cart (Ad)', 'Add to cart count (Overall)', 'Add-to-cart number']) ?? 0)),
                        ]);
                        $imported++;
                    } catch (\Exception $e) {
                        $skipped++;
                        $rowErrors++;
                        if ($firstRowError === null) {
                            $firstRowError = $e->getMessage();
                        }
                        Log::warning('Temu 2 ads upload row failed: '.$e->getMessage());
                    }
                }

                if ($imported === 0) {
                    DB::rollBack();
                    $msg = "Imported 0 rows for {$reportRange}. Existing {$reportRange} data was kept.";
                    if ($firstRowError) {
                        $msg .= " First row error: {$firstRowError}";
                    } else {
                        $msg .= ' All rows were skipped (check file format/headers).';
                    }

                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                        'imported' => 0,
                        'skipped' => $skipped,
                        'row_errors' => $rowErrors,
                    ], 422);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$imported} records for {$reportRange}",
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'row_errors' => $rowErrors,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error uploading Temu 2 campaign report: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: '.$e->getMessage(),
            ], 500);
        }
    }

    private function detectTsv(string $path): bool
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return false;
        }
        $line = fgets($handle);
        fclose($handle);

        return $line !== false && substr_count($line, "\t") >= 3;
    }

    private function parseTsvFile(string $path): array
    {
        $headers = [];
        $dataRows = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [[], []];
        }

        $lineNum = 0;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            $cols = array_map('trim', explode("\t", $line));

            if ($lineNum === 0) {
                $headers = $cols;
            } else {
                if (stripos($cols[0] ?? '', 'Total') !== false && $lineNum === 1) {
                    $lineNum++;
                    continue;
                }
                $dataRows[] = $cols;
            }
            $lineNum++;
        }
        fclose($handle);

        return [$headers, $dataRows];
    }
}
