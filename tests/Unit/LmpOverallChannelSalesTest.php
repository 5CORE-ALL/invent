<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\LmpOverallController;
use Tests\TestCase;

class LmpOverallChannelSalesTest extends TestCase
{
    public function test_channel_labels_from_shopify_source_and_tags(): void
    {
        $controller = new LmpOverallController;

        $this->assertSame('Amazon', $controller->channelLabelForSale('amazon', ''));
        $this->assertSame('eBay 2', $controller->channelLabelForSale('eBay', 'Ebay 2'));
        $this->assertSame('eBay 3', $controller->channelLabelForSale('ebay3', ''));
        $this->assertSame('eBay 1', $controller->channelLabelForSale('ebay1', ''));
        $this->assertSame('Shopify', $controller->channelLabelForSale('web', ''));
        $this->assertSame('Shopify Wholesale', $controller->channelLabelForSale('shopify_draft_order', ''));
        $this->assertSame('Temu 3', $controller->channelLabelForSale('temu3', ''));
        $this->assertSame("Macy's", $controller->channelLabelForSale("macy's", ''));
    }
}
