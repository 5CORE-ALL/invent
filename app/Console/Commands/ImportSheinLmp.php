<?php

namespace App\Console\Commands;

use App\Models\SheinLmp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportSheinLmp extends Command
{
    protected $signature = 'shein:import-lmp {file=sheinlmp.txt : Path (relative to base path or absolute) to the tab-separated LMP file}';

    protected $description = 'Import Shein LMP (lowest market price) data from the tab-separated sheinlmp.txt into the shein_lmp table';

    public function handle(): int
    {
        if (! Schema::hasTable('shein_lmp')) {
            $this->error('Table shein_lmp does not exist. Run: php artisan migrate');

            return self::FAILURE;
        }

        $fileArg = $this->argument('file');
        $path = str_starts_with($fileArg, '/') ? $fileArg : base_path($fileArg);

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Unable to open file: {$path}");

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $lineNo = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            $line = rtrim($line, "\r\n");

            if (trim($line) === '') {
                continue;
            }

            $cols = explode("\t", $line);
            $sku = trim($cols[0] ?? '');

            // Skip empty SKUs and the header row.
            if ($sku === '' || strtolower($sku) === 'sku') {
                $skipped++;
                continue;
            }

            // Prices/URLs occupy fixed column positions: (price, url) pairs
            // starting at column index 1. Preserve their positions.
            $data = ['sku' => $sku];
            $hasPrice = false;

            for ($i = 0; $i < 4; $i++) {
                $priceRaw = trim($cols[1 + $i * 2] ?? '');
                $urlRaw = trim($cols[2 + $i * 2] ?? '');

                $price = is_numeric($priceRaw) ? (float) $priceRaw : null;
                $url = str_starts_with(strtolower($urlRaw), 'http') ? $urlRaw : null;

                if ($price !== null) {
                    $hasPrice = true;
                }

                $data['price_'.($i + 1)] = $price;
                $data['url_'.($i + 1)] = $url;
            }

            $data['is_not_found'] = ! $hasPrice;

            $model = SheinLmp::updateOrCreate(['sku' => $sku], $data);

            if ($model->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        $this->info("Shein LMP import complete.");
        $this->line("Created: {$created}");
        $this->line("Updated: {$updated}");
        $this->line("Skipped (header/blank): {$skipped}");
        $this->line('Total rows in table: '.SheinLmp::count());

        return self::SUCCESS;
    }
}
