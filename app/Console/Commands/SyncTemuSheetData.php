<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ApiController;
use App\Models\TemuMetric;
use App\Models\TemuProductSheet;

class SyncTemuSheetData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:temu-sheet-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Temu product sheet data';

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

            TemuMetric::updateOrCreate(
                ['sku' => $sku],
                [
                    'temu_sheet_price'     => $this->toDecimalOrNull($row->{'R prc'} ?? null),
                    // 'pft'       => $this->toDecimalOrNull($row->{'Pft%'} ?? null),
                    // 'roi'       => $this->toDecimalOrNull($row->{'ROI%'} ?? null),
                    // 'l30'       => $this->toIntOrNull($row->{'TL30'} ?? null),
                    // 'l60'       => $this->toIntOrNull($row->{'T L60'} ?? null),
                    // 'dil'       => $this->toDecimalOrNull($row->{'Dil%'} ?? null),
                    // 'clicks'    => $this->toIntOrNull($row->{'Clicks'} ?? null)
                   
                ]
            );
        }

        $this->info('Temu sheet data synced successfully!');
    }

    private function toDecimalOrNull($value)
    {
        return is_numeric($value) ? round((float)$value, 2) : null;
    }

    private function toIntOrNull($value)
    {
        if ($value === null || $value === '') return null;
        $value = str_replace(',', '', $value);
        return is_numeric($value) ? (int)$value : null;
    }
}
