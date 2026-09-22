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

    public function test_zero_stock_offer_with_price_is_still_listed(): void
    {
        $row = new PurchasingPowerProduct();
        $row->price = 47.49;
        $row->stock = 0;

        $this->assertTrue(PurchasingPowerController::productIsLiveOffer($row));
        $out = PurchasingPowerController::resolveListedPrice(
            $row,
            PurchasingPowerController::productIsLiveOffer($row)
        );
        $this->assertTrue($out['listed']);
        $this->assertEqualsWithDelta(47.49, $out['price'], 0.001);
    }

    public function test_in_stock_legacy_row_is_live(): void
    {
        $row = new PurchasingPowerProduct();
        $row->price = 19.99;
        $row->stock = 4;

        $this->assertTrue(PurchasingPowerController::productIsLiveOffer($row));
    }

    public function test_active_sold_out_offer_is_still_listed(): void
    {
        $row = new PurchasingPowerProduct();
        $row->price = 19.99;
        $row->stock = 0;
        $row->listing_status = 'active';
        $row->updated_at = Carbon::now()->subMinutes(2);

        $this->assertTrue(PurchasingPowerController::productIsLiveOffer(
            $row,
            Carbon::now()->subMinutes(15)
        ));
    }

    public function test_active_priced_row_stays_listed_outside_freshness_window(): void
    {
        $row = new PurchasingPowerProduct();
        $row->sku = "DS CH\u{00A0}YLW\u{00A0}REST-LVR";
        $row->price = 93.99;
        $row->stock = 24;
        $row->listing_status = 'active';
        $row->updated_at = Carbon::parse('2026-09-12 16:49:53');

        $this->assertTrue(PurchasingPowerController::productIsLiveOffer(
            $row,
            Carbon::parse('2026-09-13 05:20:00')->subMinutes(15)
        ));
        $out = PurchasingPowerController::resolveListedPrice(
            $row,
            PurchasingPowerController::productIsLiveOffer(
                $row,
                Carbon::parse('2026-09-13 05:20:00')->subMinutes(15)
            )
        );
        $this->assertTrue($out['listed']);
        $this->assertEqualsWithDelta(93.99, $out['price'], 0.001);
    }

    public function test_normalize_offer_sku_collapses_nbsp(): void
    {
        $this->assertSame(
            'DS CH YLW REST-LVR',
            PurchasingPowerController::normalizeOfferSku("DS CH\u{00A0}YLW\u{00A0}REST-LVR")
        );
    }

    public function test_inactive_flag_still_shows_mcm_price(): void
    {
        $row = new PurchasingPowerProduct();
        $row->price = 123.99;
        $row->stock = 5;
        $row->listing_status = 'inactive';

        $this->assertTrue(PurchasingPowerController::productIsLiveOffer($row));
        $out = PurchasingPowerController::resolveListedPrice(
            $row,
            PurchasingPowerController::productIsLiveOffer($row)
        );
        $this->assertTrue($out['listed']);
        $this->assertEqualsWithDelta(123.99, $out['price'], 0.001);
    }

    public function test_offer_lookup_keeps_shop_sku_case_when_push_uppercases(): void
    {
        $this->assertSame(
            'GRack 9N1 OVAL',
            PurchasingPowerController::offerLookupSku('GRACK 9N1 OVAL', 'GRack 9N1 OVAL')
        );
        $this->assertSame(
            'WST 04-15 Pair',
            PurchasingPowerController::offerLookupSku('WST 04-15 PAIR', 'WST 04-15 Pair')
        );
        $this->assertSame(
            'MR6.5-4oHMX2Pcs+TW-BULLET180X2Pcs',
            PurchasingPowerController::offerLookupSku(
                'MR6.5-4OHMX2PCS+TW-BULLET180X2PCS',
                'MR6.5-4oHMX2Pcs+TW-BULLET180X2Pcs'
            )
        );
    }

    public function test_offer_lookup_uses_request_case_when_stored_sku_is_upper(): void
    {
        $this->assertSame(
            'GRack 9N1 OVAL',
            PurchasingPowerController::offerLookupSku('GRack 9N1 OVAL', 'GRACK 9N1 OVAL')
        );
    }

    public function test_offer_lookup_keeps_already_upper_sku(): void
    {
        $this->assertSame(
            'CS 05 2W',
            PurchasingPowerController::offerLookupSku('CS 05 2W', 'CS 05 2W')
        );
        $this->assertSame(
            'WF 8140 DBL D4',
            PurchasingPowerController::offerLookupSku('WF 8140 DBL D4', null)
        );
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
