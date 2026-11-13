<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MarketPlace\DepopController;
use App\Http\Controllers\MarketPlace\VintedController;
use App\Models\ProductMaster;
use App\Models\VintedSalesData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VintedSalesController extends Controller
{
    public function index()
    {
        $vintedPercentage = VintedController::marginPercent();

        return view('sales.vinted_sheet_data', [
            'vintedPercentage' => $vintedPercentage,
        ]);
    }

    /**
     * Get Vinted sales data. PFT = (sale × Vinted margin) − (LP × qty). No ship, no fee.
     */
    public function getData(Request $request)
    {
        try {
            $rows = VintedSalesData::orderBy('sale_date', 'desc')->get();
            if ($rows->isEmpty()) {
                return response()->json([]);
            }

            $margin = VintedController::marginFactor();
            $pmBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get()
                ->keyBy(function ($pm) {
                    return strtoupper(trim((string) $pm->sku));
                });
            $data = [];
            foreach ($rows as $row) {
                $quantity = (int) $row->quantity ?: 1;
                if ($quantity < 1) {
                    $quantity = 1;
                }
                $unitPrice = (float) $row->item_price;
                $saleAmount = $unitPrice * $quantity;
                $skuKey = strtoupper(trim((string) ($row->sku_code ?? '')));
                $lp = $skuKey !== '' ? DepopController::extractLp($pmBySku->get($skuKey)) : 0.0;
                $pftEach = DepopController::unitProfit($unitPrice, $lp, $margin);
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
                $pft = $pftEach * $quantity;
                $cogs = $lp * $quantity;
                $roi = $lp > 0 ? ($pftEach / $lp) * 100 : 0;

                $data[] = [
                    'id' => $row->id,
                    'product_name' => $row->description ? substr(str_replace(["\r", "\n"], ' ', $row->description), 0, 120) : '',
                    'size' => $row->size,
                    'sku' => $row->sku_code ?? '',
                    'sku_source' => $row->sku_source ?? null, // null = exact, 'guess', 'manual'
                    'sku_in_pm' => $skuKey !== '' && $pmBySku->has($skuKey),
                    'quantity' => $quantity,
                    'price' => round($unitPrice, 2),
                    'sale_amount' => round($saleAmount, 2),
                    'sale_date' => $row->sale_date?->format('Y-m-d'),
                    'buyer' => $row->buyer,
                    'source' => ($row->source ?? 'upload') === 'shopify' ? 'Shopify (Fluf)' : 'Upload',
                    'lp' => round($lp, 2),
                    'ship' => 0,
                    'ship_cost' => 0,
                    'weight_act' => 0,
                    't_weight' => 0,
                    'cogs' => round($cogs, 2),
                    'pft_each' => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    't_pft' => round($pft, 2),
                    'roi' => round($roi, 0),
                    'margin' => round($margin * 100, 2),
                ];
            }
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Vinted Sales Data Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Edit the SKU of one sheet row. A typed SKU is marked "manual" so the hourly Shopify sync never
     * overwrites it; an empty value hands the row back to the sync (next run fills it again).
     */
    public function saveSku(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'sku' => 'nullable|string|max:191',
        ]);

        if (! \Illuminate\Support\Facades\Schema::hasColumn('vinted_sales_data', 'sku_source')) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (sku_source column is missing).'], 503);
        }

        $row = VintedSalesData::find((int) $request->input('id'));
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Row not found'], 404);
        }

        // Spaces / NBSP cleaned up; use Product Master spelling when the SKU matches one.
        $sku = trim(str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', (string) $request->input('sku', '')));
        $sku = preg_replace('/\s+/', ' ', $sku);
        $matched = null;
        if ($sku !== '') {
            $want = \App\Models\ShopifySku::normalizeSkuForShopifyLookup($sku);
            $wantCompact = \App\Models\ShopifySku::compactSkuForLookup($sku);
            foreach (ProductMaster::query()->whereNull('deleted_at')->whereNotNull('sku')->pluck('sku') as $pmSku) {
                $pmSku = trim((string) $pmSku);
                if ($pmSku === '' || stripos($pmSku, 'PARENT') === 0) {
                    continue;
                }
                if (\App\Models\ShopifySku::normalizeSkuForShopifyLookup($pmSku) === $want) {
                    $matched = $pmSku;
                    break;
                }
                if ($matched === null && \App\Models\ShopifySku::compactSkuForLookup($pmSku) === $wantCompact) {
                    $matched = $pmSku;
                }
            }
        }

        $row->sku_code = $sku === '' ? null : ($matched ?? $sku);
        $row->sku_source = $sku === '' ? null : 'manual';
        $row->save();

        try {
            VintedController::syncL30FromSales();
        } catch (\Throwable $e) {
            Log::warning('Vinted sheet SKU edit: L30 refresh failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'sku' => $row->sku_code ?? '',
            'sku_source' => $row->sku_source,
            'sku_in_pm' => $sku === '' ? false : $matched !== null,
            'message' => $sku === ''
                ? 'SKU cleared; the next Shopify sync fills it again.'
                : ($matched === null ? 'Saved, but this SKU is not in Product Master.' : 'SKU saved.'),
        ]);
    }

    /**
     * Same figures the Vinted sales badges paint. G PFT % stays one decimal. No ship.
     *
     * @return array{l30: int, orders: int, qty: int, pft: int, cogs: int, gpft: string, groi: float}|null
     */
    public function dailySalesBadgeTotals(?string $startDate = null, ?string $endDate = null): ?array
    {
        // Active Channel L30 = rolling 30 days through Pacific yesterday (same window as
        // Y/L7). Summing every sheet row made L30 show all-time Vinted sales.
        $yesterday = \Illuminate\Support\Carbon::yesterday('America/Los_Angeles');
        $endDate = $endDate ?? $yesterday->toDateString();
        $startDate = $startDate ?? $yesterday->copy()->subDays(29)->toDateString();

        $response = $this->getData(request());
        if ($response->getStatusCode() >= 400) {
            return null;
        }
        $payload = $response->getData(true);
        if (! is_array($payload) || ! array_is_list($payload)) {
            return null;
        }

        $sales = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $qty = 0;
        $kept = 0;
        foreach ($payload as $row) {
            if (! is_array($row)) {
                continue;
            }
            $saleDate = (string) ($row['sale_date'] ?? '');
            if ($saleDate === '' || $saleDate < $startDate || $saleDate > $endDate) {
                continue;
            }
            $kept++;
            $qty += (int) ($row['quantity'] ?? 0);
            $sales += (float) ($row['sale_amount'] ?? 0);
            $pft += (float) ($row['t_pft'] ?? 0);
            $cogs += (float) ($row['cogs'] ?? 0);
        }

        $gpft = $sales > 0 ? number_format(($pft / $sales) * 100, 1, '.', '') : '0';
        $groi = $cogs > 0 ? round(($pft / $cogs) * 100, 1) : 0.0;

        return [
            'l30' => (int) round($sales),
            'orders' => $kept,
            'qty' => $qty,
            'pft' => (int) round($pft),
            'cogs' => (int) round($cogs),
            'gpft' => $gpft,
            'groi' => $groi,
        ];
    }

    public function getColumnVisibility()
    {
        $visibility = Cache::get('vinted_sheet_column_visibility', []);
        return response()->json($visibility);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        Cache::put('vinted_sheet_column_visibility', $visibility, now()->addYears(1));
        return response()->json(['success' => true]);
    }
}
