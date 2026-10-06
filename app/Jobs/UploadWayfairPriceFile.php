<?php

namespace App\Jobs;

use App\Models\WayfairPriceUpload;
use App\Services\Wayfair\WayfairPriceFileGenerator;
use App\Services\Wayfair\WayfairPriceFileValidator;
use App\Services\Wayfair\WayfairPriceUploadOrchestrator;
use App\Services\Wayfair\WayfairUploadLogger;
use App\Services\Wayfair\WayfairUploadService;
use App\Services\Wayfair\Upload\WayfairUploadResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class UploadWayfairPriceFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public int $uploadId)
    {
        $this->onQueue((string) config('wayfair_upload.queue', 'wayfair-upload'));
        $this->timeout = (int) config('wayfair_upload.job_timeout', 240);
        $this->tries = max(1, (int) config('wayfair_upload.max_retries', 3));
    }

    public function overlapKey(): string
    {
        return 'wayfair-price-upload-'.$this->uploadId;
    }

    public function backoff(): array
    {
        $base = max(10, (int) config('wayfair_upload.retry_delay', 60));

        return [$base, $base * 2, $base * 4];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->overlapKey()))->expireAfter($this->timeout + 30)->releaseAfter(30)];
    }

    public function handle(
        WayfairUploadService $uploads,
        WayfairPriceFileGenerator $files,
        WayfairPriceFileValidator $validator,
        WayfairUploadLogger $logger,
        WayfairPriceUploadOrchestrator $orchestrator,
    ): void {
        $upload = WayfairPriceUpload::query()->find($this->uploadId);
        if (! $upload || in_array($upload->status, [
            WayfairPriceUpload::SUCCESS,
            WayfairPriceUpload::CANCELLED,
            WayfairPriceUpload::NO_CHANGES,
        ], true)) {
            return;
        }

        $lock = Cache::lock('wayfair-price-upload-global', $this->timeout + 30);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        $started = now();
        try {
            $absolute = $upload->file_path ? storage_path('app/'.$upload->file_path) : '';
            if ($absolute === '' || ! is_file($absolute) || ! is_readable($absolute)) {
                $this->finishPermanent($upload, 'Wayfair price file is missing or not readable.', $logger);

                return;
            }

            $errors = $validator->errors($files->read($absolute));
            if ($errors !== []) {
                $this->finishPermanent($upload, implode(' ', $errors), $logger);

                return;
            }

            $upload->status = WayfairPriceUpload::UPLOADING;
            $upload->upload_started_at = $started;
            $upload->attempts = (int) $upload->attempts + 1;
            $upload->last_attempt_at = $started;
            $upload->error_message = null;
            $upload->save();

            $logger->info('Wayfair upload started', [
                'upload_id' => $upload->id,
                'filename' => $upload->filename,
                'sku_count' => $upload->total_rows,
                'changed_sku_count' => $upload->changed_rows,
                'attempt' => $upload->attempts,
                'status' => $upload->status,
                'start_time' => $started->toIso8601String(),
            ]);

            $result = $uploads->upload($absolute, $upload->id);
            $this->applyResult($upload, $result, $logger, $orchestrator);
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        $upload = WayfairPriceUpload::query()->find($this->uploadId);
        if (! $upload || in_array($upload->status, [
            WayfairPriceUpload::SUCCESS,
            WayfairPriceUpload::CANCELLED,
            WayfairPriceUpload::NO_CHANGES,
            WayfairPriceUpload::AUTH_REQUIRED,
            WayfairPriceUpload::REQUIRES_REVIEW,
        ], true)) {
            return;
        }

        $upload->status = WayfairPriceUpload::FAILED;
        $upload->error_message = WayfairUploadLogger::redact($e->getMessage());
        $upload->processed_at = now();
        $upload->save();

        app(WayfairUploadLogger::class)->error('Wayfair upload failed', [
            'upload_id' => $upload->id,
            'filename' => $upload->filename,
            'sku_count' => $upload->total_rows,
            'changed_sku_count' => $upload->changed_rows,
            'attempt' => $upload->attempts,
            'status' => $upload->status,
            'error' => $upload->error_message,
            'end_time' => now()->toIso8601String(),
        ]);
    }

    private function applyResult(
        WayfairPriceUpload $upload,
        WayfairUploadResult $result,
        WayfairUploadLogger $logger,
        WayfairPriceUploadOrchestrator $orchestrator,
    ): void {
        $upload->wayfair_reference = $result->reference ?: $upload->wayfair_reference;
        $upload->wayfair_response = json_encode($logger->scrub($result->response) ?: ['message' => WayfairUploadLogger::redact((string) $result->message)]);

        if ($result->state === 'auth_required') {
            $upload->status = WayfairPriceUpload::AUTH_REQUIRED;
            $upload->error_message = $result->message;
            $upload->processed_at = now();
            $upload->save();
            $this->logEnd($logger, $upload, 'error');

            return;
        }

        if ($result->state === 'success') {
            $upload->status = WayfairPriceUpload::SUCCESS;
            $upload->uploaded_at = $upload->uploaded_at ?: now();
            $upload->processed_at = now();
            $upload->successful_rows = $upload->total_rows;
            $upload->failed_rows = 0;
            $upload->error_message = null;
            $upload->save();
            $this->logEnd($logger, $upload, 'info');

            return;
        }

        if ($result->state === 'processing') {
            $upload->status = WayfairPriceUpload::PROCESSING;
            $upload->uploaded_at = now();
            $upload->error_message = $result->message;
            $upload->save();
            $orchestrator->dispatchStatusCheck($upload);
            $this->logEnd($logger, $upload, 'info');

            return;
        }

        $upload->error_message = WayfairUploadLogger::redact((string) $result->message);
        if ($result->screenshot) {
            $upload->error_message .= ' Screenshot: '.$result->screenshot;
        }

        if ($result->retryable && $this->attempts() < $this->tries) {
            $upload->status = WayfairPriceUpload::QUEUED;
            $delay = $this->backoff()[min($this->attempts() - 1, 2)] ?? 60;
            $upload->next_retry_at = now()->addSeconds($delay);
            $upload->save();
            $this->logEnd($logger, $upload, 'error');
            throw new \RuntimeException($upload->error_message);
        }

        $upload->status = WayfairPriceUpload::FAILED;
        $upload->processed_at = now();
        $upload->failed_rows = $upload->total_rows;
        $upload->save();
        $this->logEnd($logger, $upload, 'error');
    }

    private function finishPermanent(WayfairPriceUpload $upload, string $message, WayfairUploadLogger $logger): void
    {
        $upload->status = WayfairPriceUpload::FAILED;
        $upload->error_message = $message;
        $upload->processed_at = now();
        $upload->attempts = (int) $upload->attempts + 1;
        $upload->last_attempt_at = now();
        $upload->save();
        $this->logEnd($logger, $upload, 'error');
    }

    private function logEnd(WayfairUploadLogger $logger, WayfairPriceUpload $upload, string $level): void
    {
        $context = [
            'upload_id' => $upload->id,
            'filename' => $upload->filename,
            'sku_count' => $upload->total_rows,
            'changed_sku_count' => $upload->changed_rows,
            'attempt' => $upload->attempts,
            'status' => $upload->status,
            'wayfair_reference' => $upload->wayfair_reference,
            'error' => $upload->error_message,
            'end_time' => now()->toIso8601String(),
        ];
        if ($level === 'error') {
            $logger->error('Wayfair upload finished', $context);

            return;
        }
        $logger->info('Wayfair upload finished', $context);
    }
}
