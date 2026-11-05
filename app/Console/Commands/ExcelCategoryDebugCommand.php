<?php

namespace App\Console\Commands;

use App\Models\ExcelWorksheet;
use App\Services\ExcelCategoryLookupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ExcelCategoryDebugCommand extends Command
{
    protected $signature = 'excel:category-debug';

    protected $description = 'Show which /excel worksheets feed the eBay Category column (category = worksheet name, matched by MPN)';

    public function handle(ExcelCategoryLookupService $service): int
    {
        $importId = ExcelWorksheet::query()->orderByDesc('id')->value('excel_import_id');
        $sheets = ExcelWorksheet::query()->where('excel_import_id', $importId)->orderBy('worksheet_index')->get();
        foreach ($sheets as $w) {
            $used = $service->isCategorySheet($w->worksheet_name) ? ($service->detectMpnHeader((array) $w->headers) ?? 'NO MPN COLUMN') : 'ignored';
            $this->line("#{$w->id} {$w->worksheet_name} rows={$w->total_rows} mpn=".$used);
        }

        Cache::forget(ExcelCategoryLookupService::CACHE_KEY.':'.(int) ExcelWorksheet::max('id'));
        $map = $service->map();
        $this->info('Mapped MPNs: '.count($map));
        $this->line('Sample: '.json_encode(array_slice($map, 0, 5)));

        return self::SUCCESS;
    }
}
