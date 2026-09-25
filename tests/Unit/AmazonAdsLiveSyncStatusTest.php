<?php

namespace Tests\Unit;

use App\Support\AmazonAdsLiveSyncStatus;
use PHPUnit\Framework\TestCase;

class AmazonAdsLiveSyncStatusTest extends TestCase
{
    public function test_green_bgt_when_verified_live_matches_sbgt(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bgt', [
            'status' => 'synced',
            'reason' => 'already_matched',
            'desired_value' => 25,
            'live_value' => 25,
        ]);

        $this->assertSame('green', $out['color']);
        $this->assertSame('synced', $out['status']);
        $this->assertSame('Updated — Amazon BGT $25.00 matches SBGT $25.00', $out['tip']);
        $this->assertStringNotContainsString('Failed', $out['tip']);
    }

    public function test_green_bgt_after_post_push_verification(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bgt', [
            'status' => 'synced',
            'reason' => 'verified_after_push',
            'desired_value' => 25,
            'live_value' => 25,
            'detail' => ['verified_live' => 25, 'push_attempts' => 1],
        ]);

        $this->assertSame('green', $out['color']);
        $this->assertSame('Updated — Amazon BGT $25.00 matches SBGT $25.00', $out['tip']);
    }

    public function test_yellow_bgt_pending_verification_after_push(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bgt', [
            'status' => 'pending',
            'reason' => 'push_succeeded_waiting_verify',
            'desired_value' => 25,
            'live_value' => 10,
            'detail' => ['push_attempts' => 1, 'verify_attempts' => 0],
        ]);

        $this->assertSame('yellow', $out['color']);
        $this->assertStringContainsString('Pending — Push succeeded; waiting for Amazon BGT verification', $out['tip']);
        $this->assertStringContainsString('$25.00', $out['tip']);
    }

    public function test_yellow_bgt_when_no_state_yet(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bgt', null, 18);

        $this->assertSame('yellow', $out['color']);
        $this->assertSame('pending', $out['status']);
        $this->assertSame('Pending — BGT has not been pulled from Amazon and verified yet', $out['tip']);
    }

    public function test_red_bgt_when_live_does_not_match_after_retries(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bgt', [
            'status' => 'failed',
            'reason' => 'verify_failed: live 10 !== desired 25',
            'desired_value' => 25,
            'live_value' => 10,
            'detail' => ['push_attempts' => 3, 'verify_attempts' => 6, 'old_live' => 10],
        ]);

        $this->assertSame('red', $out['color']);
        $this->assertSame('Failed — Amazon BGT remains $10.00; expected $25.00 after 3 push attempts', $out['tip']);
        $this->assertNotSame('Failed', $out['tip']);
    }

    public function test_red_bgt_pull_failed_includes_attempts_and_error(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bgt', [
            'status' => 'failed',
            'reason' => 'pull_failed: timeout',
            'desired_value' => 25,
            'detail' => ['pull_attempts' => 3],
        ]);

        $this->assertSame('red', $out['color']);
        $this->assertSame('Pull Failed — Live Amazon BGT could not be retrieved after 3 attempts. timeout', $out['tip']);
    }

    public function test_red_rate_limited_shows_retry_reason(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bgt', [
            'status' => 'failed',
            'reason' => 'push_failed: 429 Too Many Requests',
            'desired_value' => 25,
            'detail' => ['push_attempts' => 5],
        ]);

        $this->assertSame('red', $out['color']);
        $this->assertStringStartsWith('Rate Limited — Amazon API rate limit; automatic retry scheduled', $out['tip']);
        $this->assertStringContainsString('429', $out['tip']);
    }

    public function test_synced_bid_is_not_green_when_saved_sbid_differs_from_lbid(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '531', 'sbid' => 0.68, 'last_sbid' => 0.83]],
            [
                'bid' => [
                    '531' => [
                        'status' => 'synced',
                        'reason' => 'already_matched',
                        'desired_value' => 0.83,
                        'live_value' => 0.83,
                    ],
                ],
            ]
        );

        $this->assertSame('yellow', $rows[0]['bid_sync_color']);
        $this->assertSame('sbid_differs', $rows[0]['bid_sync_reason']);
        $this->assertStringContainsString('$0.68', $rows[0]['bid_sync_tip']);
        $this->assertStringContainsString('$0.83', $rows[0]['bid_sync_tip']);
    }

    public function test_green_bid_when_verified_live_matches_sbid(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bid', [
            'status' => 'synced',
            'reason' => 'already_matched',
            'desired_value' => 0.85,
            'live_value' => 0.85,
        ]);

        $this->assertSame('green', $out['color']);
        $this->assertSame('Updated — Amazon BID $0.85 matches SBID $0.85', $out['tip']);
    }

    public function test_matching_lbid_and_sbid_stays_green_when_another_sync_holds_the_lock(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '111', 'sbid' => 0.75, 'last_sbid' => 0.75]],
            [
                'bid' => [
                    '111' => [
                        'status' => 'in_progress',
                        'reason' => 'concurrent_sync',
                        'desired_value' => 0.75,
                    ],
                ],
            ]
        );

        $this->assertSame('green', $rows[0]['bid_sync_color']);
        $this->assertSame('synced', $rows[0]['bid_sync_status']);
        $this->assertStringContainsString('matches SBID', $rows[0]['bid_sync_tip']);
    }

    public function test_blank_lbid_stays_yellow_during_concurrent_sync(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '111', 'sbid' => 0.75, 'last_sbid' => null]],
            [
                'bid' => [
                    '111' => [
                        'status' => 'in_progress',
                        'reason' => 'concurrent_sync',
                        'desired_value' => 0.75,
                    ],
                ],
            ]
        );

        $this->assertSame('yellow', $rows[0]['bid_sync_color']);
    }

    public function test_failed_bid_stays_red_even_when_lbid_matches_sbid(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '111', 'sbid' => 0.75, 'last_sbid' => 0.75]],
            [
                'bid' => [
                    '111' => [
                        'status' => 'failed',
                        'reason' => 'pull_failed: no live Amazon keyword/target bid found',
                        'desired_value' => 0.75,
                    ],
                ],
            ]
        );

        $this->assertSame('red', $rows[0]['bid_sync_color']);
    }

    public function test_yellow_bid_partial_pending(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bid', [
            'status' => 'in_progress',
            'reason' => 'concurrent_sync',
            'desired_value' => 0.85,
        ]);

        $this->assertSame('yellow', $out['color']);
        $this->assertSame('Pending — another sync is already running for this BID', $out['tip']);
    }

    public function test_red_bid_verify_failed_includes_skipped_push_reason(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bid', [
            'status' => 'failed',
            'reason' => 'verify_failed: live null !== desired 0.75',
            'desired_value' => 0.75,
            'detail' => [
                'push_attempts' => 1,
                'push_response' => [
                    'status' => 200,
                    'skipped' => [
                        ['campaign_id' => '535475101593680', 'reason' => 'no_ad_groups'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('red', $out['color']);
        $this->assertStringContainsString('Not pushed: No enabled ad group, so the bid was not written.', $out['tip']);
    }

    public function test_alert_column_shows_failed_bid_reason_and_stays_blank_when_synced(): void
    {
        $failed = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '111', 'sbid' => 0.75, 'last_sbid' => null]],
            [
                'bid' => [
                    '111' => [
                        'status' => 'failed',
                        'reason' => 'verify_failed: live null !== desired 0.75',
                        'desired_value' => 0.75,
                        'detail' => [
                            'push_response' => [
                                'skipped' => [['reason' => 'no_keywords']],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->assertStringContainsString('SBID:', $failed[0]['pushAlert']);
        $this->assertStringContainsString('No keywords and no product targets', $failed[0]['pushAlert']);
        $this->assertSame('', $failed[0]['sbgtAlert']);

        $synced = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '222', 'sbid' => 0.75, 'last_sbid' => 0.75, 'sbgt' => 4]],
            [
                'bid' => [
                    '222' => [
                        'status' => 'synced',
                        'reason' => 'already_matched',
                        'desired_value' => 0.75,
                        'live_value' => 0.75,
                    ],
                ],
                'bgt' => [
                    '222' => [
                        'status' => 'synced',
                        'reason' => 'already_matched',
                        'desired_value' => 4,
                        'live_value' => 4,
                    ],
                ],
            ]
        );

        $this->assertSame('', $synced[0]['pushAlert']);
        $this->assertSame('', $synced[0]['sbgtAlert']);
    }

    public function test_alert_column_explains_zero_sbgt_pause(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '333', 'sbgt' => 0]],
            [
                'bgt' => [
                    '333' => [
                        'status' => 'synced',
                        'reason' => 'paused_zero_sbgt',
                        'desired_value' => 0,
                        'live_value' => 4,
                    ],
                ],
            ]
        );

        $this->assertSame('', $rows[0]['pushAlert']);
        $this->assertStringContainsString('SBGT:', $rows[0]['sbgtAlert']);
        $this->assertStringContainsString('PAUSED', $rows[0]['sbgtAlert']);
    }

    public function test_synced_budget_is_not_green_when_lbgt_differs_from_sbgt(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '497', 'bgt' => 1, 'sbgt' => 4]],
            [
                'bgt' => [
                    '497' => [
                        'status' => 'synced',
                        'reason' => 'paused_zero_sbgt',
                        'desired_value' => 0,
                        'live_value' => 1,
                    ],
                ],
            ]
        );

        $this->assertSame('yellow', $rows[0]['bgt_sync_color']);
        $this->assertSame('sbgt_differs', $rows[0]['bgt_sync_reason']);
        $this->assertSame('', $rows[0]['pushAlert']);
        $this->assertStringContainsString('SBGT:', $rows[0]['sbgtAlert']);
        $this->assertStringContainsString('$4.00', $rows[0]['sbgtAlert']);
        $this->assertStringContainsString('$1.00', $rows[0]['sbgtAlert']);
    }

    public function test_matching_lbgt_and_sbgt_clears_stale_pause_alert(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '497', 'bgt' => 1, 'sbgt' => 1, 'sbid' => 0.83, 'last_sbid' => 0.83]],
            [
                'bgt' => [
                    '497' => [
                        'status' => 'synced',
                        'reason' => 'paused_zero_sbgt',
                        'desired_value' => 0,
                        'live_value' => 1,
                    ],
                ],
                'bid' => [
                    '497' => [
                        'status' => 'synced',
                        'reason' => 'verified_after_push',
                        'desired_value' => 0.83,
                        'live_value' => 0.83,
                    ],
                ],
            ]
        );

        $this->assertSame('green', $rows[0]['bgt_sync_color']);
        $this->assertSame('green', $rows[0]['bid_sync_color']);
        $this->assertSame('', $rows[0]['pushAlert']);
        $this->assertSame('', $rows[0]['sbgtAlert']);
    }

    public function test_failed_budget_stays_on_sbgt_alert_when_numbers_match(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '498', 'bgt' => 4, 'sbgt' => 4]],
            [
                'bgt' => [
                    '498' => [
                        'status' => 'failed',
                        'reason' => 'verify_failed: live 4 !== desired 4',
                        'desired_value' => 4,
                        'live_value' => 4,
                        'detail' => ['push_attempts' => 1],
                    ],
                ],
            ]
        );

        $this->assertSame('red', $rows[0]['bgt_sync_color']);
        $this->assertSame('', $rows[0]['pushAlert']);
        $this->assertStringContainsString('SBGT:', $rows[0]['sbgtAlert']);
    }

    public function test_red_bid_verify_failed_is_independent_of_bgt(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bid', [
            'status' => 'failed',
            'reason' => 'verify_failed: live 0.40 !== desired 0.85',
            'desired_value' => 0.85,
            'live_value' => 0.40,
            'detail' => ['push_attempts' => 3],
        ]);

        $this->assertSame('red', $out['color']);
        $this->assertSame('Failed — Amazon BID remains $0.40; expected $0.85 after 3 push attempts', $out['tip']);
        $this->assertStringNotContainsString('BGT', $out['tip']);
    }

    public function test_push_api_success_without_synced_status_is_not_green(): void
    {
        $out = AmazonAdsLiveSyncStatus::present('bgt', [
            'status' => 'failed',
            'reason' => 'verify_failed: live 10 !== desired 25',
            'desired_value' => 25,
            'live_value' => 10,
            'detail' => [
                'push_response' => ['status' => 200, 'success_ids' => ['111']],
                'push_attempts' => 1,
            ],
        ]);

        $this->assertSame('red', $out['color']);
        $this->assertStringContainsString('$10.00', $out['tip']);
        $this->assertStringContainsString('$25.00', $out['tip']);
    }

    public function test_bgt_green_and_bid_red_can_attach_on_same_row(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [['campaign_id' => '111', 'sbgt' => 25, 'sbid' => 0.85, 'bgt' => 25, 'last_sbid' => 0.40]],
            [
                'bgt' => [
                    '111' => [
                        'status' => 'synced',
                        'reason' => 'already_matched',
                        'desired_value' => 25,
                        'live_value' => 25,
                    ],
                ],
                'bid' => [
                    '111' => [
                        'status' => 'failed',
                        'reason' => 'verify_failed: live 0.40 !== desired 0.85',
                        'desired_value' => 0.85,
                        'live_value' => 0.40,
                        'detail' => ['push_attempts' => 3],
                    ],
                ],
            ]
        );

        $this->assertSame('green', $rows[0]['bgt_sync_color']);
        $this->assertSame('red', $rows[0]['bid_sync_color']);
        $this->assertStringContainsString('Amazon BGT $25.00 matches SBGT $25.00', $rows[0]['bgt_sync_tip']);
        $this->assertStringContainsString('Amazon BID remains $0.40; expected $0.85', $rows[0]['bid_sync_tip']);
    }

    public function test_badge_counts_match_loaded_rows_and_keep_fields_independent(): void
    {
        $rows = AmazonAdsLiveSyncStatus::attachToRows(
            [
                ['campaign_id' => '1', 'sbgt' => 10, 'sbid' => 0.5],
                ['campaign_id' => '2', 'sbgt' => 10, 'sbid' => 0.5],
                ['campaign_id' => '3', 'sbgt' => 10, 'sbid' => 0.5],
            ],
            [
                'bgt' => [
                    '1' => ['status' => 'synced', 'reason' => 'already_matched', 'desired_value' => 10, 'live_value' => 10],
                    '2' => ['status' => 'pending', 'reason' => 'pulling_live', 'desired_value' => 10],
                    '3' => ['status' => 'failed', 'reason' => 'pull_failed: timeout', 'desired_value' => 10, 'detail' => ['pull_attempts' => 3]],
                ],
                'bid' => [
                    '1' => ['status' => 'failed', 'reason' => 'verify_failed: live 0.2 !== desired 0.5', 'desired_value' => 0.5, 'live_value' => 0.2, 'detail' => ['push_attempts' => 2]],
                    '2' => ['status' => 'synced', 'reason' => 'verified_after_push', 'desired_value' => 0.5, 'live_value' => 0.5],
                ],
            ]
        );

        $counts = AmazonAdsLiveSyncStatus::countColors($rows);

        $this->assertSame(['green' => 1, 'yellow' => 1, 'red' => 1], $counts['bgt']);
        $this->assertSame(['green' => 1, 'yellow' => 1, 'red' => 1], $counts['bid']);
        $this->assertSame('green', $rows[0]['bgt_sync_color']);
        $this->assertSame('red', $rows[0]['bid_sync_color']);
        $this->assertSame('yellow', $rows[1]['bgt_sync_color']);
        $this->assertSame('green', $rows[1]['bid_sync_color']);
        $this->assertSame('red', $rows[2]['bgt_sync_color']);
        $this->assertSame('yellow', $rows[2]['bid_sync_color']);
    }

    public function test_status_filter_mapping_is_independent_per_color(): void
    {
        $this->assertTrue(AmazonAdsLiveSyncStatus::statusMatchesColor('synced', 'green'));
        $this->assertFalse(AmazonAdsLiveSyncStatus::statusMatchesColor('failed', 'green'));
        $this->assertTrue(AmazonAdsLiveSyncStatus::statusMatchesColor('pending', 'yellow'));
        $this->assertTrue(AmazonAdsLiveSyncStatus::statusMatchesColor('in_progress', 'yellow'));
        $this->assertTrue(AmazonAdsLiveSyncStatus::statusMatchesColor(null, 'yellow'));
        $this->assertTrue(AmazonAdsLiveSyncStatus::statusMatchesColor('failed', 'red'));
        $this->assertFalse(AmazonAdsLiveSyncStatus::statusMatchesColor('synced', 'red'));
        $this->assertSame(['synced'], AmazonAdsLiveSyncStatus::statusesForColor('green'));
        $this->assertSame(['failed'], AmazonAdsLiveSyncStatus::statusesForColor('red'));
        $this->assertContains('pending', AmazonAdsLiveSyncStatus::statusesForColor('yellow'));
        $this->assertTrue(AmazonAdsLiveSyncStatus::yellowIncludesMissingState());
        $this->assertSame('green', AmazonAdsLiveSyncStatus::normalizeColor('GREEN'));
        $this->assertNull(AmazonAdsLiveSyncStatus::normalizeColor('blue'));
    }

    public function test_hover_copy_is_never_generic_failed_or_partial_only(): void
    {
        $cases = [
            ['bgt', ['status' => 'failed', 'reason' => 'push_failed: amazon rejected', 'desired_value' => 12, 'detail' => ['push_attempts' => 2]]],
            ['bid', ['status' => 'pending', 'reason' => 'partial', 'desired_value' => 0.8, 'live_value' => 0.4]],
            ['bgt', ['status' => 'synced', 'reason' => 'paused_zero_sbgt', 'desired_value' => 0]],
        ];
        foreach ($cases as [$field, $state]) {
            $tip = AmazonAdsLiveSyncStatus::present($field, $state)['tip'];
            $this->assertNotSame('Failed', $tip);
            $this->assertNotSame('Partial', $tip);
            $this->assertGreaterThan(20, strlen($tip));
        }
    }
}
