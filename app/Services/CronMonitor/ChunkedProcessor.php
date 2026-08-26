<?php

namespace App\Services\CronMonitor;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Process large update lists in chunks with monitoring + resume checkpoints.
 *
 * - process() / processIdMap(): in-memory batches (API pushes)
 * - processQueryById(): Eloquent chunkById for DB updates
 */
class ChunkedProcessor
{
    public function defaultChunkSize(): int
    {
        return max(1, (int) config('cron-monitor.chunks.size', 50));
    }

    public function checkpointEvery(): int
    {
        return max(1, (int) config('cron-monitor.chunks.checkpoint_every', 1));
    }

    public function useDbTransaction(): bool
    {
        return (bool) config('cron-monitor.chunks.use_db_transaction', true);
    }

    /**
     * Process a list of items in chunks, skipping already-completed offsets on resume.
     *
     * Handler: fn(array $chunk, int $chunkIndex, int $absoluteOffset): array
     * Expected return keys (optional): updated, failed, skipped, processed, failures, retries
     *
     * @param  list<mixed>  $items
     * @param  callable(array, int, int): array  $handler
     * @param  array{transaction?: bool, failed_chunks_only?: bool}  $options
     * @return array{total: int, updated: int, failed: int, skipped: int, chunks: int, resumed_from: int, chunks_failed: int}
     */
    public function process(
        CronExecutionContext $monitor,
        array $items,
        callable $handler,
        ?int $chunkSize = null,
        ?int $resumeFrom = null,
        array $options = []
    ): array {
        $chunkSize = $chunkSize ?? $this->defaultChunkSize();
        $useTx = (bool) ($options['transaction'] ?? false);
        $failedOnly = (bool) ($options['failed_chunks_only'] ?? false);
        $fresh = (bool) ($options['fresh'] ?? false);
        $resumeFrom = $fresh ? 0 : max(0, $resumeFrom ?? $monitor->resumeOffset());
        $total = count($items);
        $allChunks = array_chunk(array_values($items), $chunkSize);
        $totalChunks = count($allChunks);

        $failedChunkIndexes = array_values(array_unique(array_map(
            'intval',
            $monitor->meta['failed_chunks'] ?? []
        )));

        if ($failedOnly && $failedChunkIndexes !== []) {
            $work = [];
            foreach ($failedChunkIndexes as $idx) {
                if (isset($allChunks[$idx])) {
                    $work[] = ['index' => $idx, 'chunk' => $allChunks[$idx]];
                }
            }
            $monitor->mergeMeta(['chunk_resumed_failed_only' => true]);
        } else {
            if ($resumeFrom > 0 && $resumeFrom < $total) {
                $items = array_slice(array_values($items), $resumeFrom);
                $allChunks = array_chunk($items, $chunkSize);
                $monitor->mergeMeta(['chunk_resumed_from' => $resumeFrom]);
            } elseif ($resumeFrom >= $total && $total > 0 && ! $failedOnly) {
                $monitor->mergeMeta(['already_complete' => true]);
                if ($monitor->processedRecords === 0) {
                    $monitor->incrementProcessed($total);
                }

                return $this->emptyStats($total, $resumeFrom);
            }

            $work = [];
            foreach ($allChunks as $idx => $chunk) {
                $work[] = ['index' => $idx, 'chunk' => $chunk];
            }
        }

        if ($work === []) {
            return $this->emptyStats($total, $resumeFrom);
        }

        $monitor->setExpected($total);
        if ($monitor->fetchedRecords === 0) {
            $monitor->setFetched($total);
        }

        $stats = [
            'total' => $total,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'chunks' => 0,
            'chunks_failed' => 0,
            'resumed_from' => $resumeFrom,
        ];

        $durations = [];
        $chunkRetriesTotal = 0;
        $absoluteOffset = $failedOnly ? $resumeFrom : $resumeFrom;
        $checkpointEvery = $this->checkpointEvery();
        $remainingFailed = $failedChunkIndexes;
        $startedAt = microtime(true);

        $this->publishProgress($monitor, [
            'total_chunks' => $totalChunks,
            'completed' => 0,
            'failed' => 0,
            'current' => 0,
            'resume_point' => $resumeFrom,
            'avg_chunk_ms' => 0,
            'eta_seconds' => null,
            'chunk_retries' => 0,
            'memory_usage' => $this->memoryUsage(),
        ]);

        foreach ($work as $workItem) {
            if ($monitor->log?->fresh()?->cancelled_at) {
                break;
            }

            $chunkIndex = (int) $workItem['index'];
            $chunk = $workItem['chunk'];
            $chunkStarted = microtime(true);
            $currentDisplay = $chunkIndex + 1;

            try {
                $result = $useTx
                    ? DB::transaction(fn () => $handler($chunk, $chunkIndex, $absoluteOffset) ?: [])
                    : ($handler($chunk, $chunkIndex, $absoluteOffset) ?: []);
            } catch (Throwable $e) {
                $result = [
                    'updated' => 0,
                    'failed' => count($chunk),
                    'skipped' => 0,
                    'processed' => count($chunk),
                    'failures' => [[
                        'sku' => null,
                        'reason' => $e->getMessage(),
                        'category' => 'exception',
                        'recoverable' => true,
                        'meta' => ['chunk_index' => $chunkIndex],
                    ]],
                ];
                if (! in_array($chunkIndex, $remainingFailed, true)) {
                    $remainingFailed[] = $chunkIndex;
                }
            }

            $elapsedMs = (int) round((microtime(true) - $chunkStarted) * 1000);
            $durations[] = $elapsedMs;

            $updated = (int) ($result['updated'] ?? count($chunk));
            $failed = (int) ($result['failed'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $processed = (int) ($result['processed'] ?? ($updated + $failed + $skipped));
            if ($processed <= 0) {
                $processed = count($chunk);
            }
            $chunkRetriesTotal += (int) ($result['retries'] ?? 0);

            $monitor->incrementProcessed($processed);
            if ($updated > 0) {
                $monitor->incrementUpdated($updated);
            }
            if ($skipped > 0) {
                $monitor->incrementSkipped($skipped);
            }

            foreach ($result['failures'] ?? [] as $failure) {
                $monitor->recordFailure(
                    sku: $failure['sku'] ?? null,
                    marketplace: $failure['marketplace'] ?? null,
                    reason: $failure['reason'] ?? null,
                    apiResponse: $failure['api_response'] ?? null,
                    meta: $failure['meta'] ?? [],
                    category: $failure['category'] ?? null,
                    httpStatus: $failure['http_status'] ?? null,
                    recoverable: $failure['recoverable'] ?? null,
                    rootCause: $failure['root_cause'] ?? null,
                );
            }

            if (empty($result['failures']) && $failed > 0) {
                $monitor->incrementFailed($failed);
            }

            $chunkHadFailure = $failed > 0 || ! empty($result['failures']);
            if ($chunkHadFailure) {
                $stats['chunks_failed']++;
                if (! in_array($chunkIndex, $remainingFailed, true)) {
                    $remainingFailed[] = $chunkIndex;
                }
            } else {
                $remainingFailed = array_values(array_filter(
                    $remainingFailed,
                    static fn ($i) => (int) $i !== $chunkIndex
                ));
            }

            $stats['updated'] += $updated;
            $stats['failed'] += $failed;
            $stats['skipped'] += $skipped;
            $stats['chunks']++;

            if (! $failedOnly) {
                $absoluteOffset += count($chunk);
            }

            $avgMs = (int) round(array_sum($durations) / max(1, count($durations)));
            $remainingChunks = max(0, $totalChunks - ($failedOnly ? $stats['chunks'] : ($chunkIndex + 1)));
            $etaSeconds = $avgMs > 0 ? (int) ceil(($avgMs * $remainingChunks) / 1000) : null;

            $progress = [
                'total_chunks' => $totalChunks,
                'completed' => $stats['chunks'],
                'failed' => $stats['chunks_failed'],
                'current' => $currentDisplay,
                'resume_point' => $absoluteOffset,
                'avg_chunk_ms' => $avgMs,
                'eta_seconds' => $etaSeconds,
                'chunk_retries' => $chunkRetriesTotal,
                'memory_usage' => $this->memoryUsage(),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
            $this->publishProgress($monitor, $progress);
            $monitor->mergeMeta(['failed_chunks' => array_values($remainingFailed)]);

            if (($stats['chunks']) % $checkpointEvery === 0) {
                $monitor->checkpoint([
                    'chunk_index' => $chunkIndex,
                    'chunk_number' => $currentDisplay,
                    'offset' => $absoluteOffset,
                    'last_id' => null,
                    'processed' => $monitor->processedRecords,
                    'updated' => $monitor->updatedRecords,
                    'failed' => $monitor->failedRecords,
                    'memory' => $this->memoryUsage(),
                    'elapsed_ms' => $progress['elapsed_ms'],
                ], $absoluteOffset);
                app(CronMonitorService::class)->sync();
            }
        }

        $monitor->mergeMeta([
            'chunk_stats' => $stats,
            'chunk_size' => $chunkSize,
            'failed_chunks' => array_values($remainingFailed),
        ]);

        return $stats;
    }

    /**
     * Convenience for id => bid maps used by Amazon/eBay bid syncs.
     *
     * @param  array<string|int, float|int>  $idToValue
     * @param  callable(array, array, int): array  $handler
     */
    public function processIdMap(
        CronExecutionContext $monitor,
        array $idToValue,
        callable $handler,
        ?int $chunkSize = null,
        ?int $resumeFrom = null,
        array $options = []
    ): array {
        $pairs = [];
        foreach ($idToValue as $id => $value) {
            $pairs[] = ['id' => $id, 'value' => $value];
        }

        return $this->process(
            $monitor,
            $pairs,
            function (array $chunk, int $chunkIndex, int $absoluteOffset) use ($handler) {
                $ids = array_column($chunk, 'id');
                $values = array_column($chunk, 'value');

                return $handler($ids, $values, $chunkIndex);
            },
            $chunkSize,
            $resumeFrom,
            $options
        );
    }

    /**
     * Stream DB rows with chunkById; resume from last_id checkpoint.
     *
     * Handler: fn(Collection $rows, int $chunkIndex, mixed $lastId): array
     *
     * @param  callable(\Illuminate\Support\Collection, int, mixed): array  $handler
     * @return array{total: int|null, updated: int, failed: int, skipped: int, chunks: int, chunks_failed: int, last_id: mixed}
     */
    public function processQueryById(
        CronExecutionContext $monitor,
        Builder $query,
        callable $handler,
        ?int $chunkSize = null,
        string $column = 'id',
        ?string $alias = null,
        array $options = []
    ): array {
        $chunkSize = $chunkSize ?? $this->defaultChunkSize();
        $useTx = array_key_exists('transaction', $options)
            ? (bool) $options['transaction']
            : $this->useDbTransaction();

        $fresh = (bool) ($options['fresh'] ?? false);
        $cursor = is_array($monitor->checkpointCursor) ? $monitor->checkpointCursor : [];
        $resumeLastId = $fresh ? null : ($cursor['last_id'] ?? null);
        if ($resumeLastId !== null) {
            $query = (clone $query)->where($column, '>', $resumeLastId);
            $monitor->mergeMeta(['chunk_resumed_last_id' => $resumeLastId]);
        }

        $totalEstimate = null;
        try {
            $totalEstimate = (clone $query)->count();
        } catch (Throwable) {
            // some builders may not support count; continue without expected
        }

        if ($totalEstimate !== null) {
            $monitor->setExpected(($monitor->expectedRecords ?? 0) > 0
                ? $monitor->expectedRecords
                : ($totalEstimate + ($monitor->processedRecords ?: 0)));
            if ($monitor->fetchedRecords === 0) {
                $monitor->setFetched($totalEstimate);
            }
        }

        if ($resumeLastId !== null && $totalEstimate === 0) {
            $monitor->mergeMeta(['already_complete' => true]);
            $done = $monitor->fetchedRecords > 0
                ? $monitor->fetchedRecords
                : (int) ($monitor->expectedRecords ?? 0);
            if ($monitor->processedRecords === 0 && $done > 0) {
                $monitor->incrementProcessed($done);
            }

            return [
                'total' => $done,
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'chunks' => 0,
                'chunks_failed' => 0,
                'last_id' => $resumeLastId,
            ];
        }

        $stats = [
            'total' => $totalEstimate,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'chunks' => 0,
            'chunks_failed' => 0,
            'last_id' => $resumeLastId,
        ];

        $durations = [];
        $chunkRetriesTotal = 0;
        $startedAt = microtime(true);
        $totalChunks = $totalEstimate !== null
            ? (int) max(1, ceil($totalEstimate / $chunkSize))
            : null;
        $checkpointEvery = $this->checkpointEvery();
        $remainingFailed = array_values(array_unique(array_map(
            'intval',
            $monitor->meta['failed_chunks'] ?? []
        )));

        $this->publishProgress($monitor, [
            'total_chunks' => $totalChunks,
            'completed' => 0,
            'failed' => 0,
            'current' => 0,
            'resume_point' => $resumeLastId,
            'avg_chunk_ms' => 0,
            'eta_seconds' => null,
            'chunk_retries' => 0,
            'memory_usage' => $this->memoryUsage(),
        ]);

        $query->chunkById($chunkSize, function ($rows) use (
            $monitor,
            $handler,
            $useTx,
            $column,
            &$stats,
            &$durations,
            &$chunkRetriesTotal,
            &$remainingFailed,
            $startedAt,
            $totalChunks,
            $checkpointEvery
        ) {
            if ($monitor->log?->fresh()?->cancelled_at) {
                return false;
            }

            $chunkIndex = $stats['chunks'];
            $currentDisplay = $chunkIndex + 1;
            $chunkStarted = microtime(true);
            $lastId = $rows->last()?->{$column};

            try {
                $result = $useTx
                    ? DB::transaction(fn () => $handler($rows, $chunkIndex, $lastId) ?: [])
                    : ($handler($rows, $chunkIndex, $lastId) ?: []);
            } catch (Throwable $e) {
                $result = [
                    'updated' => 0,
                    'failed' => $rows->count(),
                    'skipped' => 0,
                    'processed' => $rows->count(),
                    'failures' => [[
                        'sku' => null,
                        'reason' => $e->getMessage(),
                        'category' => 'exception',
                        'recoverable' => true,
                        'meta' => ['chunk_index' => $chunkIndex, 'last_id' => $lastId],
                    ]],
                ];
                if (! in_array($chunkIndex, $remainingFailed, true)) {
                    $remainingFailed[] = $chunkIndex;
                }
            }

            $elapsedMs = (int) round((microtime(true) - $chunkStarted) * 1000);
            $durations[] = $elapsedMs;

            $updated = (int) ($result['updated'] ?? $rows->count());
            $failed = (int) ($result['failed'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $processed = (int) ($result['processed'] ?? ($updated + $failed + $skipped));
            if ($processed <= 0) {
                $processed = $rows->count();
            }
            $chunkRetriesTotal += (int) ($result['retries'] ?? 0);

            $monitor->incrementProcessed($processed);
            if ($updated > 0) {
                $monitor->incrementUpdated($updated);
            }
            if ($skipped > 0) {
                $monitor->incrementSkipped($skipped);
            }

            foreach ($result['failures'] ?? [] as $failure) {
                $monitor->recordFailure(
                    sku: $failure['sku'] ?? null,
                    marketplace: $failure['marketplace'] ?? null,
                    reason: $failure['reason'] ?? null,
                    apiResponse: $failure['api_response'] ?? null,
                    meta: $failure['meta'] ?? [],
                    category: $failure['category'] ?? null,
                    httpStatus: $failure['http_status'] ?? null,
                    recoverable: $failure['recoverable'] ?? null,
                    rootCause: $failure['root_cause'] ?? null,
                );
            }

            if (empty($result['failures']) && $failed > 0) {
                $monitor->incrementFailed($failed);
            }

            $chunkHadFailure = $failed > 0 || ! empty($result['failures']);
            if ($chunkHadFailure) {
                $stats['chunks_failed']++;
                if (! in_array($chunkIndex, $remainingFailed, true)) {
                    $remainingFailed[] = $chunkIndex;
                }
            } else {
                $remainingFailed = array_values(array_filter(
                    $remainingFailed,
                    static fn ($i) => (int) $i !== $chunkIndex
                ));
            }

            $stats['updated'] += $updated;
            $stats['failed'] += $failed;
            $stats['skipped'] += $skipped;
            $stats['chunks']++;
            $stats['last_id'] = $lastId;

            $avgMs = (int) round(array_sum($durations) / max(1, count($durations)));
            $remainingChunks = $totalChunks !== null
                ? max(0, $totalChunks - $stats['chunks'])
                : null;
            $etaSeconds = ($avgMs > 0 && $remainingChunks !== null)
                ? (int) ceil(($avgMs * $remainingChunks) / 1000)
                : null;

            $progress = [
                'total_chunks' => $totalChunks,
                'completed' => $stats['chunks'],
                'failed' => $stats['chunks_failed'],
                'current' => $currentDisplay,
                'resume_point' => $lastId,
                'avg_chunk_ms' => $avgMs,
                'eta_seconds' => $etaSeconds,
                'chunk_retries' => $chunkRetriesTotal,
                'memory_usage' => $this->memoryUsage(),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
            $this->publishProgress($monitor, $progress);
            $monitor->mergeMeta(['failed_chunks' => array_values($remainingFailed)]);

            if ($stats['chunks'] % $checkpointEvery === 0) {
                $monitor->checkpoint([
                    'chunk_index' => $chunkIndex,
                    'chunk_number' => $currentDisplay,
                    'last_id' => $lastId,
                    'offset' => $monitor->processedRecords,
                    'processed' => $monitor->processedRecords,
                    'updated' => $monitor->updatedRecords,
                    'failed' => $monitor->failedRecords,
                    'memory' => $this->memoryUsage(),
                    'elapsed_ms' => $progress['elapsed_ms'],
                ], is_numeric($lastId) ? (int) $lastId : $monitor->processedRecords);
                app(CronMonitorService::class)->sync();
            }

            return true;
        }, $column, $alias);

        $monitor->mergeMeta([
            'chunk_stats' => $stats,
            'chunk_size' => $chunkSize,
            'failed_chunks' => array_values($remainingFailed),
        ]);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    protected function publishProgress(CronExecutionContext $monitor, array $progress): void
    {
        $monitor->mergeMeta(['chunk_progress' => $progress]);
    }

    protected function memoryUsage(): string
    {
        return round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';
    }

    /**
     * @return array{total: int, updated: int, failed: int, skipped: int, chunks: int, chunks_failed: int, resumed_from: int}
     */
    protected function emptyStats(int $total, int $resumeFrom): array
    {
        return [
            'total' => $total,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'chunks' => 0,
            'chunks_failed' => 0,
            'resumed_from' => $resumeFrom,
        ];
    }
}
