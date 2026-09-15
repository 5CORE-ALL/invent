<?php

namespace Tests\Unit;

use App\Models\ShopifySku;
use App\Services\MarketplaceManager\Ebay2InventorySyncService;
use App\Services\MarketplaceManager\EbayLiveListingMapper;
use App\Services\MarketplaceManager\MarketplaceMismatchBatch;
use PHPUnit\Framework\TestCase;

class Ebay2MismatchInventoryRulesTest extends TestCase
{
    public function test_trading_limit_ignores_item_ids_that_contain_518(): void
    {
        $this->assertFalse(Ebay2InventorySyncService::looksLikeTradingLimit(
            'ReviseInventoryStatus failed for ItemID 365518123456: SKU does not exist.'
        ));
        $this->assertTrue(Ebay2InventorySyncService::looksLikeTradingLimit(
            'eBay error 518: Call usage limit has been exceeded.'
        ));
        $this->assertTrue(Ebay2InventorySyncService::looksLikeTradingLimit(
            'ErrorCode 518 — GetAPIAccessRules daily limit'
        ));
    }

    public function test_sku_aliases_cover_space_hyphen_and_compact(): void
    {
        $aliases = Ebay2InventorySyncService::skuAliasesForPush('CA10D AL BLK');

        $this->assertContains('CA10D AL BLK', $aliases);
        $this->assertContains('CA10D-AL-BLK', $aliases);
        $this->assertContains('CA10DALBLK', $aliases);
    }

    public function test_variation_sku_matches_hyphen_and_space(): void
    {
        $this->assertTrue(EbayLiveListingMapper::skuEquals('CA10D-AL-BLK', 'CA10D AL BLK'));
        $this->assertTrue(EbayLiveListingMapper::skuEquals('C10BP2010R', 'C10BP 20 10 R'));
        $this->assertFalse(EbayLiveListingMapper::skuEquals('CA10D AL BLK', 'C10MC11'));
    }

    public function test_getitem_qty_uses_matching_variation_not_parent_sum(): void
    {
        $item = [
            'Quantity' => 400,
            'Variations' => [
                'Variation' => [
                    ['SKU' => 'CA10D-AL-BLK', 'Quantity' => 16],
                    ['SKU' => 'OTHER', 'Quantity' => 384],
                ],
            ],
        ];

        $this->assertSame(16, EbayLiveListingMapper::quantityFromGetItem($item, 'CA10D AL BLK'));
    }

    public function test_normalize_sku_for_screenshot_rows(): void
    {
        $this->assertSame('CA10D AL BLK', ShopifySku::normalizeSkuForShopifyLookup('CA10D-AL-BLK'));
        $this->assertSame('C10BP 20 10 R', ShopifySku::normalizeSkuForShopifyLookup('C10BP 20 10 R'));
    }

    public function test_mismatch_batch_leaves_remaining_skus_for_next_run(): void
    {
        $skus = [];
        for ($i = 1; $i <= 20; $i++) {
            $skus[] = 'TT-SKU-'.$i;
        }

        $first = MarketplaceMismatchBatch::take('tiktok2', $skus, 16);
        $this->assertCount(16, $first['batch']);
        $this->assertSame(4, $first['remaining']);
        $this->assertSame('TT-SKU-1', $first['batch'][0]);
    }
}
