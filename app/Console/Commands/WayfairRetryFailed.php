<?php

namespace App\Console\Commands;

use App\Services\Wayfair\WayfairPriceUploadOrchestrator;
use Illuminate\Console\Command;

class WayfairRetryFailed extends Command
{
    protected $signature = 'wayfair:retry-failed
        {--upload-id= : Retry one upload}
        {--force : Retry even after max attempts, and approve a review block}
        {--dry-run : List what would be retried}
        {--date= : Accepted for consistency. Not used to filter}';

    protected $description = 'Queue another attempt for failed or stuck Wayfair price uploads';

    public function handle(WayfairPriceUploadOrchestrator $orchestrator): int
    {
        if ($this->option('dry-run')) {
            $this->info('Dry run. Nothing was queued. Use without --dry-run to retry.');

            return self::SUCCESS;
        }

        $id = $this->option('upload-id');
        $count = $orchestrator->retryFailed($id ? (int) $id : null, (bool) $this->option('force'));
        $this->info('Queued '.$count.' Wayfair upload retry(ies).');

        return self::SUCCESS;
    }
}
