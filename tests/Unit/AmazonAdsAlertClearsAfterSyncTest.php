<?php

namespace Tests\Unit;

use App\Support\AmazonAdsLiveSyncStatus;
use PHPUnit\Framework\TestCase;

/**
 * The red "!" in the SBID Alert / SBGT Alert columns on /amazon-ads/all.
 * It must show while a bid or budget is really out of step, and be gone once the background sync
 * has made Lbid = SBID and Lbgt = SBGT.
 */
class AmazonAdsAlertClearsAfterSyncTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $bidState
     * @param  array<string, mixed>|null  $bgtState
     * @return array<string, mixed>
     */
    private function attach(array $row, ?array $bidState = null, ?array $bgtState = null): array
    {
        $row += ['campaign_id' => '111'];
        $states = ['bid' => [], 'bgt' => []];
        if ($bidState !== null) {
            $states['bid']['111'] = $bidState;
        }
        if ($bgtState !== null) {
            $states['bgt']['111'] = $bgtState;
        }

        return AmazonAdsLiveSyncStatus::attachToRows([$row], $states)[0];
    }

    private function failed(string $reason, float $desired, float $live): array
    {
        return ['status' => 'failed', 'reason' => $reason, 'desired_value' => $desired, 'live_value' => $live, 'detail' => []];
    }

    private function synced(float $desired, float $live): array
    {
        return ['status' => 'synced', 'reason' => 'verified_after_push', 'desired_value' => $desired, 'live_value' => $live, 'detail' => []];
    }

    // ---------- BID ----------

    public function test_bid_gap_alerts_even_before_any_sync_has_run(): void
    {
        $row = $this->attach(['sbid' => '0.80', 'last_sbid' => '0.50', 'sbgt' => '10', 'bgt' => 10]);

        $this->assertStringStartsWith('SBID:', $row['pushAlert']);
        $this->assertStringContainsString('$0.80', $row['pushAlert']);
        $this->assertStringContainsString('$0.50', $row['pushAlert']);
    }

    public function test_bid_alert_is_gone_after_the_sync_makes_lbid_equal_sbid(): void
    {
        $before = $this->attach(['sbid' => '0.80', 'last_sbid' => '0.50', 'sbgt' => '10', 'bgt' => 10]);
        $this->assertNotSame('', $before['pushAlert']);

        // What the sync leaves behind: last_sbid written with the verified live bid, state synced.
        $after = $this->attach(
            ['sbid' => '0.80', 'last_sbid' => '0.80', 'sbgt' => '10', 'bgt' => 10],
            $this->synced(0.80, 0.80)
        );

        $this->assertSame('', $after['pushAlert']);
        $this->assertSame('green', $after['bid_sync_color']);
    }

    public function test_failed_bid_alert_shows_the_reason(): void
    {
        $row = $this->attach(
            ['sbid' => '0.80', 'last_sbid' => '0.50', 'sbgt' => '10', 'bgt' => 10],
            $this->failed('push_failed: 502 bad gateway', 0.80, 0.50)
        );

        $this->assertSame('red', $row['bid_sync_color']);
        $this->assertStringStartsWith('SBID:', $row['pushAlert']);
        $this->assertStringContainsString('502 bad gateway', $row['pushAlert']);
    }

    public function test_an_old_failure_does_not_alert_once_lbid_matches_sbid(): void
    {
        $row = $this->attach(
            ['sbid' => '0.80', 'last_sbid' => '0.80', 'sbgt' => '10', 'bgt' => 10],
            $this->failed('push_failed: earlier', 0.80, 0.50)
        );

        $this->assertSame('', $row['pushAlert']);
        $this->assertSame('green', $row['bid_sync_color']);
    }

    public function test_bid_within_the_same_cent_has_no_alert(): void
    {
        $row = $this->attach(['sbid' => '0.804', 'last_sbid' => '0.80', 'sbgt' => '10', 'bgt' => 10]);

        $this->assertSame('', $row['pushAlert']);
    }

    // ---------- BGT ----------

    public function test_failed_budget_alerts_with_the_reason(): void
    {
        $row = $this->attach(
            ['sbid' => '0.80', 'last_sbid' => '0.80', 'sbgt' => '20', 'bgt' => 12],
            null,
            $this->failed('verify_failed: live 12 !== desired 20', 20.0, 12.0)
        );

        $this->assertSame('red', $row['bgt_sync_color']);
        $this->assertStringStartsWith('SBGT:', $row['sbgtAlert']);
        $this->assertStringContainsString('$12.00', $row['sbgtAlert']);
    }

    public function test_budget_alert_is_gone_after_the_sync_makes_lbgt_equal_sbgt(): void
    {
        $after = $this->attach(
            ['sbid' => '0.80', 'last_sbid' => '0.80', 'sbgt' => '20', 'bgt' => 20],
            null,
            $this->synced(20.0, 20.0)
        );

        $this->assertSame('', $after['sbgtAlert']);
        $this->assertSame('green', $after['bgt_sync_color']);
    }

    public function test_an_old_budget_failure_does_not_alert_once_lbgt_matches_sbgt(): void
    {
        $row = $this->attach(
            ['sbid' => '0.80', 'last_sbid' => '0.80', 'sbgt' => '20', 'bgt' => 20],
            null,
            $this->failed('push_failed: earlier', 20.0, 12.0)
        );

        $this->assertSame('', $row['sbgtAlert']);
        $this->assertSame('green', $row['bgt_sync_color']);
    }

    public function test_budget_gap_waiting_for_the_next_sync_is_pending_not_an_alert(): void
    {
        // Yellow means "the sync will pick this up". Only a failure or a pause is an alert.
        $row = $this->attach(['sbid' => '0.80', 'last_sbid' => '0.80', 'sbgt' => '20', 'bgt' => 12]);

        $this->assertSame('yellow', $row['bgt_sync_color']);
        $this->assertSame('', $row['sbgtAlert']);
    }

    // ---------- BID + BGT ----------

    public function test_bid_and_budget_alerts_are_independent(): void
    {
        $row = $this->attach(
            ['sbid' => '0.80', 'last_sbid' => '0.50', 'sbgt' => '20', 'bgt' => 20],
            null,
            $this->synced(20.0, 20.0)
        );

        $this->assertNotSame('', $row['pushAlert'], 'bid is still out of step');
        $this->assertSame('', $row['sbgtAlert'], 'budget is fine');
    }
}
