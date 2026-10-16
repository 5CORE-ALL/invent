<?php

namespace Tests\Feature;

use App\Http\Controllers\AmazonAdsController;
use App\Services\AmazonAdsLiveBidBgtSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs the real `amazon:ads-live-bid-bgt-sync` command (the 30-minute / 21:50 background job)
 * against an in-memory database and a fake Amazon. No page is opened and no button is clicked:
 * whenever SBID differs from Lbid, or SBGT differs from Lbgt, the sync must pull, push and verify.
 */
class AmazonAdsBackgroundSyncTest extends TestCase
{
    private const DAY = '2026-10-15';

    /** @var array{sp: array{bid: array<string, float>, bgt: array<string, float>}, sb: array{bid: array<string, float>, bgt: array<string, float>}} */
    private array $amazon;

    /** @var list<array{string, string, string, float}> channel, field, campaign, value */
    private array $pushes = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $pdo = DB::connection('sqlite')->getPdo();
        $pdo->sqliteCreateFunction('CHAR_LENGTH', static fn ($s) => mb_strlen((string) $s), 1);
        $pdo->sqliteCreateFunction('LEFT', static fn ($s, $n) => mb_substr((string) $s, 0, (int) $n), 2);

        foreach (['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'] as $table) {
            Schema::create($table, function ($t) {
                $t->id();
                $t->string('campaign_id');
                $t->string('campaignName')->nullable();
                $t->string('campaignStatus')->nullable();
                $t->string('report_date_range');
                $t->string('sbid')->nullable();
                $t->string('last_sbid')->nullable();
                $t->string('sbgt')->nullable();
                $t->decimal('campaignBudgetAmount', 10, 2)->nullable();
                $t->timestamps();
            });
        }
        Schema::create('amazon_ads_live_sync_states', function ($t) {
            $t->id();
            $t->string('channel', 8);
            $t->string('field', 8);
            $t->string('campaign_id', 100);
            $t->string('campaign_name')->nullable();
            $t->decimal('desired_value', 10, 2)->nullable();
            $t->decimal('live_value', 10, 2)->nullable();
            $t->string('status', 24)->default('pending');
            $t->text('reason')->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('detail')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->timestamps();
        });

        $this->amazon = [
            'sp' => ['bid' => [], 'bgt' => []],
            'sb' => ['bid' => [], 'bgt' => []],
        ];
        $this->pushes = [];
        $this->app->instance(AmazonAdsLiveBidBgtSyncService::class, $this->fakeAmazonService());
        // Most tests seed the SBID they want pushed, so the grid recalculation is a no-op here.
        // The tests further down swap in a recalculation that changes it, or the real one.
        $this->app->bind(AmazonAdsController::class, function () {
            return new class extends AmazonAdsController
            {
                public function refreshStoredSbids(string $table): array
                {
                    return ['rows' => 0, 'written' => 0];
                }
            };
        });
    }

    // ---------- BID ----------

    public function test_bid_mismatch_is_pulled_pushed_and_verified_with_no_page_open(): void
    {
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.50', sbgt: '10', bgt: 10.0);
        $this->amazon['sp']['bid']['111'] = 0.50;
        $this->amazon['sp']['bgt']['111'] = 10.0;

        $this->runSync();

        $this->assertSame([['sp', 'bid', '111', 0.80]], $this->pushes);
        $this->assertSame('0.8', (string) (float) $this->report('sp', '111')->last_sbid, 'Lbid must become the verified live bid');
        $this->assertState('sp', 'bid', '111', 'synced');
    }

    public function test_sb_bid_mismatch_is_pushed_too(): void
    {
        $this->campaign('sb', '222', sbid: '1.25', lastSbid: '0.90', sbgt: '10', bgt: 10.0);
        $this->amazon['sb']['bid']['222'] = 0.90;
        $this->amazon['sb']['bgt']['222'] = 10.0;

        $this->runSync();

        $this->assertSame([['sb', 'bid', '222', 1.25]], $this->pushes);
        $this->assertState('sb', 'bid', '222', 'synced');
    }

    public function test_matching_bid_is_left_alone(): void
    {
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.80', sbgt: '10', bgt: 10.0);
        $this->amazon['sp']['bid']['111'] = 0.80;
        $this->amazon['sp']['bgt']['111'] = 10.0;

        $this->runSync();

        $this->assertSame([], $this->pushes);
    }

    public function test_bid_set_to_the_same_cent_is_not_a_mismatch(): void
    {
        $this->campaign('sp', '111', sbid: '0.804', lastSbid: '0.80', sbgt: '10', bgt: 10.0);
        $this->amazon['sp']['bid']['111'] = 0.80;
        $this->amazon['sp']['bgt']['111'] = 10.0;

        $this->runSync();

        $this->assertSame([], $this->pushes);
    }

    // ---------- BGT ----------

    public function test_budget_mismatch_is_pulled_pushed_and_verified_with_no_page_open(): void
    {
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.80', sbgt: '20', bgt: 12.0);
        $this->amazon['sp']['bid']['111'] = 0.80;
        $this->amazon['sp']['bgt']['111'] = 12.0;

        $this->runSync();

        $this->assertSame([['sp', 'bgt', '111', 20.0]], $this->pushes);
        $this->assertEquals(20.0, (float) $this->report('sp', '111')->campaignBudgetAmount, 'Lbgt must become the verified live budget');
        $this->assertState('sp', 'bgt', '111', 'synced');
    }

    public function test_sb_budget_mismatch_is_pushed_too(): void
    {
        $this->campaign('sb', '222', sbid: '1.00', lastSbid: '1.00', sbgt: '8', bgt: 5.0);
        $this->amazon['sb']['bid']['222'] = 1.00;
        $this->amazon['sb']['bgt']['222'] = 5.0;

        $this->runSync();

        $this->assertSame([['sb', 'bgt', '222', 8.0]], $this->pushes);
        $this->assertState('sb', 'bgt', '222', 'synced');
    }

    public function test_matching_budget_is_left_alone(): void
    {
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.80', sbgt: '12', bgt: 12.0);
        $this->amazon['sp']['bid']['111'] = 0.80;
        $this->amazon['sp']['bgt']['111'] = 12.0;

        $this->runSync();

        $this->assertSame([], $this->pushes);
    }

    public function test_stale_saved_sbgt_is_recalculated_then_pushed_with_no_page_open(): void
    {
        // Saved SBGT 20 is what an old page load left behind. The sync recalculates it from the
        // grid rules (this stub database has no metrics, so the rules give a small number), saves
        // it, then pushes that number, not 20.
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.80', sbgt: '20', bgt: 12.0);
        $this->amazon['sp']['bid']['111'] = 0.80;
        $this->amazon['sp']['bgt']['111'] = 12.0;

        $this->runSync([], recalculate: true);

        $saved = (float) $this->report('sp', '111')->sbgt;
        $this->assertNotEquals(20.0, $saved, 'SBGT must be recalculated from the rules');
        $bgtPushes = $this->pushesFor('bgt');
        $this->assertCount(1, $bgtPushes);
        $this->assertEquals(floor($saved), $bgtPushes[0][3]);
        $this->assertEquals(floor($saved), (float) $this->report('sp', '111')->campaignBudgetAmount);
        $this->assertState('sp', 'bgt', '111', 'synced');
    }

    // ---------- BID + BGT ----------

    public function test_bid_and_budget_both_wrong_are_both_pushed_in_one_run(): void
    {
        $this->campaign('sp', '111', sbid: '0.95', lastSbid: '0.60', sbgt: '30', bgt: 15.0);
        $this->amazon['sp']['bid']['111'] = 0.60;
        $this->amazon['sp']['bgt']['111'] = 15.0;

        $this->runSync();

        $pushed = $this->pushes;
        usort($pushed, static fn ($a, $b) => strcmp($a[1], $b[1]));
        $this->assertSame([['sp', 'bgt', '111', 30.0], ['sp', 'bid', '111', 0.95]], $pushed);
        $this->assertState('sp', 'bid', '111', 'synced');
        $this->assertState('sp', 'bgt', '111', 'synced');
    }

    public function test_second_run_has_nothing_left_to_push(): void
    {
        $this->campaign('sp', '111', sbid: '0.95', lastSbid: '0.60', sbgt: '30', bgt: 15.0);
        $this->amazon['sp']['bid']['111'] = 0.60;
        $this->amazon['sp']['bgt']['111'] = 15.0;

        $this->runSync();
        $this->assertCount(2, $this->pushes);

        $this->pushes = [];
        $this->runSync();

        $this->assertSame([], $this->pushes, 'once verified, the mismatch is gone and nothing is pushed again');
    }

    // ---------- SBID refreshed without the page ----------

    public function test_stale_saved_sbid_is_recalculated_before_looking_for_a_gap(): void
    {
        // Saved sbid still equals Lbid, so a plain DB comparison sees no gap. The grid rules now
        // say 0.85. Nobody opened the page, so only the sync can save that value and push it.
        $this->campaign('sp', '111', sbid: '0.50', lastSbid: '0.50', sbgt: '10', bgt: 10.0);
        $this->amazon['sp']['bid']['111'] = 0.50;
        $this->amazon['sp']['bgt']['111'] = 10.0;

        $refreshed = [];
        $this->app->bind(AmazonAdsController::class, function () use (&$refreshed) {
            return new class($refreshed) extends AmazonAdsController
            {
                public function __construct(private array &$calls) {}

                public function refreshStoredSbids(string $table): array
                {
                    $this->calls[] = $table;
                    DB::table($table)->where('campaign_id', '111')->update(['sbid' => '0.85']);

                    return ['rows' => 1, 'written' => 1];
                }
            };
        });

        $this->runSync([], recalculate: true);

        $this->assertContains('amazon_sp_campaign_reports', $refreshed);
        $this->assertContains('amazon_sb_campaign_reports', $refreshed);
        $this->assertSame([['sp', 'bid', '111', 0.85]], $this->pushesFor('bid'));
        $this->assertState('sp', 'bid', '111', 'synced');
    }

    public function test_real_grid_recalculation_saves_sbid_for_every_campaign_without_a_request_from_the_page(): void
    {
        // No stub: this is the grid's own calculation. Both campaigns have a saved SBID the rules no
        // longer produce; one is SP and one is SB, and neither is on any page.
        $this->campaign('sp', '111', sbid: '9.99', lastSbid: '9.99', sbgt: '10', bgt: 10.0);
        $this->campaign('sb', '222', sbid: '9.99', lastSbid: '9.99', sbgt: '10', bgt: 10.0);
        $this->app->bind(AmazonAdsController::class, fn () => new AmazonAdsController);

        $controller = app(AmazonAdsController::class);
        $sp = $controller->refreshStoredSbids('amazon_sp_campaign_reports');
        $sb = $controller->refreshStoredSbids('amazon_sb_campaign_reports');

        $this->assertSame(1, $sp['rows']);
        $this->assertSame(1, $sb['rows']);
        $expected = \App\Services\Amazon\AmazonBidUtilizationService::sbidFromUb2Ub1Cpc(0.0, 0.0, 0.0, 0.0, 0.0, null, null)['sbid'];
        $this->assertNotNull($expected);
        $this->assertEquals($expected, (float) $this->report('sp', '111')->sbid);
        $this->assertEquals($expected, (float) $this->report('sb', '222')->sbid);
        $this->assertSame('9.99', $this->report('sp', '111')->last_sbid, 'recalculating SBID must never touch the live bid');
    }

    public function test_recalculated_sbid_then_gets_pushed_by_the_sync(): void
    {
        $this->campaign('sp', '111', sbid: '9.99', lastSbid: '9.99', sbgt: '10', bgt: 10.0);
        $this->amazon['sp']['bid']['111'] = 9.99;
        $this->amazon['sp']['bgt']['111'] = 10.0;
        $this->app->bind(AmazonAdsController::class, fn () => new AmazonAdsController);

        $this->runSync([], recalculate: true);

        $bidPushes = $this->pushesFor('bid');
        $this->assertCount(1, $bidPushes);
        $this->assertNotEquals(9.99, $bidPushes[0][3]);
        $this->assertState('sp', 'bid', '111', 'synced');
    }

    public function test_a_failing_sbid_recalculation_does_not_stop_the_sync(): void
    {
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.50', sbgt: '10', bgt: 10.0);
        $this->amazon['sp']['bid']['111'] = 0.50;
        $this->amazon['sp']['bgt']['111'] = 10.0;

        $this->app->bind(AmazonAdsController::class, function () {
            return new class extends AmazonAdsController
            {
                public function refreshStoredSbids(string $table): array
                {
                    throw new \RuntimeException('grid calculation blew up');
                }
            };
        });

        $this->runSync([], recalculate: true);

        $this->assertSame([['sp', 'bid', '111', 0.80]], $this->pushesFor('bid'), 'saved SBID is still pushed');
    }

    // ---------- Which campaigns are picked up ----------

    public function test_paused_campaign_is_not_pushed(): void
    {
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.50', sbgt: '20', bgt: 12.0, status: 'PAUSED');
        $this->amazon['sp']['bid']['111'] = 0.50;
        $this->amazon['sp']['bgt']['111'] = 12.0;

        $this->runSync();

        $this->assertSame([], $this->pushes, 'only ENABLED campaigns are pushed in the background');
    }

    // ---------- Failures are retried ----------

    public function test_failed_campaign_is_retried_by_the_30_minute_run_after_3_hours_not_before(): void
    {
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.50', sbgt: '10', bgt: 10.0);
        $this->campaign('sp', '222', sbid: '0.80', lastSbid: '0.50', sbgt: '10', bgt: 10.0);
        foreach (['111', '222'] as $id) {
            $this->amazon['sp']['bid'][$id] = 0.50;
            $this->amazon['sp']['bgt'][$id] = 10.0;
        }
        $this->failedState('111', now()->subHour());
        $this->failedState('222', now()->subHours(4));

        $this->runSync();

        $this->assertSame([['sp', 'bid', '222', 0.80]], $this->pushes);
    }

    public function test_retry_failed_run_picks_up_a_recent_failure_too(): void
    {
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.50', sbgt: '10', bgt: 10.0);
        $this->amazon['sp']['bid']['111'] = 0.50;
        $this->amazon['sp']['bgt']['111'] = 10.0;
        $this->failedState('111', now()->subMinutes(10));

        $this->runSync(['--retry-failed' => true]);

        $this->assertSame([['sp', 'bid', '111', 0.80]], $this->pushes);
        $this->assertState('sp', 'bid', '111', 'synced');
    }

    public function test_push_amazon_ignores_leaves_the_failure_visible(): void
    {
        $this->campaign('sp', '111', sbid: '0.80', lastSbid: '0.50', sbgt: '10', bgt: 10.0);
        $this->amazon['sp']['bid']['111'] = 0.50;
        $this->amazon['sp']['bgt']['111'] = 10.0;
        $this->amazonIgnoresBidPush = true;

        $this->runSync();

        $this->assertState('sp', 'bid', '111', 'failed');
        $this->assertSame('0.50', $this->report('sp', '111')->last_sbid, 'Lbid must not be marked matched when Amazon kept the old bid');
    }

    // ---------- helpers ----------

    private bool $amazonIgnoresBidPush = false;

    /**
     * By default pushes the saved SBID/SBGT exactly as seeded. Pass $recalculate to let the sync
     * recalculate them from the grid rules first, as the scheduled run does.
     */
    private function runSync(array $options = [], bool $recalculate = false): void
    {
        if (! $recalculate) {
            $options['--no-refresh'] = true;
        }
        $this->artisan('amazon:ads-live-bid-bgt-sync', array_merge(['--limit' => 50], $options))->run();
    }

    /** @return list<array{string, string, string, float}> */
    private function pushesFor(string $field): array
    {
        return array_values(array_filter($this->pushes, static fn ($p) => $p[1] === $field));
    }

    private function campaign(string $channel, string $id, string $sbid, string $lastSbid, string $sbgt, float $bgt, string $status = 'ENABLED'): void
    {
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
        DB::table($table)->insert([
            'campaign_id' => $id,
            'campaignName' => 'SKU '.$id.' KW',
            'campaignStatus' => $status,
            'report_date_range' => self::DAY,
            'sbid' => $sbid,
            'last_sbid' => $lastSbid,
            'sbgt' => $sbgt,
            'campaignBudgetAmount' => $bgt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function report(string $channel, string $id): object
    {
        $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';

        return DB::table($table)->where('campaign_id', $id)->where('report_date_range', self::DAY)->first();
    }

    private function failedState(string $id, $updatedAt): void
    {
        DB::table('amazon_ads_live_sync_states')->insert([
            'channel' => 'sp',
            'field' => 'bid',
            'campaign_id' => $id,
            'desired_value' => 0.80,
            'live_value' => 0.50,
            'status' => 'failed',
            'reason' => 'push_failed: boom',
            'attempts' => 3,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }

    private function assertState(string $channel, string $field, string $id, string $status): void
    {
        $row = DB::table('amazon_ads_live_sync_states')
            ->where('channel', $channel)->where('field', $field)->where('campaign_id', $id)->first();
        $this->assertNotNull($row, "no sync state saved for $channel/$field/$id");
        $this->assertSame($status, $row->status, "state for $channel/$field/$id: ".($row->reason ?? ''));
    }

    /** A fake Amazon: pull returns what it holds, push changes it, verify pulls again. */
    private function fakeAmazonService(): AmazonAdsLiveBidBgtSyncService
    {
        $held = [];

        return new AmazonAdsLiveBidBgtSyncService(null, [
            'sleeper' => static function (): void {},
            'log' => static function (): void {},
            'acquireLock' => function (string $key) use (&$held): bool {
                if (isset($held[$key])) {
                    return false;
                }
                $held[$key] = true;

                return true;
            },
            'releaseLock' => function (string $key) use (&$held): void {
                unset($held[$key]);
            },
            'pullBids' => function (string $channel, array $ids): array {
                $out = [];
                foreach ($ids as $id) {
                    $out[(string) $id] = $this->amazon[$channel]['bid'][(string) $id] ?? null;
                }

                return $out;
            },
            'pullBudgets' => function (string $channel, array $ids): array {
                $out = [];
                foreach ($ids as $id) {
                    $out[(string) $id] = $this->amazon[$channel]['bgt'][(string) $id] ?? null;
                }

                return $out;
            },
            'pushBid' => function (string $channel, $id, $desired): array {
                $this->pushes[] = [$channel, 'bid', (string) $id, (float) $desired];
                if (! $this->amazonIgnoresBidPush) {
                    $this->amazon[$channel]['bid'][(string) $id] = (float) $desired;
                }

                return ['status' => 200, 'failed' => []];
            },
            'pushBudget' => function (string $channel, $id, $desired): array {
                $this->pushes[] = [$channel, 'bgt', (string) $id, (float) $desired];
                $this->amazon[$channel]['bgt'][(string) $id] = (float) $desired;

                return ['status' => 200, 'failed' => []];
            },
            'persistLive' => function (string $channel, string $field, string $id, float $live): void {
                $table = $channel === 'sb' ? 'amazon_sb_campaign_reports' : 'amazon_sp_campaign_reports';
                DB::table($table)->where('campaign_id', $id)->update(
                    $field === 'bid'
                        ? ['last_sbid' => number_format($live, 2, '.', '')]
                        : ['campaignBudgetAmount' => round($live, 2)]
                );
            },
        ]);
    }
}
