<?php

namespace App\Console\Commands;

use App\Http\Controllers\ApiController;
use App\Models\ProductMaster;
use App\Models\TemuProductSheet;
use Illuminate\Console\Command;

class SyncTemuSip extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:sync-temu-sip';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new ApiController();
        $sheet = $controller->fetchDataFromTemuListingDataSheet();
        $rows = collect($sheet->getData()->data ?? []);

        foreach ($rows as $row) {
            $sku = trim($row->{'(Child) sku'} ?? '');
            if (!$sku) continue;

            $product = ProductMaster::firstOrNew(['sku' => $sku]);
            
            // Get existing Values or initialize empty array
            $values = $product->Values ?? [];
            
            // Update the temu_ship value in Values
            $values['temu_ship'] = $this->toDecimalOrNull($row->{'Temu Shipping'} ?? null);
            
            // Save the updated Values
            $product->Values = $values;
            $product->save();
        }

        $this->info('Temu sheet synced successfully!');
    }

    private function toDecimalOrNull($value)
    {
        return is_numeric($value) ? round((float)$value, 2) : null;
    }

    private function toIntOrNull($value)
    {
        return is_numeric($value) ? (int)$value : null;
    }
}
