<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\DepopPricing;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Depop Pricing page.
 *
 * One row per ProductMaster SKU. The editable fields (price, L30) live in
 * depop_pricing and are exposed for CSV export / re-import so users can mass-edit
 * in a spreadsheet, save, and re-upload to overwrite by SKU.
 */
class DepopController extends Controller
{
    /**
     * Column order used by the export CSV. The import side is order-agnostic
     * (it matches headers by name), so old exports that still include `title`
     * continue to import fine — extra columns are ignored.
     */
    private const CSV_HEADERS = ['parent', 'sku', 'price', 'l30'];

    /**
     * Render the Depop Pricing page.
     */
    public function pricingView()
    {
        return view('market-places.depop_pricing');
    }

    /**
     * JSON payload for the Tabulator table on the Depop Pricing page.
     * Each ProductMaster SKU (excluding PARENT rows) → one row, joined with
     * the editable price / L30 values from depop_pricing.
     */
    public function getPricingData(Request $request)
    {
        try {
            $rows = ProductMaster::query()
                ->leftJoin('depop_pricing', 'product_master.sku', '=', 'depop_pricing.sku')
                ->whereNull('product_master.deleted_at')
                ->where(function ($q) {
                    $q->whereNull('product_master.sku')->orWhere('product_master.sku', 'NOT LIKE', 'PARENT %');
                })
                ->orderBy('product_master.parent', 'asc')
                ->orderBy('product_master.sku', 'asc')
                ->get([
                    'product_master.id as id',
                    'product_master.sku as sku',
                    'product_master.parent as parent',
                    'depop_pricing.price as price',
                    'depop_pricing.sprice as sprice',
                    'depop_pricing.l30 as l30',
                ]);

            // Shopify lookup gives us INV, OV L30, and product image for each PM SKU.
            // Same source Macy's pricing uses, so the columns line up across pages.
            $skus = $rows->pluck('sku')->filter()->unique()->values()->all();
            $shopifyByPmSku = ShopifySku::mapByProductSkus($skus);

            $data = $rows->map(function ($r) use ($shopifyByPmSku) {
                $shopify = $shopifyByPmSku->get($r->sku);
                $inv    = $shopify ? (int) ($shopify->inv      ?? 0) : 0;
                $ovL30  = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
                $image  = $shopify->image_src ?? null;

                // DIL% = OV L30 ÷ INV × 100, matches the Macy's pricing formula
                // (see MacyController + macys_tabulator_view). Frontend colour-codes it.
                $dil = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : null;

                return [
                    'id'        => $r->id,
                    'image'     => $image,
                    'parent'    => $r->parent,
                    'sku'       => $r->sku,
                    'inv'       => $inv,
                    'ov_l30'    => $ovL30,
                    'dil'       => $dil,
                    'price'     => $r->price  !== null ? (float) $r->price  : null,
                    'sprice'    => $r->sprice !== null ? (float) $r->sprice : null,
                    'l30'       => $r->l30    !== null ? (int)   $r->l30    : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Depop pricing getPricingData failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Stream a CSV that the user can edit in a spreadsheet and re-upload.
     * Columns: sku, parent, title, price, l30 — exact same shape importCsv()
     * expects, so the file round-trips without any reshuffling.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filename = 'depop_pricing_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache',
        ];

        return response()->stream(function () {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel renders accented characters correctly.
            fwrite($out, "\xEF\xBB\xBF");
            // PHP 8.4 deprecates the implicit $escape argument — pass it explicitly.
            fputcsv($out, self::CSV_HEADERS, ',', '"', '\\');

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
                    'depop_pricing.l30 as l30',
                ])
                ->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $r) {
                        // Column order MUST match CSV_HEADERS: parent, sku, price, l30
                        fputcsv($out, [
                            $r->parent,
                            $r->sku,
                            $r->price !== null ? number_format((float) $r->price, 2, '.', '') : '',
                            $r->l30   !== null ? (int) $r->l30 : '',
                        ], ',', '"', '\\');
                    }
                });

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Accept the user's re-uploaded CSV (same header set as exportCsv) and
     * upsert price + L30 into depop_pricing keyed by sku. Rows with a blank
     * sku are skipped; price/L30 cells that are blank clear the stored value.
     */
    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        $handle = null;
        try {
            $path = $request->file('file')->getRealPath();
            $handle = fopen($path, 'r');
            if (!$handle) {
                return response()->json(['success' => false, 'message' => 'Could not open uploaded file'], 400);
            }

            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);
                return response()->json(['success' => false, 'message' => 'File is empty'], 400);
            }
            // Strip UTF-8 BOM if present (Excel adds one).
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

            // Sniff delimiter: comma, semicolon or tab.
            $delimiter = ',';
            foreach ([',', ';', "\t"] as $d) {
                if (count(str_getcsv($firstLine, $d, '"', '\\')) >= 4) {
                    $delimiter = $d;
                    break;
                }
            }

            $header = array_map(function ($h) {
                return strtolower(trim((string) $h));
            }, str_getcsv($firstLine, $delimiter, '"', '\\'));

            $skuIdx   = array_search('sku',   $header, true);
            $priceIdx = array_search('price', $header, true);
            $l30Idx   = array_search('l30',   $header, true);

            if ($skuIdx === false || ($priceIdx === false && $l30Idx === false)) {
                fclose($handle);
                return response()->json([
                    'success' => false,
                    'message' => 'CSV must include at least an "sku" column plus "price" and/or "l30".',
                ], 422);
            }

            DB::beginTransaction();

            $upserted = 0;
            $skipped  = 0;

            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $sku = isset($cells[$skuIdx]) ? trim((string) $cells[$skuIdx]) : '';
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    $skipped++;
                    continue;
                }

                $price = null;
                if ($priceIdx !== false && isset($cells[$priceIdx])) {
                    $raw = trim((string) $cells[$priceIdx]);
                    if ($raw !== '') {
                        $clean = preg_replace('/[^0-9.\-]/', '', $raw);
                        $price = is_numeric($clean) ? round((float) $clean, 2) : null;
                    }
                }

                $l30 = null;
                if ($l30Idx !== false && isset($cells[$l30Idx])) {
                    $raw = trim((string) $cells[$l30Idx]);
                    if ($raw !== '') {
                        $clean = preg_replace('/[^0-9\-]/', '', $raw);
                        $l30 = is_numeric($clean) ? (int) $clean : null;
                    }
                }

                DepopPricing::updateOrCreate(
                    ['sku' => $sku],
                    ['price' => $price, 'l30' => $l30]
                );
                $upserted++;
            }

            fclose($handle);
            $handle = null;

            DB::commit();

            return response()->json([
                'success'  => true,
                'message'  => "Import complete. {$upserted} row(s) upserted, {$skipped} skipped.",
                'upserted' => $upserted,
                'skipped'  => $skipped,
            ]);
        } catch (\Throwable $e) {
            if ($handle && is_resource($handle)) {
                fclose($handle);
            }
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Depop pricing importCsv failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Persist SPRICE updates from the Depop Analytics page pricing modes
     * (Decrease / Increase / Same Price). Accepts an array of { sku, sprice }
     * pairs and upserts each into depop_pricing keyed by sku. A null/blank
     * `sprice` clears the saved value for that SKU (used by Clear SPRICE).
     */
    public function saveSprice(Request $request)
    {
        $request->validate([
            'updates'              => 'required|array|min:1',
            'updates.*.sku'        => 'required|string|max:255',
            'updates.*.sprice'     => 'nullable|numeric',
        ]);

        $updates = $request->input('updates', []);
        $saved   = 0;

        try {
            DB::beginTransaction();
            foreach ($updates as $u) {
                $sku = trim((string) ($u['sku'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    continue;
                }
                $raw    = $u['sprice'] ?? null;
                $sprice = ($raw === null || $raw === '') ? null : round((float) $raw, 2);

                DepopPricing::updateOrCreate(
                    ['sku' => $sku],
                    ['sprice' => $sprice]
                );
                $saved++;
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'updated' => $saved,
                'message' => "Saved SPRICE for {$saved} SKU(s)",
            ]);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Depop pricing saveSprice failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
