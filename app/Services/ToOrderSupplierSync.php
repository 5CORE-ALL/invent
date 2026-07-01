<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single write path for SKU supplier across the purchase pipeline:
 * to_order_analysis, mfrg_progress (MIP), ready_to_ship (R2S), transit_container_details (Transit).
 * Forecast / approval.required read to_order_analysis first, then mfrg.
 */
class ToOrderSupplierSync
{
    /**
     * Propagate supplier to every pipeline table for this SKU (any page save path).
     */
    public static function setSupplierForSku(string $sku, $supplierValue, ?string $parent = null): void
    {
        $skuUpper = strtoupper(trim($sku));
        if ($skuUpper === '') {
            return;
        }

        $supplierName = $supplierValue === null ? '' : trim((string) $supplierValue);
        $parentTrim = $parent !== null ? trim($parent) : '';
        $now = now();

        // Canonical: to_order_analysis.supplier_name
        $toUpdated = (int) DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
            ->update([
                'supplier_name' => $supplierName,
                'updated_at' => $now,
            ]);

        if ($toUpdated === 0) {
            DB::table('to_order_analysis')->insert([
                'sku' => $sku,
                'parent' => $parentTrim !== '' ? $parentTrim : null,
                'supplier_name' => $supplierName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // MIP
        $mfrgUpdated = (int) DB::table('mfrg_progress')
            ->whereNull('deleted_at')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
            ->update([
                'supplier' => $supplierName,
                'updated_at' => $now,
            ]);

        if ($mfrgUpdated === 0) {
            DB::table('mfrg_progress')->insert([
                'sku' => $sku,
                'parent' => $parentTrim !== '' ? $parentTrim : null,
                'supplier' => $supplierName,
                'ready_to_ship' => 'No',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // R2S
        if (Schema::hasTable('ready_to_ship') && Schema::hasColumn('ready_to_ship', 'supplier')) {
            DB::table('ready_to_ship')
                ->whereNull('deleted_at')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                ->update([
                    'supplier' => $supplierName,
                    'updated_at' => $now,
                ]);
        }

        // Transit
        if (Schema::hasTable('transit_container_details') && Schema::hasColumn('transit_container_details', 'supplier_name')) {
            DB::table('transit_container_details')
                ->whereNull('deleted_at')
                ->whereRaw('TRIM(UPPER(our_sku)) = ?', [$skuUpper])
                ->update([
                    'supplier_name' => $supplierName,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * @deprecated Use setSupplierForSku() — kept for backfill command compatibility.
     */
    public static function syncFromMfrg(string $sku, $supplierValue, ?string $parent = null): int
    {
        self::setSupplierForSku($sku, $supplierValue, $parent);

        return 1;
    }

    /**
     * Resolve supplier for display: to_order_analysis (latest explicit) → mfrg → r2s → transit.
     */
    public static function resolveSupplierForSku(string $sku): string
    {
        $skuUpper = strtoupper(trim($sku));
        if ($skuUpper === '') {
            return '';
        }

        $toRows = DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['supplier_name']);

        foreach ($toRows as $row) {
            if ($row->supplier_name !== null) {
                return trim((string) $row->supplier_name);
            }
        }

        $mfrg = DB::table('mfrg_progress')
            ->whereNull('deleted_at')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
            ->whereNotNull('supplier')
            ->whereRaw("TRIM(supplier) != ''")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('supplier');

        if ($mfrg !== null && trim((string) $mfrg) !== '') {
            return trim((string) $mfrg);
        }

        if (Schema::hasTable('ready_to_ship') && Schema::hasColumn('ready_to_ship', 'supplier')) {
            $rts = DB::table('ready_to_ship')
                ->whereNull('deleted_at')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                ->whereNotNull('supplier')
                ->whereRaw("TRIM(supplier) != ''")
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('supplier');

            if ($rts !== null && trim((string) $rts) !== '') {
                return trim((string) $rts);
            }
        }

        if (Schema::hasTable('transit_container_details')) {
            $trn = DB::table('transit_container_details')
                ->whereNull('deleted_at')
                ->whereRaw('TRIM(UPPER(our_sku)) = ?', [$skuUpper])
                ->whereNotNull('supplier_name')
                ->whereRaw("TRIM(supplier_name) != ''")
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('supplier_name');

            if ($trn !== null && trim((string) $trn) !== '') {
                return trim((string) $trn);
            }
        }

        return '';
    }

    /**
     * SKUs where mfrg_progress.supplier differs from the latest to_order_analysis row.
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

            if ($toOrder === null || $toOrder->supplier_name === null) {
                continue;
            }

            $toOrderSupplier = trim((string) $toOrder->supplier_name);

            if ($toOrderSupplier === $mfrgSupplier) {
                continue;
            }

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
