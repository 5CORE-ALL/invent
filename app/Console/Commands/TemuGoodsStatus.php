<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TemuGoodsStatusSync;
use Illuminate\Console\Command;

/**
 * Temu 1 only: read the status Seller Center shows for every goods (Active / Inactive / Incomplete / Draft /
 * Deleted) and store it for the Temu Status column on /listing-temu. Read-only against Temu.
 */
class TemuGoodsStatus extends Command
{
    protected $signature = 'temu:goods-status {--goods= : Show the stored record of one goods_id}';

    protected $description = 'Temu 1: refresh the Seller Center status (Active/Inactive/Incomplete/Draft/Deleted) of every goods';

    public function handle(TemuGoodsStatusSync $sync): int
    {
        if ($gid = trim((string) $this->option('goods'))) {
            $data = TemuGoodsStatusSync::read();
            $this->line(json_encode($data['goods'][$gid] ?? 'not in the stored status list', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $r = $sync->refresh();
        foreach ($r['tabs'] as $tab => $count) {
            $this->line(sprintf('Tab %-11s : %s', $tab, $count));
        }
        if (! $r['ok']) {
            $this->error($r['note']);

            return self::FAILURE;
        }
        $this->info("Goods: {$r['goods']} | SKUs: {$r['skus']}");
        foreach ($r['counts'] as $label => $n) {
            $this->line(sprintf('   %-14s %d', $label, $n));
        }

        return self::SUCCESS;
    }
}
