<?php

namespace App\Console\Commands;

use App\Models\ProductMaster;
use Illuminate\Console\Command;

/**
 * Backfill LP / CBM / FRGHT in product_master.Values for every existing product.
 *
 * The actual recalculation lives in the ProductMaster `saving` model event
 * (see App\Models\ProductMaster::booted). This command simply re-saves each
 * product so the event fires and persists the formula-based values.
 *
 * Usage:
 *   php artisan products:recalc-lp            # apply changes
 *   php artisan products:recalc-lp --dry-run  # show what would change, no DB writes
 *   php artisan products:recalc-lp --sku=ABC  # limit to a single SKU
 */
class RecalcProductLp extends Command
{
    protected $signature = 'products:recalc-lp
        {--dry-run : Show what would change without saving}
        {--sku= : Limit to a specific SKU}
        {--chunk=500 : Number of products processed per chunk}';

    protected $description = 'Recalculate LP, CBM and FRGHT for products in product_master using the canonical formula';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlySku = $this->option('sku');
        $chunkSize = (int) ($this->option('chunk') ?: 500);
        if ($chunkSize < 1) {
            $chunkSize = 500;
        }

        $query = ProductMaster::query()->whereNotNull('Values');
        if ($onlySku) {
            $query->where('sku', $onlySku);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('No products matched the filter.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s LP for %d product(s)%s',
            $dryRun ? 'Previewing' : 'Recalculating',
            $total,
            $onlySku ? " (sku={$onlySku})" : ''
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $unchanged = 0;
        $skippedParents = 0;
        $changes = [];

        $query->orderBy('id')->chunkById($chunkSize, function ($products) use (
            $dryRun,
            $bar,
            &$updated,
            &$unchanged,
            &$skippedParents,
            &$changes,
        ) {
            foreach ($products as $product) {
                $sku = (string) ($product->sku ?? '');

                if ($sku !== '' && stripos($sku, 'PARENT') !== false) {
                    $skippedParents++;
                    $bar->advance();

                    continue;
                }

                $original = is_array($product->Values)
                    ? $product->Values
                    : (json_decode($product->Values ?? 'null', true) ?: []);

                $oldLp = $original['lp'] ?? null;
                $newValues = ProductMaster::recalcDerivedValues($original, $sku);
                $newLp = $newValues['lp'] ?? null;

                $isChanged = (string) $newLp !== (string) $oldLp;

                if ($isChanged) {
                    $updated++;
                    if (count($changes) < 50) {
                        $changes[] = [$sku, $oldLp ?? '-', $newLp ?? '-'];
                    }
                } else {
                    $unchanged++;
                }

                if (! $dryRun && $isChanged) {
                    $product->Values = $newValues;
                    $product->save();
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Done. %s LP: %d   Unchanged: %d   Skipped parents: %d   Total: %d',
            $dryRun ? 'Would update' : 'Updated',
            $updated,
            $unchanged,
            $skippedParents,
            $total
        ));

        if (! empty($changes)) {
            $this->newLine();
            $this->info('Sample changes (max 50):');
            $this->table(['SKU', 'Old LP', 'New LP'], $changes);
        }

        return self::SUCCESS;
    }
}
