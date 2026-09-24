<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\DepopPricing;
use App\Models\DepopSalesData;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Depop Pricing — AliExpress-style analytics without ship.
 *
 * Profit / GPFT / GROI / SGPFT / SGROI:
 *   unit profit = (price × marketplace Depop take-home) − LP
 * Ship is never subtracted. D L30 + Sales come from /depop/sheet (depop_sales_data).
 */
class DepopController extends Controller
{
    private const CSV_HEADERS = ['parent', 'sku', 'price', 'l30'];

    private const DEFAULT_MARGIN_PCT = 87.0;

    private const SOP_SHEET_CACHE_KEY = 'depop.analytics.sop_sheet_url';

    public function pricingView()
    {
        return view('market-places.depop_pricing', [
            'marginPercent' => self::marginPercent(),
            'sopSheetUrl' => trim((string) Cache::get(self::SOP_SHEET_CACHE_KEY, '')),
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

        if ($url === '') {
            Cache::forget(self::SOP_SHEET_CACHE_KEY);
        } else {
            Cache::forever(self::SOP_SHEET_CACHE_KEY, $url);
        }

        return response()->json([
            'success' => true,
            'url' => $url,
        ]);
    }

    /**
     * Tabulator payload — AliExpress field names so Sprc Dil / promo reuse the same rules.
     */
    public function getPricingData(Request $request)
    {
        try {
            $productMasters = ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('sku')->orWhere('sku', 'NOT LIKE', 'PARENT %');
                })
                ->orderBy('parent', 'asc')
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyByPmSku = ShopifySku::mapByProductSkus($skus);
            $salesBySku = self::salesL30BySku();
            $pricingBySku = $skus === []
                ? collect()
                : DepopPricing::whereIn('sku', $skus)->get()->keyBy('sku');

            $margin = self::marginFactor();
            $data = [];

            foreach ($productMasters as $pm) {
                $sku = trim((string) $pm->sku);
                if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }

                $skuUpper = strtoupper($sku);
                $shopify = $shopifyByPmSku->get($sku);
                $pricing = $pricingBySku->get($sku);
                $sale = $salesBySku[$skuUpper] ?? ['qty' => 0, 'sales' => 0.0];

                $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
                $ovL30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
                $image = $shopify->image_src ?? null;
                $price = $pricing && $pricing->price !== null ? (float) $pricing->price : 0.0;
                $sprice = $pricing && $pricing->sprice !== null ? (float) $pricing->sprice : 0.0;
                $al30 = (int) ($sale['qty'] ?? 0);
                $sales = (float) ($sale['sales'] ?? 0.0);
                $lp = self::extractLp($pm);

                // List price when uploaded; otherwise the sheet's actual sold unit price.
                // Price = 0 must NOT become profit = −LP (that made GROI −100%).
                $sellPrice = self::effectiveSellPrice($price, $sales, $al30);
                $profit = self::unitProfit($sellPrice, $lp, $margin);
                $gpft = self::gpftPercent($sellPrice, $lp, $margin);
                $groi = self::groiPercent($sellPrice, $lp, $margin);
                $sgpft = $sprice > 0 ? self::gpftPercent($sprice, $lp, $margin) : 0;
                $sroi = $sprice > 0 ? self::groiPercent($sprice, $lp, $margin) : 0;
                $dil = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0.0;
                $cvr = $ovL30 > 0 ? round(($al30 / $ovL30) * 100, 2) : 0.0;
                $missing = ($inv > 0 && $price <= 0) ? 'M' : '';

                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (! $image && is_array($values)) {
                    $image = $values['image_path'] ?? ($pm->image_path ?? null);
                }

                $data[] = [
                    'id' => $pm->id,
                    'sku' => $sku,
                    '(Child) sku' => $sku,
                    'parent' => trim((string) ($pm->parent ?? '')) ?: null,
                    'Parent' => trim((string) ($pm->parent ?? '')) ?: null,
                    'is_parent' => false,
                    'image' => $image,
                    'image_path' => $image,
                    'inv' => $inv,
                    'INV' => $inv,
                    'ov_l30' => $ovL30,
                    'L30' => $ovL30,
                    'dil' => $dil,
                    'dil_percent' => $dil,
                    'Dil%' => $dil,
                    'al30' => $al30,
                    'l30' => $al30,
                    'D L30' => $al30,
                    'price' => round($price, 2),
                    'sprice' => $sprice > 0 ? round($sprice, 2) : null,
                    'SPRICE' => $sprice > 0 ? round($sprice, 2) : null,
                    'has_custom_sprice' => $sprice > 0,
                    'gpft' => $gpft,
                    'GPFT%' => $gpft,
                    'groi' => $groi,
                    'ROI%' => $groi,
                    'NROI' => $groi,
                    'profit' => round($profit, 2),
                    'Profit' => round($profit, 2),
                    'sales' => round($sales, 2),
                    'Sales L30' => round($sales, 2),
                    'lp' => round($lp, 2),
                    'LP_productmaster' => round($lp, 2),
                    'ship' => 0,
                    'Ship_productmaster' => 0,
                    'sgpft' => $sgpft,
                    'SGPFT' => $sgpft,
                    'sroi' => $sroi,
                    'SROI' => $sroi,
                    'cvr' => $cvr,
                    'CVR%' => $cvr,
                    'missing' => $missing,
                    '_margin' => round($margin, 4),
                    'percentage' => $margin,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
                'margin' => self::marginPercent(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Depop pricing getPricingData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filename = 'depop_pricing_'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ];

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CSV_HEADERS, ',', '"', '\\');

            $salesBySku = self::salesL30BySku();

            ProductMaster::query()
                ->leftJoin('depop_pricing', 'product_master.sku', '=', 'depop_pricing.sku')
                ->whereNull('product_master.deleted_at')
                ->where(function ($q) {
                    $q->whereNull('product_master.sku')->orWhere('product_master.sku', 'NOT LIKE', 'PARENT %');
                })
                ->orderBy('product_master.parent', 'asc')
                ->orderBy('product_master.sku', 'asc')
                ->select([
                    'product_master.sku as sku',
                    'product_master.parent as parent',
                    'depop_pricing.price as price',
                ])
                ->chunk(500, function ($chunk) use ($out, $salesBySku) {
                    foreach ($chunk as $r) {
                        $skuKey = strtoupper(trim((string) $r->sku));
                        $l30 = (int) ($salesBySku[$skuKey]['qty'] ?? 0);
                        fputcsv($out, [
                            $r->parent,
                            $r->sku,
                            $r->price !== null ? number_format((float) $r->price, 2, '.', '') : '',
                            $l30,
                        ], ',', '"', '\\');
                    }
                });

            fclose($out);
        }, 200, $headers);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        $handle = null;
        try {
            $path = $request->file('file')->getRealPath();
            $handle = fopen($path, 'r');
            if (! $handle) {
                return response()->json(['success' => false, 'message' => 'Could not open uploaded file'], 400);
            }

            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);

                return response()->json(['success' => false, 'message' => 'File is empty'], 400);
            }
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

            $delimiter = ',';
            foreach ([',', ';', "\t"] as $d) {
                if (count(str_getcsv($firstLine, $d, '"', '\\')) >= 2) {
                    $delimiter = $d;
                    break;
                }
            }

            $header = array_map(function ($h) {
                return strtolower(trim((string) $h));
            }, str_getcsv($firstLine, $delimiter, '"', '\\'));

            $skuIdx = array_search('sku', $header, true);
            $priceIdx = array_search('price', $header, true);
            $l30Idx = array_search('l30', $header, true);

            if ($skuIdx === false || ($priceIdx === false && $l30Idx === false)) {
                fclose($handle);

                return response()->json([
                    'success' => false,
                    'message' => 'CSV must include at least an "sku" column plus "price" and/or "l30".',
                ], 422);
            }

            DB::beginTransaction();

            $upserted = 0;
            $skipped = 0;

            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $sku = isset($cells[$skuIdx]) ? trim((string) $cells[$skuIdx]) : '';
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    $skipped++;
                    continue;
                }

                $attrs = [];
                if ($priceIdx !== false && isset($cells[$priceIdx])) {
                    $raw = trim((string) $cells[$priceIdx]);
                    if ($raw !== '') {
                        $clean = preg_replace('/[^0-9.\-]/', '', $raw);
                        $attrs['price'] = is_numeric($clean) ? round((float) $clean, 2) : null;
                    } else {
                        $attrs['price'] = null;
                    }
                }

                if ($l30Idx !== false && isset($cells[$l30Idx])) {
                    $raw = trim((string) $cells[$l30Idx]);
                    if ($raw !== '') {
                        $clean = preg_replace('/[^0-9\-]/', '', $raw);
                        $attrs['l30'] = is_numeric($clean) ? (int) $clean : null;
                    }
                }

                if ($attrs === []) {
                    $skipped++;
                    continue;
                }

                DepopPricing::updateOrCreate(['sku' => $sku], $attrs);
                $upserted++;
            }

            fclose($handle);
            $handle = null;
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import complete. {$upserted} row(s) upserted, {$skipped} skipped.",
                'upserted' => $upserted,
                'skipped' => $skipped,
            ]);
        } catch (\Throwable $e) {
            if ($handle && is_resource($handle)) {
                fclose($handle);
            }
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Depop pricing importCsv failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function saveSprice(Request $request)
    {
        if ($request->filled('sku') && ! $request->has('updates')) {
            $request->merge([
                'updates' => [[
                    'sku' => $request->input('sku'),
                    'sprice' => $request->input('sprice'),
                ]],
            ]);
        }

        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.sku' => 'required|string|max:255',
            'updates.*.sprice' => 'nullable|numeric',
        ]);

        $updates = $request->input('updates', []);
        $saved = 0;
        $lastSgpft = null;
        $lastSroi = null;
        $margin = self::marginFactor();

        try {
            DB::beginTransaction();
            foreach ($updates as $u) {
                $sku = trim((string) ($u['sku'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    continue;
                }
                $raw = $u['sprice'] ?? null;
                $sprice = ($raw === null || $raw === '') ? null : round((float) $raw, 2);
                if ($sprice !== null && $sprice <= 0) {
                    $sprice = null;
                }

                DepopPricing::updateOrCreate(
                    ['sku' => $sku],
                    ['sprice' => $sprice]
                );
                $saved++;

                $lp = self::extractLp(ProductMaster::where('sku', $sku)->first());
                $lastSgpft = $sprice ? self::gpftPercent($sprice, $lp, $margin) : 0;
                $lastSroi = $sprice ? self::groiPercent($sprice, $lp, $margin) : 0;
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'updated' => $saved,
                'message' => "Saved SPRICE for {$saved} SKU(s)",
                'sgpft_percent' => $lastSgpft,
                'sroi_percent' => $lastSroi,
            ]);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Depop pricing saveSprice failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Take-home % from marketplace_percentages where marketplace = Depop.
     */
    public static function marginPercent(): float
    {
        try {
            if (! Schema::hasTable('marketplace_percentages')) {
                return self::DEFAULT_MARGIN_PCT;
            }

            $row = MarketplacePercentage::query()
                ->where(function ($q) {
                    $q->whereRaw('LOWER(TRIM(marketplace)) = ?', ['depop']);
                })
                ->orderBy('id')
                ->first();

            $raw = $row->percentage ?? null;
            if ($raw !== null && is_numeric($raw) && (float) $raw > 0) {
                $n = (float) $raw;

                return $n > 1 ? $n : $n * 100;
            }
        } catch (\Throwable $e) {
            Log::warning('Depop margin lookup failed: '.$e->getMessage());
        }

        return self::DEFAULT_MARGIN_PCT;
    }

    public static function marginFactor(): float
    {
        return self::marginPercent() / 100;
    }

    /**
     * List price if uploaded, else average unit price from /depop/sheet.
     * Never treat a missing list price as $0 sold (that made PFT = −LP).
     */
    public static function effectiveSellPrice(float $listPrice, float $sales, int $qty): float
    {
        if ($listPrice > 0) {
            return $listPrice;
        }
        if ($qty > 0 && $sales > 0) {
            return $sales / $qty;
        }

        return 0.0;
    }

    /** Unit profit — AliExpress rule without ship: (price × margin) − LP. Price ≤ 0 → 0. */
    public static function unitProfit(float $price, float $lp, float $margin): float
    {
        if ($price <= 0) {
            return 0.0;
        }

        return ($price * $margin) - $lp;
    }

    public static function gpftPercent(float $price, float $lp, float $margin): int
    {
        if ($price <= 0) {
            return 0;
        }

        return (int) round((self::unitProfit($price, $lp, $margin) / $price) * 100);
    }

    public static function groiPercent(float $price, float $lp, float $margin): int
    {
        if ($price <= 0 || $lp <= 0) {
            return 0;
        }

        return (int) round((self::unitProfit($price, $lp, $margin) / $lp) * 100);
    }

    /** L30 PFT from sheet sales: (sales × margin) − (LP × qty). No ship. */
    public static function l30Profit(float $sales, int $qty, float $lp, float $margin): float
    {
        if ($sales <= 0 || $qty <= 0) {
            return 0.0;
        }

        return ($sales * $margin) - ($lp * $qty);
    }

    /**
     * Active Channel / sheet window totals. Same PFT as /depop/sheet and /depop/pricing:
     * (sale × marketplace margin) − (LP × qty). No ship, no Depop fee, no 13% LP estimate.
     *
     * @param  iterable<int, mixed>  $rows  DepopSalesData models or arrays with item_price, quantity, sku_code
     * @param  \Illuminate\Support\Collection<string, mixed>|array<string, float|object>  $productMasters  keyed by uppercase SKU
     * @return array{orders: int, sales: float, qty: int, pft: float, cogs: float, gpft: float, groi: float}
     */
    public static function aggregateSalesWindow($rows, $productMasters, ?float $margin = null): array
    {
        $margin = $margin ?? self::marginFactor();
        $orders = 0;
        $sales = 0.0;
        $qty = 0;
        $pft = 0.0;
        $cogs = 0.0;

        foreach ($rows as $row) {
            $quantity = (int) (is_array($row) ? ($row['quantity'] ?? 1) : ($row->quantity ?: 1));
            if ($quantity < 1) {
                $quantity = 1;
            }
            $unitPrice = (float) (is_array($row) ? ($row['item_price'] ?? 0) : $row->item_price);
            $revenue = $unitPrice * $quantity;
            $sku = strtoupper(trim((string) (is_array($row) ? ($row['sku_code'] ?? '') : ($row->sku_code ?? ''))));

            $lp = 0.0;
            if ($sku !== '') {
                if ($productMasters instanceof \Illuminate\Support\Collection) {
                    $pm = $productMasters->get($sku);
                    $lp = is_numeric($pm) ? (float) $pm : self::extractLp($pm);
                } elseif (is_array($productMasters)) {
                    $pm = $productMasters[$sku] ?? null;
                    $lp = is_numeric($pm) ? (float) $pm : self::extractLp($pm);
                }
            }

            $orders++;
            $sales += $revenue;
            $qty += $quantity;
            if ($revenue > 0) {
                $cogs += $lp * $quantity;
            }
            $pft += self::l30Profit($revenue, $quantity, $lp, $margin);
        }

        return [
            'orders' => $orders,
            'sales' => $sales,
            'qty' => $qty,
            'pft' => $pft,
            'cogs' => $cogs,
            'gpft' => $sales > 0 ? ($pft / $sales) * 100 : 0.0,
            'groi' => $cogs > 0 ? ($pft / $cogs) * 100 : 0.0,
        ];
    }

    /** S PRC so SGROI equals target: (LP × (1 + ROI%/100)) / margin. No ship. */
    public static function targetSpriceFromRoi(float $lp, float $roiPct, float $margin): float
    {
        if ($lp <= 0 || $margin <= 0) {
            return 0.0;
        }

        return round(($lp * (1 + ($roiPct / 100))) / $margin, 2);
    }

    /** S PRC so SGPFT equals target: LP / (margin − GPFT%/100). No ship. */
    public static function targetSpriceFromGpft(float $lp, float $gpftPct, float $margin): float
    {
        $denom = $margin - ($gpftPct / 100);
        if ($lp <= 0 || $denom <= 0) {
            return 0.0;
        }

        return round($lp / $denom, 2);
    }

    /**
     * Last-30-day qty + sales from depop_sales_data (same window as /depop/sheet Channel Master).
     *
     * @return array<string, array{qty: int, sales: float}>
     */
    public static function salesL30BySku(): array
    {
        if (! Schema::hasTable('depop_sales_data')) {
            return [];
        }

        $latestSaleDate = DepopSalesData::whereNotNull('sale_date')->max('sale_date');
        if (! $latestSaleDate) {
            return [];
        }

        $latestCarbon = Carbon::parse($latestSaleDate);
        $l30Start = $latestCarbon->copy()->subDays(29)->format('Y-m-d');
        $l30End = $latestCarbon->format('Y-m-d');

        $rows = DepopSalesData::query()
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

    public static function extractLp($pm): float
    {
        if (! $pm) {
            return 0.0;
        }

        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

        $lp = 0.0;
        if (is_array($values)) {
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'lp') {
                    $lp = (float) $v;
                    break;
                }
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }

        return $lp;
    }
}
