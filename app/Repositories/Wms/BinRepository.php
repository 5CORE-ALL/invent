<?php

namespace App\Repositories\Wms;

use App\Models\Inventory;
use App\Models\Wms\Bin;
use Illuminate\Support\Collection;

class BinRepository
{
    public function findWithHierarchy(int $id): ?Bin
    {
        return Bin::query()
            ->with(['shelf.rack.zone.warehouse'])
            ->find($id);
    }

    public function warehouseIdForBin(Bin $bin): ?int
    {
        if (! $bin->relationLoaded('shelf')) {
            $bin->load('shelf.rack.zone');
        }
        $shelf = $bin->shelf;
        if (! $shelf || ! $shelf->rack || ! $shelf->rack->zone) {
            return null;
        }

        return (int) $shelf->rack->zone->warehouse_id;
    }

    /**
     * Suggest nearest pick bin: lowest rack.pick_priority, then bin id.
     */
    public function suggestPickBinForSku(string $sku, ?int $warehouseId = null): ?Bin
    {
        $bins = Bin::query()
            ->with(['shelf.rack.zone'])
            ->whereHas('inventories', function ($q) use ($sku) {
                $q->where('sku', $sku)->where('on_hand', '>', 0);
            })
            ->get();

        if ($warehouseId !== null) {
            $bins = $bins->filter(function (Bin $bin) use ($warehouseId) {
                $z = $bin->shelf?->rack?->zone;

                return $z && (int) $z->warehouse_id === $warehouseId;
            });
        }

        return $bins->sortBy(function (Bin $bin) {
            $pri = $bin->shelf?->rack?->pick_priority ?? 99999;

            return [(int) $pri, $bin->id];
        })->first();
    }

    public function productsInBin(int $binId): Collection
    {
        return Inventory::query()
            ->where('bin_id', $binId)
            ->with(['bin.shelf.rack.zone.warehouse'])
            ->orderBy('sku')
            ->get();
    }
}
