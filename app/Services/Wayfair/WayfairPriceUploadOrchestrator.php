<?php

namespace App\Services\Wayfair;

use App\Jobs\CheckWayfairPriceUploadStatus;
use App\Jobs\UploadWayfairPriceFile;
use App\Models\WayfairPriceUpload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class WayfairPriceUploadOrchestrator
{
    public function __construct(
        private WayfairPriceFileGenerator $files,
        private WayfairPriceFileValidator $validator,
        private WayfairPriceUploadPlanner $planner,
        private WayfairUploadLogger $logger,
        private WayfairUploadSettings $settings,
    ) {}

    public function settings(): WayfairUploadSettings
    {
        return $this->settings;
    }

    /**
     * @param  array<string, float>|null  $priceMap
     */
    public function generate(bool $queue, bool $force = false, ?array $priceMap = null, string $createdBy = 'scheduler'): WayfairPriceUpload
    {
        $this->assertTable();

        $map = $priceMap ?? $this->files->priceMapFromRows($this->files->pricingRows());
        $previous = $this->lastSuccessfulSnapshot();
        $changed = $this->planner->changedSkus($map, $previous);
        $decision = $this->planner->decide(
            $map,
            $changed,
            (bool) config('wayfair_upload.only_changed', true),
            config('wayfair_upload.max_price_change_percent'),
            $previous !== null,
            $force
        );

        if ($decision['action'] === 'no_changes') {
            return $this->storeAudit(WayfairPriceUpload::NO_CHANGES, $map, $changed, null, count($map), $decision['message'], $createdBy);
        }

        if ($decision['action'] === 'empty') {
            return $this->storeAudit(WayfairPriceUpload::FAILED, $map, $changed, null, 0, $decision['message'], $createdBy);
        }

        $written = $this->files->write($decision['lines']);
        $parsed = $this->files->read($written['absolute_path']);
        $errors = $this->validator->errors($parsed);
        if ($errors !== []) {
            $record = $this->storeAudit(
                WayfairPriceUpload::FAILED,
                $map,
                $changed,
                $written,
                count($decision['lines']),
                implode(' ', $errors),
                $createdBy
            );
            $this->logger->error('Wayfair price file failed validation', [
                'upload_id' => $record->id,
                'filename' => $written['filename'],
                'error' => $record->error_message,
            ]);

            return $record;
        }

        if (! $force && $this->checksumInFlightOrSuccess($written['file_sha256'])) {
            return $this->storeAudit(
                WayfairPriceUpload::NO_CHANGES,
                $map,
                $changed,
                $written,
                count($decision['lines']),
                'No price changes — upload skipped. This exact file was already uploaded.',
                $createdBy
            );
        }

        if ($decision['action'] === 'requires_review') {
            $record = $this->storeAudit(
                WayfairPriceUpload::REQUIRES_REVIEW,
                $map,
                $changed,
                $written,
                count($decision['lines']),
                $decision['message'],
                $createdBy
            );
            $this->logger->info('Wayfair upload blocked for review', [
                'upload_id' => $record->id,
                'filename' => $written['filename'],
                'sku_count' => count($decision['lines']),
                'changed_sku_count' => count($changed),
                'status' => $record->status,
            ]);

            return $record;
        }

        $record = $this->storeAudit(
            $queue ? WayfairPriceUpload::QUEUED : WayfairPriceUpload::GENERATED,
            $map,
            $changed,
            $written,
            count($decision['lines']),
            null,
            $createdBy
        );

        $this->logger->info('Wayfair price file generated', [
            'upload_id' => $record->id,
            'filename' => $record->filename,
            'sku_count' => $record->total_rows,
            'changed_sku_count' => $record->changed_rows,
            'status' => $record->status,
            'start_time' => optional($record->generated_at)->toIso8601String(),
        ]);

        if ($queue) {
            $this->dispatchUpload($record);
        }

        return $record;
    }

    /**
     * @param  array<string, float>|null  $priceMap
     * @return array{action: string, total: int, changed: int, file_rows: int, message: string}
     */
    public function preview(?array $priceMap = null, bool $force = false): array
    {
        $this->assertTable();
        $map = $priceMap ?? $this->files->priceMapFromRows($this->files->pricingRows());
        $previous = $this->lastSuccessfulSnapshot();
        $changed = $this->planner->changedSkus($map, $previous);
        $decision = $this->planner->decide(
            $map,
            $changed,
            (bool) config('wayfair_upload.only_changed', true),
            config('wayfair_upload.max_price_change_percent'),
            $previous !== null,
            $force
        );

        return [
            'action' => $decision['action'],
            'total' => count($map),
            'changed' => count($changed),
            'file_rows' => count($decision['lines']),
            'message' => $decision['message'],
        ];
    }

    public function queueExisting(WayfairPriceUpload $upload, bool $force = false): WayfairPriceUpload
    {
        if (! $force && ! in_array($upload->status, array_merge(
            WayfairPriceUpload::manualRetryStatuses(),
            [WayfairPriceUpload::GENERATED]
        ), true)) {
            return $upload;
        }

        if ($upload->status === WayfairPriceUpload::REQUIRES_REVIEW && ! $force) {
            return $upload;
        }

        $absolute = $upload->file_path ? storage_path('app/'.$upload->file_path) : '';
        if ($absolute === '' || ! is_file($absolute)) {
            $upload->status = WayfairPriceUpload::FAILED;
            $upload->error_message = 'Generated file is missing, so it cannot be uploaded.';
            $upload->save();

            return $upload;
        }

        $errors = $this->validator->errors($this->files->read($absolute));
        if ($errors !== []) {
            $upload->status = WayfairPriceUpload::FAILED;
            $upload->error_message = implode(' ', $errors);
            $upload->save();

            return $upload;
        }

        $upload->status = WayfairPriceUpload::QUEUED;
        $upload->error_message = null;
        $upload->next_retry_at = null;
        if ($force) {
            $upload->attempts = 0;
        }
        $upload->save();
        $this->dispatchUpload($upload);

        return $upload->fresh() ?? $upload;
    }

    public function retryFailed(?int $uploadId, bool $force): int
    {
        $query = WayfairPriceUpload::query()->whereIn('status', WayfairPriceUpload::manualRetryStatuses());
        if ($uploadId) {
            $query->where('id', $uploadId);
        }
        $count = 0;
        foreach ($query->orderBy('id')->get() as $upload) {
            if ($upload->status === WayfairPriceUpload::REQUIRES_REVIEW && ! $force) {
                continue;
            }
            $this->queueExisting($upload, true);
            $count++;
        }

        return $count;
    }

    public function recoverStuck(): int
    {
        $cutoff = now()->subSeconds((int) config('wayfair_upload.stuck_after', 300));
        $stuck = WayfairPriceUpload::query()
            ->where('status', WayfairPriceUpload::UPLOADING)
            ->whereNotNull('upload_started_at')
            ->where('upload_started_at', '<', $cutoff)
            ->get();

        foreach ($stuck as $upload) {
            $upload->status = WayfairPriceUpload::STUCK;
            $upload->error_message = 'Upload stayed in UPLOADING longer than the configured timeout and was marked stuck so later uploads are not blocked.';
            $upload->save();
            $this->logger->error('Wayfair upload marked stuck', [
                'upload_id' => $upload->id,
                'filename' => $upload->filename,
                'status' => $upload->status,
                'attempt' => $upload->attempts,
            ]);
        }

        return $stuck->count();
    }

    public function dispatchUpload(WayfairPriceUpload $upload): void
    {
        UploadWayfairPriceFile::dispatch($upload->id);
        $this->logger->info('Wayfair upload queued', [
            'upload_id' => $upload->id,
            'filename' => $upload->filename,
            'sku_count' => $upload->total_rows,
            'changed_sku_count' => $upload->changed_rows,
            'status' => WayfairPriceUpload::QUEUED,
        ]);
    }

    public function dispatchStatusCheck(WayfairPriceUpload $upload): void
    {
        CheckWayfairPriceUploadStatus::dispatch($upload->id)
            ->delay(now()->addSeconds((int) config('wayfair_upload.status_check_delay', 180)));
    }

    public function acquireDailyLock(): mixed
    {
        $lock = Cache::lock('wayfair-daily-price-upload', 600);
        if (! $lock->get()) {
            return null;
        }

        return $lock;
    }

    private function lastSuccessfulSnapshot(): ?array
    {
        $row = WayfairPriceUpload::query()
            ->where('status', WayfairPriceUpload::SUCCESS)
            ->whereNotNull('price_snapshot')
            ->latest('id')
            ->first();

        return $row ? (array) $row->price_snapshot : null;
    }

    private function checksumInFlightOrSuccess(string $checksum): bool
    {
        if ($checksum === '') {
            return false;
        }

        return WayfairPriceUpload::query()
            ->where('file_sha256', $checksum)
            ->whereIn('status', [
                WayfairPriceUpload::QUEUED,
                WayfairPriceUpload::UPLOADING,
                WayfairPriceUpload::UPLOADED,
                WayfairPriceUpload::PROCESSING,
                WayfairPriceUpload::SUCCESS,
            ])
            ->exists();
    }

    private function storeAudit(
        string $status,
        array $snapshot,
        array $changed,
        ?array $written,
        int $totalRows,
        ?string $message,
        string $createdBy
    ): WayfairPriceUpload {
        return WayfairPriceUpload::query()->create([
            'filename' => $written['filename'] ?? null,
            'file_path' => $written['file_path'] ?? null,
            'file_type' => $written['file_type'] ?? 'csv',
            'file_sha256' => $written['file_sha256'] ?? null,
            'generated_at' => now(),
            'status' => $status,
            'total_rows' => $totalRows,
            'changed_rows' => count($changed),
            'price_snapshot' => $snapshot,
            'error_message' => $message,
            'created_by' => $createdBy,
        ]);
    }

    private function assertTable(): void
    {
        if (! Schema::hasTable('wayfair_price_uploads')) {
            throw new \RuntimeException('Run php artisan migrate so wayfair_price_uploads exists.');
        }
    }
}
