<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\MacyController;
use Tests\TestCase;

class MacyListedPriceTest extends TestCase
{
    public function test_inactive_offer_hides_price(): void
    {
        $out = MacyController::resolveListedPrice((object) [
            'price' => 23.41,
            'activated' => false,
        ]);

        $this->assertFalse($out['listed']);
        $this->assertSame(0.0, $out['price']);
        $this->assertTrue($out['missing']);
    }

    public function test_inactive_listing_status_hides_price(): void
    {
        $out = MacyController::resolveListedPrice((object) [
            'price' => 18.00,
            'listing_status' => 'inactive',
        ]);

        $this->assertFalse($out['listed']);
        $this->assertSame(0.0, $out['price']);
        $this->assertTrue($out['missing']);
    }

    public function test_active_offer_keeps_price(): void
    {
        $out = MacyController::resolveListedPrice((object) [
            'price' => 23.41,
            'activated' => true,
        ]);

        $this->assertTrue($out['listed']);
        $this->assertEqualsWithDelta(23.41, $out['price'], 0.001);
        $this->assertFalse($out['missing']);
    }

    public function test_listing_manager_inactive_flag(): void
    {
        $this->assertTrue(MacyController::isListingMarkedInactive((object) [
            'value' => ['live_inactive' => 'Inactive'],
        ]));
        $this->assertFalse(MacyController::isListingMarkedInactive((object) [
            'value' => ['live_inactive' => 'Live'],
        ]));
    }
}
