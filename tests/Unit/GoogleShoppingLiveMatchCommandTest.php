<?php

namespace Tests\Unit;

use App\Services\GoogleShoppingLiveMatchService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The scheduled command: lease handling around the match run (overlap, crash recovery, exceptions). */
class GoogleShoppingLiveMatchCommandTest extends TestCase
{
    private int $runs = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('cache.default', 'array');
        Config::set('cron-monitor.enabled', false);
        Cache::flush();

        Config::set('database.default', 'lease_cmd');
        Config::set('database.connections.lease_cmd', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
        DB::purge('lease_cmd');
        (require base_path('database/migrations/2026_10_31_020000_create_scheduler_leases_table.php'))->up();

        $this->runs = 0;
    }

    private function bindMatch(?\Closure $onPull = null): void
    {
        $this->app->instance(GoogleShoppingLiveMatchService::class, new GoogleShoppingLiveMatchService([
            'targets' => fn () => ['1' => ['sbid' => 0.5, 'sbgt' => 10, 'status' => 'ENABLED']],
            'pull' => function () use ($onPull) {
                $this->runs++;
                if ($onPull) {
                    $onPull();
                }
            },
            'states' => fn () => ['1' => ['campaign_id' => '1', 'live_bid' => 0.5, 'live_bgt' => 10]],
        ]));
    }

    private function lease(): object
    {
        return DB::table('scheduler_leases')->where('name', 'google-shopping:match-live-bid-bgt')->first();
    }

    public function test_normal_run_acquires_and_releases_the_lease(): void
    {
        $this->bindMatch();

        $this->assertSame(0, Artisan::call('google-shopping:match-live-bid-bgt'));

        $this->assertSame(1, $this->runs);
        $this->assertNull($this->lease()->owner_token);
        $this->assertSame('success', $this->lease()->last_status);
    }

    public function test_overlapping_run_is_skipped_and_does_not_touch_the_running_one(): void
    {
        $this->bindMatch();
        $holder = \App\Support\SchedulerLease::acquire('google-shopping:match-live-bid-bgt');

        $this->assertSame(0, Artisan::call('google-shopping:match-live-bid-bgt'));

        $this->assertSame(0, $this->runs, 'the overlapping run did no work');
        $this->assertStringContainsString('already running', Artisan::output());
        $this->assertSame($holder->token, $this->lease()->owner_token, 'and did not release the other run\'s lease');
    }

    public function test_run_after_a_crash_recovers_the_expired_lease_and_does_the_work(): void
    {
        $this->bindMatch();
        \App\Support\SchedulerLease::acquire('google-shopping:match-live-bid-bgt'); // crashed run: never released
        DB::table('scheduler_leases')->update(['expires_at' => now()->subMinute()->toDateTimeString()]);

        $this->assertSame(0, Artisan::call('google-shopping:match-live-bid-bgt'));

        $this->assertSame(1, $this->runs, 'recovered without any manual unlock');
        $this->assertNull($this->lease()->owner_token);
        $this->assertSame('success', $this->lease()->last_status);
    }

    public function test_exception_releases_the_lease_so_the_next_run_can_start(): void
    {
        $this->bindMatch(function () {
            throw new \Error('segfault-ish');
        });
        // A thrown pull is a reported failure, not a crash.
        $this->assertSame(1, Artisan::call('google-shopping:match-live-bid-bgt'));
        $this->assertNull($this->lease()->owner_token);
        $this->assertSame('failed', $this->lease()->last_status);

        $this->runs = 0;
        $this->bindMatch();
        $this->assertSame(0, Artisan::call('google-shopping:match-live-bid-bgt'));
        $this->assertSame(1, $this->runs);
    }

    public function test_unexpected_exception_from_the_run_still_releases_the_lease(): void
    {
        $this->app->instance(GoogleShoppingLiveMatchService::class, new GoogleShoppingLiveMatchService([
            'targets' => function () {
                throw new \LogicException('grid query exploded');
            },
        ]));

        try {
            Artisan::call('google-shopping:match-live-bid-bgt');
            $this->fail('exception expected');
        } catch (\LogicException $e) {
            $this->assertSame('grid query exploded', $e->getMessage());
        }

        $this->assertNull($this->lease()->owner_token);
        $this->assertSame('failed', $this->lease()->last_status);
    }
}
