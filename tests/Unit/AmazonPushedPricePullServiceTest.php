<?php

namespace Tests\Unit;

use App\Services\AmazonPushedPricePullService;
use Tests\TestCase;

class AmazonPushedPricePullServiceTest extends TestCase
{
    public function test_listings_report_keeps_pushed_sale_when_your_price_differs(): void
    {
        $this->assertSame(
            56.95,
            AmazonPushedPricePullService::listingsReportPriceToWrite(124.99, 56.95)
        );
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

    public function test_live_get_ignores_your_price_when_sale_was_pushed(): void
    {
        $this->assertNull(
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

    public function test_live_get_accepts_price_within_nickel(): void
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
