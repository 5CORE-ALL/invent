<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\NeweggPricingController;
use Illuminate\Console\Command;

class ImportNeweggViews extends Command
{
    protected $signature = 'newegg:import-views {path? : Path to the Item Performance TSV/CSV/XLSX}';

    protected $description = 'Import Newegg Seller Portal Item Performance sheet (truncates newegg_listing_views)';

    public function handle(NeweggPricingController $controller): int
    {
        $path = $this->argument('path') ?: base_path('newegg.txt');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $this->info('Importing '.$path);
        $result = $controller->importViewsFromPath($path);
        if (! empty($result['error'])) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info($result['success'] ?? json_encode($result));

        return self::SUCCESS;
    }
}
