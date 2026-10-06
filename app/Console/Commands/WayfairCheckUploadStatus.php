<?php

namespace App\Console\Commands;

use App\Jobs\CheckWayfairPriceUploadStatus;
use App\Models\WayfairPriceUpload;
use App\Services\Wayfair\WayfairPriceUploadOrchestrator;
use Illuminate\Console\Command;

class WayfairCheckUploadStatus extends Command
{
    protected $signature = 'wayfair:check-upload-status
        {--upload-id= : Check one upload}
        {--dry-run : List stuck and processing uploads without changing them}
        {--date= : Accepted for consistency. Not used to filter}';

    protected $description = 'Recover stuck Wayfair uploads and queue processing-status checks';

    public function handle(WayfairPriceUploadOrchestrator $orchestrator): int
    {
        $query = WayfairPriceUpload::query()->whereIn('status', [
            WayfairPriceUpload::UPLOADING,
            WayfairPriceUpload::UPLOADED,
            WayfairPriceUpload::PROCESSING,
        ]);
        if ($this->option('upload-id')) {
            $query->where('id', $this->option('upload-id'));
        }

        $rows = $query->orderBy('id')->get();
        if ($this->option('dry-run')) {
            foreach ($rows as $row) {
                $this->line('#'.$row->id.' '.$row->status.' '.$row->filename);
            }
            $this->info($rows->count().' upload(s). Dry run, nothing changed.');

            return self::SUCCESS;
        }

        $stuck = $orchestrator->recoverStuck();
        $this->info('Marked '.$stuck.' stuck upload(s).');

        $pending = WayfairPriceUpload::query()->whereIn('status', [
            WayfairPriceUpload::UPLOADED,
            WayfairPriceUpload::PROCESSING,
        ]);
        if ($this->option('upload-id')) {
            $pending->where('id', $this->option('upload-id'));
        }
        foreach ($pending->get() as $upload) {
            CheckWayfairPriceUploadStatus::dispatch($upload->id);
            $this->line('Status check queued for #'.$upload->id);
        }

        return self::SUCCESS;
    }
}
