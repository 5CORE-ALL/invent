<?php

namespace App\Console\Commands;

use App\Models\NeweeggProductSheet;
use Illuminate\Console\Command;
use App\Http\Controllers\ApiController;

class SyncNeweeggSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:neweegg-sheet';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Neweegg Product Sheet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new ApiController();
        $sheet = $controller->fetchDataFromNeweggB2CMasterGoogleSheet();
        $rows = collect($sheet->getData()->data ?? []);

        foreach ($rows as $row) {
            $sku = trim($row->{'(Child) sku'} ?? '');
            if (!$sku) continue;

            NeweeggProductSheet::updateOrCreate(
                ['sku' => $sku],
                [
                    'price'     => $this->toDecimalOrNull($row->{'Newegg Price'} ?? null),
                    'pft'       => $this->toDecimalOrNull($row->{'PFT%'} ?? null),
                    'roi'       => $this->toDecimalOrNull($row->{'ROI'} ?? null),
                    'l30'       => $this->toIntOrNull($row->{'N L30'} ?? null),
                    'dil'       => $this->toDecimalOrNull($row->{'N Dil%'} ?? null),  
                    'buy_link'  => trim($row->{'Buyer Link'} ?? ''),
                ]
            );
        }

        

        $this->info('Newegg sheet synced successfully!');
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
