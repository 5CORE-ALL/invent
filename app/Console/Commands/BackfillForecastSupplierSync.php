<?php

namespace App\Console\Commands;

use App\Services\ToOrderSupplierSync;
use Illuminate\Console\Command;

class BackfillForecastSupplierSync extends Command
{
    protected $signature = 'forecast:backfill-supplier-sync
                            {--dry-run : List mismatches without writing}
                            {--sku= : Only process this SKU}
                            {--force : Backfill even when To Order was updated more recently than MIP}';

    protected $description = 'Backfill: propagate supplier from to_order/mfrg mismatches to all pipeline tables (to_order, MIP, R2S, transit).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $skuFilter = $this->option('sku');
        $skuFilter = is_string($skuFilter) ? trim($skuFilter) : nusll;
        if ($skuFilter === '') {
            $skuFilter = null;
        }

        $rows = ToOrderSupplierSync::findOutOfSyncSkus($skuFilter, $force);

        if ($rows === []) {
            $this->info('No out-of-sync supplier rows found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Found %d SKU(s) where mfrg_progress.supplier != latest to_order_analysis.supplier_name.',
            count($rows)
        ));

        if ($dryRun) {
            $this->table(
                ['SKU', 'MIP supplier (will apply)', 'To Order supplier (current)', 'MIP updated', 'To Order updated'],
                array_map(function (array $row) {
                    return [
                        $row['sku'],
                        $row['mfrg_supplier'],
                        $row['to_order_supplier'] ?? '(none)',
                        $row['mfrg_updated_at'] ?? '',
                        $row['to_order_updated_at'] ?? '(none)',
                    ];
                }, $rows)
            );
            $this->comment('Dry run — no changes written. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $synced = 0;
        foreach ($rows as $row) {
            ToOrderSupplierSync::syncFromMfrg($row['sku'], $row['mfrg_supplier']);
            $synced++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Synced supplier_name for {$synced} SKU(s). Refresh /forecast.analysis to verify.");

        return self::SUCCESS;
    }
}
