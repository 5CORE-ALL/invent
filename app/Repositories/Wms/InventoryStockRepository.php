<?php

namespace App\Repositories\Wms;

use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class InventoryStockRepository
{
    public function findLocked(string $sku, ?int $warehouseId, ?int $binId): ?Inventory
    {
        $q = Inventory::query()->where('sku', $sku)->lockForUpdate();
        if ($warehouseId !== null) {
            $q->where('warehouse_id', $warehouseId);
        }
        if ($binId !== null) {
            $q->where('bin_id', $binId);
        } else {
            $q->whereNull('bin_id');
        }

        return $q->first();
    }

    public function firstOrNew(string $sku, int $warehouseId, ?int $binId): Inventory
    {
        $q = Inventory::query()->where('sku', $sku)->where('warehouse_id', $warehouseId);
        if ($binId !== null) {
            $q->where('bin_id', $binId);
        } else {
            $q->whereNull('bin_id');
        }

        $row = $q->lockForUpdate()->first();
        if ($row) {
            return $row;
        }

        return new Inventory([
            'sku' => $sku,
            'warehouse_id' => $warehouseId,
            'bin_id' => $binId,
            'on_hand' => 0,
            'pick_locked_qty' => 0,
        ]);
    }

    public function transaction(callable $fn): mixed
    {
        return DB::transaction($fn);
    }
}
