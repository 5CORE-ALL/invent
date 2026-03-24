<?php

namespace App\Console\Commands;

use App\Http\Controllers\InventoryManagement\SparePartController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Debug spare-parts SKU search against the real product_master table (same logic as the UI API).
 *
 * Example:
 *   php artisan spare-parts:debug-search "sp 121"
 */
class SparePartsDebugProductMasterSearchCommand extends Command
{
    protected $signature = 'spare-parts:debug-search
                            {q : Search text (same as the Spare Parts SKU field)}
                            {--limit=15 : Max rows}';

    protected $description = 'Run product_master SKU search (Spare Parts) and print JSON for troubleshooting';

    public function handle(SparePartController $controller): int
    {
        $q = (string) $this->argument('q');
        $limit = max(1, min(50, (int) $this->option('limit')));

        $this->line('Uses table `product_master`: sku + title60/80/100/150 (if columns exist).');
        $this->line('Optional: `product_categories` via category_id (table exists: '.(Schema::hasTable('product_categories') ? 'yes' : 'no').').');
        $this->line('There is no `category` text column on product_master.');
        $this->newLine();

        $request = Request::create('/inventory/spare-parts/api/search-parts', 'GET', [
            'q' => $q,
            'limit' => $limit,
        ]);

        try {
            $response = $controller->searchParts($request);
            $this->info($response->getContent());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
