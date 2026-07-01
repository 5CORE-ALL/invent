<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ToOrderSupplierSync
{
    /**
     * Mirror mfrg_progress.supplier into to_order_analysis.supplier_name so
     * /forecast.analysis and /approval.required show the same supplier after refresh.
     *
     * @return int Rows updated or created (0 if skipped)
     */
    public static function syncFromMfrg(string $sku, $supplierValue): int
    {
        $skuUpper = strtoupper(trim($sku));
        if ($skuUpper === '') {
            return 0;
        }

        $supplierName = $supplierValue === null ? '' : trim((string) $supplierValue);

        $updated = (int) DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
            ->update([
                'supplier_name' => $supplierName,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            DB::table('to_order_analysis')->insert([
                'sku' => $sku,
                'supplier_name' => $supplierName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        }

        return $updated;
    }

    /**
     * SKUs where mfrg_progress.supplier differs from the latest to_order_analysis row
     * (same rule /forecast.analysis uses when loading the Supplier column).
     *
     * @return list<array{sku: string, mfrg_supplier: string, to_order_supplier: string|null, mfrg_updated_at: string|null, to_order_updated_at: string|null}>
     */
    public static function findOutOfSyncSkus(?string $skuFilter = null, bool $ignoreTimestamp = false): array
    {
        $mfrgRows = DB::table('mfrg_progress')
            ->whereNull('deleted_at')
            ->whereNotNull('supplier')
            ->whereRaw("TRIM(supplier) != ''")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'supplier', 'updated_at']);

        $latestMfrgBySku = [];
        foreach ($mfrgRows as $row) {
            $key = strtoupper(trim((string) $row->sku));
            if ($key === '' || isset($latestMfrgBySku[$key])) {
                continue;
            }
            $latestMfrgBySku[$key] = $row;
        }

        $toOrderRows = DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'supplier_name', 'updated_at']);

        $latestToOrderBySku = [];
        foreach ($toOrderRows as $row) {
            $key = strtoupper(trim((string) $row->sku));
            if ($key === '' || isset($latestToOrderBySku[$key])) {
                continue;
            }
            $latestToOrderBySku[$key] = $row;
        }

        $filter = $skuFilter !== null ? strtoupper(trim($skuFilter)) : null;
        $out = [];

        foreach ($latestMfrgBySku as $skuKey => $mfrg) {
            if ($filter !== null && $filter !== '' && $skuKey !== $filter) {
                continue;
            }

            $mfrgSupplier = trim((string) $mfrg->supplier);
            $toOrder = $latestToOrderBySku[$skuKey] ?? null;

            // /forecast.analysis only prefers to_order when supplier_name is NOT null.
            // Rows with null to_order supplier already display mfrg — skip those.
            if ($toOrder === null || $toOrder->supplier_name === null) {
                continue;
            }

            $toOrderSupplier = trim((string) $toOrder->supplier_name);

            if ($toOrderSupplier === $mfrgSupplier) {
                continue;
            }

            // Only backfill when MIP was updated at or after To Order — the forecast-edit
            // bug wrote to mfrg_progress only, so the newer timestamp is usually correct.
            $mfrgAt = $mfrg->updated_at ? strtotime((string) $mfrg->updated_at) : 0;
            $toAt = $toOrder->updated_at ? strtotime((string) $toOrder->updated_at) : 0;
            if ($mfrgAt > 0 && $toAt > 0 && $mfrgAt < $toAt && ! $ignoreTimestamp) {
                continue;
            }

            $out[] = [
                'sku' => (string) $mfrg->sku,
                'mfrg_supplier' => $mfrgSupplier,
                'to_order_supplier' => $toOrderSupplier,
                'mfrg_updated_at' => $mfrg->updated_at,
                'to_order_updated_at' => $toOrder->updated_at ?? null,
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['sku'], $b['sku']));

        return $out;
    }
}
