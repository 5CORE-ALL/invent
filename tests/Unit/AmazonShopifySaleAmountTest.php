<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\AmazonDetailFormatter;
use PHPUnit\Framework\TestCase;

class AmazonShopifySaleAmountTest extends TestCase
{
    public function test_sale_uses_item_price_not_tax_inclusive_amazon_total(): void
    {
        $paid = AmazonDetailFormatter::shopifySaleAmount(60.65, [
            ['price' => '56.88', 'quantity' => 1],
        ], 0);

        $this->assertSame(56.88, $paid);
    }

    public function test_sale_includes_shipping_and_ignores_marketplace_tax(): void
    {
        $paid = AmazonDetailFormatter::shopifySaleAmount(64.42, [
            ['price' => '56.88', 'quantity' => 1],
        ], 3.50);

        $this->assertSame(60.38, $paid);
    }

    public function test_falls_back_to_amazon_total_when_lines_are_empty(): void
    {
        $paid = AmazonDetailFormatter::shopifySaleAmount(60.65, [], 0);

        $this->assertSame(60.65, $paid);
    }
}
