<?php

namespace App\Console\Commands;

use App\Models\WayfairPriceUpload;
use App\Services\Wayfair\WayfairPriceUploadOrchestrator;
use Illuminate\Console\Command;

class WayfairDailyPriceUpload extends Command
{
    protected $signature = 'wayfair:daily-price-upload
        {--dry-run : Plan the daily file without writing or queueing}
        {--force : Run even when automatic upload is off, and pass the review guard}
        {--date= : Label only. Prices always come from the current Wayfair pricing page}';

    protected $description = 'Generate today\'s Wayfair price file and queue the upload. Does not upload inside the scheduler';

    public function handle(WayfairPriceUploadOrchestrator $orchestrator): int
    {
        if (! $orchestrator->settings()->enabled() && ! $this->option('force')) {
            $this->info('Wayfair automatic upload is off.');

            return self::SUCCESS;
        }

        $lock = $orchestrator->acquireDailyLock();
        if (! $lock) {
            $this->warn('Daily Wayfair price upload is already running. Duplicate run skipped.');

            return self::SUCCESS;
        }

        try {
            if ($this->option('dry-run')) {
                $preview = $orchestrator->preview(null, (bool) $this->option('force'));
                $this->info('Dry run. Nothing was queued.');
                $this->line($preview['action'].' changed='.$preview['changed'].' file_rows='.$preview['file_rows']);

                return self::SUCCESS;
            }

            $record = $orchestrator->generate(true, (bool) $this->option('force'), null, 'scheduler');
            $this->info($record->status.' #'.$record->id.' '.(string) $record->filename);
            if ($record->status === WayfairPriceUpload::QUEUED) {
                $this->line('Upload queued. The Wayfair upload is running in the background.');
            }
            if ($record->error_message) {
                $this->warn($record->error_message);
            }

            return $record->status === WayfairPriceUpload::FAILED ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
