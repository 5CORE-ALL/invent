<?php

namespace Tests\Unit;

use App\Models\AmazonAdsSbidRuleSetting;
use App\Services\Amazon\AmazonBidUtilizationService as Svc;
use App\Support\AmazonAdsSbidRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * "< Utilized 30" box and "30 to 66 Utilized" box of the SBID rule.
 */
class AmazonAdsSbidRuleVeryLowBandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        app('db')->purge('sqlite');
        Schema::create('amazon_ads_sbid_rule_settings', function (Blueprint $t) {
            $t->id();
            $t->json('rule');
            $t->timestamps();
        });
        AmazonAdsSbidRule::forgetResolvedCache();
    }

    private function saveRule(array $over): void
    {
        AmazonAdsSbidRule::persistRule(AmazonAdsSbidRule::normalizeRule($over));
    }

    public function test_defaults_keep_the_old_behaviour(): void
    {
        $d = AmazonAdsSbidRule::defaults();
        $this->assertSame(30.0, $d['util_vlow']);
        $this->assertSame(66.0, $d['util_low']);
        foreach (['l1', 'l2', 'l7'] as $k) {
            $this->assertSame($d['both_low_mult_'.$k], $d['vlow_mult_'.$k]);
        }
        $this->assertSame($d['avg_cpc_add'], $d['vlow_avg_cpc_add']);
        $this->assertSame($d['both_low_fallback'], $d['vlow_fallback']);
    }

    public function test_tiers(): void
    {
        $r = AmazonAdsSbidRule::defaults();
        $this->assertSame('vlow', AmazonAdsSbidRule::underTier(10, 29.99, $r));
        $this->assertSame('low', AmazonAdsSbidRule::underTier(30, 10, $r));   // one value not below 30
        $this->assertSame('low', AmazonAdsSbidRule::underTier(20, 50, $r));
        $this->assertSame('low', AmazonAdsSbidRule::underTier(65.9, 65.9, $r));
        $this->assertNull(AmazonAdsSbidRule::underTier(66, 10, $r));          // 66 is not below 66
        $this->assertNull(AmazonAdsSbidRule::underTier(80, 80, $r));
    }

    public function test_each_box_uses_its_own_values(): void
    {
        $this->saveRule([
            'vlow_mult_l1' => 2.0, 'vlow_mult_l2' => 3.0, 'vlow_mult_l7' => 4.0,
            'vlow_avg_cpc_add' => 0.5, 'vlow_fallback' => 1.5,
            'both_low_mult_l1' => 1.2, 'both_low_mult_l2' => 1.3, 'both_low_mult_l7' => 1.4,
            'avg_cpc_add' => 0.2, 'fallback' => 9, 'both_low_fallback' => 0.8,
        ]);

        // < 30 box
        $this->assertSame(2.0, Svc::sbidFromUb2Ub1Cpc(10, 10, 1.0, 0, 0)['sbid']);       // L1 x 2.0
        $this->assertSame(3.0, Svc::sbidFromUb2Ub1Cpc(10, 10, 0, 1.0, 0)['sbid']);       // L2 x 3.0
        $this->assertSame(4.0, Svc::sbidFromUb2Ub1Cpc(10, 10, 0, 0, 1.0)['sbid']);       // L7 x 4.0
        $this->assertSame(1.5, Svc::sbidFromUb2Ub1Cpc(10, 10, 0, 0, 0, null, 1.0)['sbid']); // Avg 1.0 + 0.5
        $this->assertSame(1.5, Svc::sbidFromUb2Ub1Cpc(10, 10, 0, 0, 0)['sbid']);         // fallback 1.5
        $this->assertSame(0.4, Svc::sbidFromUb2Ub1Cpc(10, 10, 0.30, 0, 0)['sbid']);      // L1 < 0.35 -> + 0.10
        $this->assertSame('vlow', Svc::sbidFromUb2Ub1Cpc(10, 10, 1.0, 0, 0)['tier']);

        // 30 to 66 box
        $this->assertSame(1.2, Svc::sbidFromUb2Ub1Cpc(40, 40, 1.0, 0, 0)['sbid']);
        $this->assertSame(1.3, Svc::sbidFromUb2Ub1Cpc(40, 40, 0, 1.0, 0)['sbid']);
        $this->assertSame(1.4, Svc::sbidFromUb2Ub1Cpc(40, 40, 0, 0, 1.0)['sbid']);
        $this->assertSame(1.2, Svc::sbidFromUb2Ub1Cpc(40, 40, 0, 0, 0, null, 1.0)['sbid']);
        $this->assertSame(0.8, Svc::sbidFromUb2Ub1Cpc(40, 40, 0, 0, 0)['sbid']);
        $this->assertSame(0.4, Svc::sbidFromUb2Ub1Cpc(40, 40, 0.30, 0, 0)['sbid']);
        $r = Svc::sbidFromUb2Ub1Cpc(20, 50, 1.0, 0, 0);                                    // mixed -> 30 to 66 box
        $this->assertSame(1.2, $r['sbid']);
        $this->assertSame('low', $r['tier']);
        $this->assertSame('under', $r['band']);
    }

    public function test_between_and_over_are_unchanged(): void
    {
        $this->saveRule([]);
        $this->assertNull(Svc::sbidFromUb2Ub1Cpc(80, 80, 1.0, 1.0, 1.0)['sbid']);
        $this->assertSame('none', Svc::sbidFromUb2Ub1Cpc(80, 80, 1.0, 1.0, 1.0)['band']);
        $this->assertSame(0.9, Svc::sbidFromUb2Ub1Cpc(120, 120, 1.0, 0, 0)['sbid']);
        $this->assertSame('over', Svc::sbidFromUb2Ub1Cpc(120, 120, 1.0, 0, 0)['band']);
        $this->assertNull(Svc::sbidFromUb2Ub1Cpc(120, 120, 0, 1.0, 0)['sbid']);
    }

    public function test_rule_saved_before_the_new_box_still_loads(): void
    {
        // Old row: no util_vlow / vlow_* keys, even a Low % below 30.
        AmazonAdsSbidRuleSetting::query()->create(['rule' => [
            'util_low' => 20.0, 'util_high' => 99.0, 'both_low_mult_l1' => 1.5, 'both_low_mult_l2' => 1.1,
            'both_low_mult_l7' => 1.1, 'both_low_fallback' => 0.75, 'avg_cpc_add' => 0.1, 'both_high_mult_l1' => 0.9,
        ]]);
        AmazonAdsSbidRule::forgetResolvedCache();
        $r = AmazonAdsSbidRule::resolvedRule();
        $this->assertSame(10.0, $r['util_vlow']);
        $this->assertSame(1.5, $r['both_low_mult_l1']);
        $this->assertSame(1.1, $r['vlow_mult_l1']);
    }

    public function test_validation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsSbidRule::normalizeRule(['util_vlow' => 66]); // not below Low %
    }

    public function test_validation_rejects_bad_vlow_values(): void
    {
        foreach ([['util_vlow' => 0], ['vlow_mult_l1' => 0], ['vlow_mult_l2' => 9], ['vlow_fallback' => 0], ['vlow_avg_cpc_add' => -1]] as $bad) {
            try {
                AmazonAdsSbidRule::normalizeRule($bad);
                $this->fail('Expected rejection for '.json_encode($bad));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_save_round_trip(): void
    {
        $this->saveRule(['util_vlow' => 25, 'vlow_mult_l1' => 1.25]);
        AmazonAdsSbidRule::forgetResolvedCache();
        $r = AmazonAdsSbidRule::resolvedRule();
        $this->assertSame(25.0, $r['util_vlow']);
        $this->assertSame(1.25, $r['vlow_mult_l1']);
        $this->assertSame('vlow', AmazonAdsSbidRule::underTier(24, 24, $r));
        $this->assertSame('low', AmazonAdsSbidRule::underTier(26, 24, $r));
    }
}
