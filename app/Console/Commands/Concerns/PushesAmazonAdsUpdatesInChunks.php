<?php

namespace App\Console\Commands\Concerns;

use App\Services\CronMonitor\CronExecutionContext;

/**
 * Chunked Amazon Ads ID→value pushes with failed-only retries.
 *
 * Works with KW bids, PT targets, HL/SB keywords, and similar list APIs that return
 * ['failed' => [...], 'skipped' => [...]] (or status 200 for all-success).
 */
trait PushesAmazonAdsUpdatesInChunks
{
    use ProcessesUpdatesInChunks;

    protected function adsChunkMaxRetries(): int
    {
        return defined('static::MAX_RETRY_ATTEMPTS')
            ? (int) static::MAX_RETRY_ATTEMPTS
            : 5;
    }

    protected function adsChunkRetryDelaySeconds(): int
    {
        return defined('static::RETRY_DELAY_SECONDS')
            ? (int) static::RETRY_DELAY_SECONDS
            : 5;
    }

    /**
     * Push one chunk via $apiUpdater(list $ids, list $values): array
     *
     * @param  list<string>  $ids
     * @param  list<float|int>  $values
     * @param  array<string, float|int>  $idToValue
     * @return array{updated_ids: list<string>, failed: list<array>, skipped: list<array>, attempts: int, retries: int}
     */
    protected function pushAdsChunkWithRetries(
        callable $apiUpdater,
        array $ids,
        array $values,
        array $idToValue
    ): array {
        $allSkipped = [];
        $currentIds = $ids;
        $currentValues = $values;
        $attempt = 0;
        $lastFailed = [];
        $maxAttempts = $this->adsChunkMaxRetries();
        $delay = $this->adsChunkRetryDelaySeconds();

        while (true) {
            $attempt++;
            try {
                if ($attempt > 1) {
                    $this->info("Waiting {$delay}s before chunk retry...");
                    sleep($delay);
                }

                $result = $apiUpdater($currentIds, $currentValues);

                if (is_object($result) && method_exists($result, 'getData')) {
                    $result = $result->getData(true);
                }

                if (! is_array($result)) {
                    $lastFailed = array_map(
                        fn ($id) => ['campaign_id' => $id, 'error' => 'Unexpected API result', 'status' => 500],
                        $currentIds
                    );
                    break;
                }

                $allSkipped = array_merge($allSkipped, $result['skipped'] ?? []);

                // Prefer explicit failed list; otherwise treat non-200 as all failed
                if (array_key_exists('failed', $result)) {
                    $lastFailed = $result['failed'] ?? [];
                } elseif (($result['status'] ?? null) == 200) {
                    $lastFailed = [];
                } else {
                    $err = $result['error'] ?? $result['message'] ?? 'Update failed';
                    $lastFailed = array_map(
                        fn ($id) => [
                            'campaign_id' => $id,
                            'error' => is_string($err) ? $err : json_encode($err),
                            'status' => $result['status'] ?? 500,
                        ],
                        $currentIds
                    );
                }

                if ($lastFailed === []) {
                    break;
                }

                if ($attempt >= $maxAttempts) {
                    break;
                }

                $currentIds = [];
                $currentValues = [];
                foreach ($lastFailed as $f) {
                    $cid = $f['campaign_id'] ?? null;
                    if ($cid !== null && isset($idToValue[$cid])) {
                        $currentIds[] = $cid;
                        $currentValues[] = $idToValue[$cid];
                    }
                }
                if ($currentIds === []) {
                    break;
                }
            } catch (\Exception $e) {
                $this->error("Chunk attempt {$attempt} exception: " . $e->getMessage());
                $lastFailed = array_map(
                    fn ($id) => ['campaign_id' => $id, 'error' => $e->getMessage(), 'status' => 500],
                    $currentIds
                );
                if ($attempt >= $maxAttempts) {
                    break;
                }
            }
        }

        $failedIds = collect($lastFailed)->pluck('campaign_id')->filter()->all();
        $skippedIds = collect($allSkipped)->pluck('campaign_id')->filter()->all();
        $updatedIds = array_values(array_diff($ids, $failedIds, $skippedIds));

        return [
            'updated_ids' => $updatedIds,
            'failed' => $lastFailed,
            'skipped' => $allSkipped,
            'attempts' => $attempt,
            'retries' => max(0, $attempt - 1),
        ];
    }

    /**
     * Monitored chunked push of an id => value map.
     *
     * @param  array<string, float|int>  $idToValue
     * @param  callable(list<string>, list<float|int>): mixed  $apiUpdater
     * @return array{total: int, updated: int, failed: int, skipped: int, chunks: int, resumed_from: int, updated_map: array<string, float|int>}
     */
    protected function pushAmazonAdsIdMapInChunks(
        CronExecutionContext $monitor,
        array $idToValue,
        callable $apiUpdater,
        string $marketplace = 'amazon'
    ): array {
        $updatedMap = [];

        $stats = $this->updateIdMapInChunks(
            $monitor,
            $idToValue,
            function (array $chunkIds, array $chunkValues, int $chunkIndex) use (
                $apiUpdater,
                $marketplace,
                &$updatedMap
            ) {
                $this->info('Chunk #' . ($chunkIndex + 1) . ': updating ' . count($chunkIds) . ' item(s)...');

                $chunkMap = [];
                foreach ($chunkIds as $i => $id) {
                    $chunkMap[$id] = $chunkValues[$i] ?? null;
                }

                $chunkResult = $this->pushAdsChunkWithRetries(
                    $apiUpdater,
                    $chunkIds,
                    $chunkValues,
                    $chunkMap
                );

                $failures = [];
                foreach ($chunkResult['failed'] as $f) {
                    $failures[] = [
                        'sku' => (string) ($f['campaign_id'] ?? ''),
                        'marketplace' => $marketplace,
                        'reason' => $f['error'] ?? $f['reason'] ?? 'update failed',
                        'api_response' => $f,
                        'http_status' => $f['status'] ?? $f['http_status'] ?? 500,
                    ];
                }

                foreach ($chunkResult['updated_ids'] as $id) {
                    if (isset($chunkMap[$id])) {
                        $updatedMap[$id] = $chunkMap[$id];
                    }
                }

                return [
                    'updated' => count($chunkResult['updated_ids']),
                    'failed' => count($chunkResult['failed']),
                    'skipped' => count($chunkResult['skipped']),
                    'processed' => count($chunkIds),
                    'failures' => $failures,
                    'retries' => (int) ($chunkResult['retries'] ?? 0),
                ];
            }
        );

        $stats['updated_map'] = $updatedMap;

        return $stats;
    }

    /**
     * Unmonitored chunked push (budget crons / legacy commands).
     *
     * @param  array<string, float|int>  $idToValue
     * @param  callable(list<string>, list<float|int>): mixed  $apiUpdater
     * @return array{updated_ids: list<string>, failed: list<array>, skipped: list<array>, chunks: int}
     */
    protected function pushAmazonAdsIdMapInChunksPlain(
        array $idToValue,
        callable $apiUpdater
    ): array {
        $size = $this->monitoredChunkSize();
        $pairs = [];
        foreach ($idToValue as $id => $value) {
            $pairs[] = ['id' => (string) $id, 'value' => $value];
        }

        $this->info('Processing ' . count($pairs) . " item(s) in chunks of {$size}...");

        $allUpdated = [];
        $allFailed = [];
        $allSkipped = [];
        $chunks = 0;

        foreach (array_chunk($pairs, $size) as $chunkIndex => $chunk) {
            $chunks++;
            $ids = array_column($chunk, 'id');
            $values = array_column($chunk, 'value');
            $chunkMap = array_column($chunk, 'value', 'id');

            $this->info('Chunk #' . ($chunkIndex + 1) . ': updating ' . count($ids) . ' item(s)...');
            $result = $this->pushAdsChunkWithRetries($apiUpdater, $ids, $values, $chunkMap);
            $allUpdated = array_merge($allUpdated, $result['updated_ids']);
            $allFailed = array_merge($allFailed, $result['failed']);
            $allSkipped = array_merge($allSkipped, $result['skipped']);
        }

        $this->info(sprintf(
            'Chunks done: %d | updated=%d skipped=%d failed=%d',
            $chunks,
            count($allUpdated),
            count($allSkipped),
            count($allFailed)
        ));

        return [
            'updated_ids' => $allUpdated,
            'failed' => $allFailed,
            'skipped' => $allSkipped,
            'chunks' => $chunks,
        ];
    }
}
