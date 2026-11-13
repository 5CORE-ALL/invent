<?php

namespace Tests\Unit\Settle;

use App\Models\SettleAlertLog;
use App\Models\SettleTaskRun;
use App\Models\SettleVerification;
use App\Support\Marketplace\Settle\SettleAlerter;
use App\Support\Marketplace\Settle\SettleTracker;
use App\Support\Marketplace\Settle\SettleVerifier;
use App\Support\Marketplace\Settle\Verification\VerificationResult;
use App\Support\Marketplace\Settle\Verification\VerifierRegistry;
use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Regression tests for the 8 fixes of the final production-readiness review.
 * Everything runs on the private in-memory SQLite database (see SettleTestCase).
 */
class SettleReliabilityFixesTest extends SettleTestCase
{
    // ------------------------------------------------------------------ helpers

    /** 06:00 PT the morning after DATE: every pass is due, before the 08:30 cutoff. */
    private function now(string $time = '06:00'): Carbon
    {
        return Carbon::parse(self::DATE, 'America/Los_Angeles')->addDay()->setTimeFromTimeString($time);
    }

    /** @param  array<string, array<string, mixed>>  $overrides */
    private function writeLedger(array $overrides = []): void
    {
        $tasks = [];
        foreach (Ledger::tasks() as $key => $task) {
            $done = [];
            foreach ($task['passes'] as $p) {
                $done[(string) $p] = '2026-11-13T05:'.str_pad((string) (10 + $p), 2, '0', STR_PAD_LEFT).':00-08:00';
            }
            $tasks[$key] = ['done' => $done, 'attempts' => array_fill_keys(array_map('strval', $task['passes']), 1)];
            $o = $overrides[$key] ?? [];
            if (($o['done'] ?? null) === false) {
                $tasks[$key]['done'] = [];
            }
            if (isset($o['attempts'])) {
                $tasks[$key]['attempts'] = $o['attempts'];
            }
            if (isset($o['error'])) {
                $tasks[$key]['error'] = $o['error'];
            }
        }
        File::ensureDirectoryExists(dirname(Ledger::path(self::DATE)));
        file_put_contents(Ledger::path(self::DATE), json_encode(['date' => self::DATE, 'tasks' => $tasks]));
    }

    /** Saves a verification for every task (except $except) at the given time. */
    private function verifyAll(string $status = VerificationResult::PASS, ?string $except = null, ?Carbon $at = null, array $perTask = []): void
    {
        $at ??= $this->now();
        $desc = SettleTracker::describe(self::DATE, Ledger::read(self::DATE), 2, false, $at);
        foreach ($desc['tasks'] as $key => $t) {
            if ($key === $except) {
                continue;
            }
            $st = $perTask[$key] ?? $status;
            SettleTracker::saveVerification(self::DATE, $key, new VerificationResult($st, [['code' => 'x', 'status' => $st, 'message' => 'test '.$st]]), $t['ledger_done_at'], 1, $at);
        }
    }

    private function dayStatus(string $time = '06:00'): array
    {
        return Ledger::status(self::DATE, $this->now($time));
    }

    private function event(string $task = 'tiktok'): array
    {
        return ['task' => $task, 'type' => 'missing_data', 'codes' => ['records_present'], 'title' => 'Missing data: '.$task, 'message' => 'records_present: 0 records'];
    }

    // ================================================================== FIX 1: Temu 3

    private function makeTemu3Tables(): void
    {
        foreach (['temu3_api_orders', 'temu3_orders'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id();
                $t->string('order_sn');
                $t->dateTime('parent_order_time')->nullable();
                $t->decimal('order_base_amount', 10, 2)->nullable();
                $t->string('order_status_text')->nullable();
            });
        }
    }

    /** Stores Temu 3 orders the way FetchTemu3Orders does: Pacific wall-clock time. */
    private function seedTemu3(string $table, string $ymd, int $n, string $time = '12:00:00', string $status = 'Shipped'): void
    {
        for ($i = 0; $i < $n; $i++) {
            DB::table($table)->insert(['order_sn' => $ymd.'-'.$time.'-'.$status.'-'.$i, 'parent_order_time' => $ymd.' '.$time, 'order_base_amount' => 10, 'order_status_text' => $status]);
        }
    }

    public function test_temu3_spec_points_at_the_api_table_with_the_right_columns(): void
    {
        $spec = VerifierRegistry::orderSpecs()['temu3'];

        $this->assertSame('temu3_api_orders', $spec['table'], 'app:fetch-temu3-orders writes temu3_api_orders, not temu3_orders');
        $this->assertSame('parent_order_time', $spec['time_column']);
        $this->assertSame('America/Los_Angeles', $spec['tz'], 'Temu3ApiOrder stores Pacific wall-clock time');
        $this->assertSame(['order_sn'], $spec['key']);
        $this->assertSame('order_base_amount', $spec['price_column']);
    }

    public function test_temu3_is_verified_against_temu3_api_orders_not_the_old_sheet_table(): void
    {
        $this->makeTemu3Tables();
        for ($i = 1; $i <= 7; $i++) {
            $this->seedTemu3('temu3_api_orders', Carbon::parse(self::DATE)->subDays($i)->toDateString(), 12);
        }
        // The old sheet table still has rows for the day, but nothing feeds it any more.
        $this->seedTemu3('temu3_orders', self::DATE, 50);

        $missing = VerifierRegistry::for('temu3')->verify(self::DATE, $this->ctx('temu3'));
        $this->assertSame(VerificationResult::FAIL, $missing->status, 'the api table has no rows for the day');
        $this->assertContains('records_present', $this->codes($missing->checks, 'fail'));

        $this->seedTemu3('temu3_api_orders', self::DATE, 12);
        $ok = VerifierRegistry::for('temu3')->verify(self::DATE, $this->ctx('temu3'));
        $this->assertSame(VerificationResult::PASS, $ok->status, json_encode($ok->checks));
        $this->assertSame(12, $ok->metrics['rows']);
    }

    public function test_temu3_uses_the_pacific_day_boundaries_and_ignores_cancelled_orders(): void
    {
        $this->makeTemu3Tables();
        for ($i = 1; $i <= 7; $i++) {
            $this->seedTemu3('temu3_api_orders', Carbon::parse(self::DATE)->subDays($i)->toDateString(), 12);
        }
        $this->seedTemu3('temu3_api_orders', self::DATE, 12);
        $this->seedTemu3('temu3_api_orders', self::DATE, 1, '23:30:00'); // last minutes of the Pacific day: counted
        $this->seedTemu3('temu3_api_orders', '2026-11-13', 1, '00:30:00'); // next Pacific day: not counted
        $this->seedTemu3('temu3_api_orders', self::DATE, 3, '12:00:00', 'CANCELED'); // cancelled: not counted

        $r = VerifierRegistry::for('temu3')->verify(self::DATE, $this->ctx('temu3'));

        $this->assertSame(13, $r->metrics['rows']);
    }

    // ================================================================== FIX 2: Shein shrink baseline

    private function makeSheinL60(): void
    {
        Schema::create('shein_daily_data_l60', function (Blueprint $t) {
            $t->id();
            $t->string('order_number')->nullable();
        });
    }

    private function sheinL60Rows(int $n): void
    {
        foreach (array_chunk(array_fill(0, $n, ['order_number' => 'x']), 200) as $chunk) {
            DB::table('shein_daily_data_l60')->insert($chunk);
        }
    }

    private function runShein(string $time, bool $force = true): array
    {
        return (new SettleVerifier)->run(self::DATE, $force, ['shein_l60'], false, $this->now($time));
    }

    public function test_shein_1000_to_400_to_400_never_becomes_pass_by_itself(): void
    {
        $this->makeSheinL60();
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, 'shein_l60');
        $this->sheinL60Rows(1000);

        // 1000: first verification, nothing to compare with -> PASS and it becomes the baseline.
        $first = $this->runShein('06:00');
        $this->assertSame(VerificationResult::PASS, $first['results']['shein_l60']['status']);
        $this->assertSame('full', $this->dayStatus()['level']);

        // 400: a 60% drop -> FAIL.
        DB::table('shein_daily_data_l60')->where('id', '>', 400)->delete();
        $second = $this->runShein('06:10');
        $this->assertSame(VerificationResult::FAIL, $second['results']['shein_l60']['status']);
        $this->assertContains('table_shrunk', $this->codes($second['results']['shein_l60']['checks'], 'fail'));

        // 400 again (and again): the failed run must NOT have become the baseline -> still FAIL.
        foreach (['06:20', '06:30', '06:40'] as $t) {
            $again = $this->runShein($t);
            $this->assertSame(VerificationResult::FAIL, $again['results']['shein_l60']['status'], "still failing at {$t}");
            $this->assertContains('table_shrunk', $this->codes($again['results']['shein_l60']['checks'], 'fail'));
        }
        $this->assertSame('verification_failed', $again['tasks']['shein_l60']['state']);
        $status = $this->dayStatus('06:40');
        $this->assertSame('incomplete', $status['level']);
        $this->assertFalse($status['settled']);
        $this->assertSame(0, SettleVerification::query()->where('status', 'baseline')->count(), 'nothing was auto-accepted');
        $this->assertStringContainsString('1000', $again['results']['shein_l60']['checks'][1]['message'] ?? $again['results']['shein_l60']['checks'][0]['message'].json_encode($again['results']['shein_l60']['checks']));
    }

    public function test_shein_table_recovery_passes_again(): void
    {
        $this->makeSheinL60();
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, 'shein_l60');
        $this->sheinL60Rows(1000);
        $this->runShein('06:00');
        DB::table('shein_daily_data_l60')->where('id', '>', 400)->delete();
        $this->assertSame(VerificationResult::FAIL, $this->runShein('06:10')['results']['shein_l60']['status']);

        $this->sheinL60Rows(600); // the next successful pull restores the table

        $this->assertSame(VerificationResult::PASS, $this->runShein('06:20')['results']['shein_l60']['status']);
    }

    public function test_operator_can_accept_a_smaller_table_as_the_new_baseline_explicitly(): void
    {
        $this->makeSheinL60();
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, 'shein_l60');
        $this->sheinL60Rows(1000);
        $this->runShein('06:00');
        DB::table('shein_daily_data_l60')->where('id', '>', 400)->delete();
        $this->assertSame(VerificationResult::FAIL, $this->runShein('06:10')['results']['shein_l60']['status']);
        $this->assertSame(VerificationResult::FAIL, $this->runShein('06:20')['results']['shein_l60']['status']);

        $accepted = (new SettleVerifier)->acceptBaseline(self::DATE, 'shein_l60', $this->now('06:25'));

        $this->assertTrue($accepted['ok']);
        $this->assertSame(400, $accepted['table_rows']);
        $this->assertSame(1, SettleVerification::query()->where('status', 'baseline')->count());
        $this->assertSame(VerificationResult::PASS, $this->runShein('06:30')['results']['shein_l60']['status']);
        $this->assertSame(400, DB::table('shein_daily_data_l60')->count(), 'accepting a baseline never touches marketplace data');
    }

    public function test_accept_baseline_refuses_unknown_tasks_and_tasks_without_a_table_size(): void
    {
        $this->assertFalse((new SettleVerifier)->acceptBaseline(self::DATE, 'no_such_task', $this->now())['ok']);

        $this->makeAmazonTables(); // amazon has no table_shrink spec
        $this->assertFalse((new SettleVerifier)->acceptBaseline(self::DATE, 'amazon_close', $this->now())['ok']);
        $this->assertSame(0, SettleVerification::query()->where('status', 'baseline')->count());
    }

    // ================================================================== FIX 3: Shein units + Amazon ads

    private function makeSheinL30(): void
    {
        Schema::create('shein_daily_data', function (Blueprint $t) {
            $t->id();
            $t->string('order_number')->nullable();
            $t->string('seller_sku')->nullable();
            $t->string('shein_sku')->nullable();
            $t->dateTime('order_processed_on')->nullable();
            $t->decimal('product_price', 10, 2)->nullable();
        });
    }

    /** One row PER UNIT (SheinApiService), stored in Asia/Shanghai time. */
    private function seedSheinDay(string $ymd, int $orders, int $unitsPerOrder): void
    {
        $ts = Carbon::parse($ymd.' 12:00', 'America/Los_Angeles')->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s');
        $rows = [];
        for ($o = 0; $o < $orders; $o++) {
            for ($u = 0; $u < $unitsPerOrder; $u++) {
                $rows[] = ['order_number' => $ymd.'-'.$o, 'seller_sku' => 'SKU'.$o, 'shein_sku' => 'S'.$o, 'order_processed_on' => $ts, 'product_price' => 10];
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('shein_daily_data')->insert($chunk);
        }
    }

    private function seedSheinBaseline(int $orders, int $units): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->seedSheinDay(Carbon::parse(self::DATE)->subDays($i)->toDateString(), $orders, $units);
        }
    }

    public function test_shein_rows_per_unit_with_identical_keys_are_legitimate(): void
    {
        $this->makeSheinL30();
        $this->seedSheinBaseline(40, 3);
        $this->seedSheinDay(self::DATE, 40, 3); // 3 identical (order, seller_sku, shein_sku) rows per order

        $r = VerifierRegistry::for('shein_l30')->verify(self::DATE, $this->ctx('shein_l30'));

        $this->assertSame(VerificationResult::PASS, $r->status, json_encode($r->checks));
        $this->assertSame(3.0, $r->metrics['rows_per_order']);
        $this->assertSame([], $this->codes($r->checks, 'fail'));
        $this->assertSame([], $this->codes($r->checks, 'warn'));
        $this->assertArrayNotHasKey('duplicate_rows', $r->metrics, 'identical keys are not counted as duplicates for Shein');
    }

    public function test_shein_day_imported_twice_is_caught_by_rows_per_order(): void
    {
        $this->makeSheinL30();
        $this->seedSheinBaseline(30, 4);
        $this->seedSheinDay(self::DATE, 20, 6); // same number of rows, but 6 rows/order vs 4 usual = 1.5x

        $warn = VerifierRegistry::for('shein_l30')->verify(self::DATE, $this->ctx('shein_l30'));
        $this->assertSame(VerificationResult::WARN, $warn->status, json_encode($warn->checks));
        $this->assertContains('duplicates', $this->codes($warn->checks, 'warn'));

        DB::table('shein_daily_data')->where('order_processed_on', '>=', '2000-01-01')->delete();
        $this->seedSheinBaseline(30, 3);
        $this->seedSheinDay(self::DATE, 20, 6); // 2x
        $fail = VerifierRegistry::for('shein_l30')->verify(self::DATE, $this->ctx('shein_l30'));
        $this->assertSame(VerificationResult::FAIL, $fail->status);
        $this->assertContains('duplicates', $this->codes($fail->checks, 'fail'));
    }

    private function makeSbTable(): void
    {
        Schema::create('amazon_sb_campaign_reports', function (Blueprint $t) {
            $t->id();
            $t->string('profile_id');
            $t->string('campaign_id')->nullable();
            $t->string('report_date_range');
            $t->decimal('cost', 12, 4)->nullable();
        });
    }

    public function test_amazon_sb_ads_collapse_repeated_campaigns_with_max_spend_like_the_dashboard(): void
    {
        $this->makeSbTable();
        $row = fn (string $range, string $campaign, float $cost) => DB::table('amazon_sb_campaign_reports')->insert(['profile_id' => 'p', 'campaign_id' => $campaign, 'report_date_range' => $range, 'cost' => $cost]);
        for ($d = 1; $d <= 7; $d++) {
            for ($c = 0; $c < 10; $c++) {
                $row(Carbon::parse(self::DATE)->subDays($d)->toDateString(), 'c'.$c, 5.0);
            }
        }
        for ($c = 0; $c < 10; $c++) {
            $row(self::DATE, 'c'.$c, 5.0);
        }
        $row(self::DATE, 'c0', 8.0); // the same campaign reported again with a different (larger) value
        $row(self::DATE, 'c0', 2.0);

        $r = VerifierRegistry::for('ads_amazon_sb')->verify(self::DATE, $this->ctx('ads_amazon_sb'));

        $this->assertSame(VerificationResult::PASS, $r->status, json_encode($r->checks));
        $this->assertSame(10, $r->metrics['rows']);
        $this->assertEqualsWithDelta(53.0, $r->metrics['spend'], 0.01, 'MAX per campaign (8) + 9 x 5, never summed per row');
        $this->assertSame(2, $r->metrics['collapsed_duplicate_rows']);
    }

    // ================================================================== FIX 4: same-weekday baseline

    /** Weekdays sell $weekday orders, Saturday/Sunday $weekend orders, for every day in [from, to]. */
    private function seedWeeklyPattern(string $from, string $to, int $weekday, int $weekend): void
    {
        for ($d = Carbon::parse($from); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $this->seedAmazonDay($d->toDateString(), $d->isWeekend() ? $weekend : $weekday);
        }
    }

    public function test_a_normal_weekend_is_not_compared_with_weekday_volume(): void
    {
        $this->makeAmazonTables();
        $this->seedWeeklyPattern('2026-10-10', '2026-11-13', 40, 3);
        $saturday = '2026-11-14';
        $this->assertTrue(Carbon::parse($saturday)->isSaturday());
        $this->seedAmazonDay($saturday, 3);

        $r = VerifierRegistry::for('amazon_close')->verify($saturday, $this->ctx());

        $this->assertSame(VerificationResult::PASS, $r->status, json_encode($r->checks));
        $this->assertTrue($r->metrics['baseline']['_all']['weekday_used']);
        $this->assertEqualsWithDelta(3.0, $r->metrics['baseline']['_all']['expected'], 0.01);
        $this->assertEqualsWithDelta(40.0, $r->metrics['baseline']['_all']['median_recent'], 0.01, 'the old 7-day median alone would have said 40 and failed the weekend');
    }

    public function test_a_weekday_that_normally_sells_still_fails_on_zero_records(): void
    {
        $this->makeAmazonTables();
        $this->seedWeeklyPattern('2026-10-10', '2026-11-11', 40, 3);
        $this->assertTrue(Carbon::parse(self::DATE)->isThursday());

        $r = VerifierRegistry::for('amazon_close')->verify(self::DATE, $this->ctx());

        $this->assertSame(VerificationResult::FAIL, $r->status);
        $this->assertContains('records_present', $this->codes($r->checks, 'fail'));
    }

    public function test_zero_records_on_a_normally_quiet_weekend_warns_instead_of_passing_silently(): void
    {
        $this->makeAmazonTables();
        $this->seedWeeklyPattern('2026-10-10', '2026-11-13', 40, 3);

        $r = VerifierRegistry::for('amazon_close')->verify('2026-11-14', $this->ctx());

        $this->assertSame(VerificationResult::WARN, $r->status, 'missing data is never silently accepted');
        $this->assertContains('records_present', $this->codes($r->checks, 'warn'));
        $this->assertTrue($r->isWarning());
    }

    public function test_zero_records_on_a_low_volume_channel_is_a_warning_not_a_failure(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(4); // ~4 orders a day: an empty day is plausible

        $r = VerifierRegistry::for('amazon_close')->verify(self::DATE, $this->ctx());

        $this->assertSame(VerificationResult::WARN, $r->status);
        $this->assertContains('records_present', $this->codes($r->checks, 'warn'));
        $this->assertSame(SettleTracker::stateFor($r->status), 'verified_warning');
    }

    public function test_zero_records_on_a_busy_channel_without_enough_history_is_not_a_hard_failure_but_is_flagged(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonDay(Carbon::parse(self::DATE)->subDay()->toDateString(), 40); // a single day of history

        $r = VerifierRegistry::for('amazon_close')->verify(self::DATE, $this->ctx());

        $this->assertNotSame(VerificationResult::PASS, $r->status);
        $this->assertContains('records_present', array_merge($this->codes($r->checks, 'warn'), $this->codes($r->checks, 'fail')));
    }

    // ================================================================== FIX 5: four dashboard levels

    public function test_level_fully_settled_when_everything_passed_and_by_design_is_not_a_warning(): void
    {
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, null, null, ['wayfair_api' => VerificationResult::LIMITED_BY_DESIGN, 'views_temu3' => VerificationResult::LIMITED_BY_DESIGN]);

        $s = $this->dayStatus();

        $this->assertSame('full', $s['level']);
        $this->assertSame('Fully Settled', $s['level_label']);
        $this->assertTrue($s['settled']);
        $this->assertSame([], $s['warnings']);
        $this->assertEqualsCanonicalizing(['wayfair_api', 'views_temu3'], $s['verification']['by_design']);
        $this->assertSame('limited_by_design', $s['tasks']['wayfair_api']['state']);
    }

    public function test_level_settled_with_warnings_for_warn_and_for_skipped_checks(): void
    {
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, null, null, ['tiktok' => VerificationResult::WARN, 'ebay' => VerificationResult::LIMITED]);

        $s = $this->dayStatus();

        $this->assertSame('warnings', $s['level']);
        $this->assertSame('Settled with Warnings', $s['level_label']);
        $this->assertTrue($s['settled'], 'warnings do not block the legacy settled flag');
        $this->assertEqualsCanonicalizing(['tiktok', 'ebay'], array_column($s['warnings'], 'key'));
        $byKey = array_column($s['warnings'], 'state', 'key');
        $this->assertSame('verified_warning', $byKey['tiktok']);
        $this->assertSame('limited', $byKey['ebay'], 'a skipped / unavailable check is a warning, unlike limited_by_design');
        $this->assertNotEmpty($s['warnings'][0]['messages']);
    }

    public function test_level_incomplete_for_a_failed_verification_and_for_a_failed_pull(): void
    {
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, null, null, ['tiktok' => VerificationResult::FAIL]);
        $s = $this->dayStatus();
        $this->assertSame('incomplete', $s['level']);
        $this->assertSame('Incomplete', $s['level_label']);
        $this->assertFalse($s['settled']);

        $this->writeLedger(['temu' => ['done' => false, 'attempts' => ['0' => 3, '1' => 3, '2' => 3], 'error' => 'boom']]);
        $this->verifyAll();
        $p = $this->dayStatus();
        $this->assertSame('incomplete', $p['level']);
        $this->assertFalse($p['settled']);
        $this->assertContains('temu', $p['failed']);
    }

    public function test_level_verification_pending_when_pulled_but_not_verified_or_tables_missing(): void
    {
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, 'tiktok');

        $s = $this->dayStatus();
        $this->assertSame('pending', $s['level']);
        $this->assertSame('Verification Pending', $s['level_label']);
        $this->assertFalse($s['settled']);
        $this->assertContains('tiktok', $s['verification']['unverified']);

        Schema::drop('settle_task_runs');
        $missing = $this->dayStatus();
        $this->assertSame('pending', $missing['level']);
        $this->assertFalse($missing['settled']);
    }

    public function test_level_updating_while_pulls_are_still_running(): void
    {
        $this->writeLedger(['temu' => ['done' => false, 'attempts' => ['0' => 1, '1' => 1, '2' => 1]]]);
        $this->verifyAll(VerificationResult::PASS, 'temu');

        $s = $this->dayStatus();

        $this->assertSame('updating', $s['level']);
        $this->assertFalse($s['settled']);
        $this->assertContains('temu', $s['pending']);
    }

    public function test_limited_by_design_is_distinct_from_skipped_or_unavailable_checks(): void
    {
        $this->assertSame(VerificationResult::LIMITED_BY_DESIGN, VerifierRegistry::for('wayfair_api')->verify(self::DATE, $this->ctx('wayfair_api'))->status);
        $this->assertSame(VerificationResult::LIMITED, VerifierRegistry::for('task_without_a_verifier')->verify(self::DATE, $this->ctx())->status, 'no verifier = skipped check');

        $this->assertSame('limited_by_design', SettleTracker::stateFor(VerificationResult::LIMITED_BY_DESIGN));
        $this->assertSame('limited', SettleTracker::stateFor(VerificationResult::LIMITED));
        $this->assertContains('limited', SettleTracker::WARNING_STATES);
        $this->assertNotContains('limited_by_design', SettleTracker::WARNING_STATES);

        // Orders verifier: a skipped duplicate check (order column missing) is LIMITED, i.e. a warning.
        $this->makeSheinL30();
        Schema::table('shein_daily_data', fn (Blueprint $t) => $t->dropColumn('order_number'));
        $this->seedLegacySheinRows();
        $r = VerifierRegistry::for('shein_l30')->verify(self::DATE, $this->ctx('shein_l30'));
        $this->assertContains('duplicates', $this->codes($r->checks, 'limited'));
    }

    private function seedLegacySheinRows(): void
    {
        $ts = fn (string $ymd) => Carbon::parse($ymd.' 12:00', 'America/Los_Angeles')->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s');
        for ($i = 0; $i <= 7; $i++) {
            $ymd = Carbon::parse(self::DATE)->subDays($i)->toDateString();
            DB::table('shein_daily_data')->insert(array_fill(0, 20, ['seller_sku' => 'a', 'shein_sku' => 'b', 'order_processed_on' => $ts($ymd), 'product_price' => 10]));
        }
    }

    public function test_the_dashboard_blade_uses_all_four_levels(): void
    {
        $blade = file_get_contents(resource_path('views/channels/all-marketplace-master.blade.php'));

        foreach (['Fully Settled', 'Settled with Warnings', 'Incomplete', 'Verification Pending', 'limited_by_design', 'verified_warning'] as $needle) {
            $this->assertStringContainsString($needle, $blade);
        }
    }

    // ================================================================== FIX 6: alert grace + confirmation

    public function test_a_data_failure_is_alerted_only_after_two_consecutive_failures_outside_the_grace_period(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20); // nothing for DATE -> records_present fails
        $this->writeLedger();
        $v = new SettleVerifier;

        // Pull finished 05:12. Two failures inside the 10 minute grace window: still no alert.
        $a = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('05:15'));
        $b = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('05:16'));
        $this->assertSame([], $a['alerts']);
        $this->assertSame([], $b['alerts']);
        $this->assertContains('amazon_close', $b['awaiting_confirmation']);
        $this->assertSame(0, SettleAlertLog::query()->count());
        $this->assertSame('verification_failed', $b['tasks']['amazon_close']['state'], 'the dashboard is Incomplete immediately, only the alert waits');

        // Grace over and 2+ consecutive failures: alert.
        $c = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('05:30'));
        $this->assertSame('missing_data', collect($c['alerts'])->firstWhere('task', 'amazon_close')['type']);
        $this->assertSame([], $c['awaiting_confirmation']);
    }

    public function test_a_failure_that_cleared_in_between_never_alerts(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20);
        $this->writeLedger();
        $v = new SettleVerifier;

        $fail1 = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('06:00')); // sibling task still writing
        $this->seedAmazonDay(self::DATE, 20);
        $pass = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('06:15'));
        DB::table('amazon_orders')->where('amazon_order_id', 'like', self::DATE.'-%')->delete();
        $fail2 = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('06:30'));

        $this->assertSame('pass', $pass['results']['amazon_close']['status']);
        $this->assertSame([], $fail1['alerts']);
        $this->assertSame([], $fail2['alerts'], 'only one failure since the last pass: not confirmed');
        $this->assertSame(0, SettleAlertLog::query()->count());
        $this->assertSame(1, SettleTracker::consecutiveFailures(self::DATE, 'amazon_close', $fail2['tasks']['amazon_close']['ledger_done_at']));
    }

    public function test_auth_failures_and_failed_pulls_still_alert_immediately(): void
    {
        $this->writeLedger(['temu' => ['done' => false, 'attempts' => ['0' => 3, '1' => 3, '2' => 3], 'error' => 'HTTP 401 unauthorized']]);
        SettleTracker::recordRun(self::DATE, 'temu', Ledger::tasks()['temu'], false, 'HTTP 401 unauthorized', "401 Unauthorized\n", 3, 1, $this->now());

        $out = (new SettleVerifier)->run(self::DATE, false, [], true, $this->now());

        $this->assertSame('auth_failure', collect($out['alerts'])->firstWhere('task', 'temu')['type']);
    }

    // ================================================================== FIX 7: webhook delivery tracking

    public function test_http_error_responses_are_never_recorded_as_delivered(): void
    {
        config(['settle.alert_webhook' => 'https://hooks.test/settle']);
        $status = 500;
        Http::fake(function () use (&$status) {
            return Http::response('boom', $status);
        });
        $keys = array_slice(array_keys(Ledger::tasks()), 0, 6);

        foreach ([500, 503, 400, 404, 429, 302] as $i => $code) {
            $status = $code;
            $task = $keys[$i];
            (new SettleAlerter)->dispatch(self::DATE, [$this->event($task)], $this->now());

            $row = SettleAlertLog::query()->where('task_key', $task)->first();
            $this->assertNotContains('webhook', $row->channels, "HTTP {$code} must not count as delivered");
            $this->assertStringContainsString('webhook: HTTP '.$code, implode('|', $row->failed_channels), "HTTP {$code} is recorded as a failed channel");
            $this->assertContains('log', $row->channels, 'the log channel itself did deliver');
        }
    }

    public function test_a_webhook_connection_error_is_recorded_as_failed_and_does_not_throw(): void
    {
        config(['settle.alert_webhook' => 'https://hooks.test/settle']);
        Http::fake(function () {
            throw new ConnectionException('connection refused');
        });

        (new SettleAlerter)->dispatch(self::DATE, [$this->event()], $this->now());

        $row = SettleAlertLog::query()->first();
        $this->assertNotContains('webhook', $row->channels);
        $this->assertStringContainsString('webhook:', implode('|', $row->failed_channels));
    }

    public function test_a_failed_webhook_is_retried_throttled_and_stops_once_delivered(): void
    {
        config(['settle.alert_webhook' => 'https://hooks.test/settle']);
        Http::fakeSequence()->pushStatus(500)->pushStatus(200);
        $alerter = new SettleAlerter;

        $alerter->dispatch(self::DATE, [$this->event()], $this->now('06:00'));
        $alerter->dispatch(self::DATE, [$this->event()], $this->now('06:05')); // inside the retry throttle
        Http::assertSentCount(1);

        $sent = $alerter->dispatch(self::DATE, [$this->event()], $this->now('06:11')); // retry -> 200
        Http::assertSentCount(2);
        $this->assertCount(1, $sent);
        $this->assertTrue(SettleAlertLog::query()->get()->contains(fn ($r) => in_array('webhook', $r->channels ?? [], true)));

        $alerter->dispatch(self::DATE, [$this->event()], $this->now('07:30')); // delivered everywhere: nothing more
        Http::assertSentCount(2);
        $this->assertSame(2, SettleAlertLog::query()->count());
    }

    public function test_failed_webhook_retries_are_capped(): void
    {
        config(['settle.alert_webhook' => 'https://hooks.test/settle', 'settle.alert_max_delivery_attempts' => 3, 'settle.alert_retry_minutes' => 10]);
        Http::fake(fn () => Http::response('down', 500));
        $alerter = new SettleAlerter;

        foreach (['06:00', '06:11', '06:22', '06:33', '06:44'] as $t) {
            $alerter->dispatch(self::DATE, [$this->event()], $this->now($t));
        }

        Http::assertSentCount(3);
    }

    public function test_a_log_only_alert_does_not_suppress_a_later_webhook_delivery(): void
    {
        // 1. nothing configured: log only.
        (new SettleAlerter)->dispatch(self::DATE, [$this->event()], $this->now('06:00'));
        $this->assertSame(['log'], SettleAlertLog::query()->first()->channels);

        // 2. a webhook is configured later: the SAME alert is still delivered there.
        config(['settle.alert_webhook' => 'https://hooks.test/settle']);
        Http::fake(fn () => Http::response('ok', 200));
        $sent = (new SettleAlerter)->dispatch(self::DATE, [$this->event()], $this->now('06:01'));

        Http::assertSentCount(1);
        $this->assertCount(1, $sent);
        $this->assertTrue(SettleAlertLog::query()->get()->contains(fn ($r) => $r->channels === ['webhook']));

        // 3. and is not repeated afterwards.
        (new SettleAlerter)->dispatch(self::DATE, [$this->event()], $this->now('06:30'));
        Http::assertSentCount(1);
    }

    public function test_the_same_alert_is_still_deduplicated_per_channel(): void
    {
        $alerter = new SettleAlerter;
        $first = $alerter->dispatch(self::DATE, [$this->event()], $this->now('06:00'));
        $second = $alerter->dispatch(self::DATE, [$this->event()], $this->now('06:10'));

        $this->assertCount(1, $first);
        $this->assertSame([], $second);
        $this->assertSame(1, SettleAlertLog::query()->count());
    }

    // ================================================================== FIX 8: periodic re-verification

    public function test_a_verified_table_that_later_loses_records_is_invalidated_by_the_periodic_reverification(): void
    {
        $this->makeSheinL60();
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, 'shein_l60');
        $this->sheinL60Rows(1000);
        $v = new SettleVerifier;

        $v->run(self::DATE, false, ['shein_l60'], false, $this->now('06:00'));
        $this->assertSame('full', $this->dayStatus('06:00')['level']);

        // The table is pruned / wiped after it was verified.
        DB::table('shein_daily_data_l60')->where('id', '>', 400)->delete();

        // Inside the re-verification interval the earlier result is simply not re-checked yet.
        $early = $v->run(self::DATE, false, ['shein_l60'], false, $this->now('06:30'));
        $this->assertArrayNotHasKey('shein_l60', $early['results']);

        // After reverify_verified_after_minutes (60) the task is checked again and now fails.
        $late = $v->run(self::DATE, false, ['shein_l60'], false, $this->now('07:05'));
        $this->assertArrayHasKey('shein_l60', $late['results']);
        $this->assertSame(VerificationResult::FAIL, $late['results']['shein_l60']['status']);
        $this->assertSame('verification_failed', $late['tasks']['shein_l60']['state']);

        $s = $this->dayStatus('07:05');
        $this->assertSame('incomplete', $s['level']);
        $this->assertFalse($s['settled']);
        $this->assertSame(400, DB::table('shein_daily_data_l60')->count(), 're-verification is read-only');
    }

    public function test_an_unchanged_verified_task_is_rechecked_hourly_but_limited_by_design_is_not(): void
    {
        $this->writeLedger();
        $v = new SettleVerifier;

        $v->run(self::DATE, false, ['wayfair_api'], false, $this->now('06:00'));
        $again = $v->run(self::DATE, false, ['wayfair_api'], false, $this->now('08:00'));
        $this->assertArrayNotHasKey('wayfair_api', $again['results'], 'nothing to re-check for a by-design limited task');
        $this->assertSame('limited_by_design', $again['tasks']['wayfair_api']['state']);

        // A task that can really be checked is re-verified after 60 minutes.
        $this->makeSheinL60();
        $this->sheinL60Rows(500);
        $v->run(self::DATE, false, ['shein_l60'], false, $this->now('06:00'));
        $this->assertSame(1, SettleVerification::query()->where('task_key', 'shein_l60')->count());
        $v->run(self::DATE, false, ['shein_l60'], false, $this->now('06:30'));
        $this->assertSame(1, SettleVerification::query()->where('task_key', 'shein_l60')->count(), 'not before 60 minutes');
        $v->run(self::DATE, false, ['shein_l60'], false, $this->now('07:05'));
        $this->assertSame(2, SettleVerification::query()->where('task_key', 'shein_l60')->count());
    }

    public function test_a_stale_verification_cannot_keep_the_day_fully_settled(): void
    {
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, null, $this->now('06:00'));
        $this->assertSame('full', $this->dayStatus('06:00')['level']);
        $this->assertSame('full', $this->dayStatus('08:50')['level'], 'still fresh (170 minutes)');

        // The verifier stopped running: after verification_max_age_minutes (180) it is stale.
        $stale = $this->dayStatus('09:05');

        $this->assertSame('pending', $stale['level']);
        $this->assertFalse($stale['settled']);
        $this->assertContains('amazon_close', $stale['verification']['unverified']);
    }

    public function test_limited_by_design_results_never_go_stale(): void
    {
        $this->writeLedger();
        $this->verifyAll(VerificationResult::LIMITED_BY_DESIGN, null, $this->now('06:00'));

        $this->assertSame('full', $this->dayStatus('12:00')['level']);
    }

    public function test_reverification_never_modifies_marketplace_tables(): void
    {
        $this->makeSheinL60();
        $this->writeLedger();
        $this->sheinL60Rows(300);
        $v = new SettleVerifier;

        $v->run(self::DATE, true, ['shein_l60'], false, $this->now('06:00'));
        $v->run(self::DATE, true, ['shein_l60'], false, $this->now('08:00'));

        $this->assertSame(300, DB::table('shein_daily_data_l60')->count());
        $this->assertSame(2, SettleVerification::query()->where('task_key', 'shein_l60')->count(), 'history is insert-only');
        $this->assertSame(1, SettleTaskRun::query()->where('task_key', 'shein_l60')->count());
    }
}
