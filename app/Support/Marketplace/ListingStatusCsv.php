<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingStatusCsv
{
    /**
     * @param  class-string  $statusClass
     */
    public static function import(Request $request, string $statusClass): JsonResponse
    {
        $table = (new $statusClass)->getTable();
        if (! Schema::hasTable($table)) {
            return response()->json(['error' => 'Listing status table is not ready. Run migrations.'], 503);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file->getRealPath()) ?: []);
        $header = array_map(function ($h) {
            return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h) ?? ''));
        }, $rows[0] ?? []);
        unset($rows[0]);

        $allowedHeaders = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($header as $h) {
            if ($h !== '' && ! in_array($h, $allowedHeaders, true)) {
                return response()->json([
                    'error' => "Invalid header '$h'. Allowed headers: ".implode(', ', $allowedHeaders),
                ], 422);
            }
        }

        $processed = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || count($row) < 1) {
                $skipped++;
                continue;
            }
            $headerCount = count($header);
            if (count($row) < $headerCount) {
                $row = array_pad($row, $headerCount, '');
            } elseif (count($row) > $headerCount) {
                $row = array_slice($row, 0, $headerCount);
            }

            $rowData = array_combine($header, $row) ?: [];
            $sku = trim((string) ($rowData['sku'] ?? ''));
            if ($sku === '' || ! ProductMaster::where('sku', $sku)->whereNull('deleted_at')->exists()) {
                $skipped++;
                continue;
            }

            $status = $statusClass::where('sku', $sku)->first();
            $existing = $status ? ($status->value ?? []) : [];
            if (! is_array($existing)) {
                $existing = [];
            }
            foreach (['nr_req', 'listed', 'buyer_link', 'seller_link'] as $field) {
                if (array_key_exists($field, $rowData) && $rowData[$field] !== '') {
                    $existing[$field] = trim((string) $rowData[$field]);
                }
            }

            $statusClass::updateOrCreate(['sku' => $sku], ['value' => $existing]);
            $processed++;
        }

        return response()->json([
            'success' => 'CSV imported successfully',
            'processed' => $processed,
            'skipped' => $skipped,
        ]);
    }

    /**
     * @param  class-string  $statusClass
     */
    public static function export(string $statusClass, string $filename): StreamedResponse
    {
        $table = (new $statusClass)->getTable();
        if (! Schema::hasTable($table)) {
            return new StreamedResponse(function () {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link']);
                fclose($file);
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];
        $columns = ['sku', 'nr_req', 'listed', 'buyer_link', 'seller_link'];

        $callback = function () use ($columns, $statusClass) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach (ProductMaster::query()->whereNull('deleted_at')->pluck('sku') as $sku) {
                $status = $statusClass::where('sku', $sku)->first();
                $value = is_array($status?->value) ? $status->value : [];
                fputcsv($file, [
                    'sku' => $sku,
                    'nr_req' => $value['nr_req'] ?? '',
                    'listed' => $value['listed'] ?? '',
                    'buyer_link' => $value['buyer_link'] ?? '',
                    'seller_link' => $value['seller_link'] ?? '',
                ]);
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
