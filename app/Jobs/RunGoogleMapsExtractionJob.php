<?php

namespace App\Jobs;

use App\Models\GoogleMapsExtractorResult;
use App\Models\GoogleMapsExtractorSearch;
use App\Services\GoogleMapsScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunGoogleMapsExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(private readonly string $progressToken)
    {
        $this->onQueue('google-maps-extractor');
    }

    public function handle(GoogleMapsScraperService $scraper): void
    {
        $state = Cache::get($this->stateCacheKey());

        if (! is_array($state) || empty($state['search_id'])) {
            return;
        }

        $search = GoogleMapsExtractorSearch::find($state['search_id']);

        if (! $search) {
            return;
        }

        if (in_array($search->status, ['completed', 'cancelled', 'stopped', 'failed'], true)) {
            return;
        }

        $queries = $state['queries'] ?? [];
        $limit = (int) ($state['limit'] ?? $search->result_limit);
        $hasResultLimit = $limit > 0;
        $queryIndex = (int) ($state['query_index'] ?? 0);
        $sourceIndex = (int) ($state['source_index'] ?? 0);
        $emptyLocalPages = (int) ($state['consecutive_empty_local_pages'] ?? 0);

        $search->update(['status' => 'running']);
        $this->writeProgress([
            'status' => 'running',
            'message' => 'Queued extraction worker started.',
            'records' => $search->results()->count(),
            'result_limit' => $limit,
            'total_queries' => count($queries),
            'current_query_number' => $queryIndex,
        ]);

        while ($queryIndex < count($queries) && (! $hasResultLimit || $search->results()->count() < $limit)) {
            if ($this->shouldAbortExtraction($search)) {
                return;
            }

            $controlAction = $this->waitIfPaused($search);

            if ($this->shouldAbortExtraction($search)) {
                return;
            }

            if (in_array($controlAction, ['stop', 'cancel'], true)) {
                $this->finishControlled($search, $controlAction);
                return;
            }

            $searchQuery = $queries[$queryIndex];
            $urls = $scraper->buildSearchUrlPlan($searchQuery);

            if ($sourceIndex >= count($urls)) {
                $queryIndex++;
                $sourceIndex = 0;
                $emptyLocalPages = 0;
                $this->storeState($state, $queryIndex, $sourceIndex, $emptyLocalPages);
                continue;
            }

            $sourceUrl = $urls[$sourceIndex];
            $beforeCount = $search->results()->count();
            $payload = $scraper->scrapeSearchUrl($searchQuery, $sourceUrl);
            $this->persistRecords($search, $payload['records'] ?? [], $limit);
            $afterCount = $search->results()->count();
            $newUnique = max(0, $afterCount - $beforeCount);
            $search->update(['results_count' => $afterCount]);

            if (($sourceUrl['source'] ?? '') === 'google_local') {
                $emptyLocalPages = $newUnique > 0 ? 0 : $emptyLocalPages + 1;

                if ($emptyLocalPages >= 2) {
                    $queryIndex++;
                    $sourceIndex = 0;
                    $emptyLocalPages = 0;
                } else {
                    $sourceIndex++;
                }
            } else {
                $sourceIndex++;
            }

            $this->storeState($state, $queryIndex, $sourceIndex, $emptyLocalPages);
            $this->writeProgress([
                'status' => 'running',
                'message' => ($payload['message'] ?? 'Processed search step.') . ' New unique: ' . $newUnique . '. Total saved: ' . $afterCount . '.',
                'records' => $afterCount,
                'result_limit' => $limit,
                'current_query_number' => min($queryIndex + 1, count($queries)),
                'total_queries' => count($queries),
                'current_page' => $sourceUrl['page'] ?? 1,
                'current_source' => $sourceUrl['source'] ?? null,
            ]);

            sleep(($sourceUrl['source'] ?? '') === 'google_local' ? 3 : 2);

            if ($this->shouldAbortExtraction($search)) {
                return;
            }

            $controlAction = $this->readControlAction();
            if (in_array($controlAction, ['stop', 'cancel'], true)) {
                $this->finishControlled($search, $controlAction);
                return;
            }
        }

        $this->trimResultsToLimit($search, $limit);

        $count = $search->results()->count();
        $hitResultLimit = $hasResultLimit && $count >= $limit;
        $completionMessage = $hitResultLimit
            ? "Extraction completed after reaching the {$limit} result limit."
            : ($hasResultLimit
                ? "Extraction completed after checking all selected city/search attempt(s). Saved {$count} of {$limit} requested result(s)."
                : "Extraction completed after checking all selected city/search attempt(s). Saved {$count} available result(s).");

        $search->update([
            'status' => 'completed',
            'results_count' => $count,
            'completed_at' => now(),
        ]);
        Cache::forget($this->stateCacheKey());
        Cache::forget($this->controlCacheKey());

        $this->writeProgress([
            'status' => 'completed',
            'message' => $completionMessage,
            'records' => $count,
            'result_limit' => $limit,
            'search_id' => $search->id,
            'completion_reason' => $hitResultLimit ? 'limit_reached' : 'locations_exhausted',
            'current_query_number' => count($queries),
            'total_queries' => count($queries),
            'redirect_url' => route('google-maps-data-extractor.show', $search, false),
        ]);
    }

    private function waitIfPaused(GoogleMapsExtractorSearch $search): ?string
    {
        $action = $this->readControlAction();

        if ($action === 'pause') {
            $this->writeProgress([
                'status' => 'paused',
                'message' => 'Extraction paused. Waiting for resume...',
                'records' => $search->results()->count(),
            ]);
        }

        while ($action === 'pause') {
            if ($this->shouldAbortExtraction($search)) {
                return null;
            }

            sleep(2);
            $action = $this->readControlAction();
        }

        return $action;
    }

    private function shouldAbortExtraction(GoogleMapsExtractorSearch $search): bool
    {
        $search->refresh();

        if (in_array($search->status, ['completed', 'cancelled', 'stopped', 'failed'], true)) {
            return true;
        }

        return ! Cache::has($this->stateCacheKey());
    }

    private function finishControlled(GoogleMapsExtractorSearch $search, string $action): void
    {
        if ($action === 'cancel') {
            $search->results()->delete();
            $search->update([
                'status' => 'cancelled',
                'results_count' => 0,
                'completed_at' => now(),
            ]);
            $this->writeProgress([
                'status' => 'cancelled',
                'message' => 'Extraction cancelled. Fetched records were discarded.',
                'records' => 0,
                'result_limit' => (int) $search->result_limit,
                'redirect_url' => route('google-maps-data-extractor.index', [], false),
            ]);
        } else {
            $count = $search->results()->count();
            $search->update([
                'status' => 'stopped',
                'results_count' => $count,
                'completed_at' => now(),
            ]);
            $this->writeProgress([
                'status' => 'stopped',
                'message' => "Extraction stopped. Kept {$count} fetched record(s).",
                'records' => $count,
                'result_limit' => (int) $search->result_limit,
                'search_id' => $search->id,
                'redirect_url' => route('google-maps-data-extractor.show', $search, false),
            ]);
        }

        Cache::forget($this->stateCacheKey());
        Cache::forget($this->controlCacheKey());
    }

    private function persistRecords(GoogleMapsExtractorSearch $search, array $records, int $limit): void
    {
        foreach ($records as $record) {
            if ($limit > 0 && $search->results()->count() >= $limit) {
                return;
            }

            if (empty($record['name'])) {
                continue;
            }

            $query = $search->results();

            if (! empty($record['website'])) {
                $query->where('website', $record['website']);
            } elseif (! empty($record['maps_url'])) {
                $query->where('maps_url', $record['maps_url']);
            } else {
                $query->where('name', $record['name'])
                    ->where('address', $record['address'] ?? null);
            }

            $existing = $query->first();

            if ($existing) {
                $existing->fill(array_filter($record, fn ($value) => $value !== null && $value !== []))->save();
                continue;
            }

            if ($limit > 0 && $search->results()->count() >= $limit) {
                return;
            }

            GoogleMapsExtractorResult::create(array_merge($record, [
                'search_id' => $search->id,
            ]));
        }
    }

    private function trimResultsToLimit(GoogleMapsExtractorSearch $search, int $limit): void
    {
        if ($limit <= 0) {
            return;
        }

        $excessCount = max(0, $search->results()->count() - $limit);

        if ($excessCount === 0) {
            return;
        }

        $idsToDelete = $search->results()
            ->orderByDesc('id')
            ->limit($excessCount)
            ->pluck('id');

        if ($idsToDelete->isNotEmpty()) {
            GoogleMapsExtractorResult::whereIn('id', $idsToDelete)->delete();
        }
    }

    private function storeState(array $state, int $queryIndex, int $sourceIndex, int $emptyLocalPages): void
    {
        $state['query_index'] = $queryIndex;
        $state['source_index'] = $sourceIndex;
        $state['consecutive_empty_local_pages'] = $emptyLocalPages;
        Cache::put($this->stateCacheKey(), $state, now()->addHours(6));
    }

    private function writeProgress(array $progress): void
    {
        $key = $this->progressCacheKey();
        $existing = Cache::get($key, [
            'status' => 'running',
            'records' => 0,
            'logs' => [],
        ]);
        $message = trim((string) ($progress['message'] ?? ''));
        $logs = $existing['logs'] ?? [];

        if ($message !== '') {
            $limitedMessage = Str::limit($message, 220, '...');
            $lastLog = end($logs);
            $lastMessage = is_string($lastLog) ? Str::after($lastLog, ' - ') : '';

            if ($lastMessage !== $limitedMessage) {
                $logs[] = now()->format('H:i:s') . ' - ' . $limitedMessage;
                $logs = array_slice($logs, -12);
            }
        }

        Cache::put($key, array_merge($existing, $progress, [
            'logs' => $logs,
            'updated_at' => now()->toDateTimeString(),
        ]), now()->addHours(6));
    }

    private function readControlAction(): ?string
    {
        $action = Cache::get($this->controlCacheKey());
        return in_array($action, ['pause', 'stop', 'cancel'], true) ? $action : null;
    }

    private function progressCacheKey(): string
    {
        return 'google_maps_extractor_progress:' . $this->progressToken;
    }

    private function stateCacheKey(): string
    {
        return 'google_maps_extractor_state:' . $this->progressToken;
    }

    private function controlCacheKey(): string
    {
        return 'google_maps_extractor_control:' . $this->progressToken;
    }
}
