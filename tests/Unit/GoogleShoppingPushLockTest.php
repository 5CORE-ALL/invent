<?php

namespace Tests\Unit;

use App\Http\Controllers\Campaigns\GoogleShoppingCampaignsController;
use App\Services\GoogleAdsSbidService;
use App\Services\GoogleShoppingLiveBidBgtService;
use App\Support\SchedulerLease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The page's Push SBID / Push SBGT buttons and the scheduled match push through the same code. Each campaign is
 * pushed under its own lease, so the two paths can never push the same campaign at the same time.
 */
class GoogleShoppingPushLockTest extends TestCase
{
    private object $controller;

    /** @var list<string> */
    public array $bidPushed = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.google_ads.login_customer_id', '1234567890');
        Config::set('database.default', 'push_lock_test');
        Config::set('database.connections.push_lock_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
        DB::purge('push_lock_test');

        (require base_path('database/migrations/2026_10_31_020000_create_scheduler_leases_table.php'))->up();
        Schema::create('google_ads_campaigns', function ($t) {
            $t->id();
            $t->string('campaign_id');
            $t->string('budget_id')->nullable();
            $t->date('date')->nullable();
        });

        $this->bidPushed = [];
        $test = $this;
        $this->controller = new class($test) extends GoogleShoppingCampaignsController
        {
            public function __construct(private GoogleShoppingPushLockTest $test)
            {
                parent::__construct();
            }

            protected function enrichedRowsForCampaignIds(array $campaignIds): array
            {
                $rows = [];
                foreach ($campaignIds as $id) {
                    $rows[$id] = [
                        'campaign_id' => $id, 'campaign_name' => "C{$id}", 'campaign_status' => 'ENABLED',
                        'sbid' => 0.5, 'ub7' => 0, 'ub1' => 0, 'sbgt' => 10, 'bgt' => 3, 'budget_id' => '999',
                        'inventory' => 5, 'acos_l30' => 1,
                    ];
                }
                if (isset($this->test->rowOverrides)) {
                    foreach ($this->test->rowOverrides as $id => $o) {
                        $rows[$id] = array_merge($rows[$id] ?? [], $o);
                    }
                }

                return $rows;
            }

            protected function pushSbidToGoogleAds(GoogleAdsSbidService $sbidService, string $customerId, string $campaignId, float $sbid, array $row = []): string
            {
                $this->test->bidPushed[] = $campaignId;
                if ($this->test->throwOnBid) {
                    throw new \RuntimeException('Google Ads exploded');
                }

                return '';
            }
        };
    }

    /** @var array<string, array<string, mixed>> */
    public array $rowOverrides = [];

    public bool $throwOnBid = false;

    private function fakeGoogle(): \Mockery\MockInterface
    {
        $this->mock(GoogleShoppingLiveBidBgtService::class, fn ($m) => $m->shouldReceive('recordPush')->andReturnNull());

        return $this->mock(GoogleAdsSbidService::class);
    }

    private function holder(string $campaignId): ?SchedulerLease
    {
        return SchedulerLease::acquire(GoogleShoppingCampaignsController::campaignPushLease($campaignId), 300);
    }

    private function leaseRow(string $campaignId): ?object
    {
        return DB::table('scheduler_leases')->where('name', GoogleShoppingCampaignsController::campaignPushLease($campaignId))->first();
    }

    public function test_free_campaign_is_pushed_and_its_lease_released(): void
    {
        $this->fakeGoogle();

        $out = $this->controller->pushSbidForLiveMatch(['111']);

        $this->assertTrue($out['ok']);
        $this->assertSame(['111'], $this->bidPushed);
        $this->assertStringContainsString('[OK]', $out['output']);
        $this->assertNull($this->leaseRow('111')->owner_token, 'campaign lease is released after the push');
    }

    public function test_sbid_is_not_pushed_while_another_path_is_pushing_the_same_campaign(): void
    {
        $this->fakeGoogle();
        $other = $this->holder('111'); // e.g. the scheduled run, or a button click in another request

        $out = $this->controller->pushSbidForLiveMatch(['111', '222']);

        $this->assertSame(['222'], $this->bidPushed, 'only the free campaign is pushed');
        $this->assertStringContainsString('[BUSY] 111', $out['output']);
        $this->assertTrue($out['ok'], 'busy is a skip, not an error');
        $this->assertSame($other->token, $this->leaseRow('111')->owner_token, 'the other push keeps its lease');
        $this->assertNull($this->leaseRow('222')->owner_token);
    }

    public function test_manual_push_endpoint_is_protected_the_same_way(): void
    {
        $this->fakeGoogle();
        $this->holder('111');

        $request = Request::create('/google/shopping/google-shopping/push-sbid', 'POST', ['campaign_ids' => ['111']]);
        $request->headers->set('Accept', 'application/json');
        $response = $this->controller->pushSbidShopping($request);

        $this->assertSame([], $this->bidPushed);
        $this->assertStringContainsString('[BUSY] 111', (string) ($response->getData(true)['output'] ?? ''));
    }

    public function test_sbgt_is_not_pushed_while_another_path_is_pushing_the_same_campaign(): void
    {
        $google = $this->fakeGoogle();
        $google->shouldReceive('updateCampaignBudget')->once()->withArgs(fn ($c, $res, $amount) => str_contains($res, '/999') && $amount === 10);
        $this->holder('111');

        $out = $this->controller->pushSbgtForLiveMatch(['111', '222']);

        $this->assertStringContainsString('[BUSY] 111', $out['output']);
        $this->assertStringContainsString('[OK] C222', $out['output']);
        $this->assertNull($this->leaseRow('222')->owner_token);
    }

    public function test_sbgt_zero_pause_path_is_protected_too(): void
    {
        $google = $this->fakeGoogle();
        $google->shouldNotReceive('pauseCampaign');
        $google->shouldNotReceive('updateCampaignBudget');
        $this->rowOverrides = ['111' => ['sbgt' => 0]];
        $this->holder('111');

        $out = $this->controller->pushSbgtForLiveMatch(['111']);

        $this->assertStringContainsString('[BUSY] 111', $out['output']);
    }

    public function test_lease_is_released_even_when_the_google_call_fails(): void
    {
        $this->fakeGoogle();
        $this->throwOnBid = true;

        $out = $this->controller->pushSbidForLiveMatch(['111', '222']);

        $this->assertFalse($out['ok']);
        $this->assertSame(['111', '222'], $this->bidPushed, 'one failure does not stop the next campaign');
        $this->assertNull($this->leaseRow('111')->owner_token);
        $this->assertNull($this->leaseRow('222')->owner_token);
    }

    public function test_pushes_still_work_before_the_migration_has_created_the_lease_table(): void
    {
        $this->fakeGoogle();
        Schema::drop('scheduler_leases');

        $out = $this->controller->pushSbidForLiveMatch(['111']);

        $this->assertTrue($out['ok']);
        $this->assertSame(['111'], $this->bidPushed);
    }

    public function test_a_push_that_outlasts_the_lease_ttl_keeps_its_campaign_lease(): void
    {
        // Stand-in for "one SBID push = many sequential Google calls, longer than the 300 s lease TTL":
        // ttl 5 s, 4 ad groups x ~2 s each = ~8 s. The renewal between the calls must keep the lease.
        $lease = SchedulerLease::acquire(GoogleShoppingCampaignsController::campaignPushLease('111'), 5, 1);
        $this->assertNotNull($lease);

        $competitorWon = 0;
        $calls = 0;
        $google = \Mockery::mock(GoogleAdsSbidService::class)->makePartial();
        $google->shouldReceive('runQuery')->andReturn(array_map(
            fn ($i) => ['adGroup' => ['resourceName' => "customers/1/adGroups/{$i}"], 'metrics' => []],
            range(1, 4)
        ));
        $google->shouldReceive('updateAdGroupSbid')->times(4)->andReturnUsing(function () use (&$competitorWon, &$calls) {
            $calls++;
            sleep(2); // a slow Google Ads call
            if (SchedulerLease::acquire(GoogleShoppingCampaignsController::campaignPushLease('111'), 5, 1) !== null) {
                $competitorWon++; // a manual/scheduled push would have started on the same campaign
            }
        });

        $google->updateCampaignSbids('1', '111', 0.5, false);

        $this->assertSame(4, $calls);
        $this->assertSame(0, $competitorWon, 'nobody else may take the campaign while the push is still working');
        $this->assertTrue($lease->owns());
        $this->assertTrue($lease->beat(true), 'the push still owns its lease after outlasting the TTL');
    }

    public function test_a_lease_held_by_a_dead_push_still_expires_so_the_campaign_is_not_blocked_forever(): void
    {
        $lease = SchedulerLease::acquire(GoogleShoppingCampaignsController::campaignPushLease('111'), 5, 1);
        $this->assertNotNull($lease);
        DB::table('scheduler_leases')->update(['expires_at' => now()->subSeconds(2)->toDateTimeString()]); // no more renewals

        $this->assertNotNull(SchedulerLease::acquire(GoogleShoppingCampaignsController::campaignPushLease('111'), 5, 1));
        $this->assertFalse($lease->beat(true), 'the dead push can no longer renew or overwrite');
    }

    public function test_an_expired_campaign_lease_does_not_block_a_push(): void
    {
        $this->fakeGoogle();
        $this->holder('111');
        DB::table('scheduler_leases')->update(['expires_at' => now()->subMinute()->toDateTimeString()]); // holder died

        $out = $this->controller->pushSbidForLiveMatch(['111']);

        $this->assertSame(['111'], $this->bidPushed);
        $this->assertTrue($out['ok']);
    }
}
