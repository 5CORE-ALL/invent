<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\VintedDataView;
use App\Models\VintedListingStatus;
use App\Models\VintedPricing;
use App\Models\VintedSalesData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Vinted Analytics — same profit formula as /vinted pricing and other marketplaces.
 * unit profit = (price × margin) − LP − ship. Ship is product_master Values.ship.
 * Overlay: vinted_pricing. L30/sales from /vinted/sheet (vinted_sales_data).
 */
class VintedAnalyticsController extends DepopStyleAnalyticsController
{
    private const SOP_SHEET_SKU = '__VINTED_SOP_SHEET__';

    protected static function pricingModelClass(): string
    {
        return VintedPricing::class;
    }

    protected static function viewName(): string
    {
        return 'market-places.vinted_analytics';
    }

    protected static function csvPrefix(): string
    {
        return 'vinted_pricing';
    }

    protected static function marketplaceNames(): array
    {
        return ['vinted'];
    }

    protected static function defaultMarginPercent(): float
    {
        return 95.0;
    }

    protected static function channelLogName(): string
    {
        return 'Vinted analytics';
    }

    protected static function shipCostForProfit($pm): float
    {
        return static::extractShip($pm);
    }

    public function pricingView()
    {
        return view(static::viewName(), [
            'marginPercent' => static::marginPercent(),
            'sopSheetUrl' => $this->loadSopSheetUrl(),
        ]);
    }

    public function getColumnVisibility(Request $request)
    {
        return response()->json(Cache::get($this->columnVisibilityCacheKey(), []));
    }

    public function setColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        if (! is_array($visibility)) {
            $visibility = [];
        }
        Cache::put($this->columnVisibilityCacheKey(), $visibility, now()->addDays(365));

        return response()->json(['success' => true]);
    }

    private function columnVisibilityCacheKey(): string
    {
        return 'vinted_analytics_tabulator_column_visibility_' . (auth()->id() ?? 'guest');
    }

    public function getPricingData(Request $request)
    {
        $response = parent::getPricingData($request);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $payload = $response->getData(true);
        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            return $response;
        }

        $payload['data'] = $this->attachStoredOpSprice($payload['data']);

        return response()->json($payload);
    }

    public function saveOpSprice(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'op_sprice' => 'nullable|numeric',
        ]);

        $sku = trim((string) $request->input('sku'));
        if ($sku === '' || stripos($sku, 'PARENT') === 0) {
            return response()->json(['success' => false, 'message' => 'Invalid SKU'], 422);
        }

        if (! Schema::hasTable('vinted_data_views')) {
            return response()->json([
                'success' => false,
                'message' => 'Vinted OP table is missing. Run the vinted_data_views migration.',
            ], 503);
        }

        $opSprice = static::normalizeOpSprice($request->input('op_sprice'));
        $this->persistOpSprice($sku, $opSprice);

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'op_sprice' => $opSprice,
        ]);
    }

    public function saveSopSheet(Request $request)
    {
        $url = trim((string) $request->input('url', ''));
        if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid Google Sheet or Doc URL.',
            ], 422);
        }

        if (! Schema::hasTable('vinted_data_views')) {
            return response()->json([
                'success' => false,
                'message' => 'Vinted OP table is missing. Run the vinted_data_views migration.',
            ], 503);
        }

        $view = VintedDataView::firstOrNew(['sku' => self::SOP_SHEET_SKU]);
        $value = is_array($view->value)
            ? $view->value
            : (json_decode((string) ($view->value ?? ''), true) ?: []);
        if ($url === '') {
            unset($value['sop_sheet_url']);
        } else {
            $value['sop_sheet_url'] = $url;
        }
        $view->value = $value;
        $view->save();

        return response()->json([
            'success' => true,
            'url' => $url,
        ]);
    }

    /**
     * Offer Sprice profit, same shape as other marketplace OP metrics.
     * SGPFT = ((price × margin − ship − LP) / price) × 100.
     *
     * @return array{sgpft: float, sgroi: float, spft: float, snroi: float}
     */
    public static function opProfitMetrics(?float $opSprice, float $lp, float $margin, float $adsPct = 0.0, float $ship = 0.0): array
    {
        if ($opSprice === null || $opSprice <= 0) {
            return ['sgpft' => 0.0, 'sgroi' => 0.0, 'spft' => 0.0, 'snroi' => 0.0];
        }

        $ship = max(0.0, $ship);
        $sgpft = (($opSprice * $margin - $lp - $ship) / $opSprice) * 100;
        $sgroi = $lp > 0 ? (($opSprice * $margin - $lp - $ship) / $lp) * 100 : 0.0;
        $spft = $sgpft - $adsPct;
        $snroi = $lp > 0
            ? (($opSprice * $margin - $lp - $ship - $opSprice * ($adsPct / 100)) / $lp) * 100
            : 0.0;

        return [
            'sgpft' => round($sgpft, 2),
            'sgroi' => round($sgroi, 2),
            'spft' => round($spft, 2),
            'snroi' => round($snroi, 2),
        ];
    }

    public static function normalizeOpSprice(mixed $raw): ?float
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw) || (float) $raw <= 0) {
            return null;
        }

        return round((float) $raw, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function attachStoredOpSprice(array $rows): array
    {
        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }

        $fromStatus = $this->opSpriceMap(VintedListingStatus::class, 'vinted_listing_statuses', $skus);
        $fromView = $this->opSpriceMap(VintedDataView::class, 'vinted_data_views', $skus);

        foreach ($rows as &$row) {
            $key = strtoupper(trim((string) ($row['sku'] ?? '')));
            $opSprice = $fromStatus[$key] ?? $fromView[$key] ?? null;
            $lp = (float) ($row['lp'] ?? 0);
            $ship = (float) ($row['ship'] ?? $row['Ship_productmaster'] ?? 0);
            $margin = (float) ($row['_margin'] ?? static::marginFactor());
            $metrics = static::opProfitMetrics($opSprice, $lp, $margin, 0.0, $ship);
            $row['op_sprice'] = $opSprice;
            $row['OP_SPRICE'] = $opSprice;
            $row['OP_SGPFT'] = $metrics['sgpft'];
            $row['OP_SGROI'] = $metrics['sgroi'];
            $row['OP_SPFT'] = $metrics['spft'];
            $row['OP_SNROI'] = $metrics['snroi'];
        }
        unset($row);

        return $rows;
    }

    /**
     * OP is stored on vinted_data_views and vinted_listing_statuses.
     * It never writes vinted_pricing.sprice.
     */
    private function persistOpSprice(string $sku, ?float $opSprice): void
    {
        if (Schema::hasTable('vinted_listing_statuses')) {
            $status = VintedListingStatus::firstOrNew(['sku' => $sku]);
            $value = is_array($status->value)
                ? $status->value
                : (json_decode((string) $status->value, true) ?: []);
            if ($opSprice === null) {
                unset($value['op_sprice']);
            } else {
                $value['op_sprice'] = $opSprice;
            }
            $status->value = $value;
            $status->save();
        }

        $view = VintedDataView::firstOrNew(['sku' => $sku]);
        $viewVal = is_array($view->value)
            ? $view->value
            : (json_decode((string) ($view->value ?? ''), true) ?: []);
        if ($opSprice === null) {
            unset($viewVal['OP_SPRICE'], $viewVal['op_sprice']);
        } else {
            $viewVal['OP_SPRICE'] = $opSprice;
            $viewVal['op_sprice'] = $opSprice;
        }
        $view->value = $viewVal;
        $view->save();
    }

    /**
     * @param  class-string  $modelClass
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    private function opSpriceMap(string $modelClass, string $table, array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable($table)) {
            return [];
        }

        $out = [];
        foreach ($modelClass::whereIn('sku', $skus)->get(['sku', 'value']) as $row) {
            $val = is_array($row->value)
                ? $row->value
                : (json_decode((string) ($row->value ?? ''), true) ?: []);
            $stored = $val['OP_SPRICE'] ?? $val['op_sprice'] ?? null;
            if (is_numeric($stored) && (float) $stored > 0) {
                $out[strtoupper(trim((string) $row->sku))] = round((float) $stored, 2);
            }
        }

        return $out;
    }

    private function loadSopSheetUrl(): string
    {
        if (! Schema::hasTable('vinted_data_views')) {
            return '';
        }

        $view = VintedDataView::where('sku', self::SOP_SHEET_SKU)->first();
        if (! $view) {
            return '';
        }

        $value = is_array($view->value)
            ? $view->value
            : (json_decode((string) ($view->value ?? ''), true) ?: []);

        return trim((string) ($value['sop_sheet_url'] ?? ''));
    }

    /**
     * @return array<string, array{qty: int, sales: float}>
     */
    public static function salesL30BySku(): array
    {
        if (! Schema::hasTable('vinted_sales_data')) {
            return [];
        }

        $latestSaleDate = VintedSalesData::whereNotNull('sale_date')->max('sale_date');
        if (! $latestSaleDate) {
            return [];
        }

        $latestCarbon = Carbon::parse($latestSaleDate);
        $l30Start = $latestCarbon->copy()->subDays(29)->format('Y-m-d');
        $l30End = $latestCarbon->format('Y-m-d');

        $rows = VintedSalesData::query()
            ->whereNotNull('sku_code')
            ->where('sku_code', '!=', '')
            ->whereBetween('sale_date', [$l30Start, $l30End])
            ->get(['sku_code', 'quantity', 'item_price']);

        $out = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim((string) $row->sku_code));
            if ($key === '') {
                continue;
            }
            $qty = (int) ($row->quantity ?: 1);
            if ($qty < 1) {
                $qty = 1;
            }
            $sales = ((float) $row->item_price) * $qty;
            if (! isset($out[$key])) {
                $out[$key] = ['qty' => 0, 'sales' => 0.0];
            }
            $out[$key]['qty'] += $qty;
            $out[$key]['sales'] += $sales;
        }

        return $out;
    }
}
