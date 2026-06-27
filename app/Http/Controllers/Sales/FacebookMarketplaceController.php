<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\FacebookMarketplaceSale;
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
        return view('sales.facebook_marketplace');
    }

    /**
     * Sales rows for the Tabulator grid.
     *
     * GET /facebook-marketplace/data
     */
    public function getData(Request $request)
    {
        $rows = FacebookMarketplaceSale::orderByDesc('id')
            ->get()
            ->map(function ($r) {
                return [
                    'id'           => $r->id,
                    'order_number' => $r->order_number,
                    'sku'          => $r->sku,
                    'qty_sold'     => (int) $r->qty_sold,
                    'sold_price'   => round((float) $r->sold_price, 2),
                    'total'        => round((float) $r->sold_price * (int) $r->qty_sold, 2),
                    'order_date'   => optional($r->order_date)->format('Y-m-d'),
                    'notes'        => $r->notes,
                    'created_at'   => optional($r->created_at)->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json($rows);
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
