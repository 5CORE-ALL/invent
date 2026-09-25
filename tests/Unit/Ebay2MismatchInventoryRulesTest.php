<?php

namespace Tests\Unit;

use App\Models\ShopifySku;
use App\Services\Ebay2ApiService;
use App\Services\MarketplaceManager\Ebay2InventorySyncService;
use App\Services\MarketplaceManager\Ebay2LiveListingsService;
use App\Services\MarketplaceManager\EbayLiveListingMapper;
use App\Services\MarketplaceManager\MarketplaceMismatchBatch;
use PHPUnit\Framework\TestCase;

class Ebay2MismatchInventoryRulesTest extends TestCase
{
    public function test_shared_item_id_does_not_copy_the_first_variation_qty(): void
    {
        $rows = [
            ['product_id' => '366636745339', 'sku' => '5581 USB 10', 'inventory' => 60],
            ['product_id' => '366636745339', 'sku' => '6581 USB', 'inventory' => 14],
        ];
        $indexed = EbayLiveListingMapper::indexDetailsForIds($rows, ['366636745339']);

        $first = EbayLiveListingMapper::detailForSku($indexed, '5581 USB 10');
        $second = EbayLiveListingMapper::detailForSku($indexed, '6581 USB');

        $this->assertSame(60, $first['inventory'] ?? null);
        $this->assertSame(14, $second['inventory'] ?? null);
        $this->assertSame(60, $indexed['366636745339']['inventory'] ?? null);
    }

    public function test_fixed_price_success_is_kept_when_getitem_still_shows_the_old_qty(): void
    {
        $this->assertFalse(Ebay2InventorySyncService::rejectUnconfirmedEbayQty(4, 24, true));
        $this->assertFalse(Ebay2InventorySyncService::rejectUnconfirmedEbayQty(24, 24, false));
        $this->assertFalse(Ebay2InventorySyncService::rejectUnconfirmedEbayQty(null, 24, false));
        $this->assertTrue(Ebay2InventorySyncService::rejectUnconfirmedEbayQty(4, 24, false));
    }

    public function test_fixed_price_revise_adds_units_already_sold(): void
    {
        $this->assertSame(44, Ebay2ApiService::totalQtyForFixedPriceRevise(24, 20));
        $this->assertSame(24, Ebay2ApiService::totalQtyForFixedPriceRevise(24, 0));
        $this->assertSame(20, Ebay2ApiService::soldFromItem([
            'Quantity' => 24,
            'Variations' => [
                'Variation' => [
                    [
                        'SKU' => '36L BLACK OPEN BOX',
                        'Quantity' => 24,
                        'SellingStatus' => ['QuantitySold' => 20],
                    ],
                ],
            ],
        ], '36L BLACK OPEN BOX'));
    }

    public function test_trading_limit_ignores_item_ids_that_contain_518(): void
    {
        $this->assertFalse(Ebay2InventorySyncService::looksLikeTradingLimit(
            'ReviseInventoryStatus failed for ItemID 365518123456: SKU does not exist.'
        ));
        $this->assertFalse(Ebay2InventorySyncService::looksLikeTradingLimit(
            'SKU does not exist for item #518123456789.'
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

        $soldItem = [
            'Quantity' => 44,
            'Variations' => [
                'Variation' => [
                    [
                        'SKU' => '36L BLACK OPEN BOX',
                        'Quantity' => 44,
                        'SellingStatus' => ['QuantitySold' => 20],
                    ],
                ],
            ],
        ];
        $this->assertSame(24, EbayLiveListingMapper::quantityFromGetItem($soldItem, '36L BLACK OPEN BOX'));
    }

    public function test_normalize_sku_for_screenshot_rows(): void
    {
        $this->assertSame('CA10D AL BLK', ShopifySku::normalizeSkuForShopifyLookup('CA10D-AL-BLK'));
        $this->assertSame('C10BP 20 10 R', ShopifySku::normalizeSkuForShopifyLookup('C10BP 20 10 R'));
    }

    public function test_missing_name_value_list_is_not_a_sku_mismatch(): void
    {
        $this->assertTrue(Ebay2InventorySyncService::looksLikeMissingNameValueList(
            'Missing name in name-value list. (eBay code: 21916587)'
        ));
        $this->assertFalse(Ebay2InventorySyncService::looksLikeSkuMismatch(
            'Missing name in name-value list. (eBay code: 21916587)'
        ));
    }

    public function test_variation_specifics_are_read_for_matching_sku(): void
    {
        $item = [
            'Variations' => [
                'Variation' => [
                    [
                        'SKU' => 'CS CHI BLU HTSY-L',
                        'Quantity' => 0,
                        'VariationSpecifics' => [
                            'NameValueList' => [
                                ['Name' => 'Color', 'Value' => 'Blue'],
                                ['Name' => 'Size', 'Value' => 'L'],
                            ],
                        ],
                    ],
                    [
                        'SKU' => 'OTHER',
                        'VariationSpecifics' => [
                            'NameValueList' => ['Name' => 'Color', 'Value' => 'Red'],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(
            ['Color' => 'Blue', 'Size' => 'L'],
            EbayLiveListingMapper::variationSpecificsForSku($item, 'CS CHI BLU HTSY L')
        );
        $this->assertSame([], EbayLiveListingMapper::variationSpecificsForSku($item, 'CDC11'));
        $this->assertSame([], EbayLiveListingMapper::variationSpecificsForSku(['Quantity' => 26], 'CDC11'));
    }

    public function test_pushed_qty_does_not_stamp_sibling_variations(): void
    {
        $cached = [
            ['product_id' => '111', 'sku' => '5C-WL-CHARGE', 'inventory' => 4],
            ['product_id' => '111', 'sku' => '5C-WL-CHARGE-BLK', 'inventory' => 18],
        ];

        $next = Ebay2LiveListingsService::applyPushedQtyToLiveRows($cached, [
            ['product_id' => '111', 'sku_code' => '5C-WL-CHARGE', 'inventory' => 24],
        ]);

        $this->assertSame(24, $next[0]['inventory']);
        $this->assertSame(18, $next[1]['inventory']);
    }

    public function test_variation_listing_is_detected_so_parent_qty_is_not_used(): void
    {
        $item = [
            'Quantity' => 40,
            'Variations' => [
                'Variation' => [
                    ['SKU' => '5C-WL-CHARGE', 'Quantity' => 4],
                    ['SKU' => '5C-WL-CHARGE-BLK', 'Quantity' => 18],
                ],
            ],
        ];

        $this->assertTrue(EbayLiveListingMapper::listingHasVariations($item));
        $this->assertFalse(EbayLiveListingMapper::listingHasVariations(['Quantity' => 26]));
        $this->assertSame(4, EbayLiveListingMapper::quantityFromGetItem($item, '5C-WL-CHARGE'));
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
