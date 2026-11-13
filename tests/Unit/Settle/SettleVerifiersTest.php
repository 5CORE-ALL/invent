<?php

namespace Tests\Unit\Settle;

use App\Models\SettleVerification;
use App\Support\Marketplace\Settle\SettleOutputInspector;
use App\Support\Marketplace\Settle\Verification\AdsReportVerifier;
use App\Support\Marketplace\Settle\Verification\OrdersWindowVerifier;
use App\Support\Marketplace\Settle\Verification\VerificationResult;
use App\Support\Marketplace\Settle\Verification\VerifierRegistry;
use App\Support\Marketplace\Settle\Verification\ViewsVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettleVerifiersTest extends SettleTestCase
{
    private function amazon(bool $finalPass = true): VerificationResult
    {
        return VerifierRegistry::for('amazon_close')->verify(self::DATE, $this->ctx('amazon_close', $finalPass));
    }

    // ---------------------------------------------------------------- orders: records / volume

    public function test_zero_records_on_a_channel_that_normally_sells_is_a_failure(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20);

        $r = $this->amazon();

        $this->assertSame(VerificationResult::FAIL, $r->status);
        $this->assertContains('records_present', $this->codes($r->checks, 'fail'));
        $this->assertFalse($r->isVerified());
    }

    public function test_a_normal_complete_day_passes(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20);
        $this->seedAmazonDay(self::DATE, 20);

        $r = $this->amazon();

        $this->assertSame(VerificationResult::PASS, $r->status, json_encode($r->checks));
        $this->assertTrue($r->isVerified());
        $this->assertSame(20, $r->metrics['rows']);
    }

    public function test_zero_records_without_history_is_only_a_warning(): void
    {
        $this->makeAmazonTables();

        $r = $this->amazon();

        $this->assertSame(VerificationResult::WARN, $r->status);
        $this->assertTrue($r->isVerified(), 'a quiet / new channel must not block settlement forever');
    }

    public function test_far_below_usual_volume_fails_and_moderately_low_warns(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(40);

        $this->seedAmazonDay(self::DATE, 3);
        $this->assertSame(VerificationResult::FAIL, $this->amazon()->status);

        DB::table('amazon_order_items')->delete();
        DB::table('amazon_orders')->delete();
        $this->seedAmazonBaseline(40);
        $this->seedAmazonDay(self::DATE, 8);
        $r = $this->amazon();
        $this->assertSame(VerificationResult::WARN, $r->status);
        $this->assertContains('records_present', $this->codes($r->checks, 'warn'));
    }

    public function test_newest_record_much_earlier_than_usual_warns_about_stopped_pagination(): void
    {
        $this->makeAmazonTables();
        for ($i = 1; $i <= 7; $i++) {
            $this->seedAmazonDay(\Carbon\Carbon::parse(self::DATE)->subDays($i)->toDateString(), 20, ['hour' => 22]);
        }
        $this->seedAmazonDay(self::DATE, 20, ['hour' => 9]);

        $r = $this->amazon();

        $this->assertSame(VerificationResult::WARN, $r->status);
        $this->assertContains('latest_record', $this->codes($r->checks, 'warn'));
    }

    // ---------------------------------------------------------------- duplicates

    public function test_duplicate_records_are_detected(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20);
        $this->seedAmazonDay(self::DATE, 20, ['dupIds' => 4]); // 4 extra rows = 16.7% of 24

        $r = $this->amazon();

        $this->assertSame(VerificationResult::FAIL, $r->status);
        $this->assertContains('duplicates', $this->codes($r->checks, 'fail'));
        $this->assertSame(4, $r->metrics['duplicate_rows']);
    }

    public function test_a_single_duplicate_is_only_a_warning(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(40);
        $this->seedAmazonDay(self::DATE, 40, ['dupIds' => 1]);

        $r = $this->amazon();

        $this->assertSame(VerificationResult::WARN, $r->status);
        $this->assertContains('duplicates', $this->codes($r->checks, 'warn'));
    }

    // ---------------------------------------------------------------- zero values / missing items

    public function test_orders_without_line_items_fail(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20);
        $this->seedAmazonDay(self::DATE, 20, ['noItems' => 10]);

        $r = $this->amazon();

        $this->assertSame(VerificationResult::FAIL, $r->status);
        $this->assertContains('order_items', $this->codes($r->checks, 'fail'));
    }

    public function test_pending_zero_prices_are_tolerated_until_the_late_pass_is_done(): void
    {
        $this->makeAmazonTables();
        $this->seedAmazonBaseline(20);
        $this->seedAmazonDay(self::DATE, 20, ['zeroPrice' => 12]); // 60% unpriced

        $early = $this->amazon(false);
        $late = $this->amazon(true);

        $this->assertSame(VerificationResult::WARN, $early->status, 'before the late pass: warning only');
        $this->assertSame(VerificationResult::FAIL, $late->status, 'after the late pass 60% zero prices is a failure');
        $this->assertContains('zero_price_items', $this->codes($late->checks, 'fail'));
    }

    // ---------------------------------------------------------------- missing table / group by / table-only

    public function test_missing_table_is_an_error_and_not_verified(): void
    {
        $r = $this->amazon();

        $this->assertSame(VerificationResult::ERROR, $r->status);
        $this->assertFalse($r->isVerified());
    }

    public function test_each_mirakl_channel_is_checked_separately(): void
    {
        Schema::create('mirakl_daily_data', function (Blueprint $t) {
            $t->id();
            $t->string('channel_name')->nullable();
            $t->string('order_id');
            $t->string('order_line_id');
            $t->dateTime('order_created_at');
            $t->decimal('unit_price', 10, 2)->nullable();
        });
        $insert = function (string $ymd, string $channel, int $n) {
            $utc = \Carbon\Carbon::parse($ymd.' 12:00', 'America/Los_Angeles')->utc()->format('Y-m-d H:i:s');
            for ($i = 0; $i < $n; $i++) {
                DB::table('mirakl_daily_data')->insert(['channel_name' => $channel, 'order_id' => "$ymd-$channel-$i", 'order_line_id' => '1', 'order_created_at' => $utc, 'unit_price' => 5]);
            }
        };
        for ($i = 1; $i <= 7; $i++) {
            $d = \Carbon\Carbon::parse(self::DATE)->subDays($i)->toDateString();
            $insert($d, 'Best Buy USA', 10);
            $insert($d, "Macy's, Inc.", 10);
        }
        $insert(self::DATE, 'Best Buy USA', 10); // Macy's is missing

        $r = VerifierRegistry::for('mirakl')->verify(self::DATE, $this->ctx('mirakl'));

        $this->assertSame(VerificationResult::FAIL, $r->status);
        $this->assertStringContainsString("Macy's, Inc.", $r->checks[0]['message']);
    }

    public function test_an_empty_prune_prone_table_fails_and_a_big_shrink_fails(): void
    {
        Schema::create('shein_daily_data_l60', function (Blueprint $t) {
            $t->id();
            $t->string('order_number')->nullable();
        });

        $empty = VerifierRegistry::for('shein_l60')->verify(self::DATE, $this->ctx('shein_l60'));
        $this->assertSame(VerificationResult::FAIL, $empty->status);
        $this->assertContains('table_not_empty', $this->codes($empty->checks, 'fail'));

        SettleVerification::query()->create(['settle_date' => '2026-11-11', 'task_key' => 'shein_l60', 'status' => 'pass', 'metrics' => ['table_rows' => 500]]);
        DB::table('shein_daily_data_l60')->insert(array_fill(0, 100, ['order_number' => 'x']));
        $shrunk = VerifierRegistry::for('shein_l60')->verify(self::DATE, $this->ctx('shein_l60'));
        $this->assertSame(VerificationResult::FAIL, $shrunk->status);
        $this->assertContains('table_shrunk', $this->codes($shrunk->checks, 'fail'));

        DB::table('shein_daily_data_l60')->insert(array_fill(0, 350, ['order_number' => 'y']));
        $ok = VerifierRegistry::for('shein_l60')->verify(self::DATE, $this->ctx('shein_l60'));
        $this->assertSame(VerificationResult::PASS, $ok->status);
    }

    // ---------------------------------------------------------------- ads

    private function makeSpTable(): void
    {
        Schema::create('amazon_sp_campaign_reports', function (Blueprint $t) {
            $t->id();
            $t->string('profile_id');
            $t->string('campaign_id')->nullable();
            $t->string('report_date_range');
            $t->decimal('cost', 12, 4)->nullable();
            $t->decimal('spend', 12, 4)->nullable();
        });
    }

    private function spRows(string $key, int $campaigns, float $costEach, string $prefix = 'c'): void
    {
        for ($i = 0; $i < $campaigns; $i++) {
            DB::table('amazon_sp_campaign_reports')->insert(['profile_id' => 'p1', 'campaign_id' => $prefix.$i, 'report_date_range' => $key, 'cost' => $costEach, 'spend' => $costEach]);
        }
    }

    public function test_ads_rows_of_zeros_when_a_usual_day_has_spend_fail(): void
    {
        $this->makeSpTable();
        for ($i = 1; $i <= 7; $i++) {
            $this->spRows(\Carbon\Carbon::parse(self::DATE)->subDays($i)->toDateString(), 10, 5.0);
        }
        $this->spRows(self::DATE, 10, 0.0); // zero placeholder rows from a failed report

        $r = VerifierRegistry::for('ads_amazon_sp')->verify(self::DATE, $this->ctx('ads_amazon_sp'));

        $this->assertSame(VerificationResult::FAIL, $r->status);
        $this->assertContains('ads_all_zero', $this->codes($r->checks, 'fail'));
    }

    public function test_ads_with_no_rows_for_the_date_fails_and_normal_ads_pass(): void
    {
        $this->makeSpTable();
        for ($i = 1; $i <= 7; $i++) {
            $this->spRows(\Carbon\Carbon::parse(self::DATE)->subDays($i)->toDateString(), 10, 5.0);
        }
        $none = VerifierRegistry::for('ads_amazon_sp')->verify(self::DATE, $this->ctx('ads_amazon_sp'));
        $this->assertSame(VerificationResult::FAIL, $none->status);
        $this->assertContains('records_present', $this->codes($none->checks, 'fail'));

        $this->spRows(self::DATE, 10, 5.0);
        $ok = VerifierRegistry::for('ads_amazon_sp')->verify(self::DATE, $this->ctx('ads_amazon_sp'));
        $this->assertSame(VerificationResult::PASS, $ok->status, json_encode($ok->checks));
    }

    public function test_repeated_amazon_campaign_rows_are_collapsed_like_the_dashboard_not_failed(): void
    {
        $this->makeSpTable();
        for ($i = 1; $i <= 7; $i++) {
            $this->spRows(\Carbon\Carbon::parse(self::DATE)->subDays($i)->toDateString(), 10, 5.0);
        }
        $this->spRows(self::DATE, 10, 5.0);
        $this->spRows(self::DATE, 3, 5.0); // same campaign ids again (no unique index on this table)

        $r = VerifierRegistry::for('ads_amazon_sp')->verify(self::DATE, $this->ctx('ads_amazon_sp'));

        $this->assertSame(VerificationResult::PASS, $r->status, json_encode($r->checks));
        $this->assertSame(3, $r->metrics['collapsed_duplicate_rows']);
        $this->assertSame(10, $r->metrics['rows'], 'distinct campaigns, MAX spend per campaign (amazonDistinctCampaignTotals)');
        $this->assertEqualsWithDelta(50.0, $r->metrics['spend'], 0.01, 'repeats must not double the spend');
    }

    public function test_ebay_ads_spend_strings_are_summed(): void
    {
        Schema::create('ebay_priority_reports', function (Blueprint $t) {
            $t->id();
            $t->string('report_range');
            $t->string('campaign_id');
            $t->string('cpc_ad_fees_payout_currency')->nullable();
        });
        for ($i = 1; $i <= 7; $i++) {
            DB::table('ebay_priority_reports')->insert(['report_range' => \Carbon\Carbon::parse(self::DATE)->subDays($i)->toDateString(), 'campaign_id' => 'a', 'cpc_ad_fees_payout_currency' => 'USD 1,200.50']);
        }
        DB::table('ebay_priority_reports')->insert(['report_range' => self::DATE, 'campaign_id' => 'a', 'cpc_ad_fees_payout_currency' => 'USD 0']);

        $r = VerifierRegistry::for('ads_ebay')->verify(self::DATE, $this->ctx('ads_ebay'));

        $this->assertSame(VerificationResult::FAIL, $r->status);
        $this->assertEqualsWithDelta(1200.5, $r->metrics['baseline_median_spend'], 0.01);
    }

    // ---------------------------------------------------------------- views

    private function makeViewsTable(): void
    {
        Schema::create('channel_yesterday_views', function (Blueprint $t) {
            $t->id();
            $t->string('channel');
            $t->date('snapshot_date');
            $t->unsignedInteger('views')->default(0);
        });
    }

    public function test_views_wait_for_the_task_that_stores_them(): void
    {
        $this->makeViewsTable();

        $r = VerifierRegistry::for('views_ebay')->verify(self::DATE, $this->ctx('views_ebay', true, null, fn ($k) => false));

        $this->assertSame(VerificationResult::PENDING, $r->status);
        $this->assertFalse($r->isVerified());
    }

    public function test_stored_zero_views_fail_when_the_channel_normally_has_views(): void
    {
        $this->makeViewsTable();
        for ($i = 1; $i <= 7; $i++) {
            DB::table('channel_yesterday_views')->insert(['channel' => 'ebay', 'snapshot_date' => \Carbon\Carbon::parse(self::DATE)->subDays($i)->toDateString(), 'views' => 1000]);
        }
        DB::table('channel_yesterday_views')->insert(['channel' => 'ebay', 'snapshot_date' => self::DATE, 'views' => 0]);

        $r = VerifierRegistry::for('views_ebay')->verify(self::DATE, $this->ctx('views_ebay'));
        $this->assertSame(VerificationResult::FAIL, $r->status);
        $this->assertContains('views_missing', $this->codes($r->checks, 'fail'));

        DB::table('channel_yesterday_views')->where('snapshot_date', self::DATE)->update(['views' => 900]);
        $ok = VerifierRegistry::for('views_ebay')->verify(self::DATE, $this->ctx('views_ebay'));
        $this->assertSame(VerificationResult::PASS, $ok->status);
    }

    public function test_tasks_without_a_dated_source_are_limited_by_design_never_a_full_pass(): void
    {
        $r = VerifierRegistry::for('views_temu3')->verify(self::DATE, $this->ctx('views_temu3'));
        $this->assertSame(VerificationResult::LIMITED_BY_DESIGN, $r->status);
        $this->assertTrue($r->isVerified());
        $this->assertFalse($r->isWarning(), 'by design is not a warning');

        $w = VerifierRegistry::for('wayfair_api')->verify(self::DATE, $this->ctx('wayfair_api'));
        $this->assertSame(VerificationResult::LIMITED_BY_DESIGN, $w->status);
    }

    // ---------------------------------------------------------------- output inspector (incomplete pagination / auth)

    public function test_auth_failure_printed_by_a_command_that_exits_zero_is_detected(): void
    {
        $out = "Starting Amazon sync\nFailed to get access token\nDone";
        $insp = SettleOutputInspector::inspect($out);

        $this->assertSame('auth', $insp['error_class']);
        $codes = array_column(SettleOutputInspector::checks($insp['markers']), 'code');
        $this->assertContains('auth_failure', $codes);
    }

    public function test_failed_pages_are_reported_as_incomplete_pagination_and_rate_limits_only_warn(): void
    {
        $insp = SettleOutputInspector::inspect("Page 3 failed (1/5 consecutive). Skipping.\nRate limit hit, sleeping 30s");
        $checks = SettleOutputInspector::checks($insp['markers']);
        $by = array_column($checks, 'status', 'code');

        $this->assertSame('fail', $by['incomplete_pagination']);
        $this->assertSame('warn', $by['rate_limited']);
    }

    public function test_normal_output_has_no_markers(): void
    {
        $insp = SettleOutputInspector::inspect("Fetched 429 orders in 12 pages\nProcessed 1500 items\nDone.");

        $this->assertSame([], $insp['markers']);
        $this->assertNull($insp['error_class']);
    }

    public function test_verification_result_precedence_and_verified_flags(): void
    {
        $checks = fn (string ...$s) => array_map(fn ($x, $i) => ['code' => 'c'.$i, 'status' => $x, 'message' => 'm'], $s, array_keys($s));

        $this->assertSame('fail', VerificationResult::fromChecks($checks('pass', 'warn', 'fail', 'limited'))->status);
        $this->assertSame('pending', VerificationResult::fromChecks($checks('pass', 'pending', 'warn'))->status);
        $this->assertSame('warn', VerificationResult::fromChecks($checks('pass', 'limited', 'warn'))->status);
        $this->assertSame('limited', VerificationResult::fromChecks($checks('pass', 'limited'))->status);
        $this->assertTrue(VerificationResult::fromChecks($checks('pass', 'warn'))->isVerified());
        $this->assertFalse(VerificationResult::fromChecks($checks('pending'))->isVerified());
        $this->assertFalse(VerificationResult::fromChecks($checks('error'))->isVerified());
    }

    public function test_unknown_task_keys_fall_back_to_limited_output_only(): void
    {
        $r = VerifierRegistry::for('some_future_task')->verify(self::DATE, $this->ctx('amazon_close'));
        $this->assertSame(VerificationResult::LIMITED, $r->status);
    }
}
