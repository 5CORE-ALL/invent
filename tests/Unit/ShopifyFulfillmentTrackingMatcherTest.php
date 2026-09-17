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

    public function test_channel_order_id_comes_from_marketplace_tag(): void
    {
        $matcher = new ShopifyFulfillmentTrackingMatcher;

        $this->assertSame('GSU1RG5550019KF', $matcher->channelOrderIdFromOrder([
            'tags' => 'shein-GSU1RG5550019KF',
            'note' => '',
        ]));
        $this->assertSame('113-3340426-4270650', $matcher->channelOrderIdFromOrder([
            'tags' => 'amazon-113-3340426-4270650',
            'note' => '',
        ]));
        $this->assertTrue($matcher->trackingNumbersEqual('1Z 999 AA1 01 2345 6784', '1z999aa10123456784'));
    }

    public function test_temu_po_ids_are_accepted_on_temu2_orders(): void
    {
        $matcher = new ShopifyFulfillmentTrackingMatcher;

        $this->assertSame('', $matcher->slugFromOrderId('PO-2115257707232237'));
        $this->assertTrue($matcher->slugsCompatible('temu2', 'temu'));
        $this->assertTrue($matcher->slugsCompatible('temu', 'temu2'));
        $this->assertTrue($matcher->slugsCompatible('', 'aliexpress'));
        $this->assertFalse($matcher->slugsCompatible('amazon', 'aliexpress'));
        $this->assertSame(
            'PO-2115257707232237',
            $matcher->matchFullOrderId(
                ['tags' => 'temu2-PO-2115257707232237', 'note' => ''],
                ['PO-2115257707232237']
            )
        );
        $this->assertSame(
            '82109001234613550',
            $matcher->matchFullOrderId(
                ['tags' => 'aliexpress-82109001234613550', 'note' => ''],
                ['82109001234613550']
            )
        );
        $this->assertSame(
            'AE-ORDER-NUM-99',
            $matcher->matchFullOrderId(
                ['tags' => 'aliexpress-AE-ORDER-NUM-99', 'note' => ''],
                ['82109001234613550', 'AE-ORDER-NUM-99']
            )
        );
    }
}
