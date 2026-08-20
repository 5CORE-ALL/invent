<?php

namespace App\Console\Commands;

use App\Services\Temu2AdsAutoPauseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoPauseTemu2Ads extends Command
{
    protected $signature = 'temu2:auto-pause-ads
                            {--dry-run : List matching ads without calling Temu}
                            {--force : Run even if the daily cron toggle is paused}
                            {--goods-id= : Comma-separated goods IDs to retry}';

    protected $description = 'Pause Active Temu 2 ads that match L7 clicks < threshold and ROAS < Stop ROAS';

    public function handle(Temu2AdsAutoPauseService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->option('force') && ! $service->cronEnabled()) {
            $this->warn('Daily auto-pause cron is paused. Use --force to run anyway.');
            Log::info('temu2:auto-pause-ads skipped — cron paused');

            return 0;
        }

        $below = $service->l7ClicksRedBelow();
        $stopRoas = $service->targetRoasBidding();

        $this->info(($dryRun ? 'Dry-run: ' : '') . "Pausing ads with L7 clicks < {$below} and ROAS < {$stopRoas}...");

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
        Log::info('temu2:auto-pause-ads finished', [
            'matched' => $stats['matched'],
            'paused' => $stats['paused'],
            'failed' => $stats['failed'],
            'dry_run' => $dryRun,
        ]);

        $this->info("Matched {$stats['matched']}. Paused {$stats['paused']}. Failed {$stats['failed']}.");
        if ($stats['matched'] === 0) {
            $this->warn('No rows have L7 clicks < '.$below.' and ROAS < '.$stopRoas.' with spend or clicks. Fetch L7 first if the table looks empty.');
        }
        if ($dryRun && $stats['paused_goods'] !== []) {
            foreach ($stats['paused_goods'] as $row) {
                $this->line(sprintf(
                    '  %s  L7 clicks %d  ROAS %s  spend $%s  %s',
                    $row['goods_id'],
                    $row['clicks_l7'],
                    $row['roas'],
                    number_format((float) $row['ad_spend'], 2),
                    $row['status'] ?? ''
                ));
            }
        }
        if ($stats['failed_goods'] !== []) {
            foreach ($stats['failed_goods'] as $row) {
                $this->warn("  {$row['goods_id']}: " . ($row['error'] ?? 'failed'));
            }
        }

        return $stats['failed'] > 0 && $stats['paused'] === 0 ? 1 : 0;
    }
}
