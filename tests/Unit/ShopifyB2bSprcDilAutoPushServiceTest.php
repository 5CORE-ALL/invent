<?php

namespace Tests\Unit;

use App\Services\ShopifyB2bSprcDilAutoPushService;
use App\Services\StorePricePushService;
use App\Support\AmazonDilGroiRule;
use Mockery;
use PHPUnit\Framework\TestCase;

class ShopifyB2bSprcDilAutoPushServiceTest extends TestCase
{
    private function service(): ShopifyB2bSprcDilAutoPushService
    {
        return new ShopifyB2bSprcDilAutoPushService(Mockery::mock(StorePricePushService::class));
    }

    /** @return array{rules:array,cvr_adj:array,lmp_cap:array,diff_adj:array} */
    private function ctx(float $nroi = 50.0): array
    {
        return [
            'rules' => [['key' => '0-100', 'label' => '0–100%', 'min' => 0.0, 'max' => 100.0, 'groi' => $nroi, 'nroi' => $nroi]],
            'cvr_adj' => AmazonDilGroiRule::defaultCvrAdj(),
            'lmp_cap' => AmazonDilGroiRule::defaultLmpCap(),
            'diff_adj' => ['gt_pct' => 20.0, 'points' => 10.0],
        ];
    }

    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'TEST 1',
            'inv' => 10.0,
            'dil' => 5.0,
            'views' => 0.0,
            'b2b_l30' => 0.0,
            'lp' => 10.0,
            'lmp' => 0.0,
            'standard_price' => 0.0,
            'price' => 30.0,
            'sprice' => 0.0,
            'clearance' => false,
        ], $over);
    }

    public function test_dil_price_is_lp_times_target_over_0_95_with_no_ship_and_no_ads(): void
    {
        $r = $this->service()->computeTarget($this->row(), $this->ctx(50.0));

        // (10 × 1.50) / 0.95 = 15.79
        $this->assertSame(15.79, $r['sprice']);
        $this->assertFalse($r['clearance']);
    }

    public function test_clearance_price_uses_only_the_clearance_nroi(): void
    {
        // The same numbers production has live for the clearance SKUs: LP 18.78 at 10% → 21.75.
        $r = $this->service()->computeTarget($this->row(['lp' => 18.78, 'clearance' => true, 'lmp' => 5.0, 'standard_price' => 9.0]), $this->ctx(50.0), 10.0);

        $this->assertSame(21.75, $r['sprice']);
        $this->assertTrue($r['clearance']);
    }

    public function test_clearance_is_priced_even_without_a_slab_or_std_price(): void
    {
        $ctx = $this->ctx();
        $ctx['rules'] = [];
        $r = $this->service()->computeTarget($this->row(['lp' => 4.11, 'clearance' => true]), $ctx, 10.0);

        $this->assertSame(4.76, $r['sprice']);
    }

    public function test_std_price_is_the_maximum(): void
    {
        $r = $this->service()->computeTarget($this->row(['standard_price' => 12.0]), $this->ctx(50.0));

        $this->assertSame(12.0, $r['sprice']);
    }

    public function test_lmp_cap_takes_lmp_less_the_dil_band_percent(): void
    {
        // Rule price 15.79 is above LMP 14.00; SGROI at LMP = (14 × 0.95 − 10) / 10 = 33% (≥ 20%) → LMP × (1 − x%).
        $ctx = $this->ctx(50.0);
        $pct = AmazonDilGroiRule::lmpCapReducePct(5.0, $ctx['lmp_cap']);
        $r = $this->service()->computeTarget($this->row(['lmp' => 14.0]), $ctx);

        $this->assertSame(round(14.0 * (1 - $pct / 100), 2), $r['sprice']);
        $this->assertTrue($r['lmp_capped']);
    }

    public function test_lmp_cap_is_skipped_when_sgroi_at_lmp_is_below_20_percent(): void
    {
        // LMP 11.00 → SGROI (11 × 0.95 − 10) / 10 = 4.5% → keep the rule price.
        $r = $this->service()->computeTarget($this->row(['lmp' => 11.0]), $this->ctx(50.0));

        $this->assertSame(15.79, $r['sprice']);
        $this->assertFalse($r['lmp_capped']);
    }

    public function test_diff_boost_does_not_depend_on_the_live_price_so_it_cannot_flip(): void
    {
        // Std 40 vs the unboosted rule price 15.79 is far above 20% → +10 points, whatever the live Price is.
        $svc = $this->service();
        $ctx = $this->ctx(50.0);
        $prices = [];
        foreach ([10.0, 15.79, 17.37, 30.0, 39.0, 80.0] as $live) {
            $prices[] = $svc->computeTarget($this->row(['standard_price' => 400.0, 'price' => $live]), $ctx)['sprice'];
        }

        // (10 × 1.60) / 0.95 = 16.84 for every live price (pushing it does not remove the boost).
        $this->assertSame([16.84], array_values(array_unique($prices)));
    }

    public function test_diff_boost_is_removed_when_std_is_within_the_threshold_of_the_rule_price(): void
    {
        // Std 18 is only 14% above the unboosted 15.79 → no boost.
        $r = $this->service()->computeTarget($this->row(['standard_price' => 18.0]), $this->ctx(50.0));

        $this->assertSame(15.79, $r['sprice']);
    }

    public function test_no_lp_or_no_stock_gives_no_price(): void
    {
        $svc = $this->service();

        $this->assertNull($svc->computeTarget($this->row(['lp' => 0.0]), $this->ctx()));
        $this->assertNull($svc->computeTarget($this->row(['inv' => 0.0]), $this->ctx()));
    }

    public function test_cvr_overlay_needs_views_and_b2b_sales(): void
    {
        $ctx = $this->ctx(50.0);
        $svc = $this->service();
        $none = $svc->computeTarget($this->row(['views' => 0.0, 'b2b_l30' => 2.0]), $ctx)['sprice'];
        // CVR = 2 / 100 = 2% < the default Down threshold → Down adj applied (a lower target than no overlay).
        $down = $svc->computeTarget($this->row(['views' => 100.0, 'b2b_l30' => 2.0]), $ctx)['sprice'];

        $this->assertSame(15.79, $none);
        $this->assertLessThan($none, $down);
    }

    public function test_failed_push_cooldown_key_is_per_sku_and_price(): void
    {
        $this->assertSame('shopify-b2b-push-fail:AB 1:15.79', ShopifyB2bSprcDilAutoPushService::cooldownKey(' ab 1 ', 15.79));
        $this->assertNotSame(
            ShopifyB2bSprcDilAutoPushService::cooldownKey('AB 1', 15.79),
            ShopifyB2bSprcDilAutoPushService::cooldownKey('AB 1', 16.0)
        );
    }
}
