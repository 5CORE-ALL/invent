<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\PurchasingPowerController;
use App\Models\PurchasingPowerProduct;
use Carbon\Carbon;
use Tests\TestCase;

class PurchasingPowerListedPriceTest extends TestCase
{
    public function test_missing_product_is_not_listed(): void
    {
        $out = PurchasingPowerController::resolveListedPrice(null, false);

        $this->assertFalse($out['listed']);
        $this->assertSame(0.0, $out['price']);
        $this->assertTrue($out['missing']);
    }

    public function test_leftover_stale_row_hides_price(): void
    {
        $out = PurchasingPowerController::resolveListedPrice((object) [
            'price' => 12.99,
        ], false);

        $this->assertFalse($out['listed']);
        $this->assertSame(0.0, $out['price']);
        $this->assertTrue($out['missing']);
    }

    public function test_inactive_listing_hides_price(): void
    {
        $out = PurchasingPowerController::resolveListedPrice((object) [
            'price' => 18.00,
        ], true, true);

        $this->assertFalse($out['listed']);
        $this->assertSame(0.0, $out['price']);
    }

    public function test_live_mcm_price_stays(): void
    {
        $out = PurchasingPowerController::resolveListedPrice((object) [
            'price' => 12.99,
        ], true);

        $this->assertTrue($out['listed']);
        $this->assertEqualsWithDelta(12.99, $out['price'], 0.001);
        $this->assertFalse($out['missing']);
    }

    public function test_product_in_latest_mcm_window(): void
    {
        $freshAfter = Carbon::now()->subMinutes(15);
        $fresh = new PurchasingPowerProduct();
        $fresh->updated_at = Carbon::now()->subMinutes(2);
        $stale = new PurchasingPowerProduct();
        $stale->updated_at = Carbon::now()->subHours(6);

        $this->assertTrue(PurchasingPowerController::productInLatestMcm($fresh, $freshAfter));
        $this->assertFalse(PurchasingPowerController::productInLatestMcm($stale, $freshAfter));
        $this->assertFalse(PurchasingPowerController::productInLatestMcm(null, $freshAfter));
    }

    public function test_listing_inactive_flag(): void
    {
        $this->assertTrue(PurchasingPowerController::isListingMarkedInactive((object) [
            'value' => ['live_inactive' => 'Inactive'],
        ]));
        $this->assertFalse(PurchasingPowerController::isListingMarkedInactive((object) [
            'value' => ['live_inactive' => 'Live'],
        ]));
    }
}
