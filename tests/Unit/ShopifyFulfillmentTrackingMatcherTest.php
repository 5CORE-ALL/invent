<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\ShopifyFulfillmentTrackingMatcher;
use PHPUnit\Framework\TestCase;

class ShopifyFulfillmentTrackingMatcherTest extends TestCase
{
    public function test_full_shein_order_id_matches_and_short_fragment_does_not(): void
    {
        $matcher = new ShopifyFulfillmentTrackingMatcher;
        $order = [
            'name' => '#334042',
            'tags' => 'shein-GSU1RG5550019KF',
            'note' => 'Shein GSU1RG5550019KF',
        ];

        $this->assertSame(
            'GSU1RG5550019KF',
            $matcher->matchFullOrderId($order, ['GSU1RG5550019KF'])
        );
        $this->assertNull($matcher->matchFullOrderId($order, ['GSU1RG555']));
        $this->assertNull($matcher->matchFullOrderId($order, ['113-3340426-4270650']));
    }

    public function test_amazon_order_id_is_not_treated_as_shein(): void
    {
        $matcher = new ShopifyFulfillmentTrackingMatcher;

        $this->assertSame('shein', $matcher->slugFromOrderId('GSU1RJ512001VR8'));
        $this->assertSame('amazon', $matcher->slugFromOrderId('113-3340426-4270650'));
        $this->assertSame('shein', $matcher->slugFromOrderIds(['GSU1RE28R00NLJU']));
    }

    public function test_primary_slug_prefers_shein_tag_over_amazon_number_in_note(): void
    {
        $matcher = new ShopifyFulfillmentTrackingMatcher;

        $this->assertSame('shein', $matcher->primaryMarketplaceSlug([
            'tags' => 'shein-GSU1RG5550019KF',
            'note' => '',
        ]));
        $this->assertSame('amazon', $matcher->primaryMarketplaceSlug([
            'tags' => 'amazon-113-3340426-4270650',
            'note' => 'GSU1RG5550019KF',
        ]));
    }

    public function test_single_sku_order_requires_one_real_sku(): void
    {
        $matcher = new ShopifyFulfillmentTrackingMatcher;

        $this->assertTrue($matcher->isSingleSkuOrder([
            ['sku' => 'ABC-1'],
        ]));
        $this->assertFalse($matcher->isSingleSkuOrder([
            ['sku' => 'ABC-1'],
            ['sku' => 'ABC-2'],
        ]));
        $this->assertFalse($matcher->isSingleSkuOrder([
            ['sku' => ''],
            ['sku' => '__order__'],
        ]));
    }
}
