<?php

namespace App\Jobs;

use App\Models\GoogleMapsExtractorSearch;
use App\Services\WebsiteContactExtractorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunGoogleMapsEnrichmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(private readonly string $progressToken)
    {
        $this->onQueue('google-maps-extractor');
    }

    public function handle(WebsiteContactExtractorService $contactExtractor): void
    {
        $state = Cache::get($this->enrichmentStateCacheKey());

        if (! is_array($state) || empty($state['search_id'])) {
            $this->writeProgress([
                'status' => 'failed',
                'message' => 'Enrichment state was not found for queued job.',
            ]);
            return;
        }

        $search = GoogleMapsExtractorSearch::find($state['search_id']);

        if (! $search) {
            $this->writeProgress([
                'status' => 'failed',
                'message' => 'Enrichment search record was not found.',
            ]);
            return;
        }

        $mode = $state['mode'] ?? 'pending';
        $total = (int) ($state['total'] ?? $this->enrichmentRowsQuery($search, $mode)->count());
        $processed = (int) ($state['processed'] ?? 0);
        $cursor = (int) ($state['cursor'] ?? 0);

        Log::info('Google Maps extractor queued enrichment started', [
            'search_id' => $search->id,
            'mode' => $mode,
            'total' => $total,
            'cursor' => $cursor,
        ]);

        while ($processed < $total) {
            if (! Cache::has($this->enrichmentStateCacheKey())) {
                return;
            }

            $controlAction = $this->waitIfPaused($processed, $total);

            if (! Cache::has($this->enrichmentStateCacheKey())) {
                return;
            }

            if (in_array($controlAction, ['stop', 'cancel'], true)) {
                $this->finishControlled($processed, $total, $controlAction);
                return;
            }

            $result = $this->enrichmentRowsQuery($search, $mode)
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->first();

            if (! $result) {
                break;
            }

            $cursor = $result->id;
            $this->writeProgress([
                'status' => 'running',
                'message' => 'Enriching ' . $result->name . ' - ' . $result->website,
                'records' => $processed,
                'current_query' => $result->website,
                'current_query_number' => $processed,
                'total_queries' => $total,
            ]);

            $contactData = $contactExtractor->extract($result->website, true);
            $emails = $contactData['emails'] ?? [];
            $phones = $contactData['phones'] ?? [];
            $socialLinks = $this->normalizeSocialLinks(array_merge(
                $result->social_links ?? [],
                $contactData['social_links'] ?? []
            ));
            $email = $this->cleanEmail($result->email ?: ($emails[0] ?? null));
            $rawPayload = $result->raw_payload ?? [];
            $rawPayload['contact_enrichment_attempted_at'] = now()->toDateTimeString();

            $result->update([
                'email' => $email,
                'phone' => $result->phone ?: ($phones[0] ?? null),
                'social_links' => ! empty($socialLinks) ? $socialLinks : $result->social_links,
                'raw_payload' => $rawPayload,
            ]);

            $processed++;
            $this->storeState($state, $processed, $cursor);
            $this->writeProgress([
                'status' => 'running',
                'message' => 'Enriched ' . $result->name . ' - ' . $result->website,
                'records' => $processed,
                'current_query_number' => $processed,
                'total_queries' => $total,
            ]);

            Log::info('Google Maps extractor queued enrichment row processed', [
                'search_id' => $search->id,
                'result_id' => $result->id,
                'name' => $result->name,
                'website' => $result->website,
                'has_email' => (bool) $email,
                'has_phone' => (bool) ($result->phone ?: ($phones[0] ?? null)),
                'social_count' => count($socialLinks),
            ]);

            sleep(1);

            if (! Cache::has($this->enrichmentStateCacheKey())) {
                return;
            }

            $controlAction = $this->readControlAction();
            if (in_array($controlAction, ['stop', 'cancel'], true)) {
                $this->finishControlled($processed, $total, $controlAction);
                return;
            }
        }

        Cache::forget($this->enrichmentStateCacheKey());
        Cache::forget($this->controlCacheKey());
        $this->writeProgress([
            'status' => 'completed',
            'message' => "Website enrichment completed. Processed {$processed} website row(s).",
            'records' => $processed,
            'search_id' => $search->id,
            'current_query_number' => $processed,
            'total_queries' => $total,
            'redirect_url' => route('google-maps-data-extractor.show', $search, false),
        ]);

        Log::info('Google Maps extractor queued enrichment completed', [
            'search_id' => $search->id,
            'mode' => $mode,
            'processed' => $processed,
            'total' => $total,
        ]);
    }

    private function waitIfPaused(int $processed, int $total): ?string
    {
        $action = $this->readControlAction();

        if ($action === 'pause') {
            $this->writeProgress([
                'status' => 'paused',
                'message' => 'Website enrichment paused. Waiting for resume...',
                'records' => $processed,
                'current_query_number' => $processed,
                'total_queries' => $total,
            ]);
        }

        while ($action === 'pause') {
            if (! Cache::has($this->enrichmentStateCacheKey())) {
                return null;
            }

            sleep(2);
            $action = $this->readControlAction();
        }

        return $action;
    }

    private function finishControlled(int $processed, int $total, string $action): void
    {
        $this->writeProgress([
            'status' => $action === 'cancel' ? 'cancelled' : 'stopped',
            'message' => $action === 'cancel'
                ? 'Website enrichment cancelled. Existing lead data was kept.'
                : 'Website enrichment stopped. Existing lead data was kept.',
            'records' => $processed,
            'current_query_number' => $processed,
            'total_queries' => $total,
        ]);
        Cache::forget($this->enrichmentStateCacheKey());
        Cache::forget($this->controlCacheKey());
    }

    private function storeState(array $state, int $processed, int $cursor): void
    {
        $state['processed'] = $processed;
        $state['cursor'] = $cursor;
        Cache::put($this->enrichmentStateCacheKey(), $state, now()->addHours(6));
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

    private function cleanEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if (in_array($email, ['user@domain.com', 'test@test.com', 'email@example.com', 'example@example.com'], true)) {
            return null;
        }

        if (in_array($local, ['user', 'test', 'example', 'name', 'email', 'yourname', 'username'], true)) {
            return null;
        }

        if (preg_match('/example\.|domain\.com|wixpress\.com|sentry|sentry-next|localhost|invalid/i', $domain)) {
            return null;
        }

        return preg_match('/^[a-f0-9]{24,}$/i', $local) ? null : $email;
    }

    private function normalizeSocialLinks(array $links): array
    {
        $platformLinks = [];

        foreach ($links as $link) {
            $link = trim((string) $link);

            if ($link === '') {
                continue;
            }

            $host = parse_url($link, PHP_URL_HOST) ?: $link;
            $host = strtolower(preg_replace('/^www\./', '', $host));
            $platform = match (true) {
                str_contains($host, 'facebook.com') => 'facebook',
                str_contains($host, 'instagram.com') => 'instagram',
                str_contains($host, 'linkedin.com') => 'linkedin',
                str_contains($host, 'twitter.com'), str_contains($host, 'x.com') => 'twitter',
                str_contains($host, 'youtube.com'), str_contains($host, 'youtu.be') => 'youtube',
                default => $host,
            };

            $platformLinks[$platform] ??= $link;
        }

        return array_values($platformLinks);
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

    private function enrichmentStateCacheKey(): string
    {
        return 'google_maps_extractor_enrichment_state:' . $this->progressToken;
    }

    private function controlCacheKey(): string
    {
        return 'google_maps_extractor_control:' . $this->progressToken;
    }
}
