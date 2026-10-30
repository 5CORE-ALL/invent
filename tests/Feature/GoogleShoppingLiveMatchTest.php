<?php

namespace Tests\Feature;

use App\Services\GoogleShoppingLiveMatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs the real `google-shopping:match-live-bid-bgt` command (the 30-minute background job)
 * against an in-memory database and a fake Google Ads. No page is opened and no button is clicked:
 * when SBID differs from LBid, or SBGT differs from LBgt, an ENABLED campaign must be pushed
 * and confirmed.
 */
class GoogleShoppingLiveMatchTest extends TestCase
{
    /** @var array<string, array{sbid: mixed, sbgt: mixed, status: string}> */
    private array $grid = [];

    /** @var array<string, array{bid: ?float, bgt: ?float}> what Google Ads holds */
    private array $google = [];

    /** @var list<array{string, list<string>}> */
    private array $pushes = [];

    private bool $googleIgnoresPushes = false;

    private bool $pullFails = false;

    private bool $bidPushThrows = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::connection('sqlite')->getPdo();

        Schema::create('google_shopping_live_syncs', function ($t) {
            $t->id();
            $t->string('channel', 16);
            $t->string('campaign_id', 40);
            $t->decimal('live_bid', 10, 4)->nullable();
            $t->decimal('live_bgt', 10, 2)->nullable();
            $t->timestamps();
            $t->unique(['channel', 'campaign_id']);
        });
        // The command takes a database lease (SchedulerLease) around the run.
        (require base_path('database/migrations/2026_10_31_020000_create_scheduler_leases_table.php'))->up();

        $this->grid = [];
        $this->google = [];
        $this->pushes = [];
        $this->googleIgnoresPushes = false;
        $this->pullFails = false;
        $this->bidPushThrows = false;

        $this->app->instance(GoogleShoppingLiveMatchService::class, new GoogleShoppingLiveMatchService([
            'targets' => fn () => $this->grid,
            'pull' => function (array $targets) {
                if ($this->pullFails) {
                    throw new \RuntimeException('Google Ads unavailable');
                }
                foreach ($targets as $cid => $_) {
                    $g = $this->google[$cid] ?? ['bid' => null, 'bgt' => null];
                    DB::table('google_shopping_live_syncs')->upsert([[
                        'channel' => 'shopping', 'campaign_id' => $cid,
                        'live_bid' => $g['bid'], 'live_bgt' => $g['bgt'],
                        'created_at' => now(), 'updated_at' => now(),
                    ]], ['channel', 'campaign_id'], ['live_bid', 'live_bgt', 'updated_at']);
                }
            },
            'pushBid' => function (array $ids) {
                $this->pushes[] = ['bid', $ids];
                if ($this->bidPushThrows) {
                    throw new \RuntimeException('Google Ads rejected the bid');
                }
                foreach ($ids as $id) {
                    if (! $this->googleIgnoresPushes) {
                        $this->google[$id]['bid'] = round((float) $this->grid[$id]['sbid'], 2);
                    }
                }

                return ['ok' => true, 'message' => 'SBID push finished'];
            },
            'pushBgt' => function (array $ids) {
                $this->pushes[] = ['bgt', $ids];
                foreach ($ids as $id) {
                    if (! $this->googleIgnoresPushes) {
                        $this->google[$id]['bgt'] = (float) $this->grid[$id]['sbgt'];
                    }
                }

                return ['ok' => true, 'message' => 'SBGT push finished'];
            },
        ]));
    }

    private function campaign(string $id, mixed $sbid, mixed $sbgt, ?float $liveBid, ?float $liveBgt, string $status = 'ENABLED'): void
    {
        $this->grid[$id] = ['sbid' => $sbid, 'sbgt' => $sbgt, 'status' => $status];
        $this->google[$id] = ['bid' => $liveBid, 'bgt' => $liveBgt];
    }

    private function runMatch(array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('google-shopping:match-live-bid-bgt', $options);
    }

    // ---------- BID ----------

    public function test_bid_that_differs_is_pushed_and_confirmed(): void
    {
        $this->campaign('111', 0.33, 3, 0.50, 3.0);

        $this->runMatch()->assertExitCode(0);

        $this->assertSame([['bid', ['111']]], $this->pushes);
        $this->assertSame(0.33, $this->google['111']['bid']);
        $this->assertEquals(0.33, (float) DB::table('google_shopping_live_syncs')->value('live_bid'));
    }

    public function test_bid_within_a_cent_is_left_alone(): void
    {
        $this->campaign('111', 0.33, 3, 0.34, 3.0);

        $this->runMatch()->assertExitCode(0);

        $this->assertSame([], $this->pushes);
    }

    // ---------- BUDGET ----------

    public function test_budget_that_differs_is_pushed_and_confirmed(): void
    {
        $this->campaign('222', 0.20, 2, 0.20, 3.0);

        $this->runMatch()->assertExitCode(0);

        $this->assertSame([['bgt', ['222']]], $this->pushes);
        $this->assertSame(2.0, $this->google['222']['bgt']);
    }

    public function test_bid_and_budget_both_wrong_are_both_pushed(): void
    {
        $this->campaign('333', 0.17, 3, 0.09, 2.0);

        $this->runMatch()->assertExitCode(0);

        $this->assertEqualsCanonicalizing([['bid', ['333']], ['bgt', ['333']]], $this->pushes);
        $this->assertSame(0.17, $this->google['333']['bid']);
        $this->assertSame(3.0, $this->google['333']['bgt']);
    }

    public function test_only_the_campaigns_that_differ_are_pushed(): void
    {
        $this->campaign('1', 0.30, 4, 0.30, 4.0);
        $this->campaign('2', 0.40, 4, 0.55, 4.0);
        $this->campaign('3', 0.50, 5, 0.50, 2.0);

        $this->runMatch()->assertExitCode(0);

        $this->assertEqualsCanonicalizing([['bid', ['2']], ['bgt', ['3']]], $this->pushes);
    }

    public function test_second_run_pushes_nothing(): void
    {
        $this->campaign('111', 0.33, 3, 0.50, 2.0);

        $this->runMatch()->assertExitCode(0);
        $this->pushes = [];
        $this->runMatch()->assertExitCode(0);

        $this->assertSame([], $this->pushes);
    }

    // ---------- WHO IS CHECKED ----------

    public function test_paused_and_removed_campaigns_are_not_pushed(): void
    {
        $this->campaign('1', 0.33, 3, 0.50, 2.0, 'PAUSED');
        $this->campaign('2', 0.33, 3, 0.50, 2.0, 'REMOVED');

        $this->runMatch()->assertExitCode(0);

        $this->assertSame([], $this->pushes);
    }

    public function test_sbgt_below_one_is_never_pushed_so_it_cannot_pause_a_campaign(): void
    {
        $this->campaign('1', 0.33, 0, 0.33, 5.0);
        $this->campaign('2', 0.33, 0.4, 0.33, 5.0);

        $this->runMatch()->assertExitCode(0);

        $this->assertSame([], $this->pushes);
    }

    public function test_campaign_without_an_sbid_is_not_pushed(): void
    {
        $this->campaign('1', null, 3, 0.50, 3.0);
        $this->campaign('2', 0, 3, 0.50, 3.0);

        $this->runMatch()->assertExitCode(0);

        $this->assertSame([], $this->pushes);
    }

    public function test_campaign_whose_live_value_could_not_be_read_is_not_pushed(): void
    {
        $this->campaign('1', 0.33, 3, null, null);

        $this->runMatch()->assertExitCode(0);

        $this->assertSame([], $this->pushes);
    }

    public function test_campaign_id_option_limits_the_run(): void
    {
        $this->campaign('1', 0.33, 3, 0.50, 3.0);
        $this->campaign('2', 0.33, 3, 0.50, 3.0);

        $this->runMatch(['--campaign-id' => '2'])->assertExitCode(0);

        $this->assertSame([['bid', ['2']]], $this->pushes);
    }

    public function test_dry_run_reports_but_pushes_nothing(): void
    {
        $this->campaign('1', 0.33, 3, 0.50, 2.0);

        $this->runMatch(['--dry-run' => true])
            ->expectsOutputToContain('SBID differs from live: 1 | SBGT differs from live: 1')
            ->assertExitCode(0);

        $this->assertSame([], $this->pushes);
    }

    // ---------- FAILURES ----------

    public function test_push_google_ignores_stays_visible(): void
    {
        $this->campaign('1', 0.33, 3, 0.50, 3.0);
        $this->googleIgnoresPushes = true;

        // Every operation failed (Google ignored the push): the run is reported as failed, not as a success.
        $this->runMatch()
            ->expectsOutputToContain('SBID matched 0, still different 1')
            ->assertExitCode(1);

        $this->assertEquals(0.50, (float) DB::table('google_shopping_live_syncs')->value('live_bid'));
    }

    public function test_failed_bid_push_does_not_stop_the_budget_push(): void
    {
        $this->campaign('1', 0.33, 2, 0.50, 3.0);
        $this->bidPushThrows = true;

        $this->runMatch()
            ->expectsOutputToContain('bid: Google Ads rejected the bid')
            ->assertExitCode(0);

        $this->assertSame(2.0, $this->google['1']['bgt']);
        $this->assertSame(0.50, $this->google['1']['bid']);
    }

    public function test_failed_live_pull_pushes_nothing_and_fails_the_run(): void
    {
        $this->campaign('1', 0.33, 3, 0.50, 3.0);
        $this->pullFails = true;

        $this->runMatch()->assertExitCode(1);

        $this->assertSame([], $this->pushes);
    }

    public function test_nothing_to_check_is_a_clean_run(): void
    {
        $this->runMatch()->expectsOutputToContain('Enabled shopping campaigns checked: 0')->assertExitCode(0);

        $this->assertSame([], $this->pushes);
    }

    // ---------- RETRY WAIT ----------

    public function test_campaign_google_rejected_is_not_pushed_again_right_away(): void
    {
        $this->campaign('1', 0.33, 3, 0.50, 3.0);
        $this->googleIgnoresPushes = true;

        $this->runMatch()->assertExitCode(1);
        $this->assertCount(1, $this->pushes);

        $this->runMatch()
            ->expectsOutputToContain('Waiting before the next try')
            ->assertExitCode(0);
        $this->assertCount(1, $this->pushes);
    }

    public function test_retry_failed_pushes_a_waiting_campaign_again(): void
    {
        $this->campaign('1', 0.33, 3, 0.50, 3.0);
        $this->googleIgnoresPushes = true;
        $this->runMatch()->assertExitCode(1);

        $this->googleIgnoresPushes = false;
        $this->runMatch(['--retry-failed' => true])->assertExitCode(0);

        $this->assertCount(2, $this->pushes);
        $this->assertSame(0.33, $this->google['1']['bid']);
    }

    public function test_a_campaign_that_waited_is_pushed_again_after_the_wait_and_clears_when_it_sticks(): void
    {
        $this->campaign('1', 0.33, 3, 0.50, 3.0);
        $this->googleIgnoresPushes = true;
        $this->runMatch()->assertExitCode(1);

        $this->googleIgnoresPushes = false;
        $this->travel(GoogleShoppingLiveMatchService::FAILED_RETRY_AFTER_HOURS + 1)->hours();
        $this->runMatch()->assertExitCode(0);
        $this->assertSame(0.33, $this->google['1']['bid']);

        // It stuck, so a later mismatch is pushed straight away again.
        $this->google['1']['bid'] = 0.90;
        $this->runMatch()->assertExitCode(0);
        $this->assertSame(0.33, $this->google['1']['bid']);
    }

    public function test_one_failing_campaign_does_not_hold_back_the_others(): void
    {
        $this->campaign('1', 0.33, 3, 0.50, 3.0);
        $this->googleIgnoresPushes = true;
        $this->runMatch()->assertExitCode(1);

        $this->googleIgnoresPushes = false;
        $this->campaign('2', 0.40, 3, 0.60, 3.0);
        $this->runMatch()->assertExitCode(0);

        $this->assertSame(0.40, $this->google['2']['bid']);
        $this->assertSame(0.50, $this->google['1']['bid']);
    }

    public function test_google_error_is_shown_in_plain_words(): void
    {
        $raw = "{\n \"message\": \"Request contains an invalid argument.\",\n \"code\": 3,\n \"details\": [{\"errors\": [{\"errorCode\": {\"requestError\": \"UNSUPPORTED_VERSION\"}, \"message\": \"Version v22 is deprecated. Requests to this version will be blocked.\"}]}]\n}";

        $this->assertSame(
            'Version v22 is deprecated. Requests to this version will be blocked.',
            \App\Services\GoogleAdsSbidService::briefGoogleError(new \RuntimeException($raw))
        );
        $this->assertSame('plain failure', \App\Services\GoogleAdsSbidService::briefGoogleError(new \RuntimeException('plain failure')));
    }
}
