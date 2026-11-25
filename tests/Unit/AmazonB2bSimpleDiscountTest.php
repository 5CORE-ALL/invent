<?php

namespace Tests\Unit;

use App\Services\AmazonSpApiService;
use Tests\TestCase;

/**
 * Simple Amazon Business discount: Business price = ROUND(List × (1 − pct/100), 2), List stays crossed out,
 * and no quantity discount / tier is ever part of the B2B offer.
 */
class AmazonB2bSimpleDiscountTest extends TestCase
{
    /** @return array<string, array{0: float, 1: float, 2: float}> */
    public static function examples(): array
    {
        return [
            '14.99 at 5%' => [14.99, 5.0, 14.24],
            '19.99 at 5%' => [19.99, 5.0, 18.99],
            '29.99 at 10%' => [29.99, 10.0, 26.99],
            '49.99 at 15%' => [49.99, 15.0, 42.49],
        ];
    }

    /** @dataProvider examples */
    public function test_business_price_formula(float $list, float $pct, float $expected): void
    {
        $this->assertSame($expected, AmazonSpApiService::businessPriceFromList($list, $pct));
    }

    /** @dataProvider examples */
    public function test_b2b_offer_payload_has_crossed_out_list_and_discounted_business_price(float $list, float $pct, float $expected): void
    {
        $offer = AmazonSpApiService::buildB2bOffer($list, round($list * 0.95, 2), $pct);

        $this->assertSame('B2B', $offer['audience']);
        $this->assertSame($list, $offer['our_price'][0]['schedule'][0]['value_with_tax']);
        $this->assertSame($expected, $offer['discounted_price'][0]['schedule'][0]['value_with_tax']);
        $this->assertArrayHasKey('start_at', $offer['discounted_price'][0]['schedule'][0]);
        $this->assertArrayHasKey('end_at', $offer['discounted_price'][0]['schedule'][0]);
    }

    /** @dataProvider examples */
    public function test_b2b_offer_never_contains_quantity_discount(float $list, float $pct, float $expected): void
    {
        $json = json_encode(AmazonSpApiService::buildB2bOffer($list, round($list * 0.95, 2), $pct));

        $this->assertStringNotContainsString('quantity', strtolower((string) $json));
        $this->assertStringNotContainsString('lower_bound', (string) $json);
        $this->assertStringNotContainsString('levels', (string) $json);
    }

    public function test_feature_off_keeps_existing_single_business_price(): void
    {
        config(['services.amazon_sp.b2b_discount_enabled' => false]);
        $this->assertNull(AmazonSpApiService::b2bDiscountPercent());

        $offer = AmazonSpApiService::buildB2bOffer(19.99, 18.99, AmazonSpApiService::b2bDiscountPercent());

        $this->assertSame(18.99, $offer['our_price'][0]['schedule'][0]['value_with_tax']);
        $this->assertArrayNotHasKey('discounted_price', $offer);
    }

    public function test_config_enables_and_validates_percent(): void
    {
        config(['services.amazon_sp.b2b_discount_enabled' => true, 'services.amazon_sp.b2b_discount_pct' => 5]);
        $this->assertSame(5.0, AmazonSpApiService::b2bDiscountPercent());

        foreach ([0, -3, 100, 'abc'] as $bad) {
            config(['services.amazon_sp.b2b_discount_pct' => $bad]);
            $this->assertNull(AmazonSpApiService::b2bDiscountPercent(), "pct {$bad} must disable the discount");
        }
    }

    public function test_quantity_tier_code_is_gone(): void
    {
        $this->assertFalse(method_exists(AmazonSpApiService::class, 'b2bQuantityDiscountPlan'));
        $this->assertFalse(method_exists(AmazonSpApiService::class, 'b2bQuantityTierConfig'));
    }
}
