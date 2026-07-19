<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\VintedPricing;
use App\Models\VintedSalesData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vinted Analytics — ProductMaster + vinted_pricing overlay, with L30 from
 * uploaded sales orders. Feature parity with Purchasing Power / Macys analytics
 * (GPFT, ROI, SPRICE modes, filters, NR/REQ, links, target ROI/GPFT).
 */
class VintedController extends Controller
{
    private const CSV_HEADERS = ['parent', 'sku', 'price', 'l30'];
    private const DEFAULT_MARGIN_PCT = 87;

    public function pricingView()
    {
        return view('market-places.vinted_pricing', [
            'vintedPercentage' => self::marginPercent(),
        ]);
    }

    /**
     * Tabulator ajax payload — plain array (same shape as /pp-data-json).
     */
    public function getPricingData(Request $request)
    {
        try {
            $productMasters = ProductMaster::query()
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('sku')->orWhere('sku', 'NOT LIKE', 'PARENT %');
                })
                ->orderBy('parent', 'asc')
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyByPmSku = ShopifySku::mapByProductSkus($skus);
            $salesL30BySku = self::salesL30BySku();
            $pricingBySku = VintedPricing::whereIn('sku', $skus)->get()->keyBy('sku');

            $margin = self::marginFactor();
            $result = [];

            foreach ($productMasters as $pm) {
                $sku = $pm->sku;
                $skuUpper = strtoupper(trim((string) $sku));
                $shopify = $shopifyByPmSku->get($sku);
                $pricing = $pricingBySku->get($sku);

                $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
                $ovL30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
                $vL30 = (int) ($salesL30BySku[$skuUpper] ?? ($pricing->l30 ?? 0));
                $price = $pricing && $pricing->price !== null ? (float) $pricing->price : 0.0;
                $sprice = $pricing && $pricing->sprice !== null ? (float) $pricing->sprice : null;

                [$lp, $ship] = $this->extractCosts($pm);

                $profitEach = ($price * $margin) - $lp - $ship;
                $gpft = $price > 0 ? ($profitEach / $price) * 100 : 0;
                $roi = $lp > 0 ? ($profitEach / $lp) * 100 : 0;
                $salesL30 = $price * $vL30;
                $totalPft = $profitEach * $vL30;
                $dilRatio = ($ovL30 && $inv > 0) ? round($ovL30 / $inv, 2) : 0;

                $spriceVal = $sprice ?? 0;
                $sProfitEach = ($spriceVal * $margin) - $lp - $ship;
                $sgpft = $spriceVal > 0 ? round(($sProfitEach / $spriceVal) * 100, 2) : 0;
                $sroi = $lp > 0 ? round(($sProfitEach / $lp) * 100, 2) : 0;

                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $image = $shopify->image_src ?? ($values['image_path'] ?? ($pm->image_path ?? null));

                $result[] = (object) [
                    'Parent' => $pm->parent,
                    '(Child) sku' => $sku,
                    'image_path' => $image,
                    'INV' => $inv,
                    'L30' => $ovL30,
                    'Dil%' => $dilRatio,
                    'V L30' => $vL30,
                    'V Price' => $price,
                    'nr_req' => $pricing->nr_req ?? 'REQ',
                    'NR' => '',
                    'Listed' => null,
                    'Live' => null,
                    'B Link' => $pricing->buyer_link ?? '',
                    'S Link' => $pricing->seller_link ?? '',
                    'SPRICE' => $sprice,
                    'has_custom_sprice' => $sprice !== null && $sprice > 0,
                    'SPRICE_STATUS' => $sprice !== null && $sprice > 0 ? 'saved' : null,
                    'GPFT%' => round($gpft, 2),
                    'PFT %' => round($gpft, 2),
                    'ROI%' => round($roi, 2),
                    'NROI' => round($roi, 2),
                    'Profit' => round($totalPft, 2),
                    'Total_pft' => round($totalPft, 2),
                    'T_Sale_l30' => round($salesL30, 2),
                    'Sales L30' => round($salesL30, 2),
                    'LP_productmaster' => $lp,
                    'Ship_productmaster' => $ship,
                    'percentage' => $margin,
                    'SGPFT' => $sgpft,
                    'SPFT' => $sgpft,
                    'SROI' => $sroi,
                ];
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Vinted pricing getPricingData failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateNrReq(Request $request)
    {
        $sku = trim((string) $request->input('sku'));
        $nrReq = $request->input('nr_req') === 'NR' ? 'NR' : 'REQ';
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU required'], 422);
        }

        VintedPricing::updateOrCreate(['sku' => $sku], ['nr_req' => $nrReq]);

        return response()->json(['success' => true, 'message' => 'NR/REQ updated']);
    }

    public function updateLinks(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'buyer_link' => 'nullable|string|max:1000',
            'seller_link' => 'nullable|string|max:1000',
        ]);

        $sku = trim($validated['sku']);
        $buyerLink = isset($validated['buyer_link']) ? trim((string) $validated['buyer_link']) : '';
        $sellerLink = isset($validated['seller_link']) ? trim((string) $validated['seller_link']) : '';

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $label => $link) {
            if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => ucfirst(str_replace('_', ' ', $label)) . ' must be a valid URL.',
                ], 422);
            }
        }

        VintedPricing::updateOrCreate(
            ['sku' => $sku],
            ['buyer_link' => $buyerLink ?: null, 'seller_link' => $sellerLink ?: null]
        );

        return response()->json([
            'success' => true,
            'message' => 'Links saved.',
            'buyer_link' => $buyerLink,
            'seller_link' => $sellerLink,
        ]);
    }

    public function saveSpriceTabulator(Request $request)
    {
        try {
            $sku = trim((string) $request->input('sku'));
            $sprice = (float) $request->input('sprice');
            if ($sku === '') {
                return response()->json(['error' => 'SKU and SPRICE are required'], 400);
            }

            $margin = self::marginFactor();
            [$lp, $ship] = $this->extractCosts(ProductMaster::where('sku', $sku)->first());

            $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
            $sroi = $lp > 0 ? round((($sprice * $margin - $lp - $ship) / $lp) * 100, 2) : 0;

            VintedPricing::updateOrCreate(
                ['sku' => $sku],
                ['sprice' => $sprice > 0 ? round($sprice, 2) : null]
            );

            return response()->json([
                'success' => true,
                'spft_percent' => $sgpft,
                'sroi_percent' => $sroi,
                'sgpft_percent' => $sgpft,
            ]);
        } catch (\Throwable $e) {
            Log::error('Vinted SPRICE tabulator save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function saveSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (empty($updates) && $request->has('sku')) {
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }
            if (empty($updates)) {
                return response()->json(['error' => 'No updates provided'], 400);
            }

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku = trim((string) ($update['sku'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    continue;
                }
                $raw = $update['sprice'] ?? null;
                $sprice = ($raw === null || $raw === '' || (float) $raw == 0)
                    ? null
                    : round((float) $raw, 2);

                VintedPricing::updateOrCreate(['sku' => $sku], ['sprice' => $sprice]);
                $updatedCount++;
            }

            return response()->json([
                'success' => true,
                'updated' => $updatedCount,
                'message' => "Successfully saved {$updatedCount} SPRICE update(s)",
            ]);
        } catch (\Throwable $e) {
            Log::error('Vinted SPRICE batch save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Legacy alias used by older routes / Clear SPRICE. */
    public function saveSprice(Request $request)
    {
        return $this->saveSpriceUpdates($request);
    }

    public function getColumnVisibility(Request $request)
    {
        $key = 'vinted_tabulator_column_visibility_' . (auth()->id() ?? 'guest');
        return response()->json(Cache::get($key, []));
    }

    public function setColumnVisibility(Request $request)
    {
        $key = 'vinted_tabulator_column_visibility_' . (auth()->id() ?? 'guest');
        Cache::put($key, $request->input('visibility', []), now()->addDays(365));
        return response()->json(['success' => true]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filename = 'vinted_pricing_' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache',
        ];

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CSV_HEADERS, ',', '"', '\\');

            $salesL30BySku = self::salesL30BySku();

            ProductMaster::query()
                ->leftJoin('vinted_pricing', 'product_master.sku', '=', 'vinted_pricing.sku')
                ->whereNull('product_master.deleted_at')
                ->where(function ($q) {
                    $q->whereNull('product_master.sku')->orWhere('product_master.sku', 'NOT LIKE', 'PARENT %');
                })
                ->orderBy('product_master.parent', 'asc')
                ->orderBy('product_master.sku', 'asc')
                ->select([
                    'product_master.sku as sku',
                    'product_master.parent as parent',
                    'vinted_pricing.price as price',
                    'vinted_pricing.l30 as l30',
                ])
                ->chunk(500, function ($chunk) use ($out, $salesL30BySku) {
                    foreach ($chunk as $r) {
                        $skuKey = strtoupper(trim((string) $r->sku));
                        $l30 = array_key_exists($skuKey, $salesL30BySku)
                            ? (int) $salesL30BySku[$skuKey]
                            : ($r->l30 !== null ? (int) $r->l30 : null);

                        fputcsv($out, [
                            $r->parent,
                            $r->sku,
                            $r->price !== null ? number_format((float) $r->price, 2, '.', '') : '',
                            $l30 !== null ? $l30 : '',
                        ], ',', '"', '\\');
                    }
                });

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Download merge template: ProductMaster SKUs joined with current VintedPricing.price.
     * Edit prices and re-upload via importCsv() — merges into the model by sku.
     */
    public function downloadPriceSample()
    {
        $filename = 'vinted_pricing_merge_template_' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache',
        ];

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['sku', 'price'], ',', '"', '\\');

            ProductMaster::query()
                ->leftJoin('vinted_pricing', 'product_master.sku', '=', 'vinted_pricing.sku')
                ->whereNull('product_master.deleted_at')
                ->where(function ($q) {
                    $q->whereNull('product_master.sku')->orWhere('product_master.sku', 'NOT LIKE', 'PARENT %');
                })
                ->orderBy('product_master.parent', 'asc')
                ->orderBy('product_master.sku', 'asc')
                ->select([
                    'product_master.sku as sku',
                    'vinted_pricing.price as price',
                ])
                ->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $r) {
                        if ($r->sku === null || $r->sku === '') {
                            continue;
                        }
                        fputcsv($out, [
                            $r->sku,
                            $r->price !== null ? number_format((float) $r->price, 2, '.', '') : '',
                        ], ',', '"', '\\');
                    }
                });

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Merge-upload price sheet into VintedPricing (updateOrCreate by sku).
     * Template headers: sku, price (optional: l30, parent).
     */
    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required_without:price_file|file|mimes:csv,txt|max:20480',
            'price_file' => 'required_without:file|file|mimes:csv,txt|max:20480',
        ]);

        $uploaded = $request->file('file') ?: $request->file('price_file');
        $handle = null;
        try {
            $path = $uploaded->getRealPath();
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

            $skuIdx = $this->headerIndex($header, ['sku', 'offer sku', 'offer_sku', '(child) sku', 'child sku']);
            $priceIdx = $this->headerIndex($header, ['price', 'v price', 'v_price', 'vprice', 'list price']);
            $l30Idx = $this->headerIndex($header, ['l30', 'v l30', 'v_l30']);

            if ($skuIdx === false || $priceIdx === false) {
                fclose($handle);
                return response()->json([
                    'success' => false,
                    'message' => 'Required columns not found. Use the Sample CSV template headers: sku, price (optional l30).',
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
                $rawPrice = isset($cells[$priceIdx]) ? trim((string) $cells[$priceIdx]) : '';
                if ($rawPrice !== '') {
                    $clean = preg_replace('/[^0-9.\-]/', '', $rawPrice);
                    $attrs['price'] = is_numeric($clean) ? round((float) $clean, 2) : null;
                } else {
                    $attrs['price'] = null;
                }

                if ($l30Idx !== false && isset($cells[$l30Idx])) {
                    $raw = trim((string) $cells[$l30Idx]);
                    if ($raw !== '') {
                        $clean = preg_replace('/[^0-9\-]/', '', $raw);
                        $attrs['l30'] = is_numeric($clean) ? (int) $clean : null;
                    }
                }

                // Merge into model — never truncate existing SPRICE / NR / links.
                VintedPricing::updateOrCreate(['sku' => $sku], $attrs);
                $upserted++;
            }

            fclose($handle);
            $handle = null;
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Price sheet merged into VintedPricing. {$upserted} SKU row(s) updated, {$skipped} skipped.",
                'updated' => $upserted,
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
            Log::error('Vinted pricing importCsv failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $candidates
     * @return int|false
     */
    private function headerIndex(array $header, array $candidates)
    {
        foreach ($candidates as $name) {
            $idx = array_search($name, $header, true);
            if ($idx !== false) {
                return $idx;
            }
        }

        return false;
    }

    /**
     * @return array<string, int>
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

        $latestCarbon = \Carbon\Carbon::parse($latestSaleDate);
        $l30Start = $latestCarbon->copy()->subDays(29)->format('Y-m-d');
        $l30End = $latestCarbon->format('Y-m-d');

        $raw = VintedSalesData::query()
            ->whereNotNull('sku_code')
            ->where('sku_code', '!=', '')
            ->whereBetween('sale_date', [$l30Start, $l30End])
            ->selectRaw('UPPER(TRIM(sku_code)) as sku_upper, SUM(GREATEST(COALESCE(NULLIF(quantity, 0), 1), 1)) as qty')
            ->groupBy('sku_upper')
            ->pluck('qty', 'sku_upper');

        $out = [];
        foreach ($raw as $sku => $qty) {
            $key = strtoupper(trim((string) $sku));
            if ($key === '') {
                continue;
            }
            $out[$key] = (int) $qty;
        }

        return $out;
    }

    public static function syncL30FromSales(): int
    {
        $salesL30 = self::salesL30BySku();
        if (empty($salesL30)) {
            return 0;
        }

        $pmSkuByUpper = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('sku')
            ->pluck('sku')
            ->filter()
            ->mapWithKeys(fn ($s) => [strtoupper(trim((string) $s)) => $s])
            ->all();

        $pricingSkuByUpper = VintedPricing::query()
            ->pluck('sku')
            ->filter()
            ->mapWithKeys(fn ($s) => [strtoupper(trim((string) $s)) => $s])
            ->all();

        $updated = 0;
        foreach ($salesL30 as $skuUpper => $qty) {
            if ($skuUpper === '' || stripos($skuUpper, 'PARENT') === 0) {
                continue;
            }
            $sku = $pmSkuByUpper[$skuUpper]
                ?? $pricingSkuByUpper[$skuUpper]
                ?? $skuUpper;

            VintedPricing::updateOrCreate(
                ['sku' => $sku],
                ['l30' => $qty]
            );
            $updated++;
        }

        return $updated;
    }

    /**
     * Take-home margin % from marketplace_percentages where marketplace = Vinted.
     */
    public static function marginPercent(): float
    {
        $raw = MarketplacePercentage::query()
            ->whereRaw('LOWER(TRIM(marketplace)) = ?', ['vinted'])
            ->value('percentage');

        if ($raw !== null && is_numeric($raw) && (float) $raw > 0) {
            return (float) $raw;
        }

        return (float) self::DEFAULT_MARGIN_PCT;
    }

    /**
     * Margin factor (0–1) from marketplace_percentages Vinted.
     */
    public static function marginFactor(): float
    {
        return self::marginPercent() / 100;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function extractCosts($pm): array
    {
        if (! $pm) {
            return [0.0, 0.0];
        }

        $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
        $lp = 0.0;
        if (is_array($values)) {
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = floatval($pm->lp);
        }

        $ship = 0.0;
        if (is_array($values) && isset($values['ship'])) {
            $ship = floatval($values['ship']);
        } elseif (isset($pm->ship)) {
            $ship = floatval($pm->ship);
        }

        return [$lp, $ship];
    }
}
