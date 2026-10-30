<?php

namespace Tests\Unit;

use App\Services\GoogleShoppingMismatchAlertService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GoogleShoppingMismatchAlertServiceTest extends TestCase
{
    /** @var list<string> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'services.google_shopping_alerts.enabled' => true]);
        Cache::flush();
        $this->sent = [];
    }

    private function service(bool $deliver = true): GoogleShoppingMismatchAlertService
    {
        return new GoogleShoppingMismatchAlertService([
            'rule' => fn () => ['pause_if_inv_zero' => true, 'pause_price_below' => 20, 'pause_dil_above' => 50],
            'send' => function (string $body) use ($deliver) {
                $this->sent[] = $body;

                return ['chat' => $deliver, 'whatsapp' => $deliver];
            },
        ]);
    }

    public function test_matching_campaigns_raise_nothing(): void
    {
        $issues = $this->service()->findIssues(
            ['1' => ['sbid' => 0.5, 'sbgt' => 4, 'name' => 'A']],
            ['1' => ['live_bid' => 0.5, 'live_bgt' => 4]],
            fn () => false
        );

        $this->assertSame([], $issues);
    }

    public function test_sbgt_zero_explains_inventory_reason(): void
    {
        $issues = $this->service()->findIssues(
            ['9' => ['sbid' => null, 'sbgt' => 0, 'name' => 'PARENT 18 FR', 'inventory' => 0, 'price' => null, 'dil' => 0]],
            ['9' => ['live_bid' => null, 'live_bgt' => 5]],
            fn () => false
        );

        $this->assertCount(1, $issues);
        $this->assertSame('sbgt_zero', $issues[0]['kind']);
        $this->assertStringContainsString('SBGT 0 vs LBgt $5.00', $issues[0]['label']);
        $this->assertStringContainsString('INV is 0', $issues[0]['text']);
    }

    public function test_sbgt_zero_price_and_dil_reasons(): void
    {
        $price = $this->service()->findIssues(
            ['1' => ['sbid' => null, 'sbgt' => 0, 'name' => 'P', 'inventory' => 10, 'price' => 12.5, 'dil' => 1]],
            ['1' => ['live_bgt' => 2]],
            fn () => false
        );
        $this->assertStringContainsString('below the $20.00 pause price', $price[0]['text']);

        $dil = $this->service()->findIssues(
            ['1' => ['sbid' => null, 'sbgt' => 0, 'name' => 'P', 'inventory' => 10, 'price' => 80, 'dil' => 70]],
            ['1' => ['live_bgt' => 2]],
            fn () => false
        );
        $this->assertStringContainsString('Dil 70% is above the 50%', $dil[0]['text']);
    }

    public function test_pushed_but_not_stuck_and_waiting_reasons(): void
    {
        $targets = ['1' => ['sbid' => 0.75, 'sbgt' => 4, 'name' => 'X', 'inventory' => 5]];
        $states = ['1' => ['live_bid' => 0.5, 'live_bgt' => 1]];

        $plain = $this->service()->findIssues($targets, $states, fn () => false);
        $this->assertSame(['bgt_mismatch', 'bid_mismatch'], array_column($plain, 'kind'));
        $this->assertStringContainsString('still shows the old budget', $plain[0]['text']);

        $waiting = $this->service()->findIssues($targets, $states, fn (string $field) => $field === 'bgt');
        $this->assertStringContainsString('did not stick', $waiting[0]['text']);
        $this->assertStringContainsString('Push was sent', $waiting[1]['text']);
    }

    public function test_unreadable_live_value_is_reported_with_the_google_error(): void
    {
        $issues = $this->service()->findIssues(
            ['1' => ['sbid' => 0.5, 'sbgt' => 3, 'name' => 'X']],
            ['1' => ['live_bid' => null, 'live_bgt' => null, 'bid_fetch_ok' => 0, 'bgt_fetch_ok' => 0, 'bgt_fetch_error' => 'mixed']],
            fn () => false
        );

        $this->assertSame(['lbgt_unread', 'lbid_unread'], array_column($issues, 'kind'));
        $this->assertStringContainsString('(mixed)', $issues[0]['text']);
    }

    public function test_alert_is_sent_once_per_cooldown_and_retried_when_nothing_was_delivered(): void
    {
        $issues = $this->service()->findIssues(
            ['9' => ['sbid' => null, 'sbgt' => 0, 'name' => 'PARENT 18 FR', 'inventory' => 0]],
            ['9' => ['live_bgt' => 5]],
            fn () => false
        );

        $failed = $this->service(false)->notify($issues);
        $this->assertSame(0, $failed['sent']);

        $first = $this->service()->notify($issues);
        $this->assertSame(1, $first['sent']);
        $this->assertTrue($first['chat']);
        $this->assertTrue($first['whatsapp']);
        $this->assertStringContainsString('PARENT 18 FR (9)', $this->sent[1]);
        $this->assertStringContainsString('Reason:', $this->sent[1]);

        $again = $this->service()->notify($issues);
        $this->assertSame(0, $again['new']);
        $this->assertCount(2, $this->sent);
    }

    public function test_one_message_lists_every_campaign_with_all_four_values(): void
    {
        $targets = [
            '1' => ['sbid' => 0.75, 'sbgt' => 4, 'name' => 'HW 18 WP', 'inventory' => 5],
            '2' => ['sbid' => null, 'sbgt' => 0, 'name' => 'PARENT 18 FR', 'inventory' => 0],
        ];
        $states = [
            '1' => ['live_bid' => 0.5, 'live_bgt' => 1],
            '2' => ['live_bgt' => 5],
        ];
        $svc = $this->service();
        $issues = $svc->findIssues($targets, $states, fn () => false);
        $rows = $svc->campaignRows($issues, $targets, $states);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]['can_push_bgt']);
        $this->assertTrue($rows[0]['can_push_bid']);
        $this->assertFalse($rows[0]['blocked']);
        $this->assertTrue($rows[1]['blocked']);
        $this->assertFalse($rows[1]['can_push_bgt']);

        $result = $svc->notify($issues, $rows);
        $this->assertSame(3, $result['sent']);
        $this->assertCount(1, $this->sent);

        $body = $this->sent[0];
        $this->assertStringContainsString('2 campaigns not matching', $body);
        $this->assertStringContainsString('HW 18 WP (1)', $body);
        $this->assertStringContainsString('SBGT 4 | LBgt $1.00 | SBID $0.75 | LBid $0.50', $body);
        $this->assertStringContainsString('PARENT 18 FR (2)', $body);
        $this->assertStringContainsString('SBGT 0 | LBgt $5.00 | SBID — | LBid —', $body);
        $this->assertStringContainsString('/google/shopping/google-shopping/mismatches', $body);
    }

    public function test_many_campaigns_still_go_in_a_single_message(): void
    {
        $targets = [];
        $states = [];
        for ($i = 1; $i <= 60; $i++) {
            $targets[(string) $i] = ['sbid' => null, 'sbgt' => 0, 'name' => 'PARENT CAMPAIGN NUMBER '.$i, 'inventory' => 0];
            $states[(string) $i] = ['live_bgt' => 5];
        }
        $svc = $this->service();
        $issues = $svc->findIssues($targets, $states, fn () => false);
        $svc->notify($issues, $svc->campaignRows($issues, $targets, $states));

        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('PARENT CAMPAIGN NUMBER 60 (60)', $this->sent[0]);
    }

    public function test_pull_failure_is_one_alert(): void
    {
        $svc = $this->service();
        $a = $svc->notify([$svc->pullFailedIssue('Live pull failed: timeout')]);
        $b = $svc->notify([$svc->pullFailedIssue('Live pull failed: other text')]);

        $this->assertSame(1, $a['sent']);
        $this->assertSame(0, $b['sent']);
    }
}
