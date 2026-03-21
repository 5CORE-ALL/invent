<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class WmsInstallCommand extends Command
{
    protected $signature = 'wms:install {--seed : Run WmsDemoSeeder after migrations}';

    protected $description = 'Run Warehouse (WMS) migrations only, then optionally seed demo location + sample bin stock';

    /**
     * @var list<string>
     */
    private array $wmsMigrations = [
        '2026_03_21_120001_add_code_to_warehouses_table.php',
        '2026_03_21_120002_create_zones_table.php',
        '2026_03_21_120003_create_racks_table.php',
        '2026_03_21_120004_create_shelves_table.php',
        '2026_03_21_120005_create_bins_table.php',
        '2026_03_21_120006_add_bin_tracking_to_inventories_table.php',
        '2026_03_21_120007_add_barcode_to_product_master_table.php',
        '2026_03_21_120008_create_stock_movements_table.php',
        '2026_03_21_120009_create_wms_audit_logs_table.php',
        '2026_03_21_120010_create_wms_api_request_logs_table.php',
    ];

    public function handle(): int
    {
        foreach ($this->wmsMigrations as $file) {
            $path = 'database/migrations/'.$file;
            $this->line("Migrating <comment>{$file}</comment>…");
            $code = Artisan::call('migrate', [
                '--path' => $path,
                '--force' => true,
                '--no-interaction' => true,
            ]);
            if ($code !== 0) {
                $this->error(Artisan::output());

                return self::FAILURE;
            }
            $this->output->write(Artisan::output());
        }

        $this->info('WMS migrations finished.');

        if ($this->option('seed')) {
            $this->line('Seeding <comment>WmsDemoSeeder</comment>…');
            $code = Artisan::call('db:seed', [
                '--class' => \Database\Seeders\WmsDemoSeeder::class,
                '--force' => true,
                '--no-interaction' => true,
            ]);
            $this->output->write(Artisan::output());
            if ($code !== 0) {
                return self::FAILURE;
            }
            $this->info('Demo data seeded.');
        }

        return self::SUCCESS;
    }
}
