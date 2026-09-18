<?php

namespace Tests\Unit;

use App\Services\Support\NewTemuoneSuggestedPriceStore;
use App\Services\TemuShopifySalesService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NewTemuoneSuggestedPriceStoreTest extends TestCase
{
    public function test_snroi_target_with_ads_is_below_sgroi_at_the_saved_sprice(): void
    {
        $lp = 9.22;
        $ship = 7.2;
        $ads = 5.0;
        $grossOnly = NewTemuoneSuggestedPriceStore::priceFromExactSgroi($lp, $ship, 40.0, 0.0);
        $sprice = NewTemuoneSuggestedPriceStore::priceFromExactSgroi($lp, $ship, 40.0, $ads);

        $this->assertGreaterThan($grossOnly, $sprice);

        $snroi = TemuShopifySalesService::snroiAtSprice($sprice, $lp, $ship, $ads, 0.0);
        $this->assertNotNull($snroi);
        $this->assertEqualsWithDelta(40.0, $snroi, 0.6);

        $sgroi = TemuShopifySalesService::sgroiAtSprice($sprice, $lp, $ship, 0.0);
        $this->assertNotNull($sgroi);
        $this->assertGreaterThan($snroi, $sgroi);
    }

    public function test_dil_snroi_target_survives_temu_299_band(): void
    {
        $lp = 9.90;
        $ship = 10.04;
        $ads = 3.3;
        $sprice = NewTemuoneSuggestedPriceStore::priceFromExactSgroi($lp, $ship, 40.0, $ads);
        $this->assertGreaterThan(26.99, $sprice);

        $snroi = TemuShopifySalesService::snroiAtSprice($sprice, $lp, $ship, $ads, 0.0);
        $this->assertNotNull($snroi);
        $this->assertEqualsWithDelta(40.0, $snroi, 1.0);

        $sgroi = TemuShopifySalesService::sgroiAtSprice($sprice, $lp, $ship, 0.0);
        $this->assertNotNull($sgroi);
        $this->assertGreaterThan($snroi, $sgroi);
    }

    public function test_zero_ads_snroi_matches_sgroi_at_solved_price(): void
    {
        $sprice = NewTemuoneSuggestedPriceStore::priceFromExactSgroi(9.22, 7.2, 40.0, 0.0);
        $this->assertGreaterThan(0, $sprice);

        $sgroi = TemuShopifySalesService::sgroiAtSprice($sprice, 9.22, 7.2, 0.0);
        $snroi = TemuShopifySalesService::snroiAtSprice($sprice, 9.22, 7.2, 0.0, 0.0);
        $this->assertNotNull($sgroi);
        $this->assertNotNull($snroi);
        $this->assertEqualsWithDelta(40.0, $sgroi, 0.6);
        $this->assertEqualsWithDelta($sgroi, $snroi, 0.05);
    }

    public function test_write_exact_sprice_queues_persist(): void
    {
        $store = new NewTemuoneSuggestedPriceStore();
        $this->assertSame(0, $store->pendingWriteCount());
        $store->writeExactSprice('SKU-1', 19.99, 9.22, 7.2, 24.50, ['EB']);
        $this->assertSame(1, $store->pendingWriteCount());
    }

    public function test_sprc_dil_auto_push_command_is_registered(): void
    {
        $this->assertArrayHasKey('newtemuone:sprc-dil-auto-push', Artisan::all());
    }
}
