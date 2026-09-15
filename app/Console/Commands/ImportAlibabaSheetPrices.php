<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\AlibabaAnalyticsController;
use Illuminate\Console\Command;

class ImportAlibabaSheetPrices extends Command
{
    protected $signature = 'alibaba:import-sheet-prices
                            {file? : Path to the Alibaba price sheet (xlsx, csv, or tsv)}';

    protected $description = 'Import Alibaba sheet prices (Product Id, SKU, Status, SKU Price.1, SOH, Inv Update).';

    public function handle(AlibabaAnalyticsController $controller): int
    {
        $path = $this->argument('file') ?: base_path('alibaba');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $parsed = $controller->parseSheetFile($path, basename($path));
        if (! empty($parsed['error'])) {
            $this->error($parsed['error']);

            return self::FAILURE;
        }

        $saved = $controller->upsertSheetRows($parsed['rows']);
        $this->info("Imported {$saved} row(s). Skipped {$parsed['skipped']} empty row(s). Product Id and SKU kept as in the sheet.");

        return self::SUCCESS;
    }
}
