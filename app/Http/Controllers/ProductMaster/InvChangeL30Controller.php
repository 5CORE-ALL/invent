<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Models\ShopifySku;
use App\Models\ShopifySkuInventoryHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Inv Change L30": CP Master rows with zero Shopify INV and OVL30 > 0, alongside the INV they had
 * 7 days ago (shopifysku_inventory_history.closing_inventory) so recent stock-outs surface first.
 */
class InvChangeL30Controller extends Controller
{
    public const WINDOW_DAYS = 7;

    private const SNAPSHOT_TZ = 'America/Los_Angeles';

    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('inv-change-l30', compact('mode', 'demo'));
    }

    public function getData(Request $request)
    {
        try {
            $baseResponse = app(PMController::class)->getViewProductData($request);
            $baseData = $baseResponse->getData(true);
            $products = $baseData['data'] ?? [];

            $baseline = $this->baselineInventoryBySku();

            $rows = [];
            foreach ($products as $product) {
                $sku = trim((string) ($product['SKU'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    continue;
                }

                $inv = (float) ($product['shopify_inv'] ?? 0);
                if ($inv > 0) {
                    continue;
                }

                $ovl30 = (float) ($product['shopify_quantity'] ?? 0);
                if ($ovl30 <= 0) {
                    continue;
                }

                $key = ShopifySku::normalizeSkuForShopifyLookup($sku);
                $hasBaseline = $key !== '' && array_key_exists($key, $baseline['inv']);
                $prevInv = $hasBaseline ? (float) $baseline['inv'][$key] : null;
                $change = $prevInv !== null ? $inv - $prevInv : null;

                $rows[] = [
                    'id' => $product['id'] ?? null,
                    'image' => $product['image_path'] ?? null,
                    'parent' => $product['Parent'] ?? '',
                    'sku' => $sku,
                    'inv' => $inv,
                    'prev_inv' => $prevInv,
                    'change' => $change,
                    'change_pct' => ($prevInv !== null && $prevInv > 0) ? round(($change / $prevInv) * 100) : null,
                    'baseline_date' => $hasBaseline ? ($baseline['date'][$key] ?? null) : null,
                    'ovl30' => $ovl30,
                    'dil' => InvUnder30DaysController::dilPercent($inv, $ovl30),
                ];
            }

            // SKUs that ran out most recently (had the most stock 7 days ago) first, then by OVL30.
            usort($rows, static function (array $a, array $b) {
                $cmp = ($b['prev_inv'] ?? -1) <=> ($a['prev_inv'] ?? -1);

                return $cmp !== 0 ? $cmp : ($b['ovl30'] <=> $a['ovl30']);
            });

            return response()->json([
                'status' => 200,
                'window_days' => self::WINDOW_DAYS,
                'baseline_date' => $baseline['target_date'],
                'data' => array_values($rows),
            ]);
        } catch (\Throwable $e) {
            Log::error('Inv Change L30 data failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to load Inv Change L30 data.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * INV per SKU as of 7 days ago. Uses the latest snapshot on or before T-7
     * (looking back up to 14 days); if a SKU has no snapshot that old, falls back
     * to its earliest snapshot inside the 7-day window so new SKUs still show.
     *
     * @return array{inv: array<string, int>, date: array<string, string>, target_date: string}
     */
    private function baselineInventoryBySku(): array
    {
        $today = Carbon::now(self::SNAPSHOT_TZ)->startOfDay();
        $target = $today->copy()->subDays(self::WINDOW_DAYS);
        $lookbackStart = $target->copy()->subDays(self::WINDOW_DAYS);

        $inv = [];
        $date = [];

        if (! Schema::hasTable('shopifysku_inventory_history')) {
            return ['inv' => $inv, 'date' => $date, 'target_date' => $target->toDateString()];
        }

        ShopifySkuInventoryHistory::query()
            ->select(['sku', 'closing_inventory', 'snapshot_date'])
            ->whereBetween('snapshot_date', [$lookbackStart->toDateString(), $target->toDateString()])
            ->orderBy('snapshot_date')
            ->chunk(2000, function ($chunk) use (&$inv, &$date) {
                foreach ($chunk as $row) {
                    $key = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    if ($key === '') {
                        continue;
                    }
                    $inv[$key] = (int) $row->closing_inventory;
                    $date[$key] = $row->snapshot_date?->toDateString();
                }
            });

        ShopifySkuInventoryHistory::query()
            ->select(['sku', 'closing_inventory', 'snapshot_date'])
            ->where('snapshot_date', '>', $target->toDateString())
            ->where('snapshot_date', '<', $today->toDateString())
            ->orderByDesc('snapshot_date')
            ->chunk(2000, function ($chunk) use (&$inv, &$date) {
                foreach ($chunk as $row) {
                    $key = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    if ($key === '' || array_key_exists($key, $inv)) {
                        continue;
                    }
                    $inv[$key] = (int) $row->closing_inventory;
                    $date[$key] = $row->snapshot_date?->toDateString();
                }
            });

        return ['inv' => $inv, 'date' => $date, 'target_date' => $target->toDateString()];
    }
}
