<?php

namespace Tests\Unit;

use App\Support\Marketplace\LmpStdPrice;
use PHPUnit\Framework\TestCase;

class LmpStdPriceTest extends TestCase
{
    public function test_key_matches_lmp_overall_sku_key(): void
    {
        $this->assertSame('ABC 123', LmpStdPrice::key('  abc   123  '));
        $this->assertSame('', LmpStdPrice::key('   '));
    }

    public function test_price_comes_from_the_skus_own_std_prc(): void
    {
        $price = LmpStdPrice::priceFromMap('abc', [
            'ABC' => 19.99,
        ]);

        $this->assertSame(19.99, $price);
    }

    public function test_linked_sibling_std_prc_is_used_when_the_sku_has_none(): void
    {
        $price = LmpStdPrice::priceFromMap('child', [
            'PARENT' => 36.48,
        ], ['child', 'PARENT']);

        $this->assertSame(36.48, $price);
    }

    public function test_unknown_sku_has_no_std_prc(): void
    {
        $this->assertNull(LmpStdPrice::priceFromMap('missing', [
            'OTHER' => 14.99,
        ]));
    }
}
