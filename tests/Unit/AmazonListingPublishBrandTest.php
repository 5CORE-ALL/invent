<?php

namespace Tests\Unit;

use App\Services\AmazonSpApiService;
use App\Services\MarketplaceManager\AmazonListingPublishService;
use PHPUnit\Framework\TestCase;

class AmazonListingPublishBrandTest extends TestCase
{
    public function test_uses_app_brand_instead_of_inc_suffix(): void
    {
        $this->assertSame('5 Core', AmazonListingPublishService::displayBrand([
            'brand' => '5 Core Inc.',
        ]));
        $this->assertSame('5 Core', AmazonListingPublishService::displayBrand([
            'vendor' => '5 Core',
        ]));
        $this->assertSame('5 Core', AmazonListingPublishService::displayManufacturer([
            'manufacturer' => '5 Core Inc',
        ]));
    }

    public function test_strips_inc_suffix(): void
    {
        $this->assertSame('5 Core', AmazonListingPublishService::withoutIncSuffix('5 Core Inc.'));
        $this->assertSame('5 Core', AmazonListingPublishService::withoutIncSuffix('5 Core'));
    }

    public function test_us_offer_includes_price_quantity_and_handling(): void
    {
        $offer = AmazonListingPublishService::usOfferAttributes('LS 100-6 RED', [
            'price' => 79.99,
            'list_price' => 79.99,
            'quantity' => 0,
            'handling_time' => 2,
            'merchant_shipping_group' => 'NATIONAL',
        ], 0, 'B0HJK69VHH');

        $this->assertSame('ALL', $offer['purchasable_offer'][0]['audience']);
        $this->assertSame(79.99, $offer['purchasable_offer'][0]['our_price'][0]['schedule'][0]['value_with_tax']);
        $this->assertSame(0, $offer['fulfillment_availability'][0]['quantity']);
        $this->assertSame(2, $offer['fulfillment_availability'][0]['lead_time_to_ship_max_days']);
        $this->assertSame('NATIONAL', $offer['merchant_shipping_group'][0]['value']);
        $this->assertSame('B0HJK69VHH', $offer['merchant_suggested_asin'][0]['value']);
        $this->assertSame('ATVPDKIKX0DER', $offer['purchasable_offer'][0]['marketplace_id']);
    }

    public function test_handling_days_default_to_two(): void
    {
        $this->assertSame(2, AmazonListingPublishService::handlingDays([]));
        $this->assertSame(3, AmazonListingPublishService::handlingDays(['handling_time' => '3']));
    }

    public function test_extracts_asin_from_incomplete_draft_payload(): void
    {
        $this->assertSame('B0HJK69VHH', AmazonSpApiService::extractAsinFromListingsItem([
            'sku' => 'LS 100-6 RED',
            'summaries' => [],
            'identifiers' => [
                ['asin' => 'B0HJK69VHH', 'marketplaceId' => 'ATVPDKIKX0DER'],
            ],
        ]));
        $this->assertSame('B0HJK69VHH', AmazonSpApiService::extractAsinFromListingsItem([
            'attributes' => [
                'merchant_suggested_asin' => [[
                    'value' => 'B0HJK69VHH',
                    'marketplace_id' => 'ATVPDKIKX0DER',
                ]],
            ],
        ]));
        $this->assertSame('', AmazonSpApiService::extractAsinFromListingsItem([
            'summaries' => [],
            'attributes' => [
                'externally_assigned_product_identifier' => [[
                    'type' => 'upc',
                    'value' => '810199603534',
                ]],
            ],
        ]));
    }
}
