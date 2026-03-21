<?php

namespace App\Services\Wms;

use App\Models\Warehouse;
use App\Models\Wms\Bin;
use App\Models\Wms\Rack;
use App\Models\Wms\Shelf;
use App\Models\Wms\Zone;

class LocationCodeService
{
    public function warehouseSegment(Warehouse $warehouse): string
    {
        return $warehouse->code ?: 'WH'.$warehouse->id;
    }

    public function buildFullCode(Warehouse $warehouse, Zone $zone, Rack $rack, Shelf $shelf, Bin $bin): string
    {
        return implode('-', [
            $this->warehouseSegment($warehouse),
            $zone->code,
            $rack->code,
            $shelf->code,
            $bin->code,
        ]);
    }

    public function refreshBinFullCode(Bin $bin): void
    {
        $bin->loadMissing('shelf.rack.zone.warehouse');
        $shelf = $bin->shelf;
        if (! $shelf || ! $shelf->rack || ! $shelf->rack->zone || ! $shelf->rack->zone->warehouse) {
            return;
        }

        $wh = $shelf->rack->zone->warehouse;
        $zone = $shelf->rack->zone;
        $rack = $shelf->rack;

        $bin->full_location_code = $this->buildFullCode($wh, $zone, $rack, $shelf, $bin);
        $bin->saveQuietly();
    }
}
