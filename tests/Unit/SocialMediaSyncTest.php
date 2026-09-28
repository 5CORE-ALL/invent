<?php

namespace Tests\Unit;

use App\Jobs\SyncSocialMediaAccount;
use App\Models\SocialMediaAccount;
use App\Models\SocialMediaKpiTarget;
use App\Models\SocialMediaNormalizedMetric;
use App\Models\SocialMediaPost;
use App\Models\SocialMediaSyncLog;
use App\SocialMedia\SocialMediaReportService;
use App\SocialMedia\Platforms\FacebookService;
use App\SocialMedia\Platforms\XService;
use App\SocialMedia\SocialMediaSyncService;
use App\SocialMedia\SocialMetric;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SocialMediaSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_29_021500_create_social_media_tables.php',
            '--force' => true,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00'));
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tokens_are_encrypted_and_hidden(): void
    {
        $account = SocialMediaAccount::query()->create([
            'platform' => 'facebook',
            'external_account_id' => 'page-1',
            'account_name' => 'Company Page',
            'access_token' => 'plain-token',
            'refresh_token' => 'plain-refresh',
            'status' => 'connected',
        ]);

        $this->assertSame('plain-token', $account->fresh()->access_token);
        $this->assertArrayNotHasKey('access_token', $account->toArray());
        $this->assertArrayNotHasKey('refresh_token', $account->toArray());
        $stored = DB::table('social_media_accounts')->value('access_token');
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('plain-token', $stored);
    }

    public function test_facebook_pagination_and_duplicate_protection(): void
    {
        $account = $this->account('facebook', '99');
        Http::fake(fn ($request) => $this->facebookResponse($request->url(), 10));

        $service = app(SocialMediaSyncService::class);
        $service->sync($account);
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(fn ($request) => $this->facebookResponse($request->url(), 25));
        $service->sync($account->fresh());

        $this->assertSame(2, SocialMediaPost::query()->count());
        $this->assertSame(1, SocialMediaNormalizedMetric::query()
            ->where('metric_name', 'followers')
            ->where('social_media_post_id', 0)
            ->where('availability', 'available')
            ->count());
        $followers = SocialMediaNormalizedMetric::query()
            ->where('metric_name', 'followers')
            ->where('social_media_post_id', 0)
            ->where('availability', 'available')
            ->first();
        $this->assertSame(25.0, (float) $followers->metric_value);
        $this->assertSame('success', $account->fresh()->last_sync_status);
    }

    public function test_missing_x_impressions_are_not_stored_as_zero(): void
    {
        $account = new SocialMediaAccount([
            'platform' => 'x',
            'external_account_id' => '55',
            'account_name' => 'Company',
            'access_token' => 'token',
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/tweets')) {
                return Http::response(['data' => [[
                    'id' => '9',
                    'text' => 'Update',
                    'created_at' => '2026-09-28T00:00:00Z',
                    'public_metrics' => [
                        'like_count' => 4,
                        'reply_count' => 1,
                        'retweet_count' => 0,
                        'bookmark_count' => 2,
                    ],
                ]]]);
            }

            return Http::response(['data' => [
                'name' => 'Company',
                'username' => 'company',
                'public_metrics' => ['followers_count' => 80, 'following_count' => 3],
            ]]);
        });

        $payload = app(XService::class)->fetch($account, now()->subDays(3));
        $metrics = collect($payload['posts'][0]['metrics'])->keyBy(fn (SocialMetric $metric) => $metric->name);
        $this->assertSame(SocialMetric::NOT_AVAILABLE, $metrics['impressions']->availability);
        $this->assertNull($metrics['impressions']->value);
        $this->assertSame(4.0, $metrics['likes']->value);
        $this->assertSame(0.0, $metrics['shares']->value);
    }

    public function test_rate_limit_is_recorded_for_retry(): void
    {
        $account = $this->account('facebook', '100');
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'slow down']], 429),
        ]);
        try {
            app(SocialMediaSyncService::class)->sync($account);
            $this->fail('Rate limit should raise.');
        } catch (\App\SocialMedia\Exceptions\SocialMediaRateLimitException) {
            $this->assertSame('rate_limited', $account->fresh()->last_sync_status);
        }
    }

    public function test_expired_token_does_not_call_the_api(): void
    {
        $expired = $this->account('facebook', '101');
        $expired->token_expires_at = now()->subMinute();
        $expired->save();
        Http::fake([
            '*' => function () {
                $this->fail('Expired tokens must not call the API.');
            },
        ]);

        $this->expectException(\App\SocialMedia\Exceptions\SocialMediaAuthException::class);
        app(FacebookService::class)->fetch($expired->fresh(), now()->subDay());
    }

    public function test_api_errors_do_not_keep_tokens(): void
    {
        $leaky = $this->account('facebook', '102');
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'failed access_token=sekrit-value']], 500),
        ]);
        try {
            app(SocialMediaSyncService::class)->sync($leaky);
        } catch (\Throwable) {
        }
        $message = SocialMediaSyncLog::query()->where('social_media_account_id', $leaky->id)->value('error_message');
        $this->assertStringNotContainsString('sekrit-value', (string) $message);
        $this->assertStringContainsString('[redacted]', (string) $message);
    }

    public function test_dashboard_query_uses_stored_history_only(): void
    {
        $account = $this->account('facebook', '200');
        foreach ([
            ['2026-09-01', 'likes', 10, 'available'],
            ['2026-09-01', 'impressions', 100, 'available'],
            ['2026-09-02', 'likes', 30, 'available'],
            ['2026-09-02', 'impressions', 50, 'available'],
            ['2026-09-01', 'website_clicks', null, 'not_available'],
        ] as [$date, $name, $value, $availability]) {
            SocialMediaNormalizedMetric::query()->create([
                'social_media_account_id' => $account->id,
                'social_media_post_id' => 0,
                'metric_date' => $date,
                'metric_name' => $name,
                'metric_value' => $value,
                'availability' => $availability,
            ]);
        }
        SocialMediaKpiTarget::query()->create([
            'metric_name' => 'impressions',
            'target_value' => 200,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);

        $report = app(SocialMediaReportService::class)->build(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-02'),
            []
        );

        $this->assertSame(150.0, (float) $report['cards']['impressions']['value']);
        $this->assertSame(40.0, (float) $report['cards']['engagement']['value']);
        $this->assertEqualsWithDelta(40 / 150, $report['cards']['engagement_rate']['value'], 0.00001);
        $this->assertSame('not_available', $report['cards']['website_clicks']['availability']);
        $this->assertNull($report['cards']['website_clicks']['value']);
        $this->assertSame(75.0, $report['progress']['impressions']['percent']);
        $this->assertSame('In Progress', $report['progress']['impressions']['status']);

        $day = app(SocialMediaReportService::class)->build(
            Carbon::parse('2026-09-02'),
            Carbon::parse('2026-09-02'),
            ['platform' => 'facebook']
        );
        $this->assertSame(50.0, (float) $day['cards']['impressions']['value']);

        $other = app(SocialMediaReportService::class)->build(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-02'),
            ['platform' => 'instagram']
        );
        $this->assertSame('not_available', $other['cards']['impressions']['availability']);
    }

    public function test_job_uses_backoff_and_account_lock(): void
    {
        $job = new SyncSocialMediaAccount(15);
        $this->assertSame('15', $job->uniqueId());
        $this->assertSame([60, 300, 900], $job->backoff());
        $this->assertSame('social-media', $job->queue);
    }

    private function account(string $platform, string $externalId): SocialMediaAccount
    {
        return SocialMediaAccount::query()->create([
            'platform' => $platform,
            'external_account_id' => $externalId,
            'account_name' => 'Company',
            'access_token' => 'token-'.$externalId,
            'status' => 'connected',
            'sync_enabled' => true,
        ]);
    }

    private function facebookResponse(string $url, int $followers)
    {
        if (str_contains($url, '/insights')) {
            $body = ['data' => [[
                'name' => 'page_total_media_view_unique',
                'values' => [['value' => 12, 'end_time' => '2026-09-29T07:00:00+0000']],
            ]]];
        } elseif (str_contains($url, '/posts')) {
            $body = str_contains($url, 'page=2')
                ? ['data' => [[
                    'id' => 'p2',
                    'message' => 'Second',
                    'created_time' => '2026-09-27T00:00:00+0000',
                ]]]
                : [
                    'data' => [[
                        'id' => 'p1',
                        'message' => 'Hello',
                        'created_time' => '2026-09-28T00:00:00+0000',
                        'permalink_url' => 'https://facebook.com/p1',
                        'reactions' => ['summary' => ['total_count' => 2]],
                        'comments' => ['summary' => ['total_count' => 1]],
                    ]],
                    'paging' => ['next' => 'https://graph.facebook.com/v21.0/99/posts?page=2'],
                ];
        } else {
            $body = [
                'name' => 'Company Page',
                'username' => 'company',
                'followers_count' => $followers,
            ];
        }

        return Http::response($body);
    }
}
