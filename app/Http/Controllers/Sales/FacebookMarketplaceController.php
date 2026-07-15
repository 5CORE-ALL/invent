<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MarketPlace\ShopifyAdsMasterController;
use App\Models\FacebookMarketplaceSale;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacebookMarketplaceController extends Controller
{
    /** Canonical template column order — used for download + upload column mapping. */
    public const TEMPLATE_COLUMNS = ['sku', 'qty_sold', 'sold_price', 'order_number'];

    /**
     * Facebook Marketplace sales page.
     *
     * GET /facebook-marketplace
     */
    public function index()
    {
        $y = self::computeYesterdaySales();

        return view('sales.facebook_marketplace', [
            'ySales'     => $y['sales'],
            'ySalesDate' => $y['date'],
            'yQuantity'  => $y['quantity'],
            'yOrders'    => $y['orders'],
        ]);
    }

    /**
     * Y Sales — sold_price × qty for Pacific (California) wall-clock yesterday.
     * Same source/rule as the FB Marketplace row on /all-marketplace-master.
     * Prefers order_date; falls back to created_at (PT calendar day) when order_date is null.
     *
     * @return array{sales: float, date: string, quantity: int, orders: int}
     */
    public static function computeYesterdaySales(): array
    {
        $tz = 'America/Los_Angeles';
        $yesterday = Carbon::yesterday($tz)->toDateString();
        $rangeStartUtc = Carbon::parse($yesterday, $tz)->startOfDay()->utc();
        $rangeEndUtc = Carbon::parse($yesterday, $tz)->endOfDay()->utc();

        $sales = 0.0;
        $qty = 0;
        $orderSet = [];

        FacebookMarketplaceSale::query()
            ->where(function ($q) use ($yesterday, $rangeStartUtc, $rangeEndUtc) {
                $q->whereBetween('order_date', [$yesterday, $yesterday])
                    ->orWhere(function ($q2) use ($rangeStartUtc, $rangeEndUtc) {
                        $q2->whereNull('order_date')
                            ->whereBetween('created_at', [
                                $rangeStartUtc->toDateTimeString(),
                                $rangeEndUtc->toDateTimeString(),
                            ]);
                    });
            })
            ->get(['sold_price', 'qty_sold', 'order_number'])
            ->each(function ($r) use (&$sales, &$qty, &$orderSet) {
                $lineQty = (int) $r->qty_sold;
                $sales += (float) $r->sold_price * $lineQty;
                $qty += $lineQty;
                $orderNo = trim((string) ($r->order_number ?? ''));
                if ($orderNo !== '') {
                    $orderSet[$orderNo] = true;
                }
            });

        return [
            'sales'    => round($sales, 2),
            'date'     => $yesterday,
            'quantity' => $qty,
            'orders'   => count($orderSet),
        ];
    }

    /**
     * Live Sales / GPFT / ROI from uploaded facebook_marketplace_sales rows.
     * Shared by /facebook-marketplace and /all-marketplace-master (FB Marketplace row).
     *
     * Formula (no ship; margin from marketplace_percentages):
     *   unit_pft = sold_price × factor − LP
     *   GPFT%    = Σ(unit_pft × qty) / Σ(sold_price × qty) × 100
     *   ROI%     = Σ(unit_pft × qty) / Σ(LP × qty) × 100
     *
     * @return array{
     *   rows: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *   summary: array<string, float|int|string>
     * }
     */
    public static function computeLiveMetrics(): array
    {
        $mpRow = MarketplacePercentage::where('marketplace', 'FB Marketplace')->first()
            ?: MarketplacePercentage::where('marketplace', 'FBMarketplace')->first();
        $percentage = $mpRow && $mpRow->percentage !== null ? (float) $mpRow->percentage : null;
        $factor = ($percentage !== null ? $percentage : 100) / 100;

        $lpBySku = [];
        foreach (ProductMaster::query()->get(['sku', 'Values']) as $pm) {
            $skuKey = strtoupper(trim((string) $pm->sku));
            if ($skuKey === '' || stripos($skuKey, 'PARENT') !== false) {
                continue;
            }
            $values = is_array($pm->Values)
                ? $pm->Values
                : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);
            if (!is_array($values)) {
                $values = [];
            }
            $lp = 0.0;
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'lp') {
                    $lp = (float) $v;
                    break;
                }
            }
            $lpBySku[$skuKey] = $lp;
            $lpBySku[str_replace(' ', '', $skuKey)] = $lp;
        }

        $totalPft = 0.0;
        $totalSales = 0.0;
        $totalCogs = 0.0;
        $totalQty = 0;
        $orderSet = [];

        $rows = FacebookMarketplaceSale::orderByDesc('id')
            ->get()
            ->map(function ($r) use ($factor, $lpBySku, &$totalPft, &$totalSales, &$totalCogs, &$totalQty, &$orderSet) {
                $qty = (int) $r->qty_sold;
                $price = (float) $r->sold_price;
                $lineTotal = $price * $qty;
                $skuKey = strtoupper(trim((string) $r->sku));
                $lp = (float) ($lpBySku[$skuKey] ?? $lpBySku[str_replace(' ', '', $skuKey)] ?? 0);

                $unitPft = ($price * $factor) - $lp;
                $gpft = $price > 0 ? ($unitPft / $price) * 100 : 0.0;
                $roi = $lp > 0 ? ($unitPft / $lp) * 100 : 0.0;

                if ($qty > 0 && $price > 0) {
                    $totalPft += $unitPft * $qty;
                    $totalSales += $lineTotal;
                    $totalCogs += $lp * $qty;
                    $totalQty += $qty;
                }
                $orderNo = trim((string) ($r->order_number ?? ''));
                if ($orderNo !== '') {
                    $orderSet[$orderNo] = true;
                }

                return [
                    'id'           => $r->id,
                    'order_number' => $r->order_number,
                    'sku'          => $r->sku,
                    'qty_sold'     => $qty,
                    'sold_price'   => round($price, 2),
                    'total'        => round($lineTotal, 2),
                    'lp'           => round($lp, 2),
                    'gpft'         => round($gpft, 1),
                    'roi'          => round($roi, 1),
                    'line_pft'     => round($unitPft * $qty, 2),
                    'order_date'   => optional($r->order_date)->format('Y-m-d'),
                    'notes'        => $r->notes,
                    'created_at'   => optional($r->created_at)->format('Y-m-d H:i:s'),
                ];
            })
            ->values();

        $gpftPct = $totalSales > 0 ? ($totalPft / $totalSales) * 100 : 0.0;
        $roiPct = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0.0;

        return [
            'rows' => $rows,
            'summary' => [
                'marketplace' => $mpRow->marketplace ?? 'FB Marketplace',
                'margin_percent' => $percentage !== null ? round($percentage, 2) : 100.0,
                'factor' => $factor,
                'total_sales' => round($totalSales, 2),
                'total_quantity' => $totalQty,
                'total_orders' => count($orderSet),
                'total_pft' => round($totalPft, 2),
                'total_cogs' => round($totalCogs, 2),
                'gpft_percent' => round($gpftPct, 1),
                'roi_percent' => round($roiPct, 1),
            ],
        ];
    }

    /**
     * Sales rows for the Tabulator grid.
     *
     * GET /facebook-marketplace/data
     */
    public function getData(Request $request)
    {
        $computed = self::computeLiveMetrics();
        $summary = $computed['summary'];

        // Facebook ads — same TCOS as /facebook-ads + master: Spend / Shopify S Sales
        $adSpend = 0.0;
        $shopifyNetSales = 0.0;
        try {
            $adSpend = (float) (app(ShopifyAdsMasterController::class)->getFacebookChannelSpend()['spend'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning('facebook-marketplace ads spend failed: ' . $e->getMessage());
        }
        try {
            $shopifyNetSales = (float) ShopifyAdsMasterController::advertisementMasterNetSales();
        } catch (\Throwable $e) {
            Log::warning('facebook-marketplace Shopify S Sales failed: ' . $e->getMessage());
        }
        $gpft = (float) ($summary['gpft_percent'] ?? 0);
        $roi = (float) ($summary['roi_percent'] ?? 0);
        $adsPct = $shopifyNetSales > 0
            ? ($adSpend / $shopifyNetSales) * 100
            : ($adSpend > 0 ? 100.0 : 0.0);
        $summary['total_ad_spend'] = round($adSpend, 2);
        $summary['ads_percent'] = round($adsPct, 1);
        $summary['npft_percent'] = round($gpft - $adsPct, 1);
        $summary['nroi_percent'] = round($roi - $adsPct, 1);

        $y = self::computeYesterdaySales();
        $summary['y_sales'] = $y['sales'];
        $summary['y_sales_date'] = $y['date'];
        $summary['y_quantity'] = $y['quantity'];
        $summary['y_orders'] = $y['orders'];

        return response()->json([
            'data' => $computed['rows'],
            'summary' => $summary,
        ]);
    }

    /**
     * Download a blank CSV template (with one example row).
     *
     * GET /facebook-marketplace/template
     */
    public function downloadTemplate(): StreamedResponse
    {
        $filename = 'facebook_marketplace_template.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache',
        ];

        return response()->stream(function () {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 cleanly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::TEMPLATE_COLUMNS);
            fputcsv($out, ['ABC-123', 2, 19.99, 'FBM-1001']);
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Handle CSV upload — upserts rows by (order_number + sku).
     *
     * POST /facebook-marketplace/upload
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file   = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        if (!$handle) {
            return response()->json([
                'success' => false,
                'message' => 'Could not open uploaded file.',
            ], 422);
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Uploaded file is empty.',
                ], 422);
            }

            // Strip BOM from first cell if present and normalize header names.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
            $normalized = array_map(function ($h) {
                return strtolower(trim(str_replace([' ', '-'], '_', (string) $h)));
            }, $header);

            $colIndex = [];
            foreach (self::TEMPLATE_COLUMNS as $col) {
                $idx = array_search($col, $normalized, true);
                if ($idx === false) {
                    return response()->json([
                        'success' => false,
                        'message' => "Missing required column: {$col}. Expected columns: " . implode(', ', self::TEMPLATE_COLUMNS),
                    ], 422);
                }
                $colIndex[$col] = $idx;
            }

            $imported = 0;
            $skipped  = 0;
            $errors   = [];
            $rowNum   = 1; // header row was row 1

            DB::beginTransaction();

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }

                $sku         = trim((string) ($row[$colIndex['sku']] ?? ''));
                $qtySold     = trim((string) ($row[$colIndex['qty_sold']] ?? ''));
                $soldPrice   = trim((string) ($row[$colIndex['sold_price']] ?? ''));
                $orderNumber = trim((string) ($row[$colIndex['order_number']] ?? ''));

                if ($sku === '' || $orderNumber === '') {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: missing sku or order_number";
                    continue;
                }

                $qty   = (int) ($qtySold === '' ? 0 : $qtySold);
                $price = (float) ($soldPrice === '' ? 0 : str_replace([',', '$'], '', $soldPrice));

                FacebookMarketplaceSale::updateOrCreate(
                    [
                        'order_number' => $orderNumber,
                        'sku'          => $sku,
                    ],
                    [
                        'qty_sold'   => $qty,
                        'sold_price' => $price,
                    ]
                );
                $imported++;
            }

            DB::commit();
            fclose($handle);

            return response()->json([
                'success'  => true,
                'message'  => "Imported {$imported} rows" . ($skipped ? ", skipped {$skipped}" : '') . '.',
                'imported' => $imported,
                'skipped'  => $skipped,
                'errors'   => array_slice($errors, 0, 20),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            Log::error('Facebook Marketplace upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a single row.
     *
     * DELETE /facebook-marketplace/{id}
     */
    public function destroy($id)
    {
        $row = FacebookMarketplaceSale::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Row not found.'], 404);
        }
        $row->delete();
        return response()->json(['success' => true, 'message' => 'Row deleted.']);
    }
}
