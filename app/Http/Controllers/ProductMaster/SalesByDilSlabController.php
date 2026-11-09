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
        $unmapped = ['skus' => 0, 'today' => 0.0, 'yesterday' => 0.0, 'l30' => 0.0];
        foreach ($bySku as $key => $s) {
            if (isset($matched[$key])) {
                continue;
            }
            $unmapped['skus']++;
            $unmapped['today'] += $s['today'];
            $unmapped['yesterday'] += $s['yesterday'];
            $unmapped['l30'] += $s['l30'];
        }
        foreach (['today', 'yesterday', 'l30'] as $k) {
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
