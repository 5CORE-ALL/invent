<?php

namespace App\Services\Wayfair;

use App\Models\WayfairPriceUpload;
use App\Services\Support\QueueWorkerWatchdog;

class WayfairUploadHealthService
{
    public function summary(): array
    {
        $latest = WayfairPriceUpload::query()->latest('id')->first();
        $lastStarted = WayfairPriceUpload::query()->whereNotNull('upload_started_at')->latest('upload_started_at')->first();
        $lastCompleted = WayfairPriceUpload::query()->where('status', WayfairPriceUpload::SUCCESS)->latest('processed_at')->first();
        $lastFailed = WayfairPriceUpload::query()
            ->whereIn('status', [WayfairPriceUpload::FAILED, WayfairPriceUpload::STUCK, WayfairPriceUpload::AUTH_REQUIRED])
            ->latest('updated_at')
            ->first();

        $queue = (string) config('wayfair_upload.queue', 'wayfair-upload');
        $running = false;
        try {
            $running = QueueWorkerWatchdog::isRunning($queue);
        } catch (\Throwable $e) {
            $running = false;
        }

        $stuck = WayfairPriceUpload::query()->where('status', WayfairPriceUpload::STUCK)->exists()
            || WayfairPriceUpload::query()
                ->where('status', WayfairPriceUpload::UPLOADING)
                ->where('upload_started_at', '<', now()->subSeconds((int) config('wayfair_upload.stuck_after', 300)))
                ->exists();

        return [
            'enabled' => app(WayfairUploadSettings::class)->enabled(),
            'schedule_time' => (string) config('wayfair_upload.schedule_time', '05:00'),
            'timezone' => (string) config('app.timezone'),
            'mode' => (string) config('wayfair_upload.mode', 'browser'),
            'upload_url_configured' => trim((string) config('wayfair_upload.upload_url', '')) !== '',
            'queue_running' => $running,
            'worker_healthy' => $running && ! $stuck,
            'worker_stuck' => $stuck,
            'last_job_started' => optional($lastStarted?->upload_started_at)->toDateTimeString(),
            'last_job_completed' => optional($lastCompleted?->processed_at)->toDateTimeString(),
            'last_job_failed' => optional($lastFailed?->updated_at)->toDateTimeString(),
            'latest' => $latest ? $this->present($latest) : null,
        ];
    }

    public function present(WayfairPriceUpload $upload): array
    {
        return [
            'id' => $upload->id,
            'filename' => $upload->filename,
            'status' => $upload->status,
            'generated_at' => optional($upload->generated_at)->toDateTimeString(),
            'upload_started_at' => optional($upload->upload_started_at)->toDateTimeString(),
            'uploaded_at' => optional($upload->uploaded_at)->toDateTimeString(),
            'processed_at' => optional($upload->processed_at)->toDateTimeString(),
            'total_rows' => $upload->total_rows,
            'changed_rows' => $upload->changed_rows,
            'successful_rows' => $upload->successful_rows,
            'failed_rows' => $upload->failed_rows,
            'attempts' => $upload->attempts,
            'wayfair_reference' => $upload->wayfair_reference,
            'error_message' => $upload->error_message,
            'created_by' => $upload->created_by,
            'download_url' => $upload->file_path ? route('wayfair.price-upload.download', $upload) : null,
        ];
    }
}
