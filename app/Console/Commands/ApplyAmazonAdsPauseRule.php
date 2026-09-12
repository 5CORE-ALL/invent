<?php

namespace App\Console\Commands;

use App\Services\AmazonAdsPauseRuleApplicator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ApplyAmazonAdsPauseRule extends Command
{
    protected $signature = 'amazon:ads-pause-rule
                            {--dry-run : Evaluate pause/enable without calling Amazon}
                            {--enable-name=* : Campaign name to turn back on if still paused}';

    protected $description = 'Pause matching Dil%/Price campaigns; re-enable only Pause Rule pauses from the last 31 days; pause low-review product ads';

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

        $enableNames = array_values(array_filter(array_map('strval', (array) $this->option('enable-name'))));
        if ($enableNames !== []) {
            $this->info('Turning named campaigns back on…');
            $named = $applicator->enableCampaignsByNames($enableNames, $dryRun);
            Log::info('amazon:ads-pause-rule named enable', $named + ['dry_run' => $dryRun]);
            $this->info(sprintf(
                'Named enable: Enabled %d. Unchanged %d. Skipped %d. Failed %d.',
                $named['enabled'],
                $named['unchanged'],
                $named['skipped'],
                $named['failed']
            ));
            foreach (array_slice($named['errors'], 0, 20) as $err) {
                $this->warn('  '.$err);
            }
            $stats['failed'] += (int) ($named['failed'] ?? 0);
            $stats['enabled'] += (int) ($named['enabled'] ?? 0);
        }

        return $stats['failed'] > 0 && $stats['paused'] === 0 && $stats['enabled'] === 0 ? 1 : 0;
    }
}
