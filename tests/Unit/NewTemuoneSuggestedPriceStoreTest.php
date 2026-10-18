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

    public function test_sprice_rejects_the_299_gap_and_uses_the_full_temu_price(): void
    {
        // Temu Price $4.62 = (base × 1.1364) + $2.99. $1.63 is that price with the $2.99 removed.
        $this->assertSame(0.0, TemuShopifySalesService::computeBaseFromFullTemuPrice(1.63));

        $base = TemuShopifySalesService::computeBaseFromFullTemuPrice(4.62);
        $this->assertEqualsWithDelta(1.434354, $base, 0.001);
        $this->assertEqualsWithDelta(4.62, TemuShopifySalesService::computeFullTemuPrice($base), 0.02);

        $sprice = NewTemuoneSuggestedPriceStore::priceFromExactSgroi(1.5, 2.0, 40.0, 2.2);
        $this->assertGreaterThan(4.0, $sprice);

        $snroi = TemuShopifySalesService::snroiAtSprice($sprice, 1.5, 2.0, 2.2, 0.0);
        $this->assertNotNull($snroi);
        $this->assertEqualsWithDelta(40.0, $snroi, 1.0);

        $rebuilt = TemuShopifySalesService::computeFullTemuPrice(
            TemuShopifySalesService::computeBaseFromFullTemuPrice($sprice)
        );
        $this->assertEqualsWithDelta($sprice, $rebuilt, 0.02);
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

    public function test_s_r_prc_is_sprice_times_088(): void
    {
        $this->assertSame(0.0, TemuShopifySalesService::newTemuoneSRPrice(0.0));
        $this->assertEqualsWithDelta(88.0, TemuShopifySalesService::newTemuoneSRPrice(100.0), 0.001);
        $this->assertEqualsWithDelta(19.09, TemuShopifySalesService::newTemuoneSRPrice(21.69), 0.001);
    }

    public function test_s_base_prc_is_s_r_and_drops_299_under_30(): void
    {
        // S R 88.00 (>= $30): S Base = S R.
        $this->assertEqualsWithDelta(88.0, TemuShopifySalesService::newTemuoneSBasePrice(100.0), 0.001);
        // S R 19.09 (< $30): S Base = S R - 2.99.
        $this->assertEqualsWithDelta(16.10, TemuShopifySalesService::newTemuoneSBasePrice(21.69), 0.001);
        // Boundary: S R exactly $30.00 keeps S R, just under drops $2.99.
        $this->assertEqualsWithDelta(30.0, TemuShopifySalesService::newTemuoneSBasePrice(30.0 / 0.88), 0.011);
        $this->assertEqualsWithDelta(26.99, TemuShopifySalesService::newTemuoneSBasePrice(29.99 / 0.88), 0.011);
        // S R at or below $2.99 would give no base.
        $this->assertSame(0.0, TemuShopifySalesService::newTemuoneSBasePrice(3.0));
    }

    public function test_lmp_as_sprice_follows_the_same_rule(): void
    {
        $lmp = 45.50;
        $this->assertEqualsWithDelta(40.04, TemuShopifySalesService::newTemuoneSRPrice($lmp), 0.001);
        $this->assertEqualsWithDelta(40.04, TemuShopifySalesService::newTemuoneSBasePrice($lmp), 0.001);
        $lmpLow = 30.0;
        $this->assertEqualsWithDelta(26.4, TemuShopifySalesService::newTemuoneSRPrice($lmpLow), 0.001);
        $this->assertEqualsWithDelta(23.41, TemuShopifySalesService::newTemuoneSBasePrice($lmpLow), 0.001);
    }

    public function test_sprc_dil_auto_push_command_is_registered(): void
    {
        $this->assertArrayHasKey('newtemuone:sprc-dil-auto-push', Artisan::all());
    }
}
