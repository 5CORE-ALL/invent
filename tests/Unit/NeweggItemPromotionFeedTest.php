<?php

namespace Tests\Unit;

use App\Services\NeweggApiService;
use Tests\TestCase;

class NeweggItemPromotionFeedTest extends TestCase
{
    public function test_promotion_feed_matches_the_newegg_item_promotion_schema(): void
    {
        $xml = NeweggApiService::buildItemPromotionFeedXml([
            [
                'seller_part_number' => 'GSS AL SLV',
                'promo_price' => 19.9,
                'promo_msrp' => 29.99,
                'free_shipping' => true,
                'start_date' => '2026-11-12',
                'end_date' => '2026-11-20',
                'limit_qty' => 5,
                'note' => 'Black Friday & more',
            ],
            ['seller_part_number' => 'BAD', 'promo_price' => 10, 'start_date' => '2026-11-20', 'end_date' => '2026-11-12'],
            ['seller_part_number' => '', 'promo_price' => 10, 'start_date' => '2026-11-12', 'end_date' => '2026-11-20'],
        ]);

        $this->assertStringContainsString('<MessageType>ItemPromotion</MessageType>', $xml);
        $this->assertSame(1, substr_count($xml, '<Item>'));
        $this->assertStringContainsString('<SellerPartNumber>GSS AL SLV</SellerPartNumber>', $xml);
        $this->assertStringContainsString('<PromoMSRP>29.99</PromoMSRP><PromoSellingPrice>19.90</PromoSellingPrice>', $xml);
        $this->assertStringContainsString('<PromoShipping>Free</PromoShipping>', $xml);
        $this->assertStringContainsString('<PromoStartDate>11/12/2026</PromoStartDate><PromoEndDate>11/20/2026</PromoEndDate>', $xml);
        $this->assertStringContainsString('<LimitQty>5</LimitQty><InventoryLocked>No</InventoryLocked>', $xml);
        $this->assertStringContainsString('Black Friday &amp; more', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_promotion_feed_is_empty_without_valid_rows(): void
    {
        $this->assertSame('', NeweggApiService::buildItemPromotionFeedXml([]));
    }
}
