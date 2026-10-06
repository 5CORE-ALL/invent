<?php

namespace App\Console\Commands;

use App\Services\Wayfair\WayfairPriceUploadOrchestrator;
use Illuminate\Console\Command;

class WayfairGeneratePriceFile extends Command
{
    protected $signature = 'wayfair:generate-price-file
        {--dry-run : Show what would be generated without writing a file}
        {--force : Continue past the no-change and abnormal-change guards}
        {--date= : Label only. Prices always come from the current Wayfair pricing page}';

    protected $description = 'Generate the Wayfair price CSV from the existing pricing page without uploading it';

    public function handle(WayfairPriceUploadOrchestrator $orchestrator): int
    {
        if ($this->option('dry-run')) {
            $preview = $orchestrator->preview(null, (bool) $this->option('force'));
            $this->info('Dry run. No file will be written.');
            $this->line($preview['action'].' catalog='.$preview['total'].' changed='.$preview['changed'].' file_rows='.$preview['file_rows']);
            if ($preview['message'] !== '') {
                $this->warn($preview['message']);
            }

            return self::SUCCESS;
        }

        $record = $orchestrator->generate(false, (bool) $this->option('force'), null, 'artisan');
        $this->line($record->status.' '.(string) $record->filename.' changed='.$record->changed_rows);
        if ($record->error_message) {
            $this->warn($record->error_message);
        }

        return in_array($record->status, ['FAILED'], true) ? self::FAILURE : self::SUCCESS;
    }
}
