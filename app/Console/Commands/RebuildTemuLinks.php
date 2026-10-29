<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TemuGoodsStatusSync;
use Illuminate\Console\Command;

/**
 * Temu 1 only: delete every stored goods_id / sku_id and import what Temu holds now.
 * Same as the "Refresh listings" button on /listing-temu. Temu 2 and Temu 3 are not touched.
 */
class RebuildTemuLinks extends Command
{
    protected $signature = 'temu:rebuild-links
                            {--dry-run : Only show what would change}
                            {--force : Also run when most stored goods IDs would be removed}
                            {--no-refresh : Reuse the status data of the last temu:goods-status run}';

    protected $description = 'Temu 1: replace all stored goods_id / sku_id with what Temu holds now (old goods IDs are removed)';

    public function handle(TemuGoodsStatusSync $sync): int
    {
        if (! $this->option('no-refresh')) {
            $this->info('Reading Temu 1 goods ...');
            $status = $sync->refresh();
            foreach ($status['tabs'] as $tab => $count) {
                $this->line(sprintf('   tab %-11s %s', $tab, $count));
            }
            if (! $status['ok']) {
                $this->error($status['note']);

                return self::FAILURE;
            }
            $this->line("   goods {$status['goods']} | SKUs {$status['skus']} | ".json_encode($status['counts']));
        }

        $dry = (bool) $this->option('dry-run');
        $r = $sync->rebuildLinks($dry, (bool) $this->option('force'));

        $this->newLine();
        $this->line('Live goods on Temu 1        : '.$r['live_goods']);
        $this->line('SKUs moved to another goods : '.$r['relinked']);
        $this->line('SKUs newly linked           : '.$r['added']);
        $this->line('SKUs whose goods_id removed : '.$r['cleared']);
        $this->line('Saved links removed         : '.$r['links_cleared']);
        foreach ($r['sample'] as $s) {
            $this->line(sprintf('   %-28s %s -> %s', $s['sku'], $s['old'] !== '' ? $s['old'] : '-', $s['new'] !== '' ? $s['new'] : '(none)'));
        }
        if ($r['note'] !== '') {
            $this->warn($r['note']);
        }
        $this->info($dry ? 'Dry run: nothing changed.' : ($r['ok'] ? 'Done.' : 'Nothing changed.'));

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
