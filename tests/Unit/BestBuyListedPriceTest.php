<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\BestBuyPricingController;
use App\Models\BestbuyUsaProduct;
use Carbon\Carbon;
use Tests\TestCase;

class BestBuyListedPriceTest extends TestCase
{
    public function test_missing_product_is_not_listed(): void
    {
        $out = BestBuyPricingController::resolveListedPrice(null, false);

        $this->assertFalse($out['listed']);
        $this->assertSame(0.0, $out['price']);
        $this->assertTrue($out['missing']);
    }

    public function test_sheet_or_connect_leftover_hides_price(): void
    {
        $out = BestBuyPricingController::resolveListedPrice((object) [
            'price' => 49.99,
        ], false);

        $this->assertFalse($out['listed']);
        $this->assertSame(0.0, $out['price']);
        $this->assertTrue($out['missing']);
    }

    public function test_inactive_listing_hides_price(): void
    {
        $out = BestBuyPricingController::resolveListedPrice((object) [
            'price' => 18.00,
        ], true, true);

        $this->assertFalse($out['listed']);
        $this->assertSame(0.0, $out['price']);
    }

    public function test_live_mcm_price_stays(): void
    {
        $out = BestBuyPricingController::resolveListedPrice((object) [
            'price' => 49.99,
        ], true);

        $this->assertTrue($out['listed']);
        $this->assertEqualsWithDelta(49.99, $out['price'], 0.001);
        $this->assertFalse($out['missing']);
    }

    public function test_zero_stock_legacy_row_stays_listed_before_of21(): void
    {
        $row = new BestbuyUsaProduct();
        $row->price = 47.49;
        $row->stock = 0;

        $this->assertTrue(BestBuyPricingController::productIsLiveOffer($row));
    }

    public function test_in_stock_legacy_row_is_live(): void
    {
        $row = new BestbuyUsaProduct();
        $row->price = 19.99;
        $row->stock = 4;

        $this->assertTrue(BestBuyPricingController::productIsLiveOffer($row));
    }

    public function test_active_sold_out_offer_is_still_listed(): void
    {
        $row = new BestbuyUsaProduct();
        $row->price = 19.99;
        $row->stock = 0;
        $row->listing_status = 'active';
        $row->updated_at = Carbon::now()->subMinutes(2);

        $this->assertTrue(BestBuyPricingController::productIsLiveOffer(
            $row,
            Carbon::now()->subMinutes(15)
        ));
    }

    public function test_stale_active_nbsp_leftover_is_not_live(): void
    {
        $ghost = new BestbuyUsaProduct();
        $ghost->sku = "DS CH\u{00A0}YLW\u{00A0}REST-LVR";
        $ghost->price = 93.99;
        $ghost->stock = 24;
        $ghost->listing_status = 'active';
        $ghost->updated_at = Carbon::parse('2026-09-12 16:49:53');

        $this->assertFalse(BestBuyPricingController::productIsLiveOffer(
            $ghost,
            Carbon::parse('2026-09-13 05:20:00')->subMinutes(15)
        ));
        $out = BestBuyPricingController::resolveListedPrice(
            $ghost,
            BestBuyPricingController::productIsLiveOffer(
                $ghost,
                Carbon::parse('2026-09-13 05:20:00')->subMinutes(15)
            )
        );
        $this->assertFalse($out['listed']);
        $this->assertSame(0.0, $out['price']);
    }

    public function test_inactive_status_is_not_live_even_with_stock(): void
    {
        $row = new BestbuyUsaProduct();
        $row->price = 123.99;
        $row->stock = 5;
        $row->listing_status = 'inactive';

        $this->assertFalse(BestBuyPricingController::productIsLiveOffer($row));
    }

    public function test_normalize_offer_sku_collapses_nbsp(): void
    {
        $this->assertSame(
            'DS CH YLW REST-LVR',
            BestBuyPricingController::normalizeOfferSku("DS CH\u{00A0}YLW\u{00A0}REST-LVR")
        );
    }

    public function test_listing_inactive_flag(): void
    {
        $this->assertTrue(BestBuyPricingController::isListingMarkedInactive((object) [
            'value' => ['live_inactive' => 'Inactive'],
        ]));
        $this->assertFalse(BestBuyPricingController::isListingMarkedInactive((object) [
            'value' => ['live_inactive' => 'Live'],
        ]));
    }
}
