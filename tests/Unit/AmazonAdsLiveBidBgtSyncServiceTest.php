<?php

namespace Tests\Unit;

use App\Services\AmazonAdsLiveBidBgtSyncService;
use App\Support\AmazonAdsApiRetry;
use App\Support\AmazonAdsEnabledCampaignSync;
use App\Services\AmazonAdsLiveValuePuller;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AmazonAdsLiveBidBgtSyncServiceTest extends TestCase
{
    public function test_already_matched_does_not_push(): void
    {
        $pushed = [];
        $persisted = [];
        $svc = $this->service([
            'pullBudgets' => fn () => ['111' => 12.0],
            'pushBudget' => function () use (&$pushed) {
                $pushed[] = true;

                return ['status' => 200];
            },
            'persistLive' => function ($ch, $field, $cid, $live) use (&$persisted) {
                $persisted[] = [$ch, $field, $cid, $live];
            },
        ]);

        $out = $svc->syncField('sp', 'bgt', '111', 'SKU KW', 12.0, 'test');

        $this->assertSame('synced', $out['status']);
        $this->assertSame('already_matched', $out['reason']);
        $this->assertSame('green', $out['sync_color']);
        $this->assertStringContainsString('Amazon BGT $12.00 matches SBGT $12.00', $out['sync_tip']);
        $this->assertSame([], $pushed);
        $this->assertSame([['sp', 'bgt', '111', 12.0]], $persisted);
    }

    public function test_successful_pull_with_no_amazon_bid_does_not_push(): void
    {
        $pushed = [];
        $svc = $this->service([
            'pullBids' => fn () => ['111' => null],
            'pushBid' => function () use (&$pushed) {
                $pushed[] = true;

                return ['status' => 200];
            },
        ]);

        $out = $svc->syncField('sp', 'bid', '111', 'SKU KW', 0.75, 'test');

        $this->assertSame('failed', $out['status']);
        $this->assertStringContainsString('no live Amazon keyword/target bid', $out['reason']);
        $this->assertSame([], $pushed);
        $this->assertSame('red', $out['sync_color']);
    }

    public function test_pull_failure_retries_and_does_not_mark_synced(): void
    {
        $attempts = 0;
        $svc = $this->service([
            'pullBudgets' => function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('timeout');
            },
        ]);

        $out = $svc->syncField('sp', 'bgt', '111', 'SKU KW', 12.0, 'test');

        $this->assertSame('failed', $out['status']);
        $this->assertStringContainsString('pull_failed', $out['reason']);
        $this->assertSame(AmazonAdsLiveBidBgtSyncService::PULL_ATTEMPTS, $attempts);
        $this->assertSame(AmazonAdsLiveBidBgtSyncService::PULL_ATTEMPTS, $out['pull_attempts']);
    }

    public function test_mismatch_push_then_verify_persists_live_not_desired_from_push(): void
    {
        $pushed = [];
        $persisted = [];
        $live = ['111' => 5.0];
        $svc = $this->service([
            'pullBudgets' => function () use (&$live) {
                return $live;
            },
            'pushBudget' => function ($ch, $cid, $desired) use (&$pushed, &$live) {
                $pushed[] = [$ch, $cid, $desired];
                $live[$cid] = $desired;

                return ['status' => 200, 'success_ids' => [$cid], 'failed' => []];
            },
            'persistLive' => function ($ch, $field, $cid, $val) use (&$persisted) {
                $persisted[] = [$field, $cid, $val];
            },
        ]);

        $out = $svc->syncField('sp', 'bgt', '111', 'SKU KW', 12.0, 'test');

        $this->assertSame('synced', $out['status']);
        $this->assertSame('verified_after_push', $out['reason']);
        $this->assertSame([['sp', '111', 12.0]], $pushed);
        $this->assertSame([['bgt', '111', 12.0]], $persisted);
        $this->assertSame(12.0, $out['verified_live']);
    }

    public function test_push_failure_retries_then_stays_failed(): void
    {
        $attempts = 0;
        $svc = $this->service([
            'pullBudgets' => fn () => ['111' => 5.0],
            'pushBudget' => function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('502 bad gateway');
            },
        ]);

        $out = $svc->syncField('sp', 'bgt', '111', 'SKU KW', 12.0, 'test');

        $this->assertSame('failed', $out['status']);
        $this->assertStringContainsString('push_failed', $out['reason']);
        $this->assertSame(AmazonAdsLiveBidBgtSyncService::PUSH_ATTEMPTS, $attempts);
    }

    public function test_push_success_but_amazon_keeps_old_value_is_not_synced(): void
    {
        $persisted = [];
        $svc = $this->service([
            'pullBudgets' => fn () => ['111' => 5.0],
            'pushBudget' => fn () => ['status' => 200, 'success_ids' => ['111'], 'failed' => []],
            'persistLive' => function () use (&$persisted) {
                $persisted[] = true;
            },
        ]);

        $out = $svc->syncField('sp', 'bgt', '111', 'SKU KW', 12.0, 'test');

        $this->assertSame('failed', $out['status']);
        $this->assertStringContainsString('verify_failed', $out['reason']);
        $this->assertSame('red', $out['sync_color']);
        $this->assertStringContainsString('Amazon BGT remains $5.00', $out['sync_tip']);
        $this->assertSame([], $persisted);
        $this->assertSame(AmazonAdsLiveBidBgtSyncService::VERIFY_ATTEMPTS, $out['verify_attempts']);
    }

    public function test_verification_eventual_consistency_then_syncs(): void
    {
        $pulls = 0;
        $persisted = [];
        $svc = $this->service([
            'pullBudgets' => function () use (&$pulls) {
                $pulls++;

                return ['111' => $pulls >= 3 ? 12.0 : 5.0];
            },
            'pushBudget' => fn () => ['status' => 200, 'failed' => []],
            'persistLive' => function ($ch, $field, $cid, $val) use (&$persisted) {
                $persisted[] = $val;
            },
        ]);

        $out = $svc->syncField('sb', 'bgt', '111', 'HL', 12.0, 'test');

        $this->assertSame('synced', $out['status']);
        $this->assertSame([12.0], $persisted);
        $this->assertGreaterThanOrEqual(3, $pulls);
    }

    public function test_sp_and_sb_bid_mismatch_pushes_separately(): void
    {
        $pushed = [];
        $svc = $this->service([
            'pullBids' => function ($channel, $ids) use (&$pushed) {
                $out = [];
                foreach ($ids as $id) {
                    $key = $channel.':'.$id;
                    $updated = false;
                    foreach ($pushed as $p) {
                        if (str_starts_with($p, $key.':')) {
                            $updated = true;
                            break;
                        }
                    }
                    $out[$id] = $updated ? 0.85 : 0.40;
                }

                return $out;
            },
            'pushBid' => function ($channel, $cid, $desired) use (&$pushed) {
                $pushed[] = $channel.':'.$cid.':'.$desired;

                return ['status' => 200, 'failed' => []];
            },
            'persistLive' => fn () => null,
        ]);

        $sp = $svc->syncField('sp', 'bid', '10', 'SKU KW', 0.85, 'test');
        $sb = $svc->syncField('sb', 'bid', '20', 'SKU HL', 0.85, 'test');

        $this->assertSame('synced', $sp['status']);
        $this->assertSame('synced', $sb['status']);
        $this->assertSame(['sp:10:0.85', 'sb:20:0.85'], $pushed);
    }

    public function test_multiple_campaigns_and_pages_batch(): void
    {
        $pushed = [];
        $svc = $this->service([
            'pullBudgets' => function ($ch, $ids) use (&$pushed) {
                $map = ['1' => 10.0, '2' => 3.0, '3' => 8.0];
                foreach ($pushed as $cid) {
                    $map[$cid] = 10.0;
                }

                return $map;
            },
            'pushBudget' => function ($ch, $cid, $desired) use (&$pushed) {
                $pushed[] = (string) $cid;

                return ['status' => 200, 'failed' => []];
            },
            'persistLive' => fn () => null,
        ]);

        $out = $svc->syncRows([
            ['campaign_id' => '1', 'channel' => 'sp', 'sbgt' => 10],
            ['campaign_id' => '2', 'channel' => 'sp', 'sbgt' => 10],
            ['campaign_id' => '3', 'channel' => 'sp', 'sbgt' => 10],
        ], 'test');

        $this->assertSame(3, $out['synced']);
        $this->assertSame(0, $out['failed']);
        $this->assertEqualsCanonicalizing(['2', '3'], $pushed);
    }

    public function test_pulls_live_bid_and_pushes_only_rows_that_still_differ_from_sbid(): void
    {
        $pushed = [];
        $pushedIds = [];
        $svc = $this->service([
            'pullBids' => function () use (&$pushedIds) {
                $map = ['match' => 0.83, 'diff' => 0.75];
                if (in_array('diff', $pushedIds, true)) {
                    $map['diff'] = 0.83;
                }

                return $map;
            },
            'pushBid' => function ($ch, $cid, $desired) use (&$pushed, &$pushedIds) {
                $pushed[] = [(string) $cid, (float) $desired];
                $pushedIds[] = (string) $cid;

                return ['status' => 200, 'failed' => []];
            },
            'persistLive' => static function (): void {},
        ]);

        $out = $svc->syncRows([
            ['campaign_id' => 'match', 'channel' => 'sp', 'sbid' => 0.83, 'campaign_name' => 'MATCH'],
            ['campaign_id' => 'diff', 'channel' => 'sp', 'sbid' => 0.83, 'campaign_name' => 'PARENT GRACK PT'],
        ], 'cron-live-sync');

        $this->assertSame([['diff', 0.83]], $pushed);
        $this->assertSame(2, $out['synced']);
        $this->assertSame(0, $out['failed']);
        $this->assertSame('already_matched', $out['results'][0]['reason']);
        $this->assertSame('verified_after_push', $out['results'][1]['reason']);
    }

    public function test_duplicate_concurrent_lock_does_not_push(): void
    {
        $held = [];
        $pushed = 0;
        $saved = 0;
        $svc = $this->service([
            'acquireLock' => function ($key) use (&$held) {
                if (isset($held[$key])) {
                    return false;
                }
                $held[$key] = true;

                return true;
            },
            'releaseLock' => function ($key) use (&$held) {
                unset($held[$key]);
            },
            'saveState' => function () use (&$saved) {
                $saved++;
            },
            'pullBudgets' => fn () => ['111' => 5.0],
            'pushBudget' => function () use (&$pushed) {
                $pushed++;

                return ['status' => 200, 'failed' => []];
            },
        ]);

        $held['amazon-ads-live-sync:sp:bgt:111'] = true;
        $out = $svc->syncField('sp', 'bgt', '111', 'SKU KW', 12.0, 'test');

        $this->assertSame('in_progress', $out['status']);
        $this->assertSame('concurrent_sync', $out['reason']);
        $this->assertSame(0, $pushed);
        $this->assertSame(0, $saved);
    }

    public function test_concurrent_lock_keeps_already_synced_match(): void
    {
        $svc = $this->service([
            'acquireLock' => fn () => false,
            'loadState' => fn () => [
                'status' => 'synced',
                'live_value' => 0.75,
                'desired_value' => 0.75,
            ],
        ]);

        $out = $svc->syncField('sp', 'bid', '111', 'SKU KW', 0.75, 'test');

        $this->assertSame('synced', $out['status']);
        $this->assertSame('already_matched', $out['reason']);
        $this->assertSame('green', $out['sync_color']);
    }

    public function test_sbgt_zero_pauses_and_verifies(): void
    {
        $paused = [];
        $svc = $this->service([
            'pullBudgets' => fn () => ['111' => 5.0],
            'pause' => function ($ch, $ids) use (&$paused) {
                $paused = $ids;

                return ['paused' => 1, 'failed' => 0, 'errors' => []];
            },
            'ads' => new class
            {
                public function listSpCampaignsByIds(): array
                {
                    return [['campaignId' => '111', 'state' => 'PAUSED']];
                }

                public function listSbCampaignsByIds(): array
                {
                    return [];
                }
            },
        ]);

        $out = $svc->syncField('sp', 'bgt', '111', 'SKU KW', 0.0, 'test');

        $this->assertSame('synced', $out['status']);
        $this->assertSame('paused_zero_sbgt', $out['reason']);
        $this->assertSame(['111'], $paused);
    }

    public function test_retry_helper_marks_timeouts_retryable(): void
    {
        $this->assertTrue(AmazonAdsApiRetry::isRetryable(new RuntimeException('Connection timed out')));
        $this->assertTrue(AmazonAdsApiRetry::isRetryable(new RuntimeException('Amazon Ads /sp/keywords/list list truncated after 80 pages')));
        $this->assertTrue(AmazonAdsApiRetry::valuesMatch(12.0, 12.4, 0.51));
        $this->assertFalse(AmazonAdsApiRetry::valuesMatch(12.0, 13.0, 0.51));
        $this->assertSame(12.5, AmazonAdsEnabledCampaignSync::budgetAmount(['budget' => ['budget' => 12.5]]));
        $this->assertSame(0.8, AmazonAdsLiveValuePuller::entityBid(['bid' => ['amount' => 0.8]]));
    }

    /**
     * @param  array<string, callable|object>  $hooks
     */
    private function service(array $hooks): AmazonAdsLiveBidBgtSyncService
    {
        $held = [];
        $defaults = [
            'sleeper' => static function (): void {},
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
            'log' => static function (): void {},
            'saveState' => static function (): void {},
            'persistLive' => static function (): void {},
            'pullBids' => static fn () => [],
            'pullBudgets' => static fn () => [],
            'pushBudget' => static fn () => ['status' => 200, 'failed' => []],
            'pushBid' => static fn () => ['status' => 200, 'failed' => []],
            'pause' => static fn () => ['paused' => 0, 'failed' => 0, 'errors' => []],
        ];

        return new AmazonAdsLiveBidBgtSyncService(null, array_merge($defaults, $hooks));
    }
}
