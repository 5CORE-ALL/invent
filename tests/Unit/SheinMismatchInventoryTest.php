<?php

namespace Tests\Unit;

use App\Services\SheinApiService;
use Tests\TestCase;

class SheinMismatchInventoryTest extends TestCase
{
    public function test_sku_aliases_cover_space_hyphen_and_compact(): void
    {
        $aliases = SheinApiService::skuAliasesForLookup('TABLA MIC BLK');

        $this->assertContains('TABLA MIC BLK', $aliases);
        $this->assertContains('TABLA-MIC-BLK', $aliases);
        $this->assertContains('TABLAMICBLK', $aliases);
    }

    public function test_platform_sku_code_rejects_seller_sku_and_spaces(): void
    {
        $api = new SheinApiService();

        $this->assertFalse($api->isPlatformSkuCode('TABLA MIC BLK', 'TABLA MIC BLK'));
        $this->assertFalse($api->isPlatformSkuCode('TABLA MIC BLK', 'OTHER'));
        $this->assertTrue($api->isPlatformSkuCode('I11mesukkwwr', 'TABLA MIC BLK'));
    }
}
