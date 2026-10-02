<?php

namespace App\Console\Commands;

use App\Services\TemuAdsAutoPauseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PushTemuTargetRoas extends Command
{
    protected $signature = 'temu:push-target-roas
                            {--dry-run : List ads whose Temu target differs from the click slab}
                            {--goods-id= : Comma-separated goods IDs}';

    protected $description = 'Push each Temu ad Target ROAS from its clicks slab. Target 0 pauses the ad.';

    public function handle(TemuAdsAutoPauseService $service): int
    {
        @set_time_limit(0);
        $dryRun = (bool) $this->option('dry-run');
        $onlyGoodsIds = null;
        $goodsOpt = trim((string) $this->option('goods-id'));
        if ($goodsOpt !== '') {
            $onlyGoodsIds = array_values(array_filter(array_map('trim', explode(',', $goodsOpt))));
            $this->info('Goods: '.implode(', ', $onlyGoodsIds));
        }

        $this->info(($dryRun ? 'Dry-run: ' : '').'Pushing Target ROAS for ads that do not match the clicks slab...');

        $bar = null;
        $onEach = function (int $done, int $total) use (&$bar) {
            if ($bar === null && $total > 0) {
                $bar = $this->output->createProgressBar($total);
                $bar->start();
            }
            if ($bar) {
                $bar->setProgress($done);
            }
        };

        $stats = $service->pushTargetRoas($dryRun, $onEach, $onlyGoodsIds);
        if ($bar) {
            $bar->finish();
            $this->newLine();
        }

        if ($stats['skipped_lock'] ?? false) {
            $this->warn('A Target ROAS push is already running.');

            return 0;
        }

        Log::info('temu:push-target-roas finished', [
            'checked' => $stats['checked'],
            'matched' => $stats['matched'],
            'pushed' => $stats['pushed'],
            'paused' => $stats['paused'],
            'already' => $stats['already'],
            'failed' => $stats['failed'],
            'dry_run' => $dryRun,
        ]);

        $this->info("Checked {$stats['checked']}. Already correct {$stats['already']}. Pushed {$stats['pushed']}. Paused {$stats['paused']}. Failed {$stats['failed']}.");

        if ($dryRun) {
            foreach (array_merge($stats['pushed_goods'] ?? [], $stats['paused_goods'] ?? []) as $row) {
                $this->line(sprintf(
                    '  %s  clicks %d  %s → %s',
                    $row['goods_id'],
                    $row['clicks'],
                    $row['stored_roas'] === null ? 'none' : $row['stored_roas'],
                    $row['action'] === 'pause' ? 'pause' : $row['target_roas']
                ));
            }
        }

        foreach ($stats['failed_goods'] as $row) {
            $this->warn("  {$row['goods_id']}: ".($row['error'] ?? 'failed'));
        }

        return $stats['failed'] > 0 && $stats['pushed'] === 0 && $stats['paused'] === 0 ? 1 : 0;
    }
}
