<?php

namespace Tests\Unit;

use App\Support\Marketplace\ChannelMasterViewsGuard;
use PHPUnit\Framework\TestCase;

class ChannelMasterViewsGuardTest extends TestCase
{
    public function test_shopify_and_shopify_b2c_share_views_history(): void
    {
        $this->assertSame(
            ['shopifyb2c', 'shopify'],
            ChannelMasterViewsGuard::historyChannels('Shopify B2C')
        );
        $this->assertSame(
            ['shopifyb2c', 'shopify'],
            ChannelMasterViewsGuard::historyChannels('Shopify')
        );
    }

    public function test_unknown_channel_keeps_its_own_key(): void
    {
        $this->assertSame(['amazon'], ChannelMasterViewsGuard::historyChannels('Amazon'));
    }

    public function test_zero_candidate_is_collapsed_against_baseline(): void
    {
        $this->assertTrue(ChannelMasterViewsGuard::isCollapsed(0, 17112, 255, 255));
    }

    public function test_small_drop_is_not_collapsed(): void
    {
        $this->assertFalse(ChannelMasterViewsGuard::isCollapsed(16000, 17112, 255, 255));
    }
}
