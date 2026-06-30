<?php

namespace App\Http\Controllers;

use App\Jobs\RunGoogleMapsEnrichmentJob;
use App\Jobs\RunGoogleMapsExtractionJob;
use App\Models\GoogleMapsExtractorResult;
use App\Models\GoogleMapsExtractorSearch;
use App\Services\GoogleMapsScraperService;
use App\Services\Support\QueueWorkerWatchdog;
use App\Services\WebsiteContactExtractorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GoogleMapsDataExtractorController extends Controller
{
    public function index()
    {
        $searches = GoogleMapsExtractorSearch::latest()->limit(15)->get();

        return view('tools.google-maps-data-extractor', [
            'searches' => $searches,
            'activeSearch' => null,
            'results' => collect(),
        ]);
    }

    public function search(Request $request, GoogleMapsScraperService $scraper): RedirectResponse
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $validated = $this->validateExtractionRequest($request, [
            'query' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_country' => ['nullable', 'string', 'max:100'],
            'location_state' => ['required', 'string', 'max:100'],
            'location_city_payload' => ['nullable', 'string'],
            'progress_token' => ['nullable', 'string', 'max:80'],
            'location_city' => ['nullable', 'array'],
            'location_city.*' => ['nullable', 'string', 'max:100'],
            'limit' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);
        $scraperLocation = $this->buildStructuredLocation($validated);
        $displayLocation = $this->buildStructuredLocation($validated, true);
        $progressToken = $validated['progress_token'] ?? null;

        if ($progressToken) {
            $this->writeProgress($progressToken, [
                'status' => 'running',
                'message' => 'Starting extraction...',
                'records' => 0,
                'logs' => ['Starting extraction...'],
            ]);
        }

        $search = GoogleMapsExtractorSearch::create([
            'user_id' => Auth::id(),
            'query' => $validated['query'],
            'location' => $displayLocation,
            'result_limit' => $validated['limit'],
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $records = $scraper->search(
                $validated['query'],
                $scraperLocation,
                (int) $validated['limit'],
                $progressToken ? fn (array $progress) => $this->handleScraperProgress($progressToken, $search, $progress) : null,
                $progressToken ? fn (): ?string => $this->readControlAction($progressToken) : null
            );

            $this->persistExtractorRecords($search, $records);
            $controlAction = $progressToken ? $this->readControlAction($progressToken) : null;

            if ($controlAction === 'cancel') {
                $search->results()->delete();
                $search->update([
                    'status' => 'cancelled',
                    'results_count' => 0,
                    'completed_at' => now(),
                ]);

                if ($progressToken) {
                    $this->writeProgress($progressToken, [
                        'status' => 'cancelled',
                        'message' => 'Extraction cancelled. Fetched records were discarded.',
                        'records' => 0,
                    ]);
                }

                return redirect()
                    ->route('google-maps-data-extractor.index')
                    ->with('success', 'Extraction cancelled and fetched records were discarded.');
            }

            if ($controlAction === 'stop') {
                $search->update([
                    'status' => 'stopped',
                    'results_count' => $search->results()->count(),
                    'completed_at' => now(),
                ]);

                if ($progressToken) {
                    $this->writeProgress($progressToken, [
                        'status' => 'stopped',
                        'message' => "Extraction stopped. Kept {$search->results_count} fetched record(s).",
                        'records' => $search->results_count,
                    ]);
                }

                return redirect()
                    ->route('google-maps-data-extractor.show', $search)
                    ->with('success', "Extraction stopped. Kept {$search->results_count} fetched record(s).");
            }

            $search->update([
                'status' => 'completed',
                'results_count' => $search->results()->count(),
                'completed_at' => now(),
            ]);

            if ($progressToken) {
                $this->writeProgress($progressToken, [
                    'status' => 'completed',
                    'message' => "Extraction completed with {$search->results_count} result(s).",
                    'records' => $search->results_count,
                ]);
            }
        } catch (Throwable $exception) {
            $search->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            if ($progressToken) {
                $this->writeProgress($progressToken, [
                    'status' => 'failed',
                    'message' => 'Extractor failed: ' . $exception->getMessage(),
                ]);
            }

            return redirect()
                ->route('google-maps-data-extractor.show', $search)
                ->with('error', 'Extractor failed: ' . $exception->getMessage());
        }

        $message = $search->results_count > 0
            ? "Extraction completed with {$search->results_count} result(s)."
            : 'Extraction completed, but no results were found. Try a more specific query/location or a smaller test run.';

        return redirect()
            ->route('google-maps-data-extractor.show', $search)
            ->with('success', $message);
    }

    public function start(Request $request, GoogleMapsScraperService $scraper)
    {
        $validated = $this->validateExtractionRequest($request, [
            'query' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_country' => ['nullable', 'string', 'max:100'],
            'location_state' => ['required', 'string', 'max:100'],
            'location_city_payload' => ['nullable', 'string'],
            'progress_token' => ['required', 'string', 'max:80'],
            'location_city' => ['nullable', 'array'],
            'location_city.*' => ['nullable', 'string', 'max:100'],
            'limit' => ['required'],
        ]);

        $progressToken = $validated['progress_token'];
        abort_if(! preg_match('/^[A-Za-z0-9_-]{16,80}$/', $progressToken), 422);
        $limitInput = (string) $validated['limit'];
        abort_if($limitInput !== 'all' && ! ctype_digit($limitInput), 422);
        $requestedLimit = $limitInput === 'all'
            ? 0
            : (int) $limitInput;
        abort_if($requestedLimit !== 0 && ($requestedLimit < 1 || $requestedLimit > 5000), 422);

        $scraperLocation = $this->buildStructuredLocation($validated);
        $displayLocation = $this->buildStructuredLocation($validated, true);
        $searchQueries = $scraper->buildSearchPlan($validated['query'], $scraperLocation, $requestedLimit > 0 ? $requestedLimit : 5000);

        $search = GoogleMapsExtractorSearch::create([
            'user_id' => Auth::id(),
            'query' => $validated['query'],
            'location' => $displayLocation,
            'result_limit' => $requestedLimit,
            'status' => 'running',
            'started_at' => now(),
        ]);

        Cache::put($this->stateCacheKey($progressToken), [
            'search_id' => $search->id,
            'limit' => $requestedLimit,
            'queries' => $searchQueries,
            'query_index' => 0,
            'source_index' => 0,
            'consecutive_empty_local_pages' => 0,
            'status' => 'running',
        ], now()->addHours(6));

        $this->writeProgress($progressToken, [
            'status' => 'queued',
            'message' => 'Extraction queued with ' . count($searchQueries) . ' city/search attempt(s). You can leave this page while the worker runs.',
            'records' => 0,
            'result_limit' => $requestedLimit,
            'search_id' => $search->id,
            'total_queries' => count($searchQueries),
            'current_query_number' => 0,
            'logs' => [],
        ]);

        if ($request->hasSession()) {
            $request->session()->save();
        }

        RunGoogleMapsExtractionJob::dispatch($progressToken)->onQueue('google-maps-extractor');
        QueueWorkerWatchdog::ensureWatchdogDaemonRunning();

        return response()->json([
            'ok' => true,
            'queued' => true,
            'worker_started' => QueueWorkerWatchdog::isRunning('google-maps-extractor'),
            'watchdog_running' => QueueWorkerWatchdog::isWatchdogDaemonRunning(),
            'search_id' => $search->id,
            'token' => $progressToken,
            'total_queries' => count($searchQueries),
        ]);
    }

    public function ensureWorker()
    {
        QueueWorkerWatchdog::ensureWatchdogDaemonRunning();
        QueueWorkerWatchdog::ensureRunning('google-maps-extractor');

        return response()->json([
            'ok' => true,
            'worker_started' => QueueWorkerWatchdog::isRunning('google-maps-extractor'),
            'watchdog_running' => QueueWorkerWatchdog::isWatchdogDaemonRunning(),
            'worker_running' => QueueWorkerWatchdog::isRunning('google-maps-extractor'),
        ]);
    }

    public function process(Request $request, GoogleMapsScraperService $scraper)
    {
        $validated = $request->validate([
            'progress_token' => ['required', 'string', 'max:80'],
        ]);
        $token = $validated['progress_token'];
        abort_if(! preg_match('/^[A-Za-z0-9_-]{16,80}$/', $token), 422);

        $state = Cache::get($this->stateCacheKey($token));

        if (! is_array($state) || empty($state['search_id'])) {
            return response()->json([
                'complete' => true,
                'message' => 'Extraction state was not found.',
            ], 404);
        }

        $search = GoogleMapsExtractorSearch::findOrFail($state['search_id']);
        $controlAction = $this->readControlAction($token);

        if ($controlAction === 'pause') {
            $this->writeProgress($token, [
                'status' => 'paused',
                'message' => 'Extraction paused.',
                'records' => $search->results()->count(),
            ]);

            return response()->json([
                'paused' => true,
                'complete' => false,
                'records' => $search->results()->count(),
            ]);
        }

        if (in_array($controlAction, ['stop', 'cancel'], true)) {
            return $this->finishControlledExtraction($token, $search, $controlAction);
        }

        $queries = $state['queries'] ?? [];
        $queryIndex = (int) ($state['query_index'] ?? 0);
        $limit = (int) ($state['limit'] ?? $search->result_limit);

        if ($search->results()->count() >= $limit || $queryIndex >= count($queries)) {
            return $this->completeAjaxExtraction($token, $search);
        }

        $searchQuery = $queries[$queryIndex];
        $urls = $scraper->buildSearchUrlPlan($searchQuery);
        $sourceIndex = (int) ($state['source_index'] ?? 0);

        if ($sourceIndex >= count($urls)) {
            $state['query_index'] = $queryIndex + 1;
            $state['source_index'] = 0;
            $state['consecutive_empty_local_pages'] = 0;
            Cache::put($this->stateCacheKey($token), $state, now()->addHours(6));

            $this->writeProgress($token, [
                'message' => 'Moving to next city/search.',
                'records' => $search->results()->count(),
                'current_query_number' => $state['query_index'],
                'total_queries' => count($queries),
            ]);

            return response()->json([
                'complete' => false,
                'records' => $search->results()->count(),
                'message' => 'Moving to next city/search.',
            ]);
        }

        $beforeCount = $search->results()->count();
        $sourceUrl = $urls[$sourceIndex];
        $payload = $scraper->scrapeSearchUrl($searchQuery, $sourceUrl);
        $this->persistExtractorRecords($search, $payload['records'] ?? []);
        $afterCount = $search->results()->count();
        $newUnique = max(0, $afterCount - $beforeCount);

        if (($sourceUrl['source'] ?? '') === 'google_local') {
            $state['consecutive_empty_local_pages'] = $newUnique > 0
                ? 0
                : ((int) ($state['consecutive_empty_local_pages'] ?? 0) + 1);

            if ($state['consecutive_empty_local_pages'] >= 2) {
                $state['query_index'] = $queryIndex + 1;
                $state['source_index'] = 0;
                $state['consecutive_empty_local_pages'] = 0;
            } else {
                $state['source_index'] = $sourceIndex + 1;
            }
        } else {
            $state['source_index'] = $sourceIndex + 1;
        }

        $search->update(['results_count' => $afterCount]);
        Cache::put($this->stateCacheKey($token), $state, now()->addHours(6));

        $message = ($payload['message'] ?? 'Processed search step.') . ' New unique: ' . $newUnique . '. Total saved: ' . $afterCount . '.';
        $this->writeProgress($token, [
            'status' => 'running',
            'message' => $message,
            'records' => $afterCount,
            'current_query_number' => $queryIndex + 1,
            'total_queries' => count($queries),
            'current_page' => $sourceUrl['page'] ?? 1,
            'current_source' => $sourceUrl['source'] ?? null,
        ]);

        if ($afterCount >= $limit) {
            return $this->completeAjaxExtraction($token, $search);
        }

        return response()->json([
            'complete' => false,
            'records' => $afterCount,
            'message' => $message,
            'delay_ms' => (($sourceUrl['source'] ?? '') === 'google_local') ? 2500 : 1500,
        ]);
    }

    public function progress(string $token)
    {
        abort_if(! preg_match('/^[A-Za-z0-9_-]{16,80}$/', $token), 404);

        return response()->json(Cache::get($this->progressCacheKey($token), [
            'status' => 'pending',
            'message' => 'Waiting for extraction to start...',
            'records' => 0,
            'logs' => [],
        ]));
    }

    public function control(Request $request, string $token)
    {
        abort_if(! preg_match('/^[A-Za-z0-9_-]{16,80}$/', $token), 404);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:pause,resume,stop,cancel'],
        ]);
        $action = $validated['action'];

        if ($request->hasSession()) {
            $request->session()->save();
        }

        Cache::put($this->controlCacheKey($token), $action, now()->addHours(2));

        if ($action === 'resume') {
            Cache::forget($this->controlCacheKey($token));
            $this->writeProgress($token, [
                'status' => 'running',
                'message' => 'Resume requested. Worker will continue.',
            ]);

            return response()->json([
                'ok' => true,
                'action' => $action,
            ]);
        }

        if ($action === 'pause') {
            $this->writeProgress($token, [
                'status' => 'paused',
                'message' => 'Pause requested. Worker will pause after the current step.',
            ]);

            return response()->json([
                'ok' => true,
                'action' => $action,
            ]);
        }

        if (in_array($action, ['stop', 'cancel'], true)) {
            $extractState = Cache::get($this->stateCacheKey($token));

            if (is_array($extractState) && ! empty($extractState['search_id'])) {
                $search = GoogleMapsExtractorSearch::find($extractState['search_id']);

                if ($search && ! in_array($search->status, ['completed', 'cancelled', 'stopped', 'failed'], true)) {
                    return $this->finishControlledExtraction($token, $search, $action);
                }
            }

            $enrichmentState = Cache::get($this->enrichmentStateCacheKey($token));

            if (is_array($enrichmentState) && ! empty($enrichmentState['search_id'])) {
                return $this->finishControlledEnrichment($token, $enrichmentState, $action);
            }

            $this->writeProgress($token, [
                'status' => $action === 'cancel' ? 'cancelled' : 'stopped',
                'message' => match ($action) {
                    'stop' => 'Stop requested. Waiting for worker to finish the current step.',
                    'cancel' => 'Cancel requested. Waiting for worker to finish the current step.',
                },
            ]);
        }

        return response()->json([
            'ok' => true,
            'action' => $action,
        ]);
    }

    public function show(GoogleMapsExtractorSearch $search)
    {
        $this->trimSearchResultsToLimit($search);
        $search->refresh();

        $searches = GoogleMapsExtractorSearch::latest()->limit(15)->get();
        $results = $search->results()
            ->latest()
            ->get()
            ->map(fn ($result) => $this->sanitizeResultForDisplay($result));

        return view('tools.google-maps-data-extractor', [
            'searches' => $searches,
            'activeSearch' => $search,
            'results' => $results,
        ]);
    }

    public function destroy(GoogleMapsExtractorSearch $search): RedirectResponse
    {
        $search->delete();

        return redirect()
            ->route('google-maps-data-extractor.index')
            ->with('success', 'Extractor search and its saved leads were deleted.');
    }

    public function enrich(GoogleMapsExtractorSearch $search, WebsiteContactExtractorService $contactExtractor): RedirectResponse
    {
        set_time_limit(240);

        $results = $search->results()
            ->whereNotNull('website')
            ->where(function ($query) {
                $query->whereNull('email')
                    ->orWhereNull('phone')
                    ->orWhereNull('social_links');
            })
            ->limit(20)
            ->get();

        $updated = 0;

        foreach ($results as $result) {
            $contactData = $contactExtractor->extract($result->website);
            $emails = $contactData['emails'] ?? [];
            $phones = $contactData['phones'] ?? [];
            $socialLinks = $this->normalizeSocialLinks(array_merge(
                $result->social_links ?? [],
                $contactData['social_links'] ?? []
            ));
            $email = $this->cleanEmail($result->email ?: ($emails[0] ?? null));

            $result->update([
                'email' => $email,
                'phone' => $result->phone ?: ($phones[0] ?? null),
                'social_links' => ! empty($socialLinks) ? $socialLinks : $result->social_links,
            ]);

            $updated++;
        }

        $message = $updated > 0
            ? "Website enrichment completed for {$updated} result(s). Run it again to continue enriching more rows."
            : 'No website rows need enrichment for this search.';

        return redirect()
            ->route('google-maps-data-extractor.show', $search)
            ->with('success', $message);
    }

    public function enrichBatch(Request $request, GoogleMapsExtractorSearch $search)
    {
        $validated = $request->validate([
            'progress_token' => ['required', 'string', 'max:80'],
            'mode' => ['nullable', 'string', 'in:pending,all'],
        ]);

        $progressToken = $validated['progress_token'];
        abort_if(! preg_match('/^[A-Za-z0-9_-]{16,80}$/', $progressToken), 422);

        $mode = $validated['mode'] ?? 'pending';
        $enrichmentStateKey = $this->enrichmentStateCacheKey($progressToken);
        $existingState = Cache::get($enrichmentStateKey);

        if (is_array($existingState)) {
            return response()->json([
                'ok' => true,
                'queued' => true,
                'total' => (int) ($existingState['total'] ?? 0),
                'processed' => (int) ($existingState['processed'] ?? 0),
                'message' => 'Website enrichment is already queued or running.',
            ]);
        }

        $total = $this->enrichmentRowsQuery($search, $mode)->count();
        Cache::put($enrichmentStateKey, [
            'search_id' => $search->id,
            'mode' => $mode,
            'total' => $total,
            'processed' => 0,
            'cursor' => 0,
        ], now()->addHours(6));

        $this->writeProgress($progressToken, [
            'status' => $total > 0 ? 'queued' : 'completed',
            'message' => $total > 0
                ? 'Website enrichment queued for ' . $total . ' website row(s). You can leave this page while the worker runs.'
                : 'No website rows found for enrichment.',
            'records' => 0,
            'current_query_number' => 0,
            'total_queries' => $total,
            'logs' => [],
        ]);

        if ($total > 0) {
            if ($request->hasSession()) {
                $request->session()->save();
            }

            RunGoogleMapsEnrichmentJob::dispatch($progressToken)->onQueue('google-maps-extractor');
            QueueWorkerWatchdog::ensureWatchdogDaemonRunning();
        } else {
            Cache::forget($enrichmentStateKey);
        }

        Log::info('Google Maps extractor enrichment queued', [
            'search_id' => $search->id,
            'mode' => $mode,
            'total' => $total,
        ]);

        return response()->json([
            'ok' => true,
            'queued' => $total > 0,
            'total' => $total,
            'processed' => 0,
            'complete' => $total === 0,
            'message' => $total > 0 ? 'Website enrichment queued.' : 'No website rows found for enrichment.',
        ]);
    }

    public function export(Request $request, GoogleMapsExtractorSearch $search): StreamedResponse
    {
        $filter = $request->query('filter', 'all');
        $fileName = 'google-maps-data-extractor-' . $search->id . '.csv';

        return response()->streamDownload(function () use ($search, $filter): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name',
                'Email',
                'Phone',
                'Address',
                'Website',
                'Social Links',
                'Google Maps URL',
                'Category',
                'Rating',
                'Reviews Count',
                'Source',
            ]);

            $search->results()->orderBy('name')->chunk(100, function ($results) use ($handle, $filter): void {
                foreach ($results as $result) {
                    $result = $this->sanitizeResultForDisplay($result);

                    if (! $this->matchesResultFilter($result, $filter)) {
                        continue;
                    }

                    fputcsv($handle, [
                        $result->name,
                        $result->email,
                        $result->phone,
                        $result->address,
                        $result->website,
                        implode(' | ', $result->social_links ?? []),
                        $result->maps_url,
                        $result->category,
                        $result->rating,
                        $result->reviews_count,
                        $result->source,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function validateExtractionRequest(Request $request, array $rules): array
    {
        if ($request->filled('location_city_payload')) {
            $decodedCities = json_decode((string) $request->input('location_city_payload'), true);

            if (is_array($decodedCities)) {
                $request->merge([
                    'location_city' => array_values(array_filter($decodedCities, 'is_string')),
                ]);
            }
        }

        $request->merge(['location_scope' => 'specific_city']);

        $validated = $request->validate($rules);
        $validated['location_scope'] = 'specific_city';

        $cities = collect($validated['location_city'] ?? [])
            ->map(fn ($city) => trim((string) $city))
            ->filter()
            ->values();

        if ($cities->isEmpty()) {
            throw ValidationException::withMessages([
                'location_city' => 'Select at least one city.',
            ]);
        }

        $validated['location_city'] = $cities->all();

        return $validated;
    }

    private function buildStructuredLocation(array $validated, bool $compact = false): ?string
    {
        $country = trim((string) ($validated['location_country'] ?? ''));
        $state = trim((string) ($validated['location_state'] ?? ''));
        $scope = $validated['location_scope'] ?? 'specific_city';
        $cities = collect($validated['location_city'] ?? [])
            ->map(fn ($city) => trim((string) $city))
            ->filter()
            ->values();
        $zip = trim((string) ($validated['location_zip'] ?? ''));

        if ($state === '' && $country === '') {
            return $validated['location'] ?? null;
        }

        if ($scope === 'specific_city' && $cities->isNotEmpty()) {
            if ($compact) {
                return trim($cities->count() . ' selected cities, ' . $state . ', ' . $country, ' ,');
            }

            return trim($cities->implode(', ') . ', ' . $state . ', ' . $country, ' ,');
        }

        if ($scope === 'specific_zip' && $zip !== '') {
            return trim($zip . ', ' . $state . ', ' . $country, ' ,');
        }

        return trim($state . ', ' . $country, ' ,');
    }

    private function writeProgress(string $token, array $progress): void
    {
        if (! preg_match('/^[A-Za-z0-9_-]{16,80}$/', $token)) {
            return;
        }

        $key = $this->progressCacheKey($token);
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
        ]), now()->addHours(2));
    }

    private function progressCacheKey(string $token): string
    {
        return 'google_maps_extractor_progress:' . $token;
    }

    private function stateCacheKey(string $token): string
    {
        return 'google_maps_extractor_state:' . $token;
    }

    private function enrichmentStateCacheKey(string $token): string
    {
        return 'google_maps_extractor_enrichment_state:' . $token;
    }

    private function controlCacheKey(string $token): string
    {
        return 'google_maps_extractor_control:' . $token;
    }

    private function readControlAction(string $token): ?string
    {
        $action = Cache::get($this->controlCacheKey($token));

        return in_array($action, ['pause', 'stop', 'cancel'], true) ? $action : null;
    }

    private function handleScraperProgress(string $token, GoogleMapsExtractorSearch $search, array $progress): void
    {
        $recordsBatch = $progress['records_batch'] ?? [];
        unset($progress['records_batch']);

        if (is_array($recordsBatch) && ! empty($recordsBatch)) {
            $this->persistExtractorRecords($search, $recordsBatch);
            $count = $search->results()->count();
            $search->update(['results_count' => $count]);
            $progress['records'] = $count;
        }

        $this->writeProgress($token, $progress);
    }

    private function trimSearchResultsToLimit(GoogleMapsExtractorSearch $search): void
    {
        $limit = (int) $search->result_limit;

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
            $search->update(['results_count' => $search->results()->count()]);
        }
    }

    private function enrichmentRowsQuery(GoogleMapsExtractorSearch $search, string $mode = 'pending')
    {
        $query = $search->results()->whereNotNull('website');

        if ($mode === 'all') {
            return $query;
        }

        return $query->where(function ($query) {
                $query->whereNull('email')
                    ->orWhereNull('phone')
                    ->orWhereNull('social_links');
            })
            ->where(function ($query) {
                $query->whereNull('raw_payload')
                    ->orWhere('raw_payload', 'not like', '%"contact_enrichment_attempted_at"%');
            });
    }

    private function completeAjaxExtraction(string $token, GoogleMapsExtractorSearch $search)
    {
        $count = $search->results()->count();
        $search->update([
            'status' => 'completed',
            'results_count' => $count,
            'completed_at' => now(),
        ]);
        Cache::forget($this->stateCacheKey($token));
        Cache::forget($this->controlCacheKey($token));

        $this->writeProgress($token, [
            'status' => 'completed',
            'message' => "Extraction completed with {$count} result(s).",
            'records' => $count,
        ]);

        return response()->json([
            'complete' => true,
            'records' => $count,
            'redirect_url' => route('google-maps-data-extractor.show', $search),
        ]);
    }

    private function finishControlledEnrichment(string $token, array $enrichmentState, string $action)
    {
        $processed = (int) ($enrichmentState['processed'] ?? 0);
        $total = (int) ($enrichmentState['total'] ?? 0);
        $searchId = (int) ($enrichmentState['search_id'] ?? 0);

        Cache::forget($this->enrichmentStateCacheKey($token));
        Cache::forget($this->controlCacheKey($token));

        $status = $action === 'cancel' ? 'cancelled' : 'stopped';
        $message = $action === 'cancel'
            ? 'Website enrichment cancelled. Existing lead data was kept.'
            : 'Website enrichment stopped. Existing lead data was kept.';

        $this->writeProgress($token, [
            'status' => $status,
            'message' => $message,
            'records' => $processed,
            'current_query_number' => $processed,
            'total_queries' => $total,
            'search_id' => $searchId > 0 ? $searchId : null,
            'redirect_url' => $searchId > 0
                ? route('google-maps-data-extractor.show', $searchId, false)
                : route('google-maps-data-extractor.index', [], false),
        ]);

        return response()->json([
            'ok' => true,
            'complete' => true,
            'cancelled' => $action === 'cancel',
            'stopped' => $action === 'stop',
            'records' => $processed,
            'redirect_url' => $searchId > 0
                ? route('google-maps-data-extractor.show', $searchId)
                : route('google-maps-data-extractor.index'),
        ]);
    }

    private function finishControlledExtraction(string $token, GoogleMapsExtractorSearch $search, string $action)
    {
        if ($action === 'cancel') {
            $search->results()->delete();
            $search->update([
                'status' => 'cancelled',
                'results_count' => 0,
                'completed_at' => now(),
            ]);
            Cache::forget($this->stateCacheKey($token));
            Cache::forget($this->controlCacheKey($token));
            $this->writeProgress($token, [
                'status' => 'cancelled',
                'message' => 'Extraction cancelled. Fetched records were discarded.',
                'records' => 0,
                'redirect_url' => route('google-maps-data-extractor.index', [], false),
            ]);

            return response()->json([
                'complete' => true,
                'cancelled' => true,
                'records' => 0,
                'redirect_url' => route('google-maps-data-extractor.index'),
            ]);
        }

        $count = $search->results()->count();
        $search->update([
            'status' => 'stopped',
            'results_count' => $count,
            'completed_at' => now(),
        ]);
        Cache::forget($this->stateCacheKey($token));
        Cache::forget($this->controlCacheKey($token));
        $this->writeProgress($token, [
            'status' => 'stopped',
            'message' => "Extraction stopped. Kept {$count} fetched record(s).",
            'records' => $count,
        ]);

        return response()->json([
            'complete' => true,
            'stopped' => true,
            'records' => $count,
            'redirect_url' => route('google-maps-data-extractor.show', $search),
        ]);
    }

    private function persistExtractorRecords(GoogleMapsExtractorSearch $search, array $records): void
    {
        foreach ($records as $record) {
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

            GoogleMapsExtractorResult::create(array_merge($record, [
                'search_id' => $search->id,
            ]));
        }
    }

    private function sanitizeResultForDisplay(GoogleMapsExtractorResult $result): GoogleMapsExtractorResult
    {
        $result->email = $this->cleanEmail($result->email);
        $result->social_links = $this->normalizeSocialLinks($result->social_links ?? []);

        return $result;
    }

    private function cleanEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if (in_array($email, ['user@domain.com', 'test@test.com', 'email@example.com', 'example@example.com'], true)) {
            return null;
        }

        if (in_array($local, ['user', 'test', 'example', 'name', 'email', 'yourname', 'username'], true)) {
            return null;
        }

        if (preg_match('/example\.|domain\.com|wixpress\.com|sentry|sentry-next|localhost|invalid/i', $domain)) {
            return null;
        }

        if (preg_match('/^[a-f0-9]{24,}$/i', $local)) {
            return null;
        }

        return $email;
    }

    private function normalizeSocialLinks(array $links): array
    {
        $normalized = [];

        foreach ($links as $link) {
            $link = rtrim(html_entity_decode((string) $link, ENT_QUOTES), '.,;/"\'');
            $parts = parse_url($link);

            if (! isset($parts['host'])) {
                continue;
            }

            $host = strtolower(preg_replace('/^www\./', '', $parts['host']));

            if (! preg_match('/^(facebook|instagram|linkedin|twitter|x|youtube|tiktok)\.com$/', $host)) {
                continue;
            }

            if (! isset($normalized[$host])) {
                $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
                $normalized[$host] = 'https://' . $host . $path;
            }
        }

        return array_values($normalized);
    }

    private function matchesResultFilter(GoogleMapsExtractorResult $result, string $filter): bool
    {
        $hasEmail = ! empty($result->email);
        $hasPhone = ! empty($result->phone);
        $hasSocial = ! empty($result->social_links);

        return match ($filter) {
            'email' => $hasEmail,
            'phone' => $hasPhone,
            'email_or_phone' => $hasEmail || $hasPhone,
            'social' => $hasSocial,
            default => true,
        };
    }
}
