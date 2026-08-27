<?php

namespace App\Console\Commands;

use App\Services\TemuAdsAutoPauseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoPauseTemuAds extends Command
{
    protected $signature = 'temu:auto-pause-ads
                            {--dry-run : List matching ads without calling Temu}
                            {--force : Run even if the daily cron toggle is paused}
                            {--goods-id= : Comma-separated goods IDs to retry}';

    protected $description = 'Push Temu ads whose Active/Pause status changes from the L7 click limit';

    public function handle(TemuAdsAutoPauseService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->option('force') && ! $service->cronEnabled()) {
            $this->warn('Daily auto-pause cron is paused. Use --force to run anyway.');
            Log::info('temu:auto-pause-ads skipped — cron paused');

            return 0;
        }

        $below = $service->l7ClicksRedBelow();

        $this->info(($dryRun ? 'Dry-run: ' : '') . "Pushing only ads whose Active/Pause status changes from L7 clicks vs {$below}...");

        $bar = null;
        $onEach = null;
        if (! $dryRun) {
            $onEach = function (int $done, int $total) use (&$bar) {
                if ($bar === null && $total > 0) {
                    $bar = $this->output->createProgressBar($total);
                    $bar->start();
                }
                if ($bar) {
                    $bar->setProgress($done);
                }
            };
        }

        $onlyGoodsIds = null;
        $goodsOpt = trim((string) $this->option('goods-id'));
        if ($goodsOpt !== '') {
            $onlyGoodsIds = array_values(array_filter(array_map('trim', explode(',', $goodsOpt))));
            $this->info('Retrying goods: '.implode(', ', $onlyGoodsIds));
        }

        $stats = $service->pauseMatching($dryRun, $onEach, $onlyGoodsIds);
        if ($bar) {
            $bar->finish();
            $this->newLine();
        }
        Log::info('temu:auto-pause-ads finished', [
            'matched' => $stats['matched'],
            'paused' => $stats['paused'],
            'failed' => $stats['failed'],
            'dry_run' => $dryRun,
        ]);

        $this->info("Matched {$stats['matched']}. Paused {$stats['paused']}. Resumed {$stats['resumed']}. Already correct {$stats['already']}. Failed {$stats['failed']}.");
        if ($stats['matched'] === 0) {
            $this->warn('No rows need an Active/Pause change from the L7 click limit ('.$below.'). Fetch L7 first if the table looks empty.');
        }
        if ($dryRun) {
            foreach (array_merge($stats['paused_goods'] ?? [], $stats['resumed_goods'] ?? []) as $row) {
                $this->line(sprintf(
                    '  %s  %s  L7 clicks %d  %s → %s',
                    $row['goods_id'],
                    $row['action'] ?? '',
                    $row['clicks_l7'],
                    $row['status'] ?? '',
                    ($row['action'] ?? '') === 'run' ? 'Active' : 'Inactive'
                ));
            }
        }
        if ($stats['failed_goods'] !== []) {
            foreach ($stats['failed_goods'] as $row) {
                $this->warn("  {$row['goods_id']}: " . ($row['error'] ?? 'failed'));
            }
        }

        return $stats['failed'] > 0 && $stats['paused'] === 0 && ($stats['resumed'] ?? 0) === 0 ? 1 : 0;
    }
}
