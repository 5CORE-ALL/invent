<?php

namespace App\Console\Commands\Concerns;

use App\Services\CronMonitor\ChunkedProcessor;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Helpers for large artisan update jobs (bids, prices, inventory).
 */
trait ProcessesUpdatesInChunks
{
    protected function chunkProcessor(): ChunkedProcessor
    {
        return app(ChunkedProcessor::class);
    }

    protected function monitoredChunkSize(?int $override = null): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        if (method_exists($this, 'option')) {
            try {
                $opt = (int) $this->option('chunk');
                if ($opt > 0) {
                    return $opt;
                }
            } catch (\Throwable) {
                // option not defined on this command
            }
        }

        return $this->chunkProcessor()->defaultChunkSize();
    }

    /**
     * @param  array<string|int, float|int>  $idToValue
     * @param  callable(array, array, int): array  $handler
     */
    protected function updateIdMapInChunks(
        CronExecutionContext $monitor,
        array $idToValue,
        callable $handler,
        ?int $chunkSize = null,
        array $options = []
    ): array {
        $size = $this->monitoredChunkSize($chunkSize);
        $this->info('Processing ' . count($idToValue) . " item(s) in chunks of {$size}...");

        $stats = $this->chunkProcessor()->processIdMap(
            $monitor,
            $idToValue,
            $handler,
            $size,
            null,
            $options
        );

        $this->info(sprintf(
            'Chunks done: %d | updated=%d skipped=%d failed=%d chunks_failed=%d (resumed_from=%d)',
            $stats['chunks'],
            $stats['updated'],
            $stats['skipped'],
            $stats['failed'],
            $stats['chunks_failed'] ?? 0,
            $stats['resumed_from']
        ));

        return $stats;
    }

    /**
     * DB updates via chunkById(50) with per-chunk transactions + checkpoints.
     *
     * Handler: fn(Collection $rows, int $chunkIndex, mixed $lastId): array
     *
     * @param  callable(\Illuminate\Support\Collection, int, mixed): array  $handler
     */
    protected function processQueryInChunks(
        CronExecutionContext $monitor,
        Builder $query,
        callable $handler,
        ?int $chunkSize = null,
        string $column = 'id',
        ?string $alias = null,
        array $options = []
    ): array {
        $size = $this->monitoredChunkSize($chunkSize);
        $this->info("Processing query in chunks of {$size} (chunkById)...");

        $stats = $this->chunkProcessor()->processQueryById(
            $monitor,
            $query,
            $handler,
            $size,
            $column,
            $alias,
            $options
        );

        $this->info(sprintf(
            'Query chunks done: %d | updated=%d skipped=%d failed=%d last_id=%s',
            $stats['chunks'],
            $stats['updated'],
            $stats['skipped'],
            $stats['failed'],
            (string) ($stats['last_id'] ?? '—')
        ));

        return $stats;
    }

    /**
     * Persist in-memory rows in chunks with per-chunk DB::transaction (fetch/report writes).
     * When a monitor context is active, also records chunk progress/checkpoints.
     *
     * Handler: fn(array $chunk, int $chunkIndex): array
     *
     * @param  list<mixed>  $items
     * @param  callable(array, int): array  $handler
     * @return array{chunks: int, updated: int, failed: int, skipped: int}
     */
    protected function writeItemsInChunks(
        array $items,
        callable $handler,
        ?int $chunkSize = null,
        ?CronExecutionContext $monitor = null
    ): array {
        $size = $this->monitoredChunkSize($chunkSize);

        if ($monitor !== null) {
            return $this->chunkProcessor()->process(
                $monitor,
                array_values($items),
                fn (array $chunk, int $chunkIndex, int $absoluteOffset) => $handler($chunk, $chunkIndex) ?: [],
                $size,
                null,
                ['transaction' => true]
            );
        }

        $stats = ['chunks' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0];
        foreach (array_chunk(array_values($items), $size) as $chunkIndex => $chunk) {
            $result = DB::transaction(fn () => $handler($chunk, $chunkIndex) ?: []);
            $stats['updated'] += (int) ($result['updated'] ?? count($chunk));
            $stats['failed'] += (int) ($result['failed'] ?? 0);
            $stats['skipped'] += (int) ($result['skipped'] ?? 0);
            $stats['chunks']++;
        }

        return $stats;
    }
}
