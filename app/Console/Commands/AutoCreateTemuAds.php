<?php

namespace App\Console\Commands;

use App\Services\TemuSoldRoasRuleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutoCreateTemuAds extends Command
{
    protected $signature = 'temu:auto-create-ads
                            {--dry-run : List what would be created / pushed without calling Temu}
                            {--force : Run even if Auto run is OFF}
                            {--budget=10 : Daily budget (USD) for each new ad}
                            {--max-budget=10 : Highest daily budget allowed when Temu demands a higher minimum (same as --budget = never raise)}
                            {--limit=50 : Max ads created per run}
                            {--retry-days=7 : Days to wait before retrying a goods Temu rejected}
                            {--skip-roas : Only create ads, do not run the ROAS push afterwards}';

    protected $description = 'Temu 1 Ads Rules: create the missing ad for every Parent whose slab has Target ROAS > 0 (created at that ROAS), then push ROAS / run / pause for all Parents per rule';

    public function handle(TemuSoldRoasRuleService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->option('force') && ! $service->autoRunEnabled()) {
            $this->warn('Auto run is OFF. Turn it ON in /temu/ads > Ads Rules, or use --force.');
            Log::info('temu:auto-create-ads skipped — Auto run OFF');

            return 0;
        }

        if (! $dryRun) {
            if (Cache::has(TemuSoldRoasRuleService::RUN_LOCK_KEY)) {
                $this->warn('Ads Rules run already in progress — skipping.');

                return 0;
            }
            Cache::put(TemuSoldRoasRuleService::RUN_LOCK_KEY, now()->toDateTimeString(), 3600);
        }

        $failed = 0;
        try {
            $this->info(($dryRun ? 'Dry-run: ' : '').'Creating missing ads per Ads Rules...');
            $c = $service->createMissingAds(
                (float) $this->option('budget'),
                (float) $this->option('max-budget'),
                (int) $this->option('limit'),
                (int) $this->option('retry-days'),
                $dryRun
            );
            Log::info('temu:auto-create-ads finished', collect($c)->except(['actions', 'errors'])->all() + ['dry_run' => $dryRun]);
            $this->info("Parents {$c['parents']}. Candidates {$c['candidates']}. Created {$c['created']}. Failed {$c['failed']}. Skipped: rejected {$c['skipped_rejected']}, budget too high {$c['skipped_budget']}, already has ad {$c['already_has_ad']}, not verified {$c['unverified']}.");
            foreach (array_slice($c['actions'], 0, $dryRun ? 150 : 0) as $a) {
                $this->line("  {$a['goods_id']}  sold {$a['sold_l7']}  slab {$a['slab']}  -> {$a['action']}");
            }
            foreach (array_slice($c['errors'], 0, 20) as $e) {
                $this->error($e);
            }
            $failed += $c['failed'];

            if (! $this->option('skip-roas')) {
                $this->info('Pushing ROAS / run / pause per rule...');
                $r = $service->run($dryRun);
                Log::info('temu:auto-create-ads roas push finished', collect($r)->except(['actions', 'errors'])->all() + ['dry_run' => $dryRun]);
                $this->info("Matched {$r['matched']}. Resumed {$r['resumed']}. Paused {$r['paused']}. ROAS set {$r['roas_set']}. Unchanged {$r['unchanged']}. Skipped {$r['skipped']}. Failed {$r['failed']}.");
                foreach (array_slice($r['errors'], 0, 20) as $e) {
                    $this->error($e);
                }
                $failed += $r['failed'];
            }
        } finally {
            if (! $dryRun) {
                Cache::forget(TemuSoldRoasRuleService::RUN_LOCK_KEY);
            }
        }

        return $failed > 0 ? 1 : 0;
    }
}
