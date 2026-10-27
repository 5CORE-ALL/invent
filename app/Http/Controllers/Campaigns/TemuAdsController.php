<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\MarketplaceDailyMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuAdsApiReport;
use App\Models\TemuOrder;
use App\Models\TemuMetric;
use App\Services\TemuAdCreateRejectService;
use App\Services\TemuAdsApiReportService;
use App\Services\TemuApiService;
use App\Services\TemuShopifySalesService;
use App\Services\TemuSoldRoasRuleService;
use App\Support\TemuAdsBadgeHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Temu Ads (API) — tabulator view of temu_ads_api_reports (full raw goods ad reports).
 */
class TemuAdsController extends Controller
{
    public function index(TemuApiService $temuApi)
    {
        return view('campaign.temu.temu-ads', [
            'periodRanges' => $this->periodRangeLabels($temuApi->adsPeriodRanges()),
        ]);
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

        $hasCreateReject = $this->reportHasColumn('ad_create_reject');
        $listColumns = [
            'id', 'goods_id', 'sku', 'period', 'start_ts', 'end_ts',
            'impressions', 'clicks', 'ctr',
            'cart_cnt', 'order_pay_cnt', 'order_pay_amt', 'ad_spend', 'roas', 'acos',
            'ad_status', 'success', 'error_msg', 'fetched_at', 'updated_at',
        ];
        if ($this->reportHasColumn('sku_id')) {
            $listColumns[] = 'sku_id';
        }
        if ($hasCreateReject) {
            $listColumns[] = 'ad_create_reject';
        }
        $records = $this->applyTemuSkuNames($this->dropGoodsNotOnTemu($query->get($listColumns)));
        $windowPeriod = in_array($period, ['L7', 'L30', 'L60'], true) ? $period : 'L30';
        $spendWindow = TemuAdsApiReport::latestWindow($windowPeriod);
        $inSpendWindow = function (TemuAdsApiReport $r) use ($period, $spendWindow): bool {
            if (! in_array($period, ['L7', 'L30', 'L60'], true) || ! $spendWindow) {
                return true;
            }

            return (int) $r->start_ts === $spendWindow['start_ts'];
        };
        $windowRecords = $records->filter($inSpendWindow);
        $uniqueGoods = $windowRecords->unique(fn (TemuAdsApiReport $r) => (string) $r->goods_id);
        $spendSum = round((float) $uniqueGoods->sum(fn (TemuAdsApiReport $r) => (float) ($r->ad_spend ?? 0)), 2);
        $imprSum = (int) $uniqueGoods->sum(fn (TemuAdsApiReport $r) => (int) ($r->impressions ?? 0));
        $clickSum = (int) $uniqueGoods->sum(fn (TemuAdsApiReport $r) => (int) ($r->clicks ?? 0));
        $mallPeriod = in_array($period, ['L7', 'L30', 'L60'], true) ? $period : 'L30';
        $mall = null;
        try {
            $mall = $service->mallPeriodMetrics($mallPeriod);
        } catch (\Throwable $e) {
            Log::warning('Temu ads mall spend lookup failed', ['error' => $e->getMessage()]);
        }
        if (is_array($mall) && isset($mall['spend'])) {
            $spendSum = round((float) $mall['spend'], 2);
            $imprSum = (int) ($mall['impressions'] ?? $imprSum);
            $clickSum = (int) ($mall['clicks'] ?? $clickSum);
        }

        $l7ClicksByGoods = TemuAdsApiReport::query()
            ->where('period', 'L7')
            ->whereNotNull('goods_id')
            ->selectRaw('goods_id, MAX(clicks) as clicks, MAX(impressions) as impressions')
            ->groupBy('goods_id')
            ->get()
            ->keyBy(fn ($r) => (string) $r->goods_id);

        $l30ClicksByGoods = TemuAdsApiReport::query()
            ->where('period', 'L30')
            ->whereNotNull('goods_id')
            ->selectRaw('goods_id, MAX(clicks) as clicks')
            ->groupBy('goods_id')
            ->get()
            ->keyBy(fn ($r) => (string) $r->goods_id);

        $spendLookupPeriods = in_array($period, ['L7', 'L30'], true) ? [$period] : ['L7', 'L30'];
        $spendL1ByGoods = $this->lastDaySpendByGoodsId($service, $spendLookupPeriods);

        $skus = $records->pluck('sku')
            ->filter(fn ($s) => $s !== null && trim((string) $s) !== '')
            ->map(fn ($s) => (string) $s)
            ->unique()
            ->values()
            ->all();
        $skuLookup = $this->skuLookupVariants($skus);
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skuLookup, false);
        $productMasterByNorm = $this->productMasterByNormalizedSku($skuLookup);

        $soldL7BySku = TemuSoldRoasRuleService::soldL7BySku();

        // Listing status per goods_id (temu.local.sku.list.retrieve → temu_metrics.listing_status).
        // A goods is "active" if any of its SKUs is active on Temu; otherwise "inactive".
        $listingByGoods = [];
        if (Schema::hasTable('temu_metrics') && Schema::hasColumn('temu_metrics', 'listing_status')) {
            TemuMetric::query()
                ->whereNotNull('goods_id')
                ->where('goods_id', '!=', '')
                ->whereNotNull('listing_status')
                ->get(['goods_id', 'listing_status'])
                ->each(function ($m) use (&$listingByGoods) {
                    $gid = (string) $m->goods_id;
                    $st = strtolower(trim((string) $m->listing_status));
                    if ($st === 'active' || ! isset($listingByGoods[$gid])) {
                        $listingByGoods[$gid] = $st;
                    }
                });
        }

        $rows = $records->map(function (TemuAdsApiReport $r) use ($listingByGoods, $soldL7BySku, $l7ClicksByGoods, $l30ClicksByGoods, $spendL1ByGoods, $shopifyByNorm, $productMasterByNorm, $hasCreateReject, $inSpendWindow) {
            $clicks = (int) ($r->clicks ?? 0);
            $orders = (int) ($r->order_pay_cnt ?? 0);
            $gid = (string) $r->goods_id;
            $l7Row = $l7ClicksByGoods->get($gid);
            $l30Row = $l30ClicksByGoods->get($gid);
            $clicksL7 = $r->period === 'L7'
                ? $clicks
                : (int) (optional($l7Row)->clicks ?? 0);
            $impressionsL7 = $r->period === 'L7'
                ? (int) ($r->impressions ?? 0)
                : (int) (optional($l7Row)->impressions ?? 0);
            $clicksL30 = $r->period === 'L30'
                ? $clicks
                : (int) (optional($l30Row)->clicks ?? 0);
            $spendL1 = $spendL1ByGoods[$gid] ?? 0;

            $skuKey = ShopifySku::normalizeSkuForShopifyLookup((string) ($r->sku ?? ''));
            $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
            $productMaster = $skuKey !== '' ? ($productMasterByNorm[$skuKey] ?? null) : null;
            $soldQty = $shopify ? (float) ($shopify->quantity ?? $shopify->shopify_l30 ?? 0) : 0;
            $unitPrice = $shopify ? (float) ($shopify->price ?? $shopify->b2c_price ?? 0) : 0;
            $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $ovl30 = (int) round($soldQty);
            $dilPercent = $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0;

            return [
                'id' => $r->id,
                'goods_id' => $r->goods_id,
                'parent' => $this->productMasterParent($productMaster),
                'sku' => $r->getAttribute('temu_sku_name') ?: $r->sku,
                'sku_id' => $r->sku_id ?? '',
                'image_path' => $this->productMasterImagePath($productMaster, $shopify),
                'inv' => $inv,
                'ovl30' => $ovl30,
                'dil_percent' => $dilPercent,
                'period' => $r->period,
                'start_ts' => $r->start_ts,
                'in_window' => $inSpendWindow($r),
                'impressions' => $r->impressions,
                'impressions_l7' => $impressionsL7,
                'clicks' => $r->clicks,
                'clicks_l7' => $clicksL7,
                'clicks_l30' => $clicksL30,
                'sold_l7' => (int) ($soldL7BySku[$skuKey] ?? 0),
                'ctr' => $r->ctr,
                'ctr_l7' => $impressionsL7 > 0 ? round($clicksL7 / $impressionsL7 * 100, 4) : 0.0,
                'cvr' => $clicks > 0 ? round($orders / $clicks * 100, 2) : 0,
                'cart_cnt' => $r->cart_cnt,
                'order_pay_cnt' => $r->order_pay_cnt,
                'order_pay_amt' => $r->order_pay_amt,
                'all_sale' => round($soldQty * $unitPrice, 2),
                'ad_spend' => $r->ad_spend,
                'spend_l1' => $spendL1,
                'roas' => $r->roas,
                'acos' => $r->acos,
                'ad_status' => $r->displayAdStatus(),
                'listing_status' => $listingByGoods[$gid] ?? '',
                // A create-ad reject is only meaningful while the ad does not exist on Temu.
                // If the ad is now Active/Inactive/Paused, the stored reject is stale — hide it.
                'ad_create_reject' => ($hasCreateReject && ! in_array((string) $r->ad_status, ['Active', 'Inactive', 'Paused'], true))
                    ? trim((string) ($r->ad_create_reject ?? ''))
                    : '',
                'success' => (bool) $r->success,
                'error_msg' => $r->error_msg,
                'fetched_at' => optional($r->fetched_at)->toDateTimeString(),
                'updated_at' => optional($r->updated_at)->toDateTimeString(),
                'is_parent' => false,
                'has_raw' => true,
            ];
        })->values();

        $rows = $this->fillMissingParentsOnAdsRows($rows);
        $rows = $this->unifyParentPerGoods($rows);
        $rows = $this->appendParentRows($rows);

        $tacosPeriod = in_array($period, ['L7', 'L30', 'L60'], true) ? $period : 'L30';
        $channelSales = $this->temuChannelSalesForPeriod($tacosPeriod);
        $tacosSpend = (is_array($mall) && isset($mall['spend']))
            ? $spendSum
            : ($tacosPeriod === 'L30' && ! in_array($period, ['L7', 'L30', 'L60'], true)
                ? round((float) TemuAdsApiReport::query()->inLatestWindow('L30')->sum('ad_spend'), 2)
                : $spendSum);

        $tacos = $channelSales > 0
            ? round(($tacosSpend / $channelSales) * 100, 2)
            : ($tacosSpend > 0 ? 100.0 : 0.0);

        try {
            $this->snapshotBadgeMetricsFromRows($rows, $period ?: 'ALL', $channelSales, $tacos, is_array($mall) ? $mall : null);
        } catch (\Throwable $e) {
            Log::warning('TemuAdsController badge snapshot failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'data' => $rows,
            'total' => $rows->count(),
            'spend_sum' => $spendSum,
            'impressions_sum' => $imprSum,
            'clicks_sum' => $clickSum,
            'channel_sales' => $channelSales,
            'tacos' => $tacos,
            'mall' => $mall,
        ]);
    }

    public function getRaw(int $id)
    {
        $row = TemuAdsApiReport::query()->find($id, ['id', 'goods_id', 'period', 'raw_response']);
        if (! $row) {
            return response()->json(['success' => false, 'raw_response' => null], 404);
        }

        return response()->json([
            'success' => true,
            'raw_response' => $row->raw_response,
            'goods_id' => $row->goods_id,
            'period' => $row->period,
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
        Cache::forget('temu_ads_spend_l1_'.$period);
        Cache::forget('temu_ads_spend_l1_L7_L30');
        Cache::forget('temu_ads_tcos_7');
        Cache::forget('temu_ads_tcos_30');
        Cache::forget('temu_ads_tcos_60');

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
    public function createAd(Request $request, TemuApiService $temuApi, TemuAdCreateRejectService $rejects)
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
        $rejectInfo = ['rejected' => false, 'task_id' => null, 'title' => null];
        if ($result['ok']) {
            $rejects->clearReject($goodsId);
            $statuses = $temuApi->queryAdStatuses([$goodsId]);
            $status = $statuses['statuses'][$goodsId] ?? 'Inactive';
            TemuAdsApiReport::where('goods_id', $goodsId)->update(['ad_status' => $status]);
            Temu1MissingAdsController::forgetMissingTotalCache();
        } else {
            $sku = $this->skuForGoodsId($goodsId);
            $rejectInfo = $rejects->handleFailedCreate(
                $goodsId,
                $sku !== null ? (string) $sku : null,
                (string) ($result['error_msg'] ?? ''),
                $result['error_code'] ?? null
            );
        }

        Log::info('TemuAdsController::createAd', [
            'goods_id' => $goodsId,
            'budget' => $budget,
            'roas' => $roas,
            'ok' => $result['ok'],
            'error' => $result['error_msg'] ?? null,
            'rejected' => $rejectInfo['rejected'],
            'task_id' => $rejectInfo['task_id'],
        ]);

        $failMessage = 'Create failed: '.($result['error_msg'] ?? 'unknown error');
        if (! empty($rejectInfo['task_id']) && ! empty($rejectInfo['title'])) {
            $failMessage .= ' Task created: '.$rejectInfo['title'];
        }

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok']
                ? "Created Temu ad for goods {$goodsId} (budget \${$budget}, ROAS {$roas})"
                : $failMessage,
            'result' => $result['result'] ?? null,
            'rejected' => $rejectInfo['rejected'],
            'task_id' => $rejectInfo['task_id'],
            'task_title' => $rejectInfo['title'],
        ], $result['ok'] ? 200 : 422);
    }

    /**
     * Create Temu search ads for many goods IDs (same budget / ROAS as Create Ad).
     */
    public function createAdsBulk(Request $request, TemuApiService $temuApi, TemuAdCreateRejectService $rejects)
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

        $skuByGoods = $this->skusForGoodsIds($ids);

        $created = [];
        $failed = [];
        foreach ($ids as $i => $goodsId) {
            if ($i > 0) {
                usleep(250000);
            }
            $rowRoas = isset($roasByGoods[$goodsId]) ? (float) $roasByGoods[$goodsId] : $roas;
            $result = $temuApi->createAd($goodsId, $budget, $rowRoas);
            if ($result['ok'] ?? false) {
                $rejects->clearReject($goodsId);
                $created[] = $goodsId;
            } else {
                $rejectInfo = $rejects->handleFailedCreate(
                    $goodsId,
                    $skuByGoods->get($goodsId),
                    (string) ($result['error_msg'] ?? ''),
                    $result['error_code'] ?? null
                );
                $failed[] = [
                    'goods_id' => $goodsId,
                    'message' => (string) ($result['error_msg'] ?? 'unknown error'),
                    'rejected' => $rejectInfo['rejected'],
                    'task_id' => $rejectInfo['task_id'],
                    'task_title' => $rejectInfo['title'],
                ];
            }
        }

        if ($created !== []) {
            $statuses = $temuApi->queryAdStatuses($created);
            foreach ($created as $goodsId) {
                $status = $statuses['statuses'][$goodsId] ?? 'Inactive';
                TemuAdsApiReport::where('goods_id', $goodsId)->update(['ad_status' => $status]);
            }
            Temu1MissingAdsController::forgetMissingTotalCache();
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

        $allowed = ['rows', 'impressions', 'clicks', 'spend', 'y_spend', 'sales', 'create', 'ctr', 'cvr', 'roas', 'acos', 'tacos'];
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
                'ctr' => (float) $validated['ctr'],
                'cvr' => (float) ($validated['cvr'] ?? 0),
                'roas' => (float) ($validated['roas'] ?? 0),
                'acos' => (float) ($validated['acos'] ?? 0),
                'tacos' => (float) ($validated['tacos'] ?? 0),
                'sold' => (float) ($validated['sold'] ?? 0),
                'sales' => (float) ($validated['sales'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TemuAdsController::saveBadgeSnapshot failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Snapshot failed'], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Temu channel sales for TCOS / TAcos% — same Ads% denominator as /temu-decrease
     * and all-marketplace-master (temu_orders, not Shopify L30 × Shopify price).
     */
    private function temuChannelSalesForPeriod(?string $period): float
    {
        $days = match (strtoupper((string) ($period ?: 'L30'))) {
            'L7' => 7,
            'L60' => 60,
            default => 30,
        };

        return (float) Cache::remember('temu_ads_tcos_'.$days, 120, function () use ($days) {
            if ($days === 30 && Schema::hasTable('marketplace_daily_metrics')) {
                $row = MarketplaceDailyMetric::query()
                    ->where('channel', 'Temu')
                    ->latest('date')
                    ->first(['total_sales']);
                $sales = round((float) ($row->total_sales ?? 0), 2);
                if ($sales > 0) {
                    return $sales;
                }
            }

            try {
                $start = Carbon::now()->subDays($days)->startOfDay();
                $end = Carbon::now()->endOfDay();
                $m = TemuShopifySalesService::computeMetricsFromOrders($start, $end);
                $sales = (float) ($m['sales'] ?? 0);
                if ($sales > 0) {
                    return round($sales, 2);
                }
            } catch (\Throwable $e) {
                Log::warning('Temu ads TCOS sales lookup failed', ['error' => $e->getMessage()]);
            }

            return 0.0;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    private function snapshotBadgeMetricsFromRows($rows, string $period, float $channelSales = 0.0, ?float $tacos = null, ?array $mall = null): void
    {
        $createN = 0;
        $impr = 0.0;
        $clicks = 0.0;
        $spend = 0.0;
        $ySpend = 0.0;
        $sold = 0.0;
        $sales = 0.0;
        $tacosSpend = 0.0;
        $useL30SpendForTacos = strtoupper($period) === 'ALL';

        $seenGoods = [];
        foreach ($rows as $row) {
            if (! empty($row['is_parent'])) {
                continue;
            }
            $gid = (string) ($row['goods_id'] ?? '');
            $firstGoods = $gid === '' || ! isset($seenGoods[$gid]);
            if ($gid !== '') {
                $seenGoods[$gid] = true;
            }
            $inWindow = ! array_key_exists('in_window', $row) || $row['in_window'];
            if ($inWindow && $firstGoods) {
                $impr += (float) ($row['impressions'] ?? 0);
                $clicks += (float) ($row['clicks'] ?? 0);
                $rowSpend = (float) ($row['ad_spend'] ?? 0);
                $spend += $rowSpend;
                if (! $useL30SpendForTacos || strtoupper((string) ($row['period'] ?? '')) === 'L30') {
                    $tacosSpend += $rowSpend;
                }
                $ySpend += (float) ($row['spend_l1'] ?? 0);
                $sold += (float) ($row['order_pay_cnt'] ?? 0);
                $sales += (float) ($row['order_pay_amt'] ?? 0);
            }
            if (! $firstGoods) {
                continue;
            }
            if (($row['ad_status'] ?? '') === 'No ad') {
                $createN++;
            }
        }

        if (is_array($mall) && isset($mall['spend'])) {
            $spend = (float) $mall['spend'];
            $impr = (float) ($mall['impressions'] ?? $impr);
            $clicks = (float) ($mall['clicks'] ?? $clicks);
            $sold = (float) ($mall['sold'] ?? $sold);
            $sales = (float) ($mall['sales'] ?? $sales);
            $tacosSpend = $spend;
        }

        if ($tacos === null) {
            $tacos = $channelSales > 0
                ? round(($tacosSpend / $channelSales) * 100, 2)
                : ($tacosSpend > 0 ? 100.0 : 0.0);
        }

        $this->storeBadgeSnapshot($period, [
            'rows' => (float) collect($rows)->reject(fn ($r) => ! empty($r['is_parent']))->count(),
            'impressions' => $impr,
            'clicks' => $clicks,
            'spend' => $spend,
            'y_spend' => $ySpend,
            'create' => (float) $createN,
            'ctr' => $impr > 0 ? round(($clicks / $impr) * 100, 2) : 0.0,
            'sold' => $sold,
            'sales' => $sales,
            'cvr' => $clicks > 0 ? round(($sold / $clicks) * 100, 2) : 0.0,
            'roas' => $spend > 0 ? round($sales / $spend, 2) : 0.0,
            'acos' => $sales > 0 ? round(($spend / $sales) * 100, 2) : ($spend > 0 ? 100.0 : 0.0),
            'tacos' => $tacos,
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
        $hist = TemuAdsBadgeHistory::ensureYSpendInDollars(
            is_array($row->visibility) ? $row->visibility : []
        );
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
        $raw = is_array($row?->visibility) ? $row->visibility : [];
        $hist = TemuAdsBadgeHistory::ensureYSpendInDollars($raw);
        if ($row && empty($raw[TemuAdsBadgeHistory::Y_SPEND_DOLLARS_FLAG])) {
            $row->visibility = $hist;
            $row->save();
        }
        $bucket = $hist[$period] ?? [];

        return is_array($bucket) ? $bucket : [];
    }

    /**
     * Ads Rules: L7 Sold slabs (per parent) with a Target ROAS input per slab, plus Auto run state.
     */
    public function getSoldRoasRules(TemuSoldRoasRuleService $rules)
    {
        $slabs = $rules->slabs();
        $parents = $rules->parents();
        // Today's per-slab parent counts feed the colour history dot.
        try {
            $rules->snapshot($rules->counts($slabs, $parents));
        } catch (\Throwable $e) {
            Log::warning('Temu ads rules snapshot failed: '.$e->getMessage());
        }

        return response()->json([
            'slabs' => $slabs,
            'labels' => array_map([TemuSoldRoasRuleService::class, 'slabLabel'], $slabs),
            'parents' => array_map(fn ($p) => (int) $p['sold_l7'], array_values($parents)),
            'auto_run' => $rules->autoRunEnabled(),
            'last_run' => $rules->lastRun(),
        ]);
    }

    public function saveSoldRoasRules(Request $request, TemuSoldRoasRuleService $rules)
    {
        $request->validate([
            'slabs' => 'required|array|min:1|max:50',
            'slabs.*.sold_min' => 'nullable|numeric|min:0|max:1000000',
            'slabs.*.sold_max' => 'nullable|numeric|min:0|max:1000000',
            'slabs.*.target_roas' => 'nullable|numeric|min:0|max:1000',
        ]);

        $slabs = $rules->normalizeSlabs($request->input('slabs'));
        $rules->saveSlabs($slabs);
        $rules->snapshot($rules->counts($slabs, $rules->parents()));

        return response()->json([
            'success' => true,
            'slabs' => $slabs,
            'labels' => array_map([TemuSoldRoasRuleService::class, 'slabLabel'], $slabs),
        ]);
    }

    public function setSoldRoasAutoRun(Request $request, TemuSoldRoasRuleService $rules)
    {
        $request->validate(['enabled' => 'required|boolean']);
        $rules->setAutoRun($request->boolean('enabled'));

        return response()->json(['success' => true, 'auto_run' => $rules->autoRunEnabled()]);
    }

    /**
     * Run Auto run right now (same as the daily `temu:auto-run-ads` command).
     */
    public function runSoldRoasRules(TemuSoldRoasRuleService $rules)
    {
        @set_time_limit(0);
        $stats = $rules->run(false);

        return response()->json([
            'success' => $stats['failed'] === 0,
            'stats' => collect($stats)->except(['actions', 'errors'])->all(),
            'errors' => array_slice($stats['errors'], 0, 10),
        ]);
    }

    public function soldRoasHistory(Request $request, TemuSoldRoasRuleService $rules)
    {
        $metric = (string) $request->query('metric', '');
        $days = max(1, min(180, (int) $request->query('days', 30)));

        return response()->json([
            'success' => true,
            'metric' => $metric,
            'days' => $days,
            'data' => $rules->history($metric, $days),
        ]);
    }

    private function skuForGoodsId(string $goodsId): ?string
    {
        $sku = TemuAdsApiReport::query()
            ->where('goods_id', $goodsId)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->value('sku');
        if ($sku) {
            return (string) $sku;
        }

        $sku = TemuMetric::query()
            ->where('goods_id', $goodsId)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->value('sku');

        return $sku !== null ? (string) $sku : null;
    }

    /**
     * @param  array<int, string>  $ids
     * @return \Illuminate\Support\Collection<string, string>
     */
    private function skusForGoodsIds(array $ids)
    {
        $skuByGoods = TemuAdsApiReport::query()
            ->whereIn('goods_id', $ids)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['goods_id', 'sku'])
            ->mapWithKeys(fn (TemuAdsApiReport $r) => [(string) $r->goods_id => (string) $r->sku]);

        $missing = array_values(array_filter($ids, fn ($id) => ! $skuByGoods->has((string) $id)));
        if ($missing === []) {
            return $skuByGoods;
        }

        $fromMetrics = TemuMetric::query()
            ->whereIn('goods_id', $missing)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['goods_id', 'sku']);
        foreach ($fromMetrics as $row) {
            $skuByGoods->put((string) $row->goods_id, (string) $row->sku);
        }

        return $skuByGoods;
    }

    /**
     * One information_schema read per request instead of repeated hasColumn().
     */
    private function reportHasColumn(string $name): bool
    {
        static $cols = null;
        if ($cols === null) {
            $cols = array_flip(Schema::getColumnListing('temu_ads_api_reports'));
        }

        return isset($cols[$name]);
    }

    /**
     * Expand SKU strings for whereIn (exact + trailing-dot trim). Never scans whole tables.
     *
     * @param  array<int, string>  $skus
     * @return array<int, string>
     */
    private function skuLookupVariants(array $skus): array
    {
        $out = [];
        foreach ($skus as $sku) {
            $s = trim((string) $sku);
            if ($s === '') {
                continue;
            }
            $out[$s] = true;
            $stripped = rtrim($s, '.');
            if ($stripped !== '' && $stripped !== $s) {
                $out[$stripped] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * One raw_response per goods_id (prefer L7, then L30) instead of every SKU variant.
     *
     * @param  array<int, string>  $periods
     * @return array<string, float>
     */
    private function lastDaySpendByGoodsId(TemuAdsApiReportService $service, array $periods): array
    {
        $cacheKey = 'temu_ads_spend_l1_'.implode('_', $periods);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $out = [];
        foreach ($periods as $period) {
            $idByGoods = TemuAdsApiReport::query()
                ->where('period', $period)
                ->whereNotNull('goods_id')
                ->whereNotNull('raw_response')
                ->selectRaw('goods_id, MIN(id) as id')
                ->groupBy('goods_id')
                ->pluck('id', 'goods_id');
            $need = [];
            foreach ($idByGoods as $gid => $id) {
                if (! isset($out[(string) $gid])) {
                    $need[] = (int) $id;
                }
            }
            foreach (array_chunk($need, 80) as $ids) {
                foreach (TemuAdsApiReport::query()->whereIn('id', $ids)->get(['id', 'goods_id', 'raw_response']) as $rep) {
                    $extracted = $service->lastDaySpendFromResult($rep->rawPayload);
                    $rep->raw_response = null;
                    if ($extracted !== null) {
                        $out[(string) $rep->goods_id] = $extracted;
                    }
                }
            }
        }

        Cache::put($cacheKey, $out, 180);

        return $out;
    }

    private function productMasterByNormalizedSku(array $skus): array
    {
        $wanted = [];
        foreach ($skus as $sku) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($key !== '') {
                $wanted[$key] = true;
            }
        }
        if ($wanted === [] || $skus === []) {
            return [];
        }

        $out = [];
        foreach (ProductMaster::query()->whereIn('sku', $skus)->get(['id', 'sku', 'parent', 'Values', 'main_image']) as $pm) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
            if ($key !== '' && isset($wanted[$key]) && ! isset($out[$key])) {
                $out[$key] = $pm;
            }
        }

        return $out;
    }

    /**
     * Copy a known parent onto sibling SKUs of the same goods_id.
     */
    private function fillMissingParentsOnAdsRows($rows)
    {
        $rows = collect($rows);
        $byGoods = [];
        foreach ($rows as $row) {
            $gid = (string) ($row['goods_id'] ?? '');
            $parent = trim((string) ($row['parent'] ?? ''));
            if ($gid !== '' && $parent !== '' && ! isset($byGoods[$gid])) {
                $byGoods[$gid] = $parent;
            }
        }
        if ($byGoods === []) {
            return $rows->values();
        }

        return $rows->map(function (array $row) use ($byGoods): array {
            if (trim((string) ($row['parent'] ?? '')) !== '') {
                return $row;
            }
            $gid = (string) ($row['goods_id'] ?? '');
            if ($gid !== '' && isset($byGoods[$gid])) {
                $row['parent'] = $byGoods[$gid];
            }

            return $row;
        })->values();
    }

    /**
     * Hide goods that are no longer in Temu's Seller Center product list (removed / ended).
     * Source: cache from temu:sync-goods-sku-names. If the cache is missing or looks incomplete
     * nothing is hidden, so a failed sync can never blank the page.
     *
     * @param  \Illuminate\Support\Collection<int, TemuAdsApiReport>  $records
     */
    private function dropGoodsNotOnTemu($records)
    {
        $live = Cache::get(\App\Console\Commands\SyncTemuGoodsSkuNames::LIVE_GOODS_CACHE_KEY);
        if (! is_array($live) || count($live) < 50) {
            return $records;
        }

        return $records
            ->filter(fn (TemuAdsApiReport $r) => array_key_exists((string) $r->goods_id, $live))
            ->values();
    }

    /**
     * Show only what Temu itself holds for each goods_id (cache from temu:sync-goods-sku-names):
     * - a variation whose skuId Temu no longer lists, or lists with an EMPTY seller SKU name, is dropped;
     * - the SKU shown is Temu's exact outSkuSn.
     * Goods Temu has not been synced for, or whose variations are all unnamed, are left untouched.
     *
     * @param  \Illuminate\Support\Collection<int, TemuAdsApiReport>  $records
     */
    private function applyTemuSkuNames($records)
    {
        $map = Cache::get(\App\Console\Commands\SyncTemuGoodsSkuNames::CACHE_KEY);
        if (! is_array($map) || $map === []) {
            return $records;
        }

        return $records
            ->filter(function (TemuAdsApiReport $r) use ($map): bool {
                $gid = (string) $r->goods_id;
                $skuId = trim((string) ($r->sku_id ?? ''));
                $temuSkus = $map[$gid]['skus'] ?? null;
                if ($skuId === '' || ! is_array($temuSkus)) {
                    return true; // not synced / no skuId to compare: leave as is
                }
                if (count(array_filter($temuSkus, fn ($n) => $n !== '')) === 0) {
                    return true; // Temu names none of them: do not blank the goods
                }

                return ($temuSkus[$skuId] ?? '') !== '';
            })
            ->each(function (TemuAdsApiReport $r) use ($map): void {
                $name = $map[(string) $r->goods_id]['skus'][trim((string) ($r->sku_id ?? ''))] ?? '';
                if ($name !== '') {
                    // Display name only. Inv / Parent / Sold lookups keep using our own SKU.
                    $r->setAttribute('temu_sku_name', $name);
                }
            })
            ->values();
    }

    /**
     * One Temu goods_id is one listing (one set of ad metrics). When its SKUs map to different
     * Product Master parents, the grid would show the same ad numbers under two PARENT rows.
     * Collapse them to the parent Temu itself uses for that listing (goods.detail outGoodsSn).
     */
    private function unifyParentPerGoods($rows)
    {
        $rows = collect($rows);

        $parentsByGoods = [];
        foreach ($rows as $row) {
            $gid = (string) ($row['goods_id'] ?? '');
            $parent = trim((string) ($row['parent'] ?? ''));
            if ($gid === '' || $parent === '') {
                continue;
            }
            $parentsByGoods[$gid][mb_strtolower($parent)] = $parent;
        }

        $canonical = [];
        foreach ($parentsByGoods as $gid => $parents) {
            if (count($parents) < 2) {
                continue;
            }
            $canonical[$gid] = $this->canonicalParentForGoods((string) $gid, array_values($parents));
        }
        if ($canonical === []) {
            return $rows->values();
        }

        return $rows->map(function (array $row) use ($canonical): array {
            $gid = (string) ($row['goods_id'] ?? '');
            if ($gid !== '' && isset($canonical[$gid]) && trim((string) ($row['parent'] ?? '')) !== '') {
                $row['parent'] = $canonical[$gid];
            }

            return $row;
        })->values();
    }

    /**
     * @param  list<string>  $parents  Distinct Product Master parents seen for this goods_id
     */
    private function canonicalParentForGoods(string $goodsId, array $parents): string
    {
        sort($parents, SORT_NATURAL | SORT_FLAG_CASE);
        $fallback = $parents[0];

        try {
            $cacheKey = 'temu_ads_out_goods_sn_'.$goodsId;
            $sn = (string) Cache::get($cacheKey, '');
            if ($sn === '') {
                $sn = (string) (app(TemuApiService::class)->fetchOutGoodsSn($goodsId) ?? '');
                if ($sn !== '') {
                    Cache::put($cacheKey, $sn, 86400); // only cache real answers
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Temu outGoodsSn lookup failed', ['goods_id' => $goodsId, 'error' => $e->getMessage()]);

            return $fallback;
        }

        $sn = trim((string) preg_replace('/^PARENT\s+/i', '', trim((string) $sn)));
        if ($sn === '') {
            return $fallback;
        }
        foreach ($parents as $parent) {
            if (strcasecmp(trim($parent), $sn) === 0) {
                return $parent;
            }
        }

        return $fallback;
    }

    /**
     * Insert a PARENT {name} summary row above each Product Master parent + goods + period group.
     * Ad metrics stay goods-level (copied from the first child). Inv / Ovl30 / Dil% roll up from children.
     */
    private function appendParentRows($rows)
    {
        $names = collect($rows)
            ->pluck('parent')
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $parentImages = [];
        if ($names !== []) {
            $parentSkus = array_map(fn (string $name) => 'PARENT '.$name, $names);
            $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($parentSkus, false);
            $productMasterByNorm = [];
            foreach (ProductMaster::query()->whereIn('sku', $parentSkus)->get(['id', 'sku', 'parent', 'Values', 'main_image']) as $pm) {
                $key = ShopifySku::normalizeSkuForShopifyLookup((string) $pm->sku);
                if ($key !== '' && ! isset($productMasterByNorm[$key])) {
                    $productMasterByNorm[$key] = $pm;
                }
            }
            foreach ($parentSkus as $parentSku) {
                $skuKey = ShopifySku::normalizeSkuForShopifyLookup($parentSku);
                $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
                $productMaster = $skuKey !== '' ? ($productMasterByNorm[$skuKey] ?? null) : null;
                $parentImages[$parentSku] = $this->productMasterImagePath($productMaster, $shopify);
            }
        }

        return self::synthesizeParentAdsRows($rows, $parentImages);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  array<string, string|null>  $parentImages
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function synthesizeParentAdsRows($rows, array $parentImages = [])
    {
        $children = collect($rows)->map(function (array $row): array {
            $row['is_parent'] = false;

            return $row;
        });

        $parentRows = $children
            ->filter(fn (array $row) => trim((string) ($row['parent'] ?? '')) !== '')
            ->groupBy(function (array $row) {
                return trim((string) ($row['parent'] ?? ''))."\n".(string) ($row['goods_id'] ?? '')."\n".(string) ($row['period'] ?? '');
            })
            ->map(function ($kids) use ($parentImages) {
                $first = $kids->first();
                $parent = trim((string) ($first['parent'] ?? ''));
                $parentSku = 'PARENT '.$parent;
                $inv = (int) $kids->sum(fn (array $k) => (int) ($k['inv'] ?? 0));
                $ovl30 = (int) $kids->sum(fn (array $k) => (int) ($k['ovl30'] ?? 0));
                $allSale = round((float) $kids->sum(fn (array $k) => (float) ($k['all_sale'] ?? 0)), 2);
                $image = $parentImages[$parentSku] ?? ($first['image_path'] ?? null);

                $gid = (string) ($first['goods_id'] ?? '');
                $period = (string) ($first['period'] ?? '');

                return array_merge($first, [
                    'id' => 'p-'.$gid.'-'.$period,
                    'raw_id' => $first['id'] ?? null,
                    'is_parent' => true,
                    'sku' => $parentSku,
                    'sku_id' => '',
                    'child_count' => $kids->count(),
                    'sold_l7' => $kids->contains(fn (array $k) => array_key_exists('sold_l7', $k))
                        ? (int) $kids->sum(fn (array $k) => (int) ($k['sold_l7'] ?? 0))
                        : null,
                    'image_path' => $image,
                    'inv' => $inv,
                    'ovl30' => $ovl30,
                    'dil_percent' => $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0,
                    'all_sale' => $allSale,
                    'has_raw' => true,
                ]);
            })
            ->values();

        return $children
            ->concat($parentRows)
            ->sortBy(function (array $row) {
                $parent = mb_strtolower((string) ($row['parent'] ?? ''));
                $rank = ! empty($row['is_parent']) ? '0' : '1';
                $sku = mb_strtolower((string) ($row['sku'] ?? ''));

                return $parent."\n".$rank."\n".$sku;
            })
            ->values();
    }

    private function productMasterParent(?ProductMaster $productMaster): string
    {
        $parent = trim((string) ($productMaster?->parent ?? ''));
        if ($parent === '') {
            $values = is_array($productMaster?->Values)
                ? $productMaster->Values
                : (is_string($productMaster?->Values) ? (json_decode((string) $productMaster->Values, true) ?: []) : []);
            $parent = trim((string) ($values['Parent'] ?? $values['parent'] ?? ''));
        }
        if ($parent !== '') {
            $parent = trim((string) preg_replace('/^PARENT\s+/i', '', $parent));
        }

        return $parent;
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

    /**
     * Same calendar windows as Temu Seller Center Last 7 / Last 30 (US Pacific).
     *
     * @param  array<string, array{startTs: int, endTs: int}>  $ranges
     * @return array<string, string>
     */
    private function periodRangeLabels(array $ranges): array
    {
        $out = [];
        foreach ($ranges as $key => $range) {
            $start = Carbon::createFromTimestamp((int) floor(((int) $range['startTs']) / 1000), 'America/Los_Angeles');
            $end = Carbon::createFromTimestamp((int) floor(((int) $range['endTs']) / 1000), 'America/Los_Angeles');
            $out[$key] = $start->format('m/d/Y').' - '.$end->format('m/d/Y');
        }

        return $out;
    }

    /**
     * Single Temu 1 row for /advertisement-master — L30 spend / clicks / orders /
     * sales from temu_ads_api_reports, same period as /temu/ads default.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdvertisementMasterChannelRows(): array
    {
        $metrics = $this->advertisementMasterL30Metrics();
        $row = self::advertisementMasterMetricRow('Temu', 'temu', (object) $metrics);
        $row['channel'] = 'Temu 1';

        return [$row];
    }

    /**
     * Temu L30 store sales — same TCOS denominator as /temu/ads.
     */
    public static function advertisementMasterNetSales(): float
    {
        try {
            $start = Carbon::now()->subDays(30)->startOfDay();
            $end = Carbon::now()->endOfDay();
            $m = TemuShopifySalesService::computeMetricsFromOrders($start, $end);
            $sales = (float) ($m['sales'] ?? 0);
            if ($sales > 0) {
                return round($sales, 2);
            }
        } catch (\Throwable $e) {
            Log::warning('Advertisement Master Temu net sales lookup failed: '.$e->getMessage());
        }

        try {
            if (Schema::hasTable('marketplace_daily_metrics')) {
                $row = MarketplaceDailyMetric::query()
                    ->where('channel', 'Temu')
                    ->latest('date')
                    ->first();

                return round((float) ($row->total_sales ?? 0), 2);
            }
        } catch (\Throwable $e) {
            Log::warning('Advertisement Master Temu daily-metrics sales failed: '.$e->getMessage());
        }

        return 0.0;
    }

    /**
     * @return array{spend: float, clicks: int, sold: int, sales: float, active: int}
     */
    protected function advertisementMasterL30Metrics(): array
    {
        $empty = ['spend' => 0.0, 'clicks' => 0, 'sold' => 0, 'sales' => 0.0, 'active' => 0];
        if (! Schema::hasTable('temu_ads_api_reports')) {
            return $empty;
        }

        try {
            $tot = TemuAdsApiReport::badgeTotals('L30');

            return [
                'spend' => $tot['spend'],
                'clicks' => $tot['clicks'],
                'sold' => $tot['sold'],
                'sales' => $tot['sales'],
                'active' => (int) TemuAdsApiReport::query()->inLatestWindow('L30')->liveAds()->count(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Advertisement Master Temu L30 metrics failed: '.$e->getMessage());

            return $empty;
        }
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
            'channel_key' => $channel,
            'channel_group' => 'Temu',
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
            'marketplace' => 'temu',
        ];
    }
}
