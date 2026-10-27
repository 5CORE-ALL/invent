<?php

namespace App\Console\Commands;

use App\Services\TemuSoldRoasRuleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoRunTemuAds extends Command
{
    protected $signature = 'temu:auto-run-ads
                            {--dry-run : List the changes without calling Temu}
                            {--force : Run even if Auto run is OFF}';

    protected $description = 'Temu 1 Ads Rules Auto run: per parent L7 sold -> slab Target ROAS -> run/pause ad via Temu API';

    public function handle(TemuSoldRoasRuleService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->option('force') && ! $service->autoRunEnabled()) {
            $this->warn('Auto run is OFF. Turn it ON in /temu/ads > Ads Rules, or use --force.');
            Log::info('temu:auto-run-ads skipped — Auto run OFF');

            return 0;
        }

        $this->info(($dryRun ? 'Dry-run: ' : '').'Checking L7 sold per parent and applying slab Target ROAS...');
        $bar = null;
        $onEach = $dryRun ? null : function (int $done, int $total) use (&$bar) {
            if ($bar === null && $total > 0) {
                $bar = $this->output->createProgressBar($total);
                $bar->start();
            }
            if ($bar) {
                $bar->setProgress($done);
            }
        };

        $stats = $service->run($dryRun, $onEach);
        if ($bar) {
            $bar->finish();
            $this->newLine();
        }

        Log::info('temu:auto-run-ads finished', collect($stats)->except(['actions', 'errors'])->all() + ['dry_run' => $dryRun]);
        $this->info("Parents {$stats['parents']}. Matched {$stats['matched']}. Resumed {$stats['resumed']}. Paused {$stats['paused']}. ROAS set {$stats['roas_set']}. Unchanged {$stats['unchanged']}. Skipped {$stats['skipped']}. Failed {$stats['failed']}.");
        if ($dryRun) {
            foreach (array_slice($stats['actions'], 0, 100) as $a) {
                $this->line("  {$a['goods_id']}  sold {$a['sold_l7']}  slab {$a['slab']}  -> {$a['action']}");
            }
        }
        foreach (array_slice($stats['errors'], 0, 20) as $e) {
            $this->error($e);
        }

        return $stats['failed'] > 0 ? 1 : 0;
    }
}
