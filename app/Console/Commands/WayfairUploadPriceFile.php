<?php

namespace App\Console\Commands;

use App\Models\WayfairPriceUpload;
use App\Services\Wayfair\WayfairPriceUploadOrchestrator;
use Illuminate\Console\Command;

class WayfairUploadPriceFile extends Command
{
    protected $signature = 'wayfair:upload-price-file
        {--upload-id= : Queue an existing generated file}
        {--dry-run : Show the plan without queueing an upload}
        {--force : Queue even when the no-change or review guard would stop it}
        {--date= : Label only. Does not rebuild historical prices}';

    protected $description = 'Queue a Wayfair price-file upload. The browser upload runs on the queue worker';

    public function handle(WayfairPriceUploadOrchestrator $orchestrator): int
    {
        if ($this->option('dry-run')) {
            $preview = $orchestrator->preview(null, (bool) $this->option('force'));
            $this->info('Dry run. Nothing was queued.');
            $this->line($preview['action'].' file_rows='.$preview['file_rows'].' changed='.$preview['changed']);

            return self::SUCCESS;
        }

        $id = $this->option('upload-id');
        if ($id) {
            $upload = WayfairPriceUpload::query()->find($id);
            if (! $upload) {
                $this->error('Upload '.$id.' was not found.');

                return self::FAILURE;
            }
            $upload = $orchestrator->queueExisting($upload, (bool) $this->option('force'));
        } else {
            $upload = $orchestrator->generate(true, (bool) $this->option('force'), null, 'artisan');
        }

        $this->info($upload->status.' #'.$upload->id.' '.(string) $upload->filename);
        if ($upload->status === WayfairPriceUpload::QUEUED) {
            $this->line('Upload queued. The Wayfair upload is running in the background.');
        }
        if ($upload->error_message) {
            $this->warn($upload->error_message);
        }

        return $upload->status === WayfairPriceUpload::FAILED ? self::FAILURE : self::SUCCESS;
    }
}
