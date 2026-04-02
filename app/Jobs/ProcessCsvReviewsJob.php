<?php

namespace App\Jobs;

use App\Services\ReviewFetchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCsvReviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;
    public $tries   = 2;

    protected string $filePath;
    protected int    $uploadedBy;

    public function __construct(string $filePath, int $uploadedBy = 0)
    {
        $this->filePath   = $filePath;
        $this->uploadedBy = $uploadedBy;
        $this->onQueue('reviews');
    }

    public function handle(ReviewFetchService $service): void
    {
        Log::info("ProcessCsvReviewsJob: Starting", ['file' => $this->filePath]);

        if (!Storage::exists($this->filePath)) {
            Log::error("ProcessCsvReviewsJob: File not found", ['file' => $this->filePath]);
            return;
        }

        $fullPath = Storage::path($this->filePath);
        $rows     = $this->parseCsv($fullPath);

        if (empty($rows)) {
            Log::warning("ProcessCsvReviewsJob: No valid rows found", ['file' => $this->filePath]);
            return;
        }

        $stats = $service->processCsvRows($rows);

        Log::info("ProcessCsvReviewsJob: Complete", array_merge($stats, ['file' => $this->filePath]));

        // Cleanup temp file
        Storage::delete($this->filePath);
    }

    private function parseCsv(string $path): array
    {
        $rows    = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) === false) {
            return [];
        }

        $lineNum = 0;
        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            $lineNum++;
            if ($lineNum === 1) {
                $headers = array_map('trim', array_map('strtolower', $data));
                continue;
            }

            if (count($data) !== count($headers)) {
                continue;
            }

            $row = array_combine($headers, $data);
            if ($row !== false) {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ProcessCsvReviewsJob: Failed", [
            'file'  => $this->filePath,
            'error' => $e->getMessage(),
        ]);
    }
}
