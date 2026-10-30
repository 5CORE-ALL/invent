<?php

namespace Tests\Unit;

use App\Services\GoogleShoppingLiveMatchService;
use App\Support\SchedulerLease;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoogleShoppingLiveMatchRunTest extends TestCase
{
    /** @var array<string, array<string, mixed>> grid SBID / SBGT */
    private array $grid = [];

    /** @var array<string, array<string, mixed>> what "Google Ads" holds */
    private array $live = [];

    /** @var list<array{field: string, ids: list<string>}> */
    private array $calls = [];

    /** @var array<string, string> field:id => error text returned by the push */
    private array $failures = [];

    /** @var list<string> field:id that another push (e.g. the page button) is holding */
    private array $busy = [];

    /** @var list<string> field:id that make the whole push call throw */
    private array $throwOn = [];

    /** @var list<int> */
    private array $slept = [];

    private ?\Closure $afterPush = null;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('cache.default', 'array');
        Cache::flush();
    }

    private function campaigns(int $n, bool $bidMismatch = true, bool $bgtMismatch = true): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $id = (string) (1000 + $i);
            $this->grid[$id] = ['sbid' => 0.50, 'sbgt' => 10, 'status' => 'ENABLED', 'name' => "C{$i}"];
            $this->live[$id] = [
                'campaign_id' => $id,
                'live_bid' => $bidMismatch ? 0.20 : 0.50,
                'live_bgt' => $bgtMismatch ? 4 : 10,
            ];
        }
    }

    private function service(): GoogleShoppingLiveMatchService
    {
        $push = function (string $field) {
            return function (array $ids) use ($field): array {
                $this->calls[] = ['field' => $field, 'ids' => $ids];
                foreach ($ids as $id) {
                    if (in_array($field.':'.$id, $this->throwOn, true)) {
                        throw new \RuntimeException('boom '.$id);
                    }
                }
                $lines = [];
                $errors = 0;
                foreach ($ids as $id) {
                    $key = $field.':'.$id;
                    if (in_array($key, $this->busy, true)) {
                        $lines[] = "[BUSY] {$id} ({$id}): another push for this campaign is running — skipped, nothing changed.";

                        continue;
                    }
                    if (isset($this->failures[$key])) {
                        $lines[] = "[ERROR] C ({$id}): ".$this->failures[$key];
                        $errors++;

                        continue;
                    }
                    if ($field === 'bid') {
                        $this->live[$id]['live_bid'] = $this->grid[$id]['sbid'];
                    } else {
                        $this->live[$id]['live_bgt'] = $this->grid[$id]['sbgt'];
                    }
                    $lines[] = "[OK] C ({$id}): pushed";
                }
                if ($this->afterPush) {
                    ($this->afterPush)($field, $ids);
                }

                return ['ok' => $errors === 0, 'message' => $errors ? "{$errors} error(s)" : 'ok', 'output' => implode("\n", $lines)];
            };
        };

        return new GoogleShoppingLiveMatchService([
            'targets' => fn () => $this->grid,
            'pull' => fn () => null,
            'states' => fn (array $ids) => array_intersect_key($this->live, array_flip($ids)),
            'pushBid' => $push('bid'),
            'pushBgt' => $push('bgt'),
            'sleep' => function (int $s) {
                $this->slept[] = $s;
            },
        ]);
    }

    public function test_every_mismatch_is_pushed_and_confirmed_in_chunks(): void
    {
        $this->campaigns(25);

        $stats = $this->service()->run();

        $this->assertNull($stats['error']);
        $this->assertSame(25, $stats['checked']);
        $this->assertSame(25, $stats['bid_mismatch']);
        $this->assertSame(25, $stats['bgt_mismatch']);
        $this->assertSame(25, $stats['bid_pushed']);
        $this->assertSame(25, $stats['bgt_pushed']);
        $this->assertSame(0, $stats['bid_left']);
        $this->assertSame(0, $stats['bgt_left']);
        $this->assertCount(25, $stats['pushed_ids']['bid']);
        $this->assertCount(25, $stats['pushed_ids']['bgt']);
        $this->assertSame([], $stats['failed']);
        foreach ($this->calls as $c) {
            $this->assertLessThanOrEqual(GoogleShoppingLiveMatchService::PUSH_CHUNK, count($c['ids']));
        }
        $this->assertCount(6, $this->calls, '3 bid chunks + 3 bgt chunks (10+10+5)');
        foreach ($this->live as $live) {
            $this->assertEquals(0.50, $live['live_bid']);
            $this->assertEquals(10, $live['live_bgt']);
        }
    }

    public function test_matching_campaigns_are_left_alone(): void
    {
        $this->campaigns(5, false, false);

        $stats = $this->service()->run();

        $this->assertSame([], $this->calls);
        $this->assertSame(0, $stats['bid_mismatch'] + $stats['bgt_mismatch']);
    }

    public function test_one_failing_campaign_or_call_does_not_stop_the_others(): void
    {
        $this->campaigns(25, true, false);
        $this->failures['bid:1003'] = 'INVALID_ARGUMENT: bid below minimum';  // permanent, one campaign
        $this->throwOn = ['bid:1015'];                                         // the whole chunk of 1011-1020 throws

        $stats = $this->service()->run();

        $this->assertNull($stats['error']);
        $this->assertContains('1001', $stats['pushed_ids']['bid']);
        $this->assertContains('1025', $stats['pushed_ids']['bid'], 'last chunk still processed');
        $this->assertNotContains('1003', $stats['pushed_ids']['bid']);
        $this->assertArrayHasKey('bid:1003', $stats['failed']);
        $this->assertSame('INVALID_ARGUMENT: bid below minimum', $stats['failed']['bid:1003']);
        foreach (range(1011, 1020) as $id) {
            $this->assertArrayHasKey('bid:'.$id, $stats['failed'], "chunk that threw is reported failed ({$id})");
        }
        $this->assertSame(25 - 1 - 10, $stats['bid_pushed']);
        $this->assertSame(11, $stats['bid_left']);
        // Failed campaigns are held (existing 3 hour rule), pushed ones are not.
        $this->assertTrue(Cache::has('google-shopping:match-failed:bid:1003'));
        $this->assertFalse(Cache::has('google-shopping:match-failed:bid:1001'));
    }

    public function test_failed_push_hold_is_respected_until_retry_failed(): void
    {
        $this->campaigns(3, true, false);
        Cache::put('google-shopping:match-failed:bid:1002', 'x', now()->addHours(3));

        $stats = $this->service()->run();
        $this->assertSame(1, $stats['waiting']);
        $this->assertSame(['bid:1002'], $stats['held_ids']);
        $this->assertNotContains('1002', array_merge(...array_column($this->calls, 'ids')));

        $this->calls = [];
        $this->service()->run(false, null, true);
        $this->assertContains('1002', array_merge(...array_column($this->calls, 'ids')));
    }

    public function test_temporary_failure_is_retried_once_and_only_if_still_different(): void
    {
        $this->campaigns(3, true, false);
        $this->failures['bid:1002'] = 'RESOURCE_EXHAUSTED 429 rate limit';
        $this->afterPush = function (string $field, array $ids): void {
            // The temporary problem is gone by the time of the retry.
            unset($this->failures['bid:1002']);
        };

        $stats = $this->service()->run();

        $this->assertSame(1, $stats['retried']);
        $this->assertSame([GoogleShoppingLiveMatchService::RETRY_BACKOFF_SECONDS], $this->slept);
        $this->assertContains('1002', $stats['pushed_ids']['bid']);
        $this->assertSame([], $stats['failed']);
        $perCampaign = array_count_values(array_merge(...array_column($this->calls, 'ids')));
        $this->assertSame(2, $perCampaign['1002'], 'pushed twice: the failed attempt and the retry');
        $this->assertSame(1, $perCampaign['1001'], 'a campaign that already succeeded is never pushed again');
        $this->assertSame(1, $perCampaign['1003']);
    }

    public function test_a_campaign_busy_with_a_manual_push_is_skipped_not_failed_held_or_retried(): void
    {
        $this->campaigns(3, true, false);
        $this->busy = ['bid:1002'];

        $stats = $this->service()->run();

        $this->assertArrayHasKey('bid:1002', $stats['skipped']);
        $this->assertStringStartsWith('busy:', $stats['skipped']['bid:1002']);
        $this->assertSame([], $stats['failed']);
        $this->assertFalse(Cache::has('google-shopping:match-failed:bid:1002'), 'busy must not start the 3 hour failed-push hold');
        $this->assertSame(0, $stats['retried']);
        $this->assertSame([], $this->slept);
        $this->assertSame(1, array_count_values(array_merge(...array_column($this->calls, 'ids')))['1002'], 'not pushed again');
        $this->assertContains('1001', $stats['pushed_ids']['bid']);
        $this->assertContains('1003', $stats['pushed_ids']['bid']);
        $this->assertNotContains('1002', $stats['pushed_ids']['bid']);

        // Next scheduled run: the lock is free, the campaign is pushed normally.
        $this->busy = [];
        $this->calls = [];
        $next = $this->service()->run();
        $this->assertContains('1002', $next['pushed_ids']['bid']);
    }

    public function test_permanent_failure_is_not_retried(): void
    {
        $this->campaigns(2, true, false);
        $this->failures['bid:1001'] = 'INVALID_ARGUMENT: bid below minimum';

        $stats = $this->service()->run();

        $this->assertSame(0, $stats['retried']);
        $this->assertSame([], $this->slept);
        $this->assertSame(1, array_count_values(array_merge(...array_column($this->calls, 'ids')))['1001']);
        $this->assertArrayHasKey('bid:1001', $stats['failed']);
    }

    public function test_a_campaign_that_matches_by_push_time_is_not_pushed(): void
    {
        $this->campaigns(12, true, false);
        $service = $this->service();
        // After the first chunk, someone fixes 1011 in Google Ads and its live value is old enough to be re-read.
        $this->afterPush = function (string $field, array $ids) use ($service): void {
            if ($ids[0] !== '1001') {
                return;
            }
            $this->live['1011']['live_bid'] = 0.50;
            $freshAt = new \ReflectionProperty($service, 'freshAt');
            $freshAt->setAccessible(true);
            $freshAt->setValue($service, ['1011' => 0.0, '1012' => microtime(true)]);
        };

        $stats = $service->run();

        $second = $this->calls[1]['ids'];
        $this->assertSame(['1012'], $second, 'the already matching campaign is dropped before its push');
        $this->assertSame([], $stats['failed']);
    }

    public function test_pull_failure_stops_before_any_push(): void
    {
        $this->campaigns(3);
        $service = new GoogleShoppingLiveMatchService([
            'targets' => fn () => $this->grid,
            'pull' => function () {
                throw new \RuntimeException('Google Ads down');
            },
            'states' => fn (array $ids) => $this->live,
            'pushBid' => function () {
                $this->fail('must not push without a fresh live value');
            },
            'pushBgt' => function () {
                $this->fail('must not push without a fresh live value');
            },
        ]);

        $stats = $service->run();

        $this->assertStringContainsString('Live pull failed', (string) $stats['error']);
    }

    public function test_dry_run_pushes_nothing(): void
    {
        $this->campaigns(4);

        $stats = $this->service()->run(true);

        $this->assertSame([], $this->calls);
        $this->assertSame(4, $stats['bid_mismatch']);
    }

    public function test_sbgt_below_one_and_non_enabled_campaigns_are_never_pushed(): void
    {
        $this->campaigns(3, false, true);
        $this->grid['1001']['sbgt'] = 0;               // would pause the campaign: never pushed here
        $this->grid['1002']['status'] = 'PAUSED';

        $this->service()->run();

        $this->assertSame([['field' => 'bgt', 'ids' => ['1003']]], $this->calls);
    }

    public function test_a_run_that_lost_its_lease_stops_pushing(): void
    {
        $dbFile = sys_get_temp_dir().'/lease_match_'.getmypid().'.sqlite';
        @unlink($dbFile);
        touch($dbFile);
        Config::set('database.default', 'lease_match');
        Config::set('database.connections.lease_match', ['driver' => 'sqlite', 'database' => $dbFile, 'prefix' => '', 'foreign_key_constraints' => false]);
        DB::purge('lease_match');
        (require base_path('database/migrations/2026_10_31_020000_create_scheduler_leases_table.php'))->up();

        try {
            $this->campaigns(25, true, false);
            $old = SchedulerLease::acquire('match', 5, 1);
            $this->afterPush = function () use ($old): void {
                // The process stalled past its lease: a newer run took over, and the old one resumes.
                DB::table('scheduler_leases')->update(['expires_at' => now()->subSeconds(10)->toDateTimeString()]);
                SchedulerLease::acquire('match');
                $last = new \ReflectionProperty($old, 'lastBeat');
                $last->setAccessible(true);
                $last->setValue($old, 0);
            };

            $stats = $this->service()->run(false, null, false, $old);

            $this->assertStringContainsString('Lease lost', (string) $stats['error']);
            $this->assertCount(1, $this->calls, 'nothing is pushed after the lease was lost');
            $this->assertTrue($old->lost());
        } finally {
            DB::disconnect('lease_match');
            @unlink($dbFile);
        }
    }
}
