<?php

namespace Tests\Unit;

use App\Services\AmazonSpApiService;
use PHPUnit\Framework\TestCase;

class AmazonSaleBusinessMinTest extends TestCase
{
    public function test_business_and_min_are_five_percent_below_sprc(): void
    {
        $plan = AmazonSpApiService::computeSaleBusinessMin(100);

        $this->assertSame(100.0, $plan['sale_price']);
        $this->assertSame(95.0, $plan['business_price']);
        $this->assertSame(95.0, $plan['min_price']);
    }

    public function test_business_and_min_round_to_cents_and_never_exceed_sale(): void
    {
        $plan = AmazonSpApiService::computeSaleBusinessMin(10.01);

        $this->assertSame(10.01, $plan['sale_price']);
        $this->assertSame(9.51, $plan['business_price']);
        $this->assertSame(9.51, $plan['min_price']);
        $this->assertLessThanOrEqual($plan['sale_price'], $plan['min_price']);
        $this->assertLessThanOrEqual($plan['sale_price'], $plan['business_price']);
    }

    public function test_listing_price_sent_to_amazon_is_the_sprc(): void
    {
        $this->assertSame(80.0, AmazonSpApiService::listingPriceForSite(100.0, 80.0));
        $this->assertSame(120.0, AmazonSpApiService::listingPriceForSite(100.0, 120.0));
    }

    public function test_high_std_keeps_your_price_and_puts_sprice_in_sales_price(): void
    {
        $offer = AmazonSpApiService::resolveYourAndSale(100.0, 80.0);

        $this->assertSame(100.0, $offer['your_price']);
        $this->assertSame(80.0, $offer['sale_price']);
        $this->assertTrue($offer['send_sale']);
    }

    public function test_suggestion_above_std_is_capped_and_is_not_a_separate_sale(): void
    {
        $offer = AmazonSpApiService::resolveYourAndSale(50.0, 80.0);

        $this->assertSame(50.0, $offer['your_price']);
        $this->assertSame(50.0, $offer['sale_price']);
        $this->assertFalse($offer['send_sale']);
    }
}
