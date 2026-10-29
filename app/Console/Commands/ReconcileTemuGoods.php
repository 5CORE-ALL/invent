<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\Temu2LinkMapSyncService;
use App\Services\MarketplaceManager\TemuLinkMapSyncService;
use Illuminate\Console\Command;

/**
 * Temu 1 / Temu 2: make Missing L follow the marketplace. SKUs whose goods_id Temu no longer lists
 * (deleted / removed goods) lose that link (and the saved buyer / seller links), so they show as
 * Missing L again and are published as brand-new listings. Temu 3 is not touched.
 */
class ReconcileTemuGoods extends Command
{
    protected $signature = 'temu:reconcile-goods
                            {--shop=all : 1, 2 or all}
                            {--dry-run : Only show what would be removed}
                            {--force : Also run when the live list disagrees with most of the stored goods}';

    protected $description = 'Remove Temu 1 / Temu 2 goods_id links that Temu no longer lists, so Missing L matches the marketplace';

    public function handle(TemuLinkMapSyncService $temu1, Temu2LinkMapSyncService $temu2): int
    {
        $shop = strtolower((string) $this->option('shop'));
        $targets = [];
        if (in_array($shop, ['all', '1', 'temu', 'temu1'], true)) {
            $targets['Temu 1'] = $temu1;
        }
        if (in_array($shop, ['all', '2', 'temu2'], true)) {
            $targets['Temu 2'] = $temu2;
        }
        if ($targets === []) {
            $this->error('Use --shop=1, --shop=2 or --shop=all');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $exit = self::SUCCESS;
        foreach ($targets as $label => $service) {
            $this->newLine();
            $this->info("== {$label} ==");
            $r = $service->reconcileStaleGoodsIds($dry, (bool) $this->option('force'));

            $this->line('Goods Temu lists now      : '.$r['live']);
            $this->line('SKU rows pointing nowhere : '.$r['stale_skus'].' (in '.$r['stale_goods'].' deleted goods)');
            foreach ($r['sample'] as $row) {
                $this->line(sprintf('   %-28s goods_id %s', $row['sku'], $row['goods_id']));
            }
            if ($r['stale_skus'] > count($r['sample'])) {
                $this->line('   ... and '.($r['stale_skus'] - count($r['sample'])).' more');
            }
            if ($r['note'] !== '') {
                $this->warn($r['note']);
            }
            $this->line('Saved links '.($dry ? 'that would be removed' : 'removed').': '.$r['links_cleared']);
            $this->info($dry ? 'Dry run: nothing changed.' : 'goods_id removed: '.$r['cleared']);
            if (! $r['ok']) {
                $exit = self::FAILURE;
            }
        }

        return $exit;
    }
}
