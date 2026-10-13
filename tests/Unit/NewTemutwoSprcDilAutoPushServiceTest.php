<?php

namespace Tests\Unit;

use App\Services\NewTemutwoSprcDilAutoPushService;
use App\Services\TemuShopifySalesService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class NewTemutwoSprcDilAutoPushServiceTest extends TestCase
{
    private function pricer(array $over = []): StdPrcVsDilPricer
    {
        return new StdPrcVsDilPricer(array_merge([
            'dil' => [['min' => 0, 'max' => 100, 'disc' => 5]],
            'age' => [['min' => 0, 'max' => 9999, 'disc' => 3]],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [],
            'review_max' => 4,
            'buss' => [['min' => 0, 'max' => 9999, 'disc' => 2]],
            'zero_sold_disc' => 0,
            'min_npft' => 10,
        ], $over), 'newtemutwo');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'NT2-TEST',
            'inv' => 10,
            'dil' => 20,
            'ov_l30' => 2,
            'cvr' => 5,
            'cvr_60' => 5,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 3,
            'margin' => 0.9,
            'ads_percent' => 2.2,
            'lmp' => 0,
            'standard_price' => 100,
            'clearance' => 'NO',
        ], $over);
    }

    public function test_std_rule_sums_discounts_and_returns_push_base(): void
    {
        $svc = new NewTemutwoSprcDilAutoPushService();
        $out = $svc->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 age + 5 dil + 0 cvr + 2 buss) / 100)
        $this->assertSame(90.0, $out['sprice']);
        $this->assertFalse($out['lmp_capped']);
        $base = TemuShopifySalesService::computePushBaseFromSprice(90.0);
        $this->assertSame(round($base, 2), $out['base']);
        $this->assertEqualsWithDelta(90.0, $out['full'], 0.02);
    }

    public function test_min_npft_raises_price_and_clearance_is_exempt(): void
    {
        $svc = new NewTemutwoSprcDilAutoPushService();
        $low = $this->row(['lp' => 70]);

        $raised = $svc->computeTarget($low, $this->pricer());
        $this->assertNotNull($raised);
        $this->assertGreaterThan(90.0, $raised['sprice']);

        $clearance = $svc->computeTarget(array_merge($low, ['clearance' => 'YES']), $this->pricer());
        $this->assertNotNull($clearance);
        $this->assertSame(90.0, $clearance['sprice']);
    }

    public function test_lmp_cap_applies_when_sgroi_at_lmp_is_at_least_20(): void
    {
        $svc = new NewTemutwoSprcDilAutoPushService();
        $out = $svc->computeTarget($this->row(['lmp' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertSame(80.0, $out['sprice']);
        $this->assertTrue($out['lmp_capped']);
    }

    public function test_lmp_cap_skipped_when_sgroi_at_lmp_is_below_20(): void
    {
        $svc = new NewTemutwoSprcDilAutoPushService();
        $out = $svc->computeTarget($this->row(['lp' => 60, 'lmp' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertSame(90.0, $out['sprice']);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_std_price_is_the_maximum_and_lmp_never_raises_the_price(): void
    {
        $svc = new NewTemutwoSprcDilAutoPushService();

        $this->assertSame(100.0, $svc->capToLmp(120.0, 0.0, 20.0, 3.0, 100.0));
        $this->assertSame(90.0, $svc->capToLmp(90.0, 150.0, 20.0, 3.0, 100.0));
    }

    public function test_rows_without_stock_or_std_price_are_skipped(): void
    {
        $svc = new NewTemutwoSprcDilAutoPushService();

        $this->assertNull($svc->computeTarget($this->row(['inv' => 0]), $this->pricer()));
        $this->assertNull($svc->computeTarget($this->row(['standard_price' => 0]), $this->pricer()));
    }

    public function test_zero_sold_uses_ov_l30_like_the_page(): void
    {
        $svc = new NewTemutwoSprcDilAutoPushService();
        $pricer = $this->pricer(['zero_sold_disc' => 10]);

        $sold = $svc->computeTarget($this->row(['ov_l30' => 3]), $pricer);
        $zero = $svc->computeTarget($this->row(['ov_l30' => 0]), $pricer);

        $this->assertSame(90.0, $sold['sprice']);
        $this->assertSame(80.0, $zero['sprice']);
    }

    public function test_ebay_and_amazon_caps_still_apply_with_labels(): void
    {
        $svc = new NewTemutwoSprcDilAutoPushService();

        $eb = $svc->computeTarget($this->row(['ebay' => 85]), $this->pricer());
        $this->assertSame(85.0, $eb['sprice']);
        $this->assertSame(['EB'], $eb['labels']);

        $amz = $svc->computeTarget($this->row(['ebay' => 85, 'amz' => 80]), $this->pricer());
        $this->assertSame(80.0, $amz['sprice']);
        $this->assertSame(['Amz'], $amz['labels']);

        $above = $svc->computeTarget($this->row(['ebay' => 150, 'amz' => 150]), $this->pricer());
        $this->assertSame(90.0, $above['sprice']);
        $this->assertSame([], $above['labels']);
    }
}
