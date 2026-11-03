<?php

namespace Tests\Unit;

use App\Support\Marketplace\Ebay2VariationMissingHeal;
use PHPUnit\Framework\TestCase;

class Ebay2VariationMissingHealTest extends TestCase
{
    private const REAL_ERROR = 'Variation SKU DS CH BLU SDL HD was not found on eBay item 366634843558.: '
        .'[{"code":"VariationSkuMissing","message":"Variation SKU DS CH BLU SDL HD was not found on eBay item 366634843558. Price was not changed."}]';

    public function test_detects_the_variation_missing_error(): void
    {
        $this->assertTrue(Ebay2VariationMissingHeal::looksVariationMissing(self::REAL_ERROR));
        $this->assertTrue(Ebay2VariationMissingHeal::looksVariationMissing('Variation SKU X was not found on eBay item 366634843558.'));
    }

    public function test_ignores_other_errors(): void
    {
        $this->assertFalse(Ebay2VariationMissingHeal::looksVariationMissing('Not pushed: Price $40.08 ≠ saved S PRC $39.56'));
        $this->assertFalse(Ebay2VariationMissingHeal::looksVariationMissing('[eBay #21919067] It looks like this listing is for an item you already have on eBay'));
        $this->assertFalse(Ebay2VariationMissingHeal::looksVariationMissing(null));
    }

    public function test_extracts_the_item_id(): void
    {
        $this->assertSame('366634843558', Ebay2VariationMissingHeal::itemIdFromMessage(self::REAL_ERROR));
        $this->assertNull(Ebay2VariationMissingHeal::itemIdFromMessage('no item here'));
    }
}
