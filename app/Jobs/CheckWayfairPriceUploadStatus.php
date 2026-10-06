<?php

namespace App\Jobs;

use App\Models\WayfairPriceUpload;
use App\Services\Wayfair\WayfairPriceUploadOrchestrator;
use App\Services\Wayfair\WayfairUploadLogger;
use App\Services\Wayfair\WayfairUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckWayfairPriceUploadStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $uploadId)
    {
        $this->onQueue((string) config('wayfair_upload.queue', 'wayfair-upload'));
    }

    public function backoff(): array
    {
        $base = max(30, (int) config('wayfair_upload.status_check_delay', 180));

        return [$base, $base * 2];
    }

    public function handle(WayfairUploadService $uploads, WayfairUploadLogger $logger, WayfairPriceUploadOrchestrator $orchestrator): void
    {
        $upload = WayfairPriceUpload::query()->find($this->uploadId);
        if (! $upload || ! in_array($upload->status, [WayfairPriceUpload::UPLOADED, WayfairPriceUpload::PROCESSING], true)) {
            return;
        }

        $result = $uploads->checkStatus($upload->wayfair_reference, ['upload_id' => $upload->id]);
        $upload->wayfair_response = json_encode($logger->scrub($result->response) ?: ['message' => WayfairUploadLogger::redact((string) $result->message)]);

        if ($result->state === 'success') {
            $upload->status = WayfairPriceUpload::SUCCESS;
            $upload->processed_at = now();
            $upload->successful_rows = $upload->total_rows;
            $upload->failed_rows = 0;
            $upload->error_message = null;
            $upload->wayfair_reference = $result->reference ?: $upload->wayfair_reference;
            $upload->save();
        } elseif ($result->state === 'failed') {
            $upload->status = WayfairPriceUpload::FAILED;
            $upload->processed_at = now();
            $upload->failed_rows = $upload->total_rows;
            $upload->error_message = WayfairUploadLogger::redact((string) $result->message);
            $upload->save();
        } else {
            $upload->status = WayfairPriceUpload::PROCESSING;
            $upload->error_message = $result->message;
            $upload->save();
            $checks = (int) cache()->increment('wayfair-status-checks-'.$upload->id);
            $max = max(1, (int) config('wayfair_upload.max_retries', 3));
            if ($checks < $max) {
                $orchestrator->dispatchStatusCheck($upload);
            }
        }

        $logger->info('Wayfair upload status checked', [
            'upload_id' => $upload->id,
            'filename' => $upload->filename,
            'sku_count' => $upload->total_rows,
            'changed_sku_count' => $upload->changed_rows,
            'attempt' => $this->attempts(),
            'status' => $upload->status,
            'wayfair_reference' => $upload->wayfair_reference,
            'error' => $upload->error_message,
        ]);
    }
}
