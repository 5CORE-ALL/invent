<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TemuLinkMapSyncService;
use Illuminate\Console\Command;

/**
 * Temu 1: make Missing L follow the marketplace. SKUs whose goods_id Temu no longer lists
 * (deleted / removed goods) lose that link, so they show as Missing L again.
 */
class ReconcileTemuGoods extends Command
{
    protected $signature = 'temu:reconcile-goods
                            {--dry-run : Only show what would be removed}
                            {--force : Also run when the live list disagrees with more than half of the stored goods}';

    protected $description = 'Remove Temu 1 goods_id links that Temu no longer lists, so Missing L matches the marketplace';

    public function handle(TemuLinkMapSyncService $sync): int
    {
        $r = $sync->reconcileStaleGoodsIds((bool) $this->option('dry-run'), (bool) $this->option('force'));

        $this->line('Goods Temu lists now      : '.$r['live']);
        $this->line('SKU rows pointing nowhere : '.$r['stale_skus']);
        foreach ($r['sample'] as $row) {
            $this->line(sprintf('   %-28s goods_id %s', $row['sku'], $row['goods_id']));
        }
        if ($r['stale_skus'] > count($r['sample'])) {
            $this->line('   ... and '.($r['stale_skus'] - count($r['sample'])).' more');
        }
        if ($r['note'] !== '') {
            $this->warn($r['note']);
        }
        $this->info($this->option('dry-run') ? 'Dry run: nothing changed.' : 'Links removed: '.$r['cleared']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
