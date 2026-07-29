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
        $this->info('Temu sheet data sync has been deprecated (temu_product_sheets dropped). Use temu_metrics instead.');
        return 0;
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
