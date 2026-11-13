<?php

namespace Tests\Unit\Settle;

use App\Models\SettleAlertLog;
use App\Models\SettleTaskRun;
use App\Models\SettleVerification;
use App\Support\Marketplace\Settle\SettleAlerter;
use App\Support\Marketplace\Settle\SettleTracker;
use App\Support\Marketplace\Settle\SettleVerifier;
use App\Support\Marketplace\Settle\Verification\VerificationResult;
use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SettleStatusAndAlertsTest extends SettleTestCase
{
    /** 06:00 PT the morning after DATE: passes 0..2 are all due; before the 08:30 cutoff. */
    private function now(string $time = '06:00'): Carbon
    {
        return Carbon::parse(self::DATE, 'America/Los_Angeles')->addDay()->setTimeFromTimeString($time);
    }

    /**
     * Writes a ledger file directly (Ledger::write also prunes old files in the real folder).
     *
     * @param  array<string, array<string, mixed>>  $overrides  per task key: ['done' => [...]|false, 'attempts' => [...], 'error' => '...']
     */
    private function writeLedger(array $overrides = []): void
    {
        $tasks = [];
        foreach (Ledger::tasks() as $key => $task) {
            $done = [];
            foreach ($task['passes'] as $p) {
                $done[(string) $p] = '2026-11-13T05:'.str_pad((string) (10 + $p), 2, '0', STR_PAD_LEFT).':00-08:00';
            }
            $tasks[$key] = ['done' => $done, 'attempts' => array_fill_keys(array_map('strval', $task['passes']), 1)];
            if (isset($overrides[$key])) {
                $o = $overrides[$key];
                if (($o['done'] ?? null) === false) {
                    $tasks[$key]['done'] = [];
                } elseif (isset($o['done'])) {
                    $tasks[$key]['done'] = $o['done'];
                }
                if (isset($o['attempts'])) {
                    $tasks[$key]['attempts'] = $o['attempts'];
                }
                if (isset($o['error'])) {
                    $tasks[$key]['error'] = $o['error'];
                }
            }
        }
        File::ensureDirectoryExists(dirname(Ledger::path(self::DATE)));
        file_put_contents(Ledger::path(self::DATE), json_encode(['date' => self::DATE, 'tasks' => $tasks]));
    }

    private function verifyAll(string $status = VerificationResult::PASS, ?string $except = null): void
    {
        $desc = SettleTracker::describe(self::DATE, Ledger::read(self::DATE), 2, false, $this->now());
        foreach ($desc['tasks'] as $key => $t) {
            if ($key === $except) {
                continue;
            }
            SettleTracker::saveVerification(self::DATE, $key, new VerificationResult($status, [['code' => 'x', 'status' => $status, 'message' => 'test']]), $t['ledger_done_at'], 1);
        }
    }

    // ---------------------------------------------------------------- migration

    public function test_migration_creates_only_new_tables_and_is_repeatable(): void
    {
        $migration = include base_path('database/migrations/2026_11_13_060000_create_settle_tracking_tables.php');
        $migration->up(); // second run (setUp already ran it): hasTable guards make it a no-op

        foreach (['settle_task_runs', 'settle_verifications', 'settle_alert_logs'] as $t) {
            $this->assertTrue(Schema::hasTable($t), $t);
        }
        $this->assertTrue(Schema::hasColumns('settle_task_runs', ['settle_date', 'task_key', 'marketplace', 'state', 'attempts', 'last_error', 'next_retry_at', 'verification_status', 'verified_for_done_at']));

        $migration->down();
        foreach (['settle_task_runs', 'settle_verifications', 'settle_alert_logs'] as $t) {
            $this->assertFalse(Schema::hasTable($t), $t);
        }
    }

    // ---------------------------------------------------------------- never settled without verification

    public function test_fully_pulled_ledger_is_not_settled_until_every_task_is_verified(): void
    {
        $this->writeLedger();

        $s = Ledger::status(self::DATE, $this->now());

        $this->assertTrue($s['ledger_settled'], 'every pull finished (old rule)');
        $this->assertFalse($s['settled'], 'but nothing is verified yet');
        $this->assertFalse($s['verification']['all_verified']);
        $this->assertNull($s['settled_at']);
        $this->assertSame(count(Ledger::tasks()), count($s['verification']['unverified']));

        $this->verifyAll();
        $s = Ledger::status(self::DATE, $this->now());
        $this->assertTrue($s['settled']);
        $this->assertTrue($s['verification']['all_verified']);
        $this->assertNotNull($s['settled_at']);
    }

    public function test_one_failed_verification_blocks_settled_and_names_the_task(): void
    {
        $this->writeLedger();
        $this->verifyAll(VerificationResult::PASS, 'tiktok');
        $doneAt = SettleTracker::describe(self::DATE, Ledger::read(self::DATE), 2, false, $this->now())['tasks']['tiktok']['ledger_done_at'];
        SettleTracker::saveVerification(self::DATE, 'tiktok', VerificationResult::error('x', 'bad'), $doneAt, 1);

        $s = Ledger::status(self::DATE, $this->now());

        $this->assertFalse($s['settled']);
        $this->assertContains('tiktok', $s['verification']['failed']);
        $this->assertSame('verification_failed', $s['tasks']['tiktok']['state']);
        $this->assertSame('TikTok', $s['tasks']['tiktok']['marketplace']);
    }

    public function test_a_verification_of_an_older_pull_does_not_count_after_the_task_ran_again(): void
    {
        $this->writeLedger();
        $this->verifyAll();
        $this->assertTrue(Ledger::status(self::DATE, $this->now())['settled']);

        // tiktok is pulled again later (new "done" timestamp) -> the earlier verification is stale.
        $this->writeLedger(['tiktok' => ['done' => ['0' => '2026-11-13T05:55:00-08:00', '1' => '2026-11-13T05:56:00-08:00', '2' => '2026-11-13T05:57:00-08:00']]]);
        $s = Ledger::status(self::DATE, $this->now());

        $this->assertFalse($s['settled']);
        $this->assertSame('awaiting_verification', $s['tasks']['tiktok']['state']);
        $this->assertSame('stale', $s['tasks']['tiktok']['verification']['status']);
    }

    public function test_limited_verification_counts_as_verified_but_pending_does_not(): void
    {
        $this->writeLedger();
        $this->verifyAll(VerificationResult::LIMITED);
        $this->assertTrue(Ledger::status(self::DATE, $this->now())['settled']);

        $this->verifyAll(VerificationResult::PENDING);
        $this->assertFalse(Ledger::status(self::DATE, $this->now())['settled']);
    }

    public function test_when_the_tracking_tables_are_missing_the_day_is_never_settled(): void
    {
        $this->writeLedger();
        Schema::drop('settle_task_runs');

        $s = Ledger::status(self::DATE, $this->now());

        $this->assertTrue($s['ledger_settled']);
        $this->assertFalse($s['settled']);
        $this->assertFalse($s['verification']['available']);
    }

    public function test_verification_can_be_switched_off_by_config_to_restore_the_old_rule(): void
    {
        config(['settle.require_verification' => false]);
        $this->writeLedger();

        $s = Ledger::status(self::DATE, $this->now());

        $this->assertTrue($s['settled']);
    }

    public function test_legacy_status_keys_are_unchanged(): void
    {
        $this->writeLedger();
        $s = Ledger::status(self::DATE, $this->now());

        foreach (['date', 'settled', 'passes_due', 'passes_total', 'pending', 'failed', 'settled_at', 'deadline_passed'] as $k) {
            $this->assertArrayHasKey($k, $s);
        }
        $this->assertSame(3, $s['passes_due']);
        $this->assertSame([], $s['pending']);
        $this->assertSame([], $s['failed']);
    }

    public function test_incomplete_ledger_keeps_the_old_pending_and_failed_lists(): void
    {
        $this->writeLedger([
            'tiktok' => ['done' => false, 'attempts' => ['0' => 1]],
            'temu' => ['done' => false, 'attempts' => ['0' => 3, '1' => 3, '2' => 3], 'error' => 'HTTP 401 unauthorized'],
        ]);

        $s = Ledger::status(self::DATE, $this->now());

        $this->assertFalse($s['settled']);
        $this->assertContains('tiktok', $s['pending']);
        $this->assertContains('temu', $s['failed']);
    }

    // ---------------------------------------------------------------- per-task visibility

    public function test_per_task_state_attempts_error_and_next_retry(): void
    {
        $this->writeLedger([
            'tiktok' => ['done' => false, 'attempts' => ['0' => 1], 'error' => 'exit 1: boom'],
            'temu' => ['done' => false, 'attempts' => ['0' => 3, '1' => 3, '2' => 3], 'error' => 'HTTP 401 unauthorized'],
            'wayfair' => ['done' => false, 'attempts' => []],
        ]);

        $d = SettleTracker::describe(self::DATE, Ledger::read(self::DATE), 2, false, $this->now('06:07'))['tasks'];

        $this->assertSame('retry_wait', $d['tiktok']['state']);
        $this->assertSame(1, $d['tiktok']['attempts']);
        $this->assertSame('exit 1: boom', $d['tiktok']['last_error']);
        $this->assertSame('2026-11-13T06:10:00-08:00', $d['tiktok']['next_retry_at']);

        $this->assertSame('failed', $d['temu']['state']);
        $this->assertSame(3, $d['temu']['attempts']);
        $this->assertSame('HTTP 401 unauthorized', $d['temu']['last_error']);
        $this->assertNull($d['temu']['next_retry_at']);
        $this->assertStringContainsString('No more retries', $d['temu']['next_retry_note']);

        $this->assertSame('pending', $d['wayfair']['state']);
    }

    public function test_no_retry_is_promised_after_the_cutoff_or_deadline(): void
    {
        $this->writeLedger(['tiktok' => ['done' => false, 'attempts' => ['0' => 1], 'error' => 'boom']]);

        $cut = SettleTracker::describe(self::DATE, Ledger::read(self::DATE), 2, false, $this->now('08:40'))['tasks']['tiktok'];
        $this->assertNull($cut['next_retry_at']);
        $this->assertStringContainsString('08:30', $cut['next_retry_note']);

        $dead = SettleTracker::describe(self::DATE, Ledger::read(self::DATE), 2, true, $this->now('09:05'))['tasks']['tiktok'];
        $this->assertSame('failed', $dead['state']);
        $this->assertStringContainsString('09:00', $dead['next_retry_note']);
    }

    public function test_tasks_not_due_yet_are_not_reported_as_failures(): void
    {
        $this->writeLedger(['tiktok' => ['done' => false, 'attempts' => []]]);

        $d = SettleTracker::describe(self::DATE, ['tasks' => []], -1, false, $this->now('00:10'))['tasks'];

        $this->assertSame('not_due', $d['tiktok']['state']);
    }

    public function test_record_run_stores_error_class_and_output_markers(): void
    {
        SettleTracker::recordRun(self::DATE, 'temu', Ledger::tasks()['temu'], false, 'exit 1', "Error: 401 Unauthorized\n", 12, 1, now());
        $row = SettleTaskRun::query()->where('task_key', 'temu')->first();
        $this->assertSame('auth', $row->error_class);
        $this->assertStringContainsString('exit 1', $row->last_error);
        $this->assertSame(1, $row->last_exit_code);

        SettleTracker::recordRun(self::DATE, 'tiktok', Ledger::tasks()['tiktok'], true, '', "Failed to get access token\n", 5, 0, now());
        $ok = SettleTaskRun::query()->where('task_key', 'tiktok')->first();
        $this->assertNull($ok->last_error, 'exit 0: the ledger still calls it done');
        $this->assertSame('auth', $ok->output_markers[0]['type']);
    }

    // ---------------------------------------------------------------- verifier orchestration + alerts

    public function test_exit_zero_with_auth_error_text_is_not_verified_and_raises_an_auth_alert(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20);
        $this->seedAmazonDay(self::DATE, 20); // data looks fine...
        $this->writeLedger();
        // ...but the command printed an auth failure while exiting 0.
        SettleTracker::recordRun(self::DATE, 'amazon_close', Ledger::tasks()['amazon_close'], true, '', "Failed to get access token\n", 5, 0, now());

        $out = (new SettleVerifier)->run(self::DATE, true, ['amazon_close'], true, $this->now());

        $this->assertSame('fail', $out['results']['amazon_close']['status']);
        $this->assertSame('verification_failed', $out['tasks']['amazon_close']['state']);
        $types = array_column($out['alerts'], 'type');
        $this->assertContains('auth_failure', $types);
        $this->assertFalse(Ledger::status(self::DATE, $this->now())['settled']);
    }

    public function test_missing_data_raises_a_missing_data_alert_naming_the_marketplace_and_check(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20); // nothing for DATE
        $this->writeLedger();

        $v = new SettleVerifier;
        $first = $v->run(self::DATE, true, ['amazon_close'], true, $this->now());
        $this->assertSame([], $first['alerts'], 'a data-based failure is only alerted after it was confirmed (fix 6)');
        $out = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('06:15'));

        $alert = collect($out['alerts'])->firstWhere('task', 'amazon_close');
        $this->assertSame('missing_data', $alert['type']);
        $this->assertStringContainsString('Amazon', $alert['title']);
        $this->assertStringContainsString('records_present', $alert['message']);
        $this->assertSame(1, SettleAlertLog::query()->where('task_key', 'amazon_close')->count());
    }

    public function test_the_same_alert_is_not_repeated_and_mail_is_attempted_when_configured(): void
    {
        config(['settle.alert_emails' => ['ops@example.com']]);
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20);
        $this->writeLedger();
        $v = new SettleVerifier;

        $unconfirmed = $v->run(self::DATE, true, ['amazon_close'], true, $this->now());
        $first = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('06:15'));
        $second = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('06:30'));

        $this->assertSame([], $unconfirmed['alerts']);
        $this->assertNotEmpty($first['alerts']);
        $this->assertSame([], $second['alerts'], 'deduplicated within settle.alert_repeat_hours');
        $log = SettleAlertLog::query()->first();
        $this->assertContains('log', $log->channels);
        $this->assertContains('mail', $log->channels);
        $this->assertSame(1, SettleAlertLog::query()->where('task_key', 'amazon_close')->count());

        // after the repeat window it is sent again
        $third = $v->run(self::DATE, true, ['amazon_close'], true, $this->now('06:30')->addHours(13));
        $this->assertNotEmpty($third['alerts']);
    }

    public function test_failed_tasks_alert_with_attempts_and_last_error(): void
    {
        $this->writeLedger(['temu' => ['done' => false, 'attempts' => ['0' => 3, '1' => 3, '2' => 3], 'error' => 'HTTP 401 unauthorized']]);
        SettleTracker::recordRun(self::DATE, 'temu', Ledger::tasks()['temu'], false, 'HTTP 401 unauthorized', "401 Unauthorized\n", 3, 1, now());

        $out = (new SettleVerifier)->run(self::DATE, false, [], true, $this->now());

        $alert = collect($out['alerts'])->firstWhere('task', 'temu');
        $this->assertSame('auth_failure', $alert['type']);
        $this->assertStringContainsString('3/3', $alert['message']);
        $this->assertStringContainsString('HTTP 401 unauthorized', $alert['message']);
    }

    public function test_verification_history_is_insert_only_and_does_not_touch_other_tables(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20);
        $this->seedAmazonDay(self::DATE, 20);
        $this->writeLedger();
        $before = [\DB::table('amazon_orders')->count(), \DB::table('amazon_order_items')->sum('price')];

        $v = new SettleVerifier;
        $v->run(self::DATE, true, ['amazon_close'], false, $this->now());
        $v->run(self::DATE, true, ['amazon_close'], false, $this->now('06:30'));

        $this->assertSame(2, SettleVerification::query()->where('task_key', 'amazon_close')->count(), 'history keeps every check');
        $this->assertSame($before, [\DB::table('amazon_orders')->count(), \DB::table('amazon_order_items')->sum('price')]);
        $this->assertSame('verified', SettleTaskRun::query()->where('task_key', 'amazon_close')->value('state'));
    }

    public function test_alert_fingerprint_is_stable_and_order_independent(): void
    {
        $a = ['type' => 'missing_data', 'task' => 'tiktok', 'codes' => ['b', 'a']];
        $b = ['type' => 'missing_data', 'task' => 'tiktok', 'codes' => ['a', 'b']];

        $this->assertSame(SettleAlerter::fingerprint(self::DATE, $a), SettleAlerter::fingerprint(self::DATE, $b));
        $this->assertNotSame(SettleAlerter::fingerprint(self::DATE, $a), SettleAlerter::fingerprint('2026-11-11', $a));
    }

    public function test_verifier_does_nothing_when_tracking_tables_are_missing(): void
    {
        $this->writeLedger();
        Schema::drop('settle_task_runs');

        $out = (new SettleVerifier)->run(self::DATE, true, [], false, $this->now());

        $this->assertFalse($out['available']);
    }
}
