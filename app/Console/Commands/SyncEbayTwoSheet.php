<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ApiController;
use App\Models\EbayTwoProductSheet;

class SyncEbayTwoSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:ebay-two-sheet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync eBay 2 product sheet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new ApiController();
        $sheet = $controller->fetchDataFromEbay2ListingDataSheet();
        $rows = collect($sheet->getData()->data ?? []);

        foreach ($rows as $row) {
            $sku = trim($row->{'SKU'} ?? '');
            if (!$sku) continue;

            EbayTwoProductSheet::updateOrCreate(
                ['sku' => $sku],
                [
                    'price'     => $this->toDecimalOrNull($row->{'eBay Price'} ?? null),
                    'pft'       => $this->toDecimalOrNull($row->{'PFT %'} ?? null),
                    'roi'       => $this->toDecimalOrNull($row->{'ROI%'} ?? null),
                    'l30'       => $this->toIntOrNull($row->{'E L30'} ?? null),
                    'dil'       => $this->toDecimalOrNull($row->{'Dil%'} ?? null),
                    'buy_link'  => trim($row->{'Buyer Link'} ?? ''),
                ]
            );
        }
    

        $this->info('EBay 2 sheet synced successfully!');
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
