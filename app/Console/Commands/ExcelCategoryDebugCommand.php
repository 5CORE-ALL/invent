<?php

namespace App\Console\Commands;

use App\Models\ExcelWorksheet;
use App\Services\ExcelCategoryLookupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ExcelCategoryDebugCommand extends Command
{
    protected $signature = 'excel:category-debug';

    protected $description = 'Show which /excel worksheets feed the eBay Category column (category = worksheet name, matched by Item ID)';

    public function handle(ExcelCategoryLookupService $service): int
    {
        $importId = ExcelWorksheet::query()->orderByDesc('id')->value('excel_import_id');
        $sheets = ExcelWorksheet::query()->where('excel_import_id', $importId)->orderBy('worksheet_index')->get();
        foreach ($sheets as $w) {
            $used = $service->isCategorySheet($w->worksheet_name) ? ($service->detectItemIdHeader((array) $w->headers) ?? 'NO ITEM ID COLUMN') : 'ignored';
            $this->line("#{$w->id} {$w->worksheet_name} rows={$w->total_rows} item_id_col=".$used);
        }

        Cache::forget(ExcelCategoryLookupService::CACHE_KEY.':'.(int) ExcelWorksheet::max('id'));
        $map = $service->build(function ($category, $header, $rows, $added, $keys) {
            $this->line("  [{$category}] header='{$header}' rows_read={$rows} ids_added={$added} data_keys=".json_encode($keys));
        });
        $this->info('Mapped item ids: '.count($map));
        $this->line('Sample: '.json_encode(array_slice($map, 0, 5)));

        return self::SUCCESS;
    }
}
