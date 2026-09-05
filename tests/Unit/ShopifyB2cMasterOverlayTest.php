<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\ChannelMasterController;
use ReflectionMethod;
use Tests\TestCase;

class ShopifyB2cMasterOverlayTest extends TestCase
{
    public function test_zero_live_views_do_not_wipe_saved_shopify_b2c_views(): void
    {
        $row = $this->applyViewsSnapshot(
            ['Channel ' => 'Shopify B2C', 'Qty' => 255, 'Total Views' => 17112, 'CVR' => 2.83],
            ['total_views' => 0, 'cvr_pct' => 0.0]
        );

        $this->assertSame(17112, (int) $row['Total Views']);
        $this->assertEqualsWithDelta(2.83, (float) $row['CVR'], 0.001);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{total_views?: int|float, cvr_pct?: float}  $snap
     * @return array<string, mixed>
     */
    private function applyViewsSnapshot(array $row, array $snap): array
    {
        $controller = app(ChannelMasterController::class);
        $method = new ReflectionMethod($controller, 'applyShopifyB2CViewsSnapshot');
        $method->setAccessible(true);

        return $method->invoke($controller, $row, $snap);
    }
}
