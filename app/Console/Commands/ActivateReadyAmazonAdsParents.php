<?php

namespace App\Console\Commands;

use App\Services\AmazonAdsPauseRuleApplicator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActivateReadyAmazonAdsParents extends Command
{
    protected $signature = 'amazon:ads-activate-ready
                            {--dry-run : List what would be turned on without calling Amazon}';

    protected $description = 'Turn PARENT campaigns that are paused on Amazon back on when inventory is available (Inv > 0, Dil% rule clear) and budget is available (SBGT > 0, no red ACOS pause rule).';

    public function handle(AmazonAdsPauseRuleApplicator $applicator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $stats = $applicator->activateReadyParents($dryRun);

        Log::info('amazon:ads-activate-ready finished', [
            'dry_run' => $dryRun,
            'paused_parents' => $stats['paused_parents'],
            'enabled' => $stats['enabled'],
            'failed' => $stats['failed'],
            'blocked' => $stats['blocked'],
            'errors' => array_slice($stats['errors'], 0, 10),
        ]);

        $this->info(sprintf(
            '%d paused PARENT campaign(s). %s %d. Failed %d. Blocked: inventory %d, Dil%% %d, ACOS red %d, budget %d, FBA %d.',
            $stats['paused_parents'],
            $dryRun ? 'Ready' : 'Enabled',
            $dryRun ? count($stats['candidates']) : $stats['enabled'],
            $stats['failed'],
            $stats['blocked']['inventory'],
            $stats['blocked']['dil'],
            $stats['blocked']['acos_red'],
            $stats['blocked']['budget'],
            $stats['blocked']['fba']
        ));
        foreach (array_slice($stats['candidates'], 0, 50) as $c) {
            $this->line(sprintf('  %s %s (INV %s, SBGT $%s)', $dryRun ? 'READY  ' : 'ENABLED', $c['campaign_name'], $c['inv'], $c['sbgt']));
        }
        foreach (array_slice($stats['errors'], 0, 20) as $err) {
            $this->warn('  '.$err);
        }

        return $stats['failed'] > 0 && $stats['enabled'] === 0 ? 1 : 0;
    }
}
