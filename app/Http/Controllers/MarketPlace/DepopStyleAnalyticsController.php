<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared Depop-style analytics (no ship): Tabulator grid + CSV template upsert.
 *
 * Profit / GPFT / GROI / SGPFT / SGROI:
 *   unit profit = (price × marketplace take-home) − LP
 */
abstract class DepopStyleAnalyticsController extends Controller
{
    protected const CSV_HEADERS = ['parent', 'sku', 'price', 'l30'];

    abstract protected static function pricingModelClass(): string;

    abstract protected static function viewName(): string;

    abstract protected static function csvPrefix(): string;

    /** @return list<string> */
    abstract protected static function marketplaceNames(): array;

    abstract protected static function defaultMarginPercent(): float;

    abstract protected static function channelLogName(): string;

    /**
     * Last-30-day qty + sales keyed by uppercase SKU.
     *
     * @return array<string, array{qty: int, sales: float}>
     */
    abstract protected static function salesL30BySku(): array;

    public function pricingView()
    {
        return view(static::viewName(), [
            'marginPercent' => static::marginPercent(),
        ]);
    }

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
            $salesBySku = static::salesL30BySku();
            $pricingBySku = static::pricingBySku($skus);

            $margin = static::marginFactor();
            $data = [];

            foreach ($productMasters as $pm) {
                $sku = trim((string) $pm->sku);
                if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }

                $skuUpper = strtoupper($sku);
                $shopify = $shopifyByPmSku->get($sku);
                $pricing = $pricingBySku->get($sku);
                $sold = static::resolveSoldMetrics($pricing, $skuUpper, $salesBySku);

                $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
                $ovL30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
                $image = $shopify->image_src ?? null;
                $price = $pricing && $pricing->price !== null ? (float) $pricing->price : (float) ($sold['fallback_price'] ?? 0);
                $sprice = $pricing && $pricing->sprice !== null ? (float) $pricing->sprice : 0.0;
                $al30 = (int) ($sold['qty'] ?? 0);
                $sales = (float) ($sold['sales'] ?? 0.0);
                $lp = static::extractLp($pm);

                $sellPrice = static::effectiveSellPrice($price, $sales, $al30);
                $profit = static::unitProfit($sellPrice, $lp, $margin);
                $gpft = static::gpftPercent($sellPrice, $lp, $margin);
                $groi = static::groiPercent($sellPrice, $lp, $margin);
                $sgpft = $sprice > 0 ? static::gpftPercent($sprice, $lp, $margin) : 0;
                $sroi = $sprice > 0 ? static::groiPercent($sprice, $lp, $margin) : 0;
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
                'margin' => static::marginPercent(),
            ]);
        } catch (\Throwable $e) {
            Log::error(static::channelLogName().' getPricingData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filename = $request->attributes->get('csv_filename')
            ?: (static::csvPrefix().'_'.now()->format('Y-m-d_His').'.csv');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ];

        $pricingTable = static::pricingTable();

        return response()->stream(function () use ($pricingTable) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CSV_HEADERS, ',', '"', '\\');

            $salesBySku = static::salesL30BySku();
            $query = ProductMaster::query()
                ->whereNull('product_master.deleted_at')
                ->where(function ($q) {
                    $q->whereNull('product_master.sku')->orWhere('product_master.sku', 'NOT LIKE', 'PARENT %');
                })
                ->orderBy('product_master.parent', 'asc')
                ->orderBy('product_master.sku', 'asc');

            if ($pricingTable !== null) {
                $query->leftJoin($pricingTable, 'product_master.sku', '=', $pricingTable.'.sku')
                    ->select([
                        'product_master.sku as sku',
                        'product_master.parent as parent',
                        $pricingTable.'.price as price',
                        $pricingTable.'.l30 as overlay_l30',
                    ]);
            } else {
                $query->select([
                    'product_master.sku as sku',
                    'product_master.parent as parent',
                ]);
            }

            $query->chunk(500, function ($chunk) use ($out, $salesBySku) {
                foreach ($chunk as $r) {
                    $skuKey = strtoupper(trim((string) $r->sku));
                    $sold = static::resolveSoldMetrics(
                        (object) ['price' => $r->price ?? null, 'l30' => $r->overlay_l30 ?? null],
                        $skuKey,
                        $salesBySku
                    );
                    fputcsv($out, [
                        $r->parent,
                        $r->sku,
                        isset($r->price) && $r->price !== null ? number_format((float) $r->price, 2, '.', '') : '',
                        (int) ($sold['qty'] ?? 0),
                    ], ',', '"', '\\');
                }
            });

            fclose($out);
        }, 200, $headers);
    }

    /** Download merge template: ProductMaster SKUs with current price / l30. */
    public function downloadSample(Request $request): StreamedResponse
    {
        $request->attributes->set('csv_filename', static::csvPrefix().'_template_'.now()->format('Y-m-d').'.csv');

        return $this->exportCsv($request);
    }

    public function importCsv(Request $request)
    {
        if (static::pricingTable() === null) {
            return response()->json([
                'success' => false,
                'message' => 'Pricing table is missing. Run migrations first.',
            ], 500);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        $handle = null;
        $pricingClass = static::pricingModelClass();
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

            $skuIdx = static::headerIndex($header, ['sku', 'offer sku', 'offer_sku', '(child) sku', 'child sku']);
            $priceIdx = static::headerIndex($header, ['price', 'v price', 'v_price', 'i price', 'list price']);
            $l30Idx = static::headerIndex($header, ['l30', 'v l30', 'i l30', 'd l30']);

            if ($skuIdx === false || ($priceIdx === false && $l30Idx === false)) {
                fclose($handle);

                return response()->json([
                    'success' => false,
                    'message' => 'CSV must include at least an "sku" column plus "price" and/or "l30". Download the template and fill those columns.',
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

                $pricingClass::updateOrCreate(['sku' => $sku], $attrs);
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
            Log::error(static::channelLogName().' importCsv failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function saveSprice(Request $request)
    {
        if (static::pricingTable() === null) {
            return response()->json([
                'success' => false,
                'message' => 'Pricing table is missing. Run migrations first.',
            ], 500);
        }

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
        $margin = static::marginFactor();
        $pricingClass = static::pricingModelClass();

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

                $pricingClass::updateOrCreate(
                    ['sku' => $sku],
                    ['sprice' => $sprice]
                );
                $saved++;

                $lp = static::extractLp(ProductMaster::where('sku', $sku)->first());
                $lastSgpft = $sprice ? static::gpftPercent($sprice, $lp, $margin) : 0;
                $lastSroi = $sprice ? static::groiPercent($sprice, $lp, $margin) : 0;
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
            Log::error(static::channelLogName().' saveSprice failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public static function marginPercent(): float
    {
        try {
            if (! Schema::hasTable('marketplace_percentages')) {
                return static::defaultMarginPercent();
            }

            $names = array_map(fn ($n) => strtolower(trim((string) $n)), static::marketplaceNames());
            $row = MarketplacePercentage::query()
                ->where(function ($q) use ($names) {
                    foreach ($names as $name) {
                        $q->orWhereRaw('LOWER(TRIM(marketplace)) = ?', [$name]);
                    }
                })
                ->orderBy('id')
                ->first();

            $raw = $row->percentage ?? null;
            if ($raw !== null && is_numeric($raw) && (float) $raw > 0) {
                $n = (float) $raw;

                return $n > 1 ? $n : $n * 100;
            }
        } catch (\Throwable $e) {
            Log::warning(static::channelLogName().' margin lookup failed: '.$e->getMessage());
        }

        return static::defaultMarginPercent();
    }

    public static function marginFactor(): float
    {
        return static::marginPercent() / 100;
    }

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

        return (int) round((static::unitProfit($price, $lp, $margin) / $price) * 100);
    }

    public static function groiPercent(float $price, float $lp, float $margin): int
    {
        if ($price <= 0 || $lp <= 0) {
            return 0;
        }

        return (int) round((static::unitProfit($price, $lp, $margin) / $lp) * 100);
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

    /**
     * @param  object|null  $pricing
     * @param  array<string, array{qty: int, sales: float, fallback_price?: float}>  $salesBySku
     * @return array{qty: int, sales: float, fallback_price?: float}
     */
    protected static function resolveSoldMetrics($pricing, string $skuUpper, array $salesBySku): array
    {
        $sale = $salesBySku[$skuUpper] ?? ['qty' => 0, 'sales' => 0.0];
        $qty = (int) ($sale['qty'] ?? 0);
        $sales = (float) ($sale['sales'] ?? 0.0);
        $fallbackPrice = (float) ($sale['fallback_price'] ?? 0);

        if ($qty <= 0 && $pricing && isset($pricing->l30) && $pricing->l30 !== null) {
            $qty = (int) $pricing->l30;
            $price = $pricing && isset($pricing->price) && $pricing->price !== null
                ? (float) $pricing->price
                : $fallbackPrice;
            if ($qty > 0 && $price > 0 && $sales <= 0) {
                $sales = $price * $qty;
            }
        }

        return [
            'qty' => $qty,
            'sales' => $sales,
            'fallback_price' => $fallbackPrice,
        ];
    }

    protected static function pricingTable(): ?string
    {
        $table = (new (static::pricingModelClass()))->getTable();

        return Schema::hasTable($table) ? $table : null;
    }

    /**
     * @param  list<string>  $skus
     * @return \Illuminate\Support\Collection<string, mixed>
     */
    protected static function pricingBySku(array $skus)
    {
        if ($skus === [] || static::pricingTable() === null) {
            return collect();
        }

        return static::pricingModelClass()::whereIn('sku', $skus)->get()->keyBy('sku');
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $candidates
     * @return int|false
     */
    protected static function headerIndex(array $header, array $candidates)
    {
        foreach ($candidates as $name) {
            $idx = array_search($name, $header, true);
            if ($idx !== false) {
                return $idx;
            }
        }

        return false;
    }
}
