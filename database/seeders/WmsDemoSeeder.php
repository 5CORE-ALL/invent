<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Wms\Bin;
use App\Models\Wms\Rack;
use App\Models\Wms\Shelf;
use App\Models\Wms\Zone;
use App\Services\Wms\BarcodeService;
use App\Services\Wms\LocationCodeService;
use Illuminate\Database\Seeder;

/**
 * Demo zone/rack/shelf/bin and optional sample inventory. Run after WMS migrations:
 * php artisan wms:install --seed
 * Or only seed: php artisan db:seed --class=WmsDemoSeeder
 */
class WmsDemoSeeder extends Seeder
{
    public const DEMO_SKU = 'WMS-DEMO-001';

    public function run(): void
    {
        Inventory::query()->where('bin_id', '<=', 0)->update(['bin_id' => null]);

        $warehouse = Warehouse::query()->orderBy('id')->first();
        if (! $warehouse) {
            $this->command?->warn('No warehouse row found — create one first.');

            return;
        }

        if (! $warehouse->code) {
            $warehouse->code = 'WH1';
            $warehouse->save();
        }

        $zone = Zone::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'Z1'],
            ['name' => 'Zone 1']
        );

        $rack = Rack::query()->firstOrCreate(
            ['zone_id' => $zone->id, 'code' => 'R1'],
            ['name' => 'Rack 1', 'pick_priority' => 10]
        );

        $shelf = Shelf::query()->firstOrCreate(
            ['rack_id' => $rack->id, 'code' => 'S1'],
            ['name' => 'Shelf 1']
        );

        $bin = Bin::query()->firstOrCreate(
            ['shelf_id' => $shelf->id, 'code' => 'B1'],
            ['name' => 'Bin 1', 'capacity' => 500]
        );

        $bin2 = Bin::query()->firstOrCreate(
            ['shelf_id' => $shelf->id, 'code' => 'B2'],
            ['name' => 'Bin 2', 'capacity' => 500]
        );

        $locationCode = app(LocationCodeService::class);
        $locationCode->refreshBinFullCode($bin->fresh());
        $locationCode->refreshBinFullCode($bin2->fresh());

        $product = ProductMaster::query()->where('sku', self::DEMO_SKU)->first();
        if (! $product) {
            $template = ProductMaster::query()->orderBy('id')->first();
            if (! $template) {
                $this->command?->warn('No product_master row to clone — demo bins created; add a product manually then re-run or assign stock in UI.');

                return;
            }
            $product = $template->replicate();
            $product->sku = self::DEMO_SKU;
            $product->barcode = null;
            $product->save();
        }

        app(BarcodeService::class)->ensureBarcode($product);
        $product->refresh();

        Inventory::query()->updateOrCreate(
            [
                'sku' => $product->sku,
                'warehouse_id' => $warehouse->id,
                'bin_id' => $bin->id,
            ],
            [
                'on_hand' => 25,
                'pick_locked_qty' => 0,
            ]
        );

        $this->command?->info('WMS demo: warehouse '.$warehouse->id.', bins '.$bin->id.' ('.$bin->fresh()->full_location_code.') & '.$bin2->id.' ('.$bin2->fresh()->full_location_code.')');
        $this->command?->info('Scan test: SKU or barcode '.$product->sku.' / '.$product->barcode);
    }
}
