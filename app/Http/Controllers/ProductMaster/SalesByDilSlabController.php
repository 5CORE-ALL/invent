<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ShopifySku;
use App\Services\Support\SkuSalesByWindowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Sales by Dil slab: Today / Yesterday / L30 sales (our own marketplace order data,
 * not Shopify) per SKU, bucketed by the SKU's Dil %, with NPFT % and NROI %.
 */
class SalesByDilSlabController extends Controller
{
    public function index(Request $request): View
    {
        return view('product-master.sales_by_dil_slab', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
        ]);
    }

    /**
     * Same inventory badges as All Marketplace Master: inv / Inv@SP / Inv@LP / TAT / Avail
     * plus the Inv@SP DIL band slices and their daily history (for the trend dots).
     */
    public function badges(Request $request): JsonResponse
    {
        @set_time_limit(120);

        $out = [
            'inv' => 0, 'inv_at_sp' => 0, 'inv_at_lp' => 0, 'tat' => 0,
            'avail_pct' => 0, 'avail_bg' => '#dc3545', 'avail_fg' => '#ffffff',
            'dots' => [], 'slices' => [], 'history' => [],
        ];

        try {
            $amm = \App\Models\BadgeData::dataForPage('all-marketplace-master');
            $out['inv'] = (float) ($amm['inventory_value_amazon'] ?? 0);
            $out['tat'] = (float) ($amm['tat'] ?? 0);
            $out['inv_at_sp'] = (float) ($amm['inv_at_sp'] ?? 0);
            $out['inv_at_lp'] = (float) ($amm['inv_at_lp'] ?? 0);

            $fa = \App\Models\BadgeData::dataForPage('forecast-analysis');
            $avail = max(0, min(100, 100 - (int) ($fa['zero_stock_pct'] ?? 0)));
            $out['avail_pct'] = $avail;
        } catch (\Throwable $e) {
            Log::warning('Sales by Dil slab badges: badge data failed: '.$e->getMessage());
        }

        try {
            $pies = json_decode(
                app(\App\Http\Controllers\Channels\ChannelMasterController::class)->getInventoryPies($request)->getContent(),
                true
            );
            if (is_array($pies) && ! empty($pies['slices'])) {
                $out['slices'] = $pies['slices'];
                $out['history'] = $pies['history'] ?? [];
                $out['inv_at_amz'] = (float) ($pies['inv_at_amz'] ?? 0);
                // Live values win over the saved badge snapshot.
                if ((float) ($pies['inv_at_sp'] ?? 0) > 0) {
                    $out['inv_at_sp'] = (float) $pies['inv_at_sp'];
                }
                if ((float) ($pies['inv_at_lp'] ?? 0) > 0) {
                    $out['inv_at_lp'] = (float) $pies['inv_at_lp'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Sales by Dil slab badges: inventory pies failed: '.$e->getMessage());
        }

        // Dot pair [previous day, latest day] per badge, same source as the All Marketplace Master dots.
        try {
            $dotReq = Request::create('/channel-metric-dot-trends', 'GET', ['channels' => 'all']);
            $dots = json_decode(
                app(\App\Http\Controllers\Channels\ChannelMasterController::class)->getChannelMetricDotTrends($dotReq)->getContent(),
                true
            );
            $all = $dots['channels']['all'] ?? [];
            foreach (['inventory', 'inv_at_sp', 'inv_at_lp', 'tat'] as $m) {
                if (isset($all[$m]) && is_array($all[$m])) {
                    $out['dots'][$m] = $all[$m];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Sales by Dil slab badges: dot trends failed: '.$e->getMessage());
        }
        try {
            $zero = \App\Models\BadgeDataHistory::series('forecast-analysis', 'zero_stock_pct', 14);
            $n = count($zero);
            if ($n >= 2) {
                $out['dots']['avail'] = [100 - (float) $zero[$n - 2]['value'], 100 - (float) $zero[$n - 1]['value']];
            }
        } catch (\Throwable $e) {
            // dot stays gray
        }

        return response()->json($out);
    }

    public function data(Request $request, SkuSalesByWindowService $sales): JsonResponse
    {
        @set_time_limit(180);

        // Same SKU list, INV, L30 units and Dil % as Movement Analysis.
        $movement = app(MovementAnalysisController::class)->getViewMovementAnalysisData($request);
        $products = json_decode($movement->getContent(), true);
        $products = is_array($products) ? $products : [];

        $window = $sales->build($request->boolean('fresh'));
        $bySku = $window['skus'];
        $npftNroi = $this->npftNroiBySku();

        // Channel Ads % for Amazon (same source the Amazon pricing page uses).
        $adsPct = 0.0;
        try {
            $adsPct = (float) (\App\Models\ChannelMasterCalculatedData::where('channel', 'Amazon')->value('ads_percentage')
                ?? \App\Models\ChannelMasterCalculatedData::where('channel', 'like', 'Amazon%')->value('ads_percentage')
                ?? 0);
        } catch (\Throwable $e) {
            Log::warning('Sales by Dil slab: Amazon ads % failed: '.$e->getMessage());
        }

        $rows = [];
        $matched = [];
        foreach ($products as $p) {
            if (! empty($p['is_parent'])) {
                continue;
            }
            $sku = trim((string) ($p['sku'] ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            $key = ShopifySku::compactSkuForLookup($sku);
            $s = $bySku[$key] ?? null;
            if ($s !== null) {
                $matched[$key] = true;
            }
            $inv = (float) ($p['INV'] ?? 0);
            // Keep every in-stock SKU (it belongs to a slab) and any out-of-stock SKU that still sold.
            if ($inv <= 0 && $s === null) {
                continue;
            }
            $m = $npftNroi[$key] ?? null;

            $lp = (float) ($p['lp'] ?? 0);
            $ship = (float) ($p['ship'] ?? 0);
            $std = (float) ($p['std_price'] ?? 0);
            $amz = (float) ($p['amz_price'] ?? 0);
            $npft = $m['npft'] ?? null;
            $nroi = $m['nroi'] ?? null;

            // Amazon profit / ROI per unit, same formulas as the Amazon pricing page:
            // gross = price x 0.80 - LP - ship; net = gross - price x channel Ads%.
            $amzPft = $amzPftPct = $amzRoi = null;
            if ($amz > 0) {
                $amzPft = ($amz * 0.80) - $lp - $ship - ($amz * $adsPct / 100);
                $amzPftPct = ($amzPft / $amz) * 100;
                $amzRoi = $lp > 0 ? ($amzPft / $lp) * 100 : null;
            }

            $rows[] = [
                'sku' => $sku,
                'parent' => (string) ($p['parent'] ?? ''),
                'image' => $p['image'] ?? null,
                'INV' => $inv,
                'L30' => (float) ($p['L30'] ?? 0),
                'dil' => (float) ($p['dil'] ?? 0),
                'today' => round((float) ($s['today'] ?? 0), 2),
                'yesterday' => round((float) ($s['yesterday'] ?? 0), 2),
                'l30_sales' => round((float) ($s['l30'] ?? 0), 2),
                // Projected 30-day sales = last 7 days / 7 x 30 (same formula as All Marketplace Master).
                'projected' => round(((float) ($s['l7'] ?? 0)) / 7 * 30, 2),
                'npft' => $npft,
                'nroi' => $nroi,
                'lp' => round($lp, 2),
                'std_price' => $std > 0 ? round($std, 2) : null,
                // $ per unit: Std price x NPFT %, and LP x NROI %.
                'npft_amt' => ($std > 0 && $npft !== null) ? round($std * $npft / 100, 2) : null,
                'nroi_amt' => ($lp > 0 && $nroi !== null) ? round($lp * $nroi / 100, 2) : null,
                'amz_price' => $amz > 0 ? round($amz, 2) : null,
                'amz_pft' => $amzPft !== null ? round($amzPft, 2) : null,
                'amz_pft_pct' => $amzPftPct !== null ? round($amzPftPct, 2) : null,
                'amz_roi_pct' => $amzRoi !== null ? round($amzRoi, 2) : null,
            ];
        }

        // Orders for SKUs that are not in Product Master.
        $unmapped = ['skus' => 0, 'today' => 0.0, 'yesterday' => 0.0, 'l30' => 0.0, 'l7' => 0.0];
        foreach ($bySku as $key => $s) {
            if (isset($matched[$key])) {
                continue;
            }
            $unmapped['skus']++;
            $unmapped['today'] += $s['today'];
            $unmapped['yesterday'] += $s['yesterday'];
            $unmapped['l30'] += $s['l30'];
            $unmapped['l7'] += $s['l7'] ?? 0;
        }
        $unmapped['projected'] = $unmapped['l7'] / 7 * 30;
        foreach (['today', 'yesterday', 'l30', 'projected'] as $k) {
            $unmapped[$k] = round($unmapped[$k], 2);
        }

        return response()->json([
            'rows' => $rows,
            'unmapped' => $unmapped,
            'channels' => $window['channels'],
            'errors' => $window['errors'],
            'as_of' => $window['as_of'],
            'today' => $window['today'],
            'yesterday' => $window['yesterday'],
            'l30_from' => $window['l30_from'],
        ]);
    }

    private const HISTORY_PAGE = 'sales-by-dil-slab';

    /**
     * Save today's per-slab Count / Inv Val@LP / Inv@Amz Prc (computed in the page from the same rows the
     * Master Dil Slab Rule modal shows). One value per field per day (PT), so reloading the page only updates today.
     */
    public function saveHistory(Request $request): JsonResponse
    {
        $values = $request->input('values');
        $clean = [];
        if (is_array($values)) {
            foreach ($values as $field => $value) {
                $field = (string) $field;
                if (! preg_match('/^(count|lp|amz)_[0-9.\-]{1,40}$/', $field) || ! is_numeric($value)) {
                    continue;
                }
                $clean[$field] = round((float) $value, 2);
            }
        }
        if (count($clean) > 200) {
            $clean = array_slice($clean, 0, 200, true);
        }
        if ($clean !== []) {
            \App\Models\BadgeDataHistory::recordPage(self::HISTORY_PAGE, $clean);
        }

        return response()->json(['success' => true, 'saved' => count($clean)]);
    }

    /**
     * Daily history of the per-slab values saved by saveHistory(): [{date, label, count_<slab>, lp_<slab>, amz_<slab>}].
     */
    public function history(Request $request): JsonResponse
    {
        $days = max(7, min(90, (int) $request->input('days', 30)));
        $data = [];
        try {
            if (Schema::hasTable('badges_data_histories')) {
                $start = now(\App\Models\BadgeDataHistory::TZ)->subDays($days - 1)->toDateString();
                $rows = \App\Models\BadgeDataHistory::query()
                    ->where('page_name', self::HISTORY_PAGE)
                    ->whereDate('snapshot_date', '>=', $start)
                    ->orderBy('snapshot_date')
                    ->get(['field', 'snapshot_date', 'value']);
                foreach ($rows as $r) {
                    $d = $r->snapshot_date instanceof \DateTimeInterface
                        ? $r->snapshot_date->format('Y-m-d')
                        : substr((string) $r->snapshot_date, 0, 10);
                    $data[$d] ??= ['date' => $d, 'label' => substr($d, 5)];
                    $data[$d][(string) $r->field] = (float) $r->value;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Sales by Dil slab history failed: '.$e->getMessage());
        }

        return response()->json(['success' => true, 'days' => $days, 'data' => array_values($data)]);
    }

    /**
     * Latest per-SKU Avg NPFT % / Avg NROI % from the Pricing Master (CVR) daily snapshot,
     * the same numbers Forecast Analysis shows.
     *
     * @return array<string, array{npft: float|null, nroi: float|null}>
     */
    private function npftNroiBySku(): array
    {
        $out = [];
        try {
            if (
                ! Schema::hasTable('pricing_master_daily_snapshots_sku')
                || ! Schema::hasColumn('pricing_master_daily_snapshots_sku', 'avg_pft')
                || ! Schema::hasColumn('pricing_master_daily_snapshots_sku', 'avg_nroi')
            ) {
                return [];
            }
            $latest = DB::table('pricing_master_daily_snapshots_sku')->max('snapshot_date');
            if (! $latest) {
                return [];
            }
            $snap = DB::table('pricing_master_daily_snapshots_sku')
                ->where('snapshot_date', $latest)
                ->select(['sku', 'avg_pft', 'avg_nroi'])
                ->get();
            foreach ($snap as $r) {
                $key = ShopifySku::compactSkuForLookup((string) $r->sku);
                if ($key === '') {
                    continue;
                }
                $out[$key] = [
                    'npft' => $r->avg_pft !== null ? (float) $r->avg_pft : null,
                    'nroi' => $r->avg_nroi !== null ? (float) $r->avg_nroi : null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Sales by Dil slab: NPFT/NROI snapshot failed: '.$e->getMessage());
        }

        return $out;
    }
}
