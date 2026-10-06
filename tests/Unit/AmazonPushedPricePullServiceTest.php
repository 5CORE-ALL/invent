<?php

namespace Tests\Unit;

use App\Services\AmazonPushedPricePullService;
use Tests\TestCase;

class AmazonPushedPricePullServiceTest extends TestCase
{
    public function test_listings_report_keeps_amazon_price_when_it_differs_from_pushed_sale(): void
    {
        $this->assertSame(
            124.99,
            AmazonPushedPricePullService::listingsReportPriceToWrite(124.99, 56.95)
        );
    }

    public function test_customer_price_is_the_active_sale(): void
    {
        $this->assertSame(21.99, AmazonPushedPricePullService::customerPrice(18.06, 21.99));
        $this->assertSame(18.06, AmazonPushedPricePullService::customerPrice(18.06, null));
        $this->assertNull(AmazonPushedPricePullService::customerPrice(0, 0));
    }

    public function test_tabulator_price_stays_on_pushed_sale_not_your_price(): void
    {
        $this->assertSame(34.03, AmazonPushedPricePullService::tabulatorPriceAfterPush(36.99, 34.03));
        $this->assertSame(36.99, AmazonPushedPricePullService::tabulatorPriceAfterPush(36.99, null));
    }

    public function test_listings_report_uses_report_price_when_it_matches_sale(): void
    {
        $this->assertSame(
            56.95,
            AmazonPushedPricePullService::listingsReportPriceToWrite(56.95, 56.95)
        );
    }

    public function test_listings_report_uses_report_price_when_never_pushed(): void
    {
        $this->assertSame(
            124.99,
            AmazonPushedPricePullService::listingsReportPriceToWrite(124.99, null)
        );
    }

    public function test_listings_report_falls_back_to_pushed_sale_when_report_has_no_price(): void
    {
        $this->assertSame(
            56.95,
            AmazonPushedPricePullService::listingsReportPriceToWrite(null, 56.95)
        );
    }

    public function test_live_get_stores_amazon_price_when_it_differs_from_pushed_sale(): void
    {
        $this->assertSame(
            124.99,
            AmazonPushedPricePullService::livePriceToPersist(124.99, 56.95)
        );
    }

    public function test_live_get_accepts_sale_that_matches_push(): void
    {
        $this->assertSame(
            56.95,
            AmazonPushedPricePullService::livePriceToPersist(56.95, 56.95)
        );
    }

    public function test_live_get_keeps_amazon_cents_when_within_a_nickel_of_sprice(): void
    {
        $this->assertSame(
            56.97,
            AmazonPushedPricePullService::livePriceToPersist(56.97, 56.95)
        );
    }

    public function test_pushed_sale_from_value_reads_amazon_pushed_sale(): void
    {
        $this->assertSame(
            18.10,
            AmazonPushedPricePullService::pushedSaleFromValue([
                'AMAZON_PUSHED_SALE' => 18.1,
                'AMAZON_PUSHED_BUSINESS' => 18.1,
                'AMAZON_PUSHED_MIN' => 18.1,
            ])
        );
    }

    public function test_lookup_pushed_sale_matches_compact_sku(): void
    {
        $lookup = [
            '3501 USB WB' => 56.95,
            '3501USBWB' => 56.95,
        ];

        $this->assertSame(
            56.95,
            AmazonPushedPricePullService::lookupPushedSale($lookup, '3501 USB WB')
        );
        $this->assertSame(
            56.95,
            AmazonPushedPricePullService::lookupPushedSale($lookup, '3501USBWB')
        );
    }
}
