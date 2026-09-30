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
 * "Inv Change 7days": CP Master rows whose Shopify INV differs from the daily
 * snapshot taken 7 days ago (shopifysku_inventory_history.closing_inventory).
 */
class InvChangeSevenDaysController extends Controller
{
    public const WINDOW_DAYS = 7;

    private const SNAPSHOT_TZ = 'America/Los_Angeles';

    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('inv-change-seven-days', compact('mode', 'demo'));
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

                $key = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($key === '' || ! array_key_exists($key, $baseline['inv'])) {
                    continue;
                }

                $inv = (float) ($product['shopify_inv'] ?? 0);
                $ovl30 = (float) ($product['shopify_quantity'] ?? 0);
                $prevInv = (float) $baseline['inv'][$key];
                $change = $inv - $prevInv;
                if (abs($change) < 0.0001) {
                    continue;
                }

                $rows[] = [
                    'id' => $product['id'] ?? null,
                    'image' => $product['image_path'] ?? null,
                    'parent' => $product['Parent'] ?? '',
                    'sku' => $sku,
                    'inv' => $inv,
                    'prev_inv' => $prevInv,
                    'change' => $change,
                    'change_pct' => $prevInv > 0 ? round(($change / $prevInv) * 100) : null,
                    'baseline_date' => $baseline['date'][$key] ?? null,
                    'ovl30' => $ovl30,
                    'dil' => InvUnder30DaysController::dilPercent($inv, $ovl30),
                ];
            }

            usort($rows, static fn (array $a, array $b) => abs($b['change']) <=> abs($a['change']));

            return response()->json([
                'status' => 200,
                'window_days' => self::WINDOW_DAYS,
                'baseline_date' => $baseline['target_date'],
                'data' => array_values($rows),
            ]);
        } catch (\Throwable $e) {
            Log::error('Inv Change 7days data failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to load Inv Change 7days data.',
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
