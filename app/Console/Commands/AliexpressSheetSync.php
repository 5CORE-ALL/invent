<?php

namespace App\Console\Commands;

use App\Http\Controllers\ApiController;
use App\Models\AliExpressSheetData;
use Illuminate\Console\Command;

class AliexpressSheetSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:aliexpress-sheet-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync AliExpress sheet data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting AliExpress sheet sync...');

        $controller = new ApiController();
        $sheet = $controller->fetchDataFromAliExpressGoogleSheet();
        $rows = collect($sheet->getData()->data ?? []);

        $this->info('Fetched ' . $rows->count() . ' rows from Google Sheet.');

        $count = 0;
        foreach ($rows as $row) {
            $sku = trim($row->{'(Child) sku'} ?? '');
            if (!$sku) continue;

            AliExpressSheetData::updateOrCreate(
                ['sku' => $sku],
                [
                    'price'     => $this->toDecimalOrNull($row->{'Main Price'} ?? null),
                    'aliexpress_l30' => $this->toIntOrNull($row->{'AEL30'} ?? null),
                    'aliexpress_l60' => $this->toDecimalOrNull($row->{'AEL60'} ?? null),

                ]
            );
            $count++;
        }

        $this->info('Synced ' . $count . ' SKUs successfully.');
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
