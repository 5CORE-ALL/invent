<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\MarketplaceTrackingOwnership;
use Tests\TestCase;

class MarketplaceTrackingOwnershipTest extends TestCase
{
    public function test_amazon_tracking_formats_are_wrong_on_shein(): void
    {
        $ownership = new MarketplaceTrackingOwnership;

        $this->assertTrue($ownership->looksLikeForeignChannelTracking('TBA123456789000', 'shein'));
        $this->assertTrue($ownership->looksLikeForeignChannelTracking('113-3340426-4270650', 'shein'));
        $this->assertFalse($ownership->looksLikeForeignChannelTracking('TBA123456789000', 'amazon'));
        $this->assertFalse($ownership->looksLikeForeignChannelTracking('1Z999AA10123456784', 'shein'));
        $this->assertFalse($ownership->looksLikeForeignChannelTracking('1Z999AA10123456784', 'amazon'));
    }

    public function test_identity_from_shein_and_amazon_raw_rows(): void
    {
        $ownership = new MarketplaceTrackingOwnership;

        $shein = $ownership->identityFromRawRow((object) [
            'order_id' => '9988776655443',
            'tags' => 'shein-GSU1RJ512001VR8',
            'note' => '',
            'order_number' => '#334042',
        ]);
        $this->assertSame('shein', $shein['slug']);
        $this->assertSame('GSU1RJ512001VR8', $shein['order_id']);
        $this->assertSame('9988776655443', $shein['shopify_order_id']);

        $amazon = $ownership->identityFromRawRow((object) [
            'order_id' => '1122334455667',
            'tags' => 'amazon-113-3340426-4270650',
            'note' => '',
            'order_number' => '#334043',
        ]);
        $this->assertSame('amazon', $amazon['slug']);
        $this->assertSame('113-3340426-4270650', $amazon['order_id']);
    }

    public function test_same_channel_order_id_ignores_separators(): void
    {
        $ownership = new MarketplaceTrackingOwnership;

        $this->assertTrue($ownership->sameChannelOrderId('GSU1RE28R00NLJU', 'gsu1re28r00nlju'));
        $this->assertFalse($ownership->sameChannelOrderId('GSU1RE28R00NLJU', '113-3340426-4270650'));
    }
}
