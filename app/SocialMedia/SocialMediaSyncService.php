<?php

namespace App\SocialMedia;

use App\Models\SocialMediaAccount;
use App\Models\SocialMediaAccountMetric;
use App\Models\SocialMediaNormalizedMetric;
use App\Models\SocialMediaPost;
use App\Models\SocialMediaPostMetric;
use App\Models\SocialMediaSyncLog;
use App\SocialMedia\Exceptions\SocialMediaAuthException;
use App\SocialMedia\Exceptions\SocialMediaRateLimitException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SocialMediaSyncService
{
    public function __construct(
        private readonly SocialMediaPlatformRegistry $platforms,
    ) {
    }

    public function sync(SocialMediaAccount $account, string $syncType = 'account'): int
    {
        $lock = Cache::lock('social-media-sync-'.$account->id, 900);
        if (! $lock->get()) {
            return 0;
        }

        $since = $this->since($account);
        $log = SocialMediaSyncLog::query()->create([
            'social_media_account_id' => $account->id,
            'platform' => $account->platform,
            'sync_type' => $syncType,
            'started_at' => now(),
            'status' => 'running',
            'records_processed' => 0,
            'reference' => 'since:'.$since->toDateString(),
        ]);

        try {
            $payload = $this->platforms->get($account->platform)->fetch($account, $since);
            $count = DB::transaction(fn () => $this->store($account, $payload));
            $account->forceFill([
                'last_synced_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_error' => null,
                'status' => 'connected',
                'account_name' => $payload['profile']['name'] ?? $payload['profile']['localizedName'] ?? $account->account_name,
                'username' => $payload['profile']['username'] ?? $account->username,
            ])->save();
            $log->forceFill([
                'completed_at' => now(),
                'status' => 'success',
                'records_processed' => $count,
            ])->save();

            return $count;
        } catch (SocialMediaRateLimitException $e) {
            $this->fail($account, $log, 'rate_limited', $e);
            throw $e;
        } catch (SocialMediaAuthException $e) {
            $account->forceFill(['status' => 'needs_reconnect'])->save();
            $this->fail($account, $log, 'auth_error', $e);
            throw $e;
        } catch (Throwable $e) {
            $this->fail($account, $log, 'failed', $e);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function markExpiredTokens(): int
    {
        return SocialMediaAccount::query()
            ->where('sync_enabled', true)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<', now())
            ->where('status', '!=', 'needs_reconnect')
            ->update([
                'status' => 'needs_reconnect',
                'last_sync_status' => 'auth_error',
                'last_sync_error' => 'Access token expired — reconnect the account.',
            ]);
    }

    private function since(SocialMediaAccount $account): CarbonInterface
    {
        $days = $account->last_synced_at
            ? (int) config('social_media.daily_sync_days', 3)
            : (int) config('social_media.initial_sync_days', 30);
        $metricSince = now()->subDays(max(1, $days));
        $postSince = now()->subDays(max(1, (int) config('social_media.post_lookback_days', 30)));

        return $postSince->lt($metricSince) ? $postSince : $metricSince;
    }

    /**
     * @param  array{profile: array<string, mixed>, days: list<array<string, mixed>>, posts: list<array<string, mixed>>}  $payload
     */
    private function store(SocialMediaAccount $account, array $payload): int
    {
        $count = 0;
        $snapshotDate = now()->toDateString();
        foreach ($payload['days'] as $day) {
            $packed = $this->pack($day['metrics'] ?? []);
            $this->upsertDated(
                SocialMediaAccountMetric::query()->where('social_media_account_id', $account->id),
                $day['date'],
                [
                    'social_media_account_id' => $account->id,
                    'metrics' => $packed,
                    'raw_metrics' => $day['raw'] ?? [],
                ]
            );
            $count += $this->storeNormalized($account->id, 0, $day['date'], $day['metrics'] ?? []);
        }

        foreach ($payload['posts'] as $post) {
            if (($post['external_post_id'] ?? '') === '') {
                continue;
            }
            $record = SocialMediaPost::query()->updateOrCreate(
                [
                    'social_media_account_id' => $account->id,
                    'external_post_id' => (string) $post['external_post_id'],
                ],
                [
                    'content_type' => $post['content_type'] ?? 'post',
                    'title' => $post['title'] ?? null,
                    'published_at' => $post['published_at'] ?? null,
                    'permalink' => $post['permalink'] ?? null,
                    'media_url' => $post['media_url'] ?? null,
                    'status' => 'published',
                    'metadata' => $post['raw'] ?? [],
                ]
            );
            $this->upsertDated(
                SocialMediaPostMetric::query()->where('social_media_post_id', $record->id),
                $snapshotDate,
                [
                    'social_media_post_id' => $record->id,
                    'metrics' => $this->pack($post['metrics'] ?? []),
                    'raw_metrics' => $post['raw'] ?? [],
                ]
            );
            $count += $this->storeNormalized($account->id, $record->id, $snapshotDate, $post['metrics'] ?? []);
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<SocialMetric>  $metrics
     */
    private function storeNormalized(int $accountId, int $postId, string $date, array $metrics): int
    {
        $written = 0;
        foreach ($metrics as $metric) {
            if (! $metric instanceof SocialMetric) {
                continue;
            }
            $this->upsertDated(
                SocialMediaNormalizedMetric::query()
                    ->where('social_media_account_id', $accountId)
                    ->where('social_media_post_id', $postId)
                    ->where('metric_name', $metric->name),
                $date,
                [
                    'social_media_account_id' => $accountId,
                    'social_media_post_id' => $postId,
                    'metric_name' => $metric->name,
                    'metric_value' => $metric->value,
                    'availability' => $metric->availability,
                ]
            );
            $written++;
        }

        return $written;
    }

    /**
     * Match an existing calendar day even when the column is stored with a time.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $values
     */
    private function upsertDated($query, string $date, array $values): void
    {
        $existing = (clone $query)->whereDate('metric_date', $date)->first();
        if ($existing) {
            $existing->fill($values)->save();

            return;
        }
        $query->getModel()->newQuery()->create($values + ['metric_date' => $date]);
    }

    /**
     * @param  list<SocialMetric>  $metrics
     * @return array<string, array{value: float|null, availability: string}>
     */
    private function pack(array $metrics): array
    {
        $packed = [];
        foreach ($metrics as $metric) {
            if ($metric instanceof SocialMetric) {
                $packed[$metric->name] = $metric->toArray();
            }
        }

        return $packed;
    }

    private function fail(SocialMediaAccount $account, SocialMediaSyncLog $log, string $status, Throwable $e): void
    {
        $message = $this->safeMessage($e->getMessage());
        $account->forceFill([
            'last_sync_status' => $status,
            'last_sync_error' => $message,
        ])->save();
        $log->forceFill([
            'completed_at' => now(),
            'status' => $status,
            'error_message' => $message,
        ])->save();
    }

    private function safeMessage(string $message): string
    {
        $message = preg_replace('/access_token=[^&\s]+/i', 'access_token=[redacted]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;

        return Str::limit($message, 500);
    }
}
