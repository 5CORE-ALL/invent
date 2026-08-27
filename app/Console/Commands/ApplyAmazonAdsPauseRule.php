<?php

namespace App\Console\Commands;

use App\Services\AmazonAdsPauseRuleApplicator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ApplyAmazonAdsPauseRule extends Command
{
    protected $signature = 'amazon:ads-pause-rule
                            {--dry-run : Evaluate pause/enable without calling Amazon}';

    protected $description = 'Pause or enable Amazon SP/SB campaigns from /amazon-ads/all Pause Rule and PR Dil% threshold';

    public function handle(AmazonAdsPauseRuleApplicator $applicator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? 'Dry-run: ' : '').'Applying Amazon Ads pause rule…');

        $stats = $applicator->applyAll($dryRun);

        Log::info('amazon:ads-pause-rule finished', $stats + ['dry_run' => $dryRun]);

        if (($stats['errors'][0] ?? '') !== '' && $stats['paused'] === 0 && $stats['enabled'] === 0 && str_contains((string) $stats['errors'][0], 'No pause')) {
            $this->warn($stats['errors'][0]);
        }

        $this->info(sprintf(
            'Paused %d. Enabled %d. Unchanged %d. Skipped %d. Failed %d.',
            $stats['paused'],
            $stats['enabled'],
            $stats['unchanged'],
            $stats['skipped'],
            $stats['failed']
        ));
        foreach (array_slice($stats['errors'], 0, 20) as $err) {
            $this->warn('  '.$err);
        }

        return $stats['failed'] > 0 && $stats['paused'] === 0 && $stats['enabled'] === 0 ? 1 : 0;
    }
}
