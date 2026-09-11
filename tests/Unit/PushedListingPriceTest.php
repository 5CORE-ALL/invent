<?php

namespace Tests\Unit;

use App\Services\ChannelLivePriceSync;
use App\Services\DilRuleSpriceApplyService;
use App\Services\TemuShopifySalesService;
use App\Support\PushedListingPrice;
use Tests\TestCase;

class PushedListingPriceTest extends TestCase
{
    public function test_prefer_keeps_pushed_sale_when_catalog_sends_list(): void
    {
        $this->assertSame(56.95, PushedListingPrice::prefer(124.99, 56.95));
    }

    public function test_prefer_uses_catalog_when_it_matches_push(): void
    {
        $this->assertSame(56.95, PushedListingPrice::prefer(56.95, 56.95));
    }

    public function test_prefer_uses_catalog_when_never_pushed(): void
    {
        $this->assertSame(124.99, PushedListingPrice::prefer(124.99, null));
    }

    public function test_prefer_falls_back_to_pushed_when_catalog_has_no_price(): void
    {
        $this->assertSame(56.95, PushedListingPrice::prefer(null, 56.95));
    }

    public function test_from_value_reads_sprice_pushed_value(): void
    {
        $this->assertSame(18.10, PushedListingPrice::fromValue([
            'SPRICE_PUSHED_VALUE' => 18.1,
        ]));
    }

    public function test_temu_base_keeps_incoming_when_it_is_the_push_base(): void
    {
        $sprice = 29.99;
        $base = TemuShopifySalesService::computePushBaseFromSprice($sprice);
        $this->assertNotNull($base);
        $this->assertSame($base, PushedListingPrice::temuBaseToWrite($base, $sprice));
    }

    public function test_temu_base_does_not_store_full_sprice_in_base_column(): void
    {
        $sprice = 29.99;
        $expected = TemuShopifySalesService::computePushBaseFromSprice($sprice);
        $this->assertSame($expected, PushedListingPrice::temuBaseToWrite($sprice, $sprice));
    }

    public function test_temu_base_keeps_pushed_base_when_catalog_sends_other_list(): void
    {
        $sprice = 29.99;
        $expected = TemuShopifySalesService::computePushBaseFromSprice($sprice);
        $this->assertSame($expected, PushedListingPrice::temuBaseToWrite(99.00, $sprice));
    }

    public function test_temu_prefer_incoming_keeps_live_api_price(): void
    {
        $this->assertSame(
            9.32,
            ChannelLivePriceSync::preferIncoming('temu', 'CS 04 2W', 9.32, ['CS 04 2W' => 17.39])
        );
    }

    public function test_temu_compares_sprice_to_live_base_not_full_price(): void
    {
        $m = new \ReflectionMethod(DilRuleSpriceApplyService::class, 'shouldEnqueuePush');
        $m->setAccessible(true);
        $svc = DilRuleSpriceApplyService::for('temu');

        $this->assertFalse($m->invoke($svc, ['sku' => 'CS 04 2W', 'live' => 16.28], 16.28, true));
        $this->assertTrue($m->invoke($svc, ['sku' => 'CS 04 2W', 'live' => 9.32], 16.28, true));
    }

    public function test_dil_does_not_enqueue_when_live_already_matches(): void
    {
        $m = new \ReflectionMethod(DilRuleSpriceApplyService::class, 'shouldEnqueuePush');
        $m->setAccessible(true);
        $svc = DilRuleSpriceApplyService::for('bestbuy');

        $this->assertFalse($m->invoke($svc, ['sku' => 'X', 'live' => 37.50], 37.50, true));
    }

    public function test_dil_enqueues_when_live_differs_and_never_pushed(): void
    {
        $m = new \ReflectionMethod(DilRuleSpriceApplyService::class, 'shouldEnqueuePush');
        $m->setAccessible(true);
        $svc = DilRuleSpriceApplyService::for('bestbuy');

        $this->assertTrue($m->invoke($svc, ['sku' => 'X', 'live' => 49.99], 37.50, true));
    }

    public function test_dil_repairs_instead_of_enqueue_when_already_pushed(): void
    {
        $m = new \ReflectionMethod(DilRuleSpriceApplyService::class, 'shouldEnqueuePush');
        $m->setAccessible(true);
        $svc = DilRuleSpriceApplyService::for('bestbuy');

        $this->assertFalse($m->invoke($svc, [
            'sku' => 'X',
            'live' => 49.99,
            'pushed_sprice' => 37.50,
        ], 37.50, true));
    }
}
