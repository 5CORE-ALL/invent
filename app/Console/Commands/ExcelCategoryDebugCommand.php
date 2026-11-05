<?php

namespace App\Console\Commands;

use App\Models\ExcelWorksheet;
use App\Services\ExcelCategoryLookupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ExcelCategoryDebugCommand extends Command
{
    protected $signature = 'excel:category-debug';

    protected $description = 'Show which /excel worksheet feeds the eBay Category column';

    public function handle(ExcelCategoryLookupService $service): int
    {
        $sheets = ExcelWorksheet::query()->orderByDesc('id')->limit(10)->get();
        foreach ($sheets as $w) {
            [$sku, $cat] = $service->detectColumns((array) $w->headers);
            $this->line("#{$w->id} {$w->worksheet_name} rows={$w->total_rows} sku=".($sku ?? '-').' category='.($cat ?? '-'));
            $this->line('   headers: '.json_encode($w->headers));
        }

        Cache::forget(ExcelCategoryLookupService::CACHE_KEY.':'.(int) ExcelWorksheet::max('id'));
        $map = $service->map();
        $this->info('Mapped SKUs: '.count($map));
        $this->line('Sample: '.json_encode(array_slice($map, 0, 5)));

        return self::SUCCESS;
    }
}
