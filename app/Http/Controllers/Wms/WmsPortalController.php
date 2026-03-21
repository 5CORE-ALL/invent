<?php

namespace App\Http\Controllers\Wms;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Wms\Bin;
use App\Services\Wms\BarcodeService;
use Illuminate\View\View;

class WmsPortalController extends Controller
{
    public function dashboard(): View
    {
        $warehouses = Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('wms.dashboard', compact('warehouses'));
    }

    public function structure(): View
    {
        $warehouses = Warehouse::query()->orderBy('name')->get();

        return view('wms.structure', compact('warehouses'));
    }

    public function inventoryByLocation(): View
    {
        $warehouses = Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('wms.inventory-by-location', compact('warehouses'));
    }

    public function scan(): View
    {
        $demoBin = Bin::query()->orderBy('id')->first();
        $demoBinId = $demoBin?->id;
        $demoScanCode = null;
        $demoSku = null;

        if ($demoBin) {
            $invOnBin = Inventory::query()
                ->where('bin_id', $demoBin->id)
                ->orderBy('id')
                ->first();
            if ($invOnBin) {
                $p = ProductMaster::query()->where('sku', $invOnBin->sku)->first();
                if ($p) {
                    app(BarcodeService::class)->ensureBarcode($p);
                    $p->refresh();
                    $demoSku = $p->sku;
                    $demoScanCode = $p->barcode ?: $p->sku;
                }
            }
        }

        if (! $demoScanCode) {
            $p = ProductMaster::query()->orderBy('id')->first();
            if ($p) {
                app(BarcodeService::class)->ensureBarcode($p);
                $p->refresh();
                $demoSku = $p->sku;
                $demoScanCode = $p->barcode ?: $p->sku;
            }
        }

        return view('wms.scan', compact('demoBinId', 'demoScanCode', 'demoSku'));
    }

    public function movements(): View
    {
        return view('wms.movements');
    }

    public function pick(): View
    {
        $warehouses = Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('wms.pick', compact('warehouses'));
    }

    public function putaway(): View
    {
        $warehouses = Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('wms.putaway', compact('warehouses'));
    }

    public function locate(): View
    {
        return view('wms.locate');
    }
}
